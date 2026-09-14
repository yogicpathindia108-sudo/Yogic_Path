<?php
/**
 * Shortcode: ShortcodeUtils class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Shortcode;

use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentUtils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * ShortcodeUtils class.
 *
 * This class provides utility methods for handling shortcodes.
 *
 * @since ??
 */
class ShortcodeUtils {

	/**
	 * Original callbacks for wrapped shortcode tags.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private static $_wrapped_shortcode_callbacks = [];

	/**
	 * Wrap depth counter for nested Theme Builder renders.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	private static $_wrap_depth = 0;

	/**
	 * Wrap all registered shortcodes with Theme Builder context handling.
	 *
	 * Called lazily from Layout::render() when Theme Builder is actually rendering.
	 * Wraps third-party shortcodes to automatically fix the $post context during rendering.
	 *
	 * Excludes Divi's internal shortcodes (et_pb_*) to avoid regressions.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function wrap_shortcodes_for_theme_builder(): void {
		global $shortcode_tags;

		// Support nested renders: only perform the actual wrapping once.
		if ( self::$_wrap_depth > 0 ) {
			++self::$_wrap_depth;
			return;
		}

		self::$_wrap_depth = 1;

		// Wrap each third-party shortcode with our Theme Builder-aware wrapper.
		// Skip Divi's internal shortcodes (et_pb_*) to avoid regressions.
		foreach ( $shortcode_tags as $tag => $callback ) {
			// Skip Divi shortcodes - they may intentionally use layout post context.
			if ( str_starts_with( $tag, 'et_pb_' ) ) {
				continue;
			}

			// Skip lazy placeholders during initial wrap. They are wrapped later,
			// after shortcode manager resolves real callbacks at parse-time.
			if ( self::_is_empty_placeholder_callback( $callback ) ) {
				continue;
			}

			// Store original callback for this wrapped shortcode only.
			self::$_wrapped_shortcode_callbacks[ $tag ] = $callback;

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporarily wrapping shortcodes for Theme Builder context fix.
			$shortcode_tags[ $tag ] = [ self::class, 'shortcode_wrapper' ];
		}
	}

	/**
	 * Wrap a resolved shortcode callback while Theme Builder scope is active.
	 *
	 * Lazy placeholder shortcodes are skipped during initial wrap pass so shortcode
	 * manager can resolve their real callbacks first. This method is called from
	 * shortcode manager pre-do-shortcode boundary after resolution.
	 *
	 * @since ??
	 *
	 * @param string $tag Shortcode tag.
	 *
	 * @return void
	 */
	public static function maybe_wrap_resolved_shortcode_for_theme_builder( string $tag ): void {
		global $shortcode_tags;

		// Only wrap during active Theme Builder wrapping scope.
		if ( self::$_wrap_depth <= 0 ) {
			return;
		}

		if ( '' === $tag || str_starts_with( $tag, 'et_pb_' ) ) {
			return;
		}

		$current_callback = $shortcode_tags[ $tag ] ?? null;
		if ( ! $current_callback ) {
			return;
		}

		// Already wrapped.
		if ( [ self::class, 'shortcode_wrapper' ] === $current_callback ) {
			return;
		}

		// Keep placeholders untouched until they are resolved.
		if ( self::_is_empty_placeholder_callback( $current_callback ) ) {
			return;
		}

		// Store original callback and wrap.
		self::$_wrapped_shortcode_callbacks[ $tag ] = $current_callback;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporarily wrapping shortcodes for Theme Builder context fix.
		$shortcode_tags[ $tag ] = [ self::class, 'shortcode_wrapper' ];
	}

