<?php
/**
 * Module: DynamicContentUtils class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicContent;

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentOptions;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\Shortcode\ShortcodeUtils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Module: DynamicContentUtils class.
 *
 * This class provides utility functions for retrieving dynamic content.
 *
 * This includes:
 * - Processing dynamic content value.
 * - Filtering the dynamic content value to resolve the value.
 * - Get custom meta option label.
 * - Get default option setting value.
 *
 * @since ??
 */
class DynamicContentUtils {

	/**
	 * Resolve archive term from explicit current page URL context.
	 *
	 * @since ??
	 *
	 * @param string $current_page_url The current archive page URL.
	 *
	 * @return \WP_Term|null The resolved term or null.
	 */
	private static function _get_archive_term_from_url( string $current_page_url ): ?\WP_Term {
		$path = wp_parse_url( $current_page_url, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return null;
		}

		// Strip the WordPress base URL path to support subdirectory installations.
		// When WordPress is installed at e.g. /wordpress/, the archive URL path would be
		// /wordpress/category/my-category/ and the taxonomy rewrite slug is just 'category'.
		// Without stripping the base, the prefix matching below would compare 'WordPress'
		// against 'category' and never find a match.
		$base_path = wp_parse_url( site_url(), PHP_URL_PATH );
		if ( is_string( $base_path ) && '' !== $base_path && '/' !== $base_path ) {
			$normalized_base = rtrim( $base_path, '/' );
			if ( str_starts_with( $path, $normalized_base . '/' ) ) {
				$path = substr( $path, strlen( $normalized_base ) );
			}
		}

		$path_segments = array_values(
			array_filter(
				explode( '/', trim( $path, '/' ) ),
				static function ( $segment ) {
					return '' !== $segment;
				}
			)
		);

		if ( count( $path_segments ) < 2 ) {
			return null;
		}

		$taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
		foreach ( $taxonomies as $taxonomy => $taxonomy_object ) {
			$taxonomy_rewrite = $taxonomy_object->rewrite['slug'] ?? '';
			if ( ! is_string( $taxonomy_rewrite ) || '' === $taxonomy_rewrite ) {
				continue;
			}

			$rewrite_segments = array_values(
				array_filter(
					explode( '/', trim( $taxonomy_rewrite, '/' ) ),
					static function ( $segment ) {
						return '' !== $segment;
					}
				)
			);

			$rewrite_segments_count = count( $rewrite_segments );
			if ( 0 === $rewrite_segments_count || count( $path_segments ) <= $rewrite_segments_count ) {
				continue;
			}

			$current_prefix = array_slice( $path_segments, 0, $rewrite_segments_count );
			if ( implode( '/', $current_prefix ) !== implode( '/', $rewrite_segments ) ) {
				continue;
			}

			$term_slug = (string) end( $path_segments );
			$term      = get_term_by( 'slug', $term_slug, $taxonomy );
			if ( $term instanceof \WP_Term ) {
				return $term;
			}
		}

		return null;
	}

	/**
	 * Get the formatted custom meta label based on the given key.
	 *
	 * This function replaces underscores and dashes with spaces in the key, capitalizes
	 * the first letter of each word, and removes any leading or trailing spaces.
	 *
	 * @since ??
	 *
	 * @param string $key The custom meta key.
	 *
	 * @return string The custom meta label.
	 *
	 * @example:
	 * ```php
	 *  $key = 'my_custom_key';
	 *  $label = DynamicContentUtils::get_custom_meta_label($key);
	 *  echo $label;
	 * ```
	 *
	 * @output:
	 * ```php
	 * 'My Custom Key'
	 * ```
	 *
	 * @example:
	 * ```php
	 *  $key = 'another-custom-key';
	 *  $label = DynamicContentUtils::get_custom_meta_label($key);
	 *  echo $label;
	 * ```
	 *
	 * @output:
	 * ```php
	 *  'Another Custom Key'
	 * ```
	 *
	 * @example:
	 * ```php
	 *  $key = 'this_is-a_key';
	 *  $label = DynamicContentUtils::get_custom_meta_label($key);
	 *  echo $label;
	 * ```
	 *
	 * @output:
	 * ```php
	 *  'This Is A Key'
	 * ```
	 */
	public static function get_custom_meta_label( string $key ): string {
		$label = str_replace( [ '_', '-' ], ' ', $key );
		$label = ucwords( $label );
		$label = trim( $label );
		return $label;
	}

	/**
	 * Get the default value of a setting.
	 *
	 * Retrieves the default value of a setting based on the provided arguments. If the name or setting is not provided,
	 * an empty string is returned. The function uses the `DynamicContentOptions::get_options()` to retrieve the options for the
	 * specified post ID and then accesses the corresponding default value based on the provided name and setting.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *   An array of arguments that define the context for retrieving the default value.
	 *
	 *   @type int    $post_id Optional. The ID of the post for which to retrieve the default value. Default is 0.
	 *   @type string $option  Optional. Option name. Default empty string.
	 *   @type string $setting Optional. Option settings. Default empty string.
	 * }
	 *                    - 'post_id'  (int)    The ID of the post for which to retrieve the default value. Default is 0.
	 *                    - 'name'     (string) The name of the option. Default is an empty string.
	 *                    - 'setting'  (string) The name of the setting. Default is an empty string.
	 *
	 * @return string The default value of the specified setting or an empty string if the name or setting is not provided.
	 *
	 * @example:
	 * ```php
	 * $args = [
	 *     'post_id' => 123,
	 *     'name'    => 'example_option',
	 *     'setting' => 'example_setting',
	 * ];
	 * $default_value = DynamicContentUtils::get_default_setting_value($args);
	 *
	 * echo $default_value;
	 * ```
	 *
	 * @output:
	 * ```php
	 * Example Default Value
	 * ```
	 */
	public static function get_default_setting_value( array $args ): string {
		$post_id = $args['post_id'] ?? 0;
		$name    = $args['name'] ?? '';
		$setting = $args['setting'] ?? '';

		if ( ! $name || ! $setting ) {
			return '';
		}

		$options = DynamicContentOptions::get_options( $post_id, 'edit' );

		return $options[ $name ]['fields'][ $setting ]['default'] ?? '';
	}

	/**
	 * Get the label of the post type.
	 *
	 * This function retrieves the post type label based on the provided post ID.
	 * If `get_post_type( $post_id )` return an empty value or the post type is a layout post type,
	 * the function returns the translated string 'Post'.
	 * Otherwise, it fetches the singular name of the post type from the post type object (via `get_post_type_object`).
	 *
	 * @since ??
	 *
	 * @param int $post_id The ID of the post.
	 *
	 * @return string The label of the post type.
	 *
	 * @example:
	 * ```php
	 * $post_type_label = DynamicContentUtils::get_post_type_label( $post_id );
	 * echo 'Post Type Label: ' . $post_type_label;
	 * ```
	 *
	 * @output:
	 * ```php
	 * Post Type Label: Blog Post
	 * ```
	 */
	public static function get_post_type_label( int $post_id ): string {
		$post_type = get_post_type( $post_id );

		// phpcs:ignore ET.Comments.Todo.TodoFound -- TODO has issue reference (#25149) but doesn't match exact PHPCS format requirement.
		// TODO feat(D5, Theme Builder): Replace `et_theme_builder_is_layout_post_type` once
		// the Theme Builder is implemented in D5.
		// @see https://github.com/elegantthemes/Divi/issues/25149.
		if ( ! $post_type || et_theme_builder_is_layout_post_type( $post_type ) ) {
			return esc_html__( 'Post', 'et_builder_5' );
		}

		return get_post_type_object( $post_type )->labels->singular_name;
	}

	/**
	 * Get common loop field definitions for dynamic content options.
	 *
	 * This function returns the common field definitions (before, after, loop_position)
	 * that are used across multiple dynamic content option classes.
	 *
	 * @since ??
	 *
	 * @return array The common field definitions.
	 */
	public static function get_common_loop_fields(): array {
		return [
			'before'        => [
				'label'   => esc_html__( 'Before', 'et_builder_5' ),
				'type'    => 'text',
				'default' => '',
			],
			'after'         => [
				'label'   => esc_html__( 'After', 'et_builder_5' ),
				'type'    => 'text',
				'default' => '',
			],
			'loop_position' => [
				'label'       => esc_html__( 'Loop Position', 'et_builder_5' ),
				'type'        => 'text',
				'default'     => '',
				'renderAfter' => 'n',
			],
		];
	}