	/**
	 * Restore original shortcode callbacks after Theme Builder rendering.
	 *
	 * Called from Layout::render() after content is rendered to unwrap shortcodes.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function unwrap_shortcodes_for_theme_builder(): void {
		global $shortcode_tags;

		if ( self::$_wrap_depth <= 0 ) {
			return;
		}

		--self::$_wrap_depth;

		// If still nested, defer restoration to the outermost unwrap.
		if ( self::$_wrap_depth > 0 ) {
			return;
		}

		// Restore only the shortcodes we wrapped to avoid clobbering runtime updates.
		if ( ! empty( self::$_wrapped_shortcode_callbacks ) ) {
			foreach ( self::$_wrapped_shortcode_callbacks as $tag => $callback ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring wrapped shortcode callback.
				$shortcode_tags[ $tag ] = $callback;
			}

			self::$_wrapped_shortcode_callbacks = [];
		}
	}

	/**
	 * Wrapper function for third-party shortcodes that fixes Theme Builder context.
	 *
	 * This wrapper is only registered for non-Divi shortcodes. It ensures they
	 * execute with the correct post context by temporarily replacing the global
	 * $post with the actual displayed post instead of the Theme Builder layout post.
	 *
	 * @since ??
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Shortcode content.
	 * @param string       $tag     Shortcode tag.
	 *
	 * @return string|mixed Shortcode output with correct Theme Builder context.
	 */
	public static function shortcode_wrapper( $atts, $content = null, $tag = '' ) {
		// Get original callback.
		$original_callback = self::$_wrapped_shortcode_callbacks[ $tag ] ?? null;

		if ( ! $original_callback || ! is_callable( $original_callback ) ) {
			return '';
		}

		// Get the actual displayed post ID.
		$main_post_id    = class_exists( '\ET_Post_Stack' ) ? \ET_Post_Stack::get_main_post_id() : 0;
		$current_post_id = get_the_ID();

		// Get the actual displayed post object.
		$main_post = 0 < $main_post_id ? get_post( $main_post_id ) : null;

		// No context switching needed if post IDs match or main post invalid.
		// Also skip if a loop post context is already active — with_loop_post_context() has set
		// $post to the loop item and shortcode_wrapper() must not override it with the main post.
		// Also skip inside an active WooCommerce product loop item ([products], related, etc.)
		// so nested wishlist/compare shortcodes do not rewrite $post / $product mid-loop.
		if (
			! $main_post
			|| $current_post_id === $main_post_id
			|| DynamicContentUtils::has_active_loop_post_context()
			|| self::_has_active_woocommerce_product_loop_context()
		) {
			return call_user_func( $original_callback, $atts, $content, $tag );
		}

		global $post;

		// Save the original post.
		$original_post = $post;

		// Temporarily replace global $post with the actual displayed post.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for Theme Builder shortcode context.
		$post = $main_post;

		// Set up post data for template tags.
		setup_postdata( $main_post );

		try {
			// Execute the original shortcode callback with correct post context.
			$output = call_user_func( $original_callback, $atts, $content, $tag );
		} finally {
			// Always restore original post, even if shortcode throws exception.
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring original state.
			$post = $original_post;

			// Reset post data.
			wp_reset_postdata();
		}

		return $output;
	}

	/**
	 * Whether the current post is already an intentional WooCommerce product loop item.
	 *
	 * Used by `shortcode_wrapper()` to avoid overriding the loop product with
	 * `ET_Post_Stack::get_main_post()` when nested third-party shortcodes run mid-loop
	 * (e.g. wishlist/compare buttons inside `[products]`).
	 *
	 * Reads `$GLOBALS['woocommerce_loop']` directly instead of `wc_get_loop_prop()` so
	 * this check does not initialize a default loop via `wc_setup_loop()`.
	 *
	 * @since ??
	 *
	 * @return bool True when inside a WC shortcode/named product loop on a product post.
	 */
	private static function _has_active_woocommerce_product_loop_context(): bool {
		if ( empty( $GLOBALS['woocommerce_loop'] ) || ! is_array( $GLOBALS['woocommerce_loop'] ) ) {
			return false;
		}

		$is_shortcode_loop = ! empty( $GLOBALS['woocommerce_loop']['is_shortcode'] );
		$loop_name         = $GLOBALS['woocommerce_loop']['name'] ?? '';

		// Shortcode loops ([products]) and named loops (related, up-sells, cross-sells).
		// Singular product context (`is_product`) alone must not match — TB shortcodes on
		// single-product templates still need main-post correction (#41239).
		if ( ! $is_shortcode_loop && '' === $loop_name ) {
			return false;
		}

		$current_post_id = get_the_ID();

		return 0 < $current_post_id && 'product' === get_post_type( $current_post_id );
	}

	/**
	 * Determine whether callback is placeholder callback.
	 *
	 * @since ??
	 *
	 * @param mixed $callback Shortcode callback.
	 *
	 * @return bool
	 */
	private static function _is_empty_placeholder_callback( $callback ): bool {
		return '__return_empty_string' === $callback;
	}

	/**
	 * Get processed `embed` shortcode if the content has `embed` shortcode.
	 *
	 * This function checks if the provided content contains the `[embed][/embed]` shortcode and
	 * processes it using `$wp_embed->run_shortcode` from the global `$wp_embed` object.
	 *
	 * @since ??
	 *
	 * @param string $content Content to search for shortcodes.
	 *
	 * @return string Content with processed embed shortcode.
	 *
	 * @example:
	 * ```php
	 * $content = '[embed]http://www.wordpress.test/watch?v=embed-shortcode[/embed]';
	 * $processedContent = ShortcodeUtils::get_processed_embed_shortcode( $content );
	 * echo $processedContent;
	 *
	 * // Output: <a href="http://www.wordpress.test/watch?v=embed-shortcode">http://www.wordpress.test/watch?v=embed-shortcode</a>
	 * ```
	 */
	public static function get_processed_embed_shortcode( string $content ): string {
		if ( has_shortcode( $content, 'embed' ) ) {
			global $wp_embed;
			$content = $wp_embed->run_shortcode( $content );
		}

		return $content;
	}
}