	/**
	 * Resolve archive term context from runtime query object or request context.
	 *
	 * This method prefers the active WordPress query object and falls back to
	 * the explicit request context sent by the Visual Builder.
	 *
	 * @since ??
	 *
	 * @param array $data_args Dynamic content data arguments.
	 *
	 * @return \WP_Term|null The resolved term, or null when unavailable.
	 */
	public static function get_archive_term_context( array $data_args = [] ): ?\WP_Term {
		$queried_object = get_queried_object();
		if ( $queried_object instanceof \WP_Term ) {
			return $queried_object;
		}

		$request_context = $data_args['request_context'] ?? [];
		if ( ! is_array( $request_context ) ) {
			return null;
		}

		$request_type = $request_context['requestType'] ?? '';
		if ( 'archive' !== $request_type ) {
			return null;
		}

		$current_page_id = isset( $request_context['currentPageId'] ) ? (int) $request_context['currentPageId'] : 0;
		if ( $current_page_id > 0 ) {
			$term = get_term( $current_page_id );
			if ( $term instanceof \WP_Term ) {
				return $term;
			}
		}

		$current_page_url = (string) ( $request_context['currentPageUrl'] ?? '' );
		if ( '' === $current_page_url ) {
			return null;
		}

		$term = self::_get_archive_term_from_url( $current_page_url );
		if ( $term instanceof \WP_Term ) {
			return $term;
		}

		return null;
	}

	/**
	 * Determine whether dynamic content is being resolved in loop context.
	 *
	 * @since ??
	 *
	 * @param array $data_args Dynamic content data arguments.
	 *
	 * @return bool True when loop context args are present.
	 */
	public static function has_loop_context( array $data_args ): bool {
		$loop_id         = $data_args['loop_id'] ?? null;
		$loop_query_type = $data_args['loop_query_type'] ?? null;

		return ! empty( $loop_id ) || ! empty( $loop_query_type );
	}

	/**
	 * Resolve archive term featured image attachment id for non-loop contexts.
	 *
	 * @since ??
	 *
	 * @param array $data_args Dynamic content data arguments.
	 *
	 * @return int Archive term thumbnail attachment id, or 0.
	 */
	public static function get_archive_term_thumbnail_id( array $data_args ): int {
		if ( self::has_loop_context( $data_args ) ) {
			return 0;
		}

		$archive_term = self::get_archive_term_context( $data_args );
		if ( ! $archive_term instanceof \WP_Term ) {
			return 0;
		}

		return (int) get_term_meta( (int) $archive_term->term_id, 'thumbnail_id', true );
	}





	/**
	 * Get date format field definitions for dynamic content options.
	 *
	 * This function returns the common date format field definitions (date_format, custom_date_format)
	 * that are used across multiple dynamic content option classes that handle date values.
	 *
	 * @since ??
	 *
	 * @return array The date format field definitions.
	 */
	public static function get_date_format_fields(): array {
		return [
			'date_format'        => [
				'label'   => esc_html__( 'Date Format', 'et_builder_5' ),
				'type'    => 'select',
				'options' => [
					'default' => et_builder_i18n( 'Default' ),
					'M j, Y'  => esc_html__( 'Aug 6, 1999 (M j, Y)', 'et_builder_5' ),
					'F d, Y'  => esc_html__( 'August 06, 1999 (F d, Y)', 'et_builder_5' ),
					'm/d/Y'   => esc_html__( '08/06/1999 (m/d/Y)', 'et_builder_5' ),
					'm.d.Y'   => esc_html__( '08.06.1999 (m.d.Y)', 'et_builder_5' ),
					'j M, Y'  => esc_html__( '6 Aug, 1999 (j M, Y)', 'et_builder_5' ),
					'l, M d'  => esc_html__( 'Tuesday, Aug 06 (l, M d)', 'et_builder_5' ),
					'custom'  => esc_html__( 'Custom', 'et_builder_5' ),
				],
				'default' => 'default',
			],
			'custom_date_format' => [
				'label'   => esc_html__( 'Custom Date Format', 'et_builder_5' ),
				'type'    => 'text',
				'default' => '',
				'show_if' => [
					'date_format' => 'custom',
				],
			],
		];
	}

	/**
	 * Get the resolved post ID for dynamic content processing.
	 *
	 * Determines the appropriate post ID to use for dynamic content resolution based on the current context.
	 * Priority order:
	 * 1. Theme Builder main post ID (if Theme Builder layout and main post exists)
	 * 2. Archive context (-1 for archive pages with no posts, so dynamic content can resolve archive titles properly)
	 * 3. Current post ID from WordPress global state
	 *
	 * @since ??
	 *
	 * @return int|false The resolved post ID, or -1 for archive contexts, or false if no post ID available.
	 *
	 * @example:
	 * ```php
	 * $post_id = DynamicContentUtils::get_dynamic_content_post_id();
	 * $dynamic_data = DynamicData::get_processed_dynamic_data( $document, $post_id, true );
	 * ```
	 */
	public static function get_dynamic_content_post_id() {
		if ( \ET_Theme_Builder_Layout::is_theme_builder_layout() && \ET_Post_Stack::get_main_post() ) {
			return \ET_Post_Stack::get_main_post_id();
		}

		// For archive pages with no posts, use -1 instead of layout ID.
		// So dynamic content can resolve archive titles properly.
		if ( is_archive() ) {
			return -1;
		}

		return get_the_ID();
	}

	/**
	 * Get processed dynamic content value.
	 *
	 * Retrieves the resolved value of dynamic content based on the provided arguments.
	 * If the name of the dynamic content is empty, an empty string will be returned.
	 *
	 * @since ??
	 *
	 * @param array       $value            Array of dynamic content values.
	 * @param int|null    $loop_id          Optional. The loop post ID for loop context. Default `null`.
	 * @param string|null $loop_query_type  Optional. The loop query type. Default `null`.
	 * @param mixed       $loop_object      Optional. The loop object (WP_Post, WP_User, WP_Term, etc.). Default `null`.
	 * @param array|null  $request_context  Optional. The request context from the Visual Builder. Default `null`.
	 *
	 * @return string The resolved value of the dynamic content.
	 *
	 * @example:
	 * ```php
	 * $value = [
	 *   'name'     => 'dynamic_content_name',
	 *   'settings' => [
	 *     'setting1' => 'value1',
	 *     'setting2' => 'value2',
	 *   ],
	 * ];
	 * $processed_content = DynamicContentUtils::get_processed_dynamic_content( $value );
	 * ```
	 */
	public static function get_processed_dynamic_content(
		array $value,
		?int $loop_id = null,
		?string $loop_query_type = null,
		$loop_object = null,
		?array $request_context = null
	): string {
		$name = $value['name'] ?? '';

		if ( empty( $name ) ) {
			return '';
		}

		$settings = $value['settings'] ?? [];
		$post_id  = $value['post_id'] ?? self::get_dynamic_content_post_id();

		// Sanitize settings with enhanced security validation.
		$sanitized_settings = [];
		foreach ( $settings as $key => $setting ) {
			// Type validation: ensure $setting is a string to prevent type confusion attacks.
			if ( ! is_string( $setting ) ) {
				continue;
			}

			// Validate key is also a string to prevent array key manipulation.
			if ( ! is_string( $key ) ) {
				continue;
			}

			if ( in_array( $key, [ 'before', 'after' ], true ) ) {
				// Handle escaped backslashes from JSON decoding using WordPress's recommended wp_unslash().
				// This is safer than stripslashes() as it's designed for WordPress data handling.
				$unescaped_setting          = wp_unslash( $setting );
				$sanitized_settings[ $key ] = wp_kses_post( $unescaped_setting );
			} else {
				// Apply consistent sanitization to all other fields.
				$sanitized_settings[ $key ] = wp_kses_post( $setting );
			}
		}

		// Determine context: 'edit' for Visual Builder (REST API requests), 'display' for frontend.
		// This ensures permission checks work correctly - custom fields should be restricted in edit mode
		// when permission is off, but always render on frontend for backward compatibility.
		$context = Conditions::is_rest_api_request() ? 'edit' : 'display';

		// Detect if this is a manual entry (user manually entered custom field name).
		// Manual entries should bypass permission check - this matches D4 behavior where
		// manual entries work even when permission is off.
		$is_manual_entry = false;
		if ( 'post_meta_key' === $name ) {
			// Check if it's a manual entry: either direct meta_key or select_meta_key is manual.
			$selected_meta_key = $sanitized_settings['select_meta_key'] ?? '';
			if ( 'custom_meta_manual_custom_field_value' === $selected_meta_key || ( empty( $selected_meta_key ) && ! empty( $sanitized_settings['meta_key'] ) ) ) {
				$is_manual_entry = true;
			}
		} elseif ( 'custom_meta_manual_custom_field_value' === $name ) {
			// Simple format: custom_meta_manual_custom_field_value is always a manual entry.
			$is_manual_entry = true;
		}

		$resolved_value = self::get_resolved_value(
			[
				'name'                  => sanitize_text_field( $name ),
				'settings'              => $sanitized_settings,
				'post_id'               => $post_id,
				'context'               => $context,
				'loop_id'               => $loop_id,
				'loop_query_type'       => $loop_query_type,
				'loop_object'           => $loop_object,
				'request_context'       => $request_context ?? [],
				'is_manual_entry'       => $is_manual_entry,

				// By default, empty value is allowed to make sure we follow the same behavior as D4
				// where the before and after text can be displayed even if the custom meta value is
				// empty or not set.
				'allow_render_on_empty' => true,
			]
		);

		return $resolved_value;
	}

	/**
	 * Parse a dynamic content upload sub-field setting value.
	 *
	 * Upload sub-fields store a JSON-encoded attachment object (`src`, `id`, `alt`) as a string.
	 *
	 * @since ??
	 *
	 * @param string $setting Sanitized upload sub-field setting value.
	 *
	 * @return array {
	 *     Parsed upload field data.
	 *
	 *     @type string $src Attachment URL.
	 *     @type int    $id  Attachment ID when available.
	 *     @type string $alt Alt text.
	 * }
	 */
	public static function parse_upload_field_setting( string $setting ): array {
		$empty = [
			'src' => '',
			'id'  => 0,
			'alt' => '',
		];

		$setting = trim( $setting );

		if ( '' === $setting ) {
			return $empty;
		}

		$decoded = json_decode( $setting, true );

		if ( ! is_array( $decoded ) ) {
			// Some saved settings may contain slashed JSON from WordPress serialization.
			// Only unslash after normal decoding fails so escaped quotes in valid JSON stay intact.
			$decoded = json_decode( wp_unslash( $setting ), true );
		}

		if ( is_array( $decoded ) && isset( $decoded['src'] ) && is_string( $decoded['src'] ) && '' !== $decoded['src'] ) {
			return [
				'src' => esc_url_raw( $decoded['src'] ),
				'id'  => isset( $decoded['id'] ) ? absint( $decoded['id'] ) : 0,
				'alt' => isset( $decoded['alt'] ) && is_string( $decoded['alt'] ) ? esc_attr( $decoded['alt'] ) : '',
			];
		}

		return $empty;
	}

	/**
	 * Resolve the loop post ID for a module from its own attrs and, when absent, from ancestor blocks.
	 *
	 * When the loop is configured on a parent block (e.g. column), the child module's own
	 * `module_attrs` will not carry `__loop_post_id`. This function walks the ancestor chain
	 * to find the first block that does, mirroring the pattern used in
	 * `WooCommerceUtils::get_loop_context_product_id()`.
	 *
	 * @since ??
	 *
	 * @param array       $module_attrs   The module attributes array (e.g. `$this->module_attrs`).
	 * @param string      $module_id      The unique block ID used to look up ancestors in the store.
	 * @param int|null    $store_instance Optional. The BlockParserStore instance ID. Default null.
	 *
	 * @return int The resolved loop post ID, or 0 when not in a loop context.
	 */
	public static function get_loop_post_id( array $module_attrs, string $module_id, ?int $store_instance = null ): int {
		$loop_post_id = absint( $module_attrs['__loop_post_id'] ?? 0 );

		if ( $loop_post_id > 0 ) {
			return $loop_post_id;
		}

		$ancestors = BlockParserStore::get_ancestors( $module_id, $store_instance );

		foreach ( $ancestors as $ancestor ) {
			if ( isset( $ancestor->attrs['__loop_post_id'] ) ) {
				$ancestor_loop_post_id = absint( $ancestor->attrs['__loop_post_id'] );

				if ( $ancestor_loop_post_id > 0 ) {
					return $ancestor_loop_post_id;
				}
			}
		}

		return 0;
	}

	/**
	 * Stack to store backup `$post` global for nested shortcode calls.
	 * Uses `setup_postdata()` / `wp_reset_postdata()` plus explicit restore (see
	 * `PostUtils::get_comments_popup_link()`) so nested overrides unwind correctly.
	 *
	 * @var array<int, \WP_Post|null>
	 */
	private static $_post_data_stack = [];

	/**
	 * Check whether a loop post context is currently active.
	 *
	 * Returns true when `with_loop_post_context()` has been called and the post context
	 * has been overridden for a loop item. Used by shortcode wrappers to avoid overriding
	 * a loop item context with the Theme Builder main post.
	 *
	 * @since ??
	 *
	 * @return bool True when a loop post context is active.
	 */
	public static function has_active_loop_post_context(): bool {
		return ! empty( self::$_post_data_stack );
	}

	/**
	 * Override post data for loop context.
	 *
	 * Temporarily sets the global `$post` and calls `setup_postdata()` so that WordPress
	 * template tags like `get_the_ID()` and `get_the_title()` return values for the loop item.
	 *
	 * Uses a stack-based approach to handle nested calls safely without conflicting with
	 * ShortcodeModule's global backup variables.
	 *
	 * This function uses the following globals:
	 * - @global \WP_Post $post The current post object.
	 *
	 * @since ??
	 *
	 * @param int $loop_post_id The loop post ID to set as the current post.
	 *
	 * @return bool True when global post was overridden and a matching `restore_post_data()` call is required.
	 */
	public static function override_post_data( int $loop_post_id ): bool {
		global $post;

		$post_data = get_post( $loop_post_id );
		if ( ! $post_data ) {
			return false;
		}

		// Push current $post onto stack before overriding (handles nested calls correctly).
		self::$_post_data_stack[] = $post;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for loop context shortcode execution.
		$post = $post_data;
		setup_postdata( $post_data );

		return true;
	}

	/**
	 * Restore the original post data.
	 *
	 * Unwinds one `setup_postdata()` level via `wp_reset_postdata()`, then restores the
	 * saved `$post` from the stack and calls `setup_postdata()` again when it is a post object,
	 * matching `PostUtils::get_comments_popup_link()` so nested overrides resolve correctly.
	 *
	 * This function uses the following globals:
	 * - @global \WP_Post $post The current post object.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function restore_post_data(): void {
		global $post;

		if ( empty( self::$_post_data_stack ) ) {
			return;
		}

		$previous_post = array_pop( self::$_post_data_stack );

		wp_reset_postdata();

		if ( $previous_post instanceof \WP_Post ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore prior post context.
			$post = $previous_post;

			setup_postdata( $previous_post );
		}
	}

	/**
	 * Execute callback with loop post context.
	 *
	 * Sets global post context before executing callback and restores it after, even if callback throws exception.
	 * This ensures WordPress template tags like `get_the_ID()` work correctly in shortcodes within Loop Builder.
	 *
	 * @since ??
	 *
	 * @param int      $loop_post_id The loop post ID to set as the current post.
	 * @param callable $callback     The callback to execute with loop post context.
	 *
	 * @return mixed The return value of the callback.
	 */
	public static function with_loop_post_context( int $loop_post_id, callable $callback ) {
		$did_override = self::override_post_data( $loop_post_id );

		try {
			return call_user_func( $callback );
		} finally {
			if ( $did_override ) {
				self::restore_post_data();
			}
		}
	}

	/**
	 * Get dynamic content resolved value based on the given arguments.
	 *
	 * This function retrieves the resolved value of a dynamic content option based on the specified arguments.
	 * This function runs the value through the `divi_module_dynamic_content_resolved_value` and
	 * `divi_module_dynamic_content_resolved_value_{$name}` filters.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *   An array of arguments.
	 *
	 *   @type string  $name            Optional. The name of the option. Default empty string.
	 *   @type array   $settings        Optional. The settings of the option. Default `[]`.
	 *   @type integer $post_id         Optional. The ID of the post associated with the option. Default `null`.
	 *   @type string  $context         Optional. The context in which the option is used, e.g., `display` or `edit`. Default empty string.
	 *   @type array   $overrides       Optional. An associative array of option_name => value pairs to override the option value. Default `[]`.
	 *   @type bool    $is_content      Optional. Whether the dynamic content is used in the module's main_content field. Default `false`.
	 *   @type integer $loop_id         Optional. The loop post ID for loop context. Default `null`.
	 *   @type string  $loop_query_type Optional. The loop query type. Default `null`.
	 *   @type mixed   $loop_object     Optional. The loop object (WP_Post, WP_User, WP_Term, etc.). Default `null`.
	 * }
	 *
	 * @return string The resolved value of the dynamic content option.
	 *
	 * @example:
	 * ```php
	 *  $resolved_value = DynamicContentUtils::get_resolved_value(
	 *    [
	 *      'name'     => 'post_title',
	 *      'settings' => [],
	 *      'post_id'  => 123,
	 *      'context'  => 'display',
	 *    ]
	 *  );
	 * ```
	 */
	public static function get_resolved_value( array $args ): string {
		$name       = $args['name'] ?? '';
		$is_content = $args['is_content'] ?? false;
		$data_args  = [
			'name'                  => $name,
			'settings'              => $args['settings'] ?? [],
			'post_id'               => $args['post_id'] ?? null,
			'context'               => $args['context'] ?? '',
			'overrides'             => $args['overrides'] ?? [],
			'is_content'            => $is_content,
			'loop_id'               => $args['loop_id'] ?? null,
			'loop_query_type'       => $args['loop_query_type'] ?? null,
			'loop_object'           => $args['loop_object'] ?? null,
			'request_context'       => $args['request_context'] ?? [],
			'is_manual_entry'       => $args['is_manual_entry'] ?? false,

			// By default, empty value is allowed to make sure we follow the same behavior as D4
			// where the before and after text can be displayed even if the custom meta value is
			// empty or not set.
			'allow_render_on_empty' => $args['allow_render_on_empty'] ?? true,
		];

		$value = '';
		/**
		 * Filter dynamic content value to resolve based on given options and post.
		 *
		 * @since ??
		 *
		 * @param string $value     Dynamic content resolved value.
		 * @param array  $data_args {
		 *     An array of arguments.
		 *
		 *     @type string  $name            Option name.
		 *     @type array   $settings        Option settings.
		 *     @type integer $post_id         Post Id.
		 *     @type string  $context         Context e.g `edit`, `display`.
		 *     @type array   $overrides       An associative array of option_name => value to override option value.
		 *     @type bool    $is_content      Whether dynamic content used in module's main_content field.
		 *     @type integer $loop_id         The loop post ID for loop context.
		 *     @type string  $loop_query_type The loop query type.
		 *     @type mixed   $loop_object     The loop object (WP_Post, WP_User, WP_Term, etc.).
		 * }
		 */
		$value = apply_filters( 'divi_module_dynamic_content_resolved_value', $value, $data_args );

		/**
		 * Filter option-specific dynamic content value to resolve based on a given option and post.
		 *
		 * @since ??
		 *
		 * @param string $value     Dynamic content resolved value.
		 * @param array  $data_args {
		 *     An array of arguments.
		 *
		 *     @type string  $name            Option name.
		 *     @type array   $settings        Option settings.
		 *     @type integer $post_id         Post Id.
		 *     @type string  $context         Context e.g `edit`, `display`.
		 *     @type array   $overrides       An associative array of option_name => value to override option value.
		 *     @type bool    $is_content      Whether dynamic content used in module's main_content field.
		 *     @type integer $loop_id         The loop post ID for loop context.
		 *     @type string  $loop_query_type The loop query type.
		 *     @type mixed   $loop_object     The loop object (WP_Post, WP_User, WP_Term, etc.).
		 * }
		 */
		$value = apply_filters( "divi_module_dynamic_content_resolved_value_{$name}", $value, $data_args );

		$value = $is_content ? ShortcodeUtils::get_processed_embed_shortcode( $value ) : $value;

		if ( ! $is_content ) {
			return $value;
		}

		$loop_id = $data_args['loop_id'] ?? null;

		if ( $loop_id > 0 ) {
			return self::with_loop_post_context( $loop_id, fn() => do_shortcode( $value ) );
		}

		return do_shortcode( $value );
	}

	/**
	 * This function only strip the Dynamic Content D4 format. We keep it here to back port
	 * `get_strip_dynamic_content` function and as fallback just in case the old
	 * post content still contains Dynamic Content D4 format.
	 *
	 * @since ??
	 *
	 * @param string $content Post Content.
	 *
	 * @return string
	 */
	public static function get_strip_dynamic_content( $content ) {
		return preg_replace( '/@ET-DC@(.*?)@/', '', $content );
	}
}
