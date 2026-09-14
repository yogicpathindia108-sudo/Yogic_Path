<?php
/**
 * Hooks: Hooks class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\VisualBuilder\Fonts\FontsUtility;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\VisualBuilder\REST\Portability\PortabilityController;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentElements;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentACFUtils;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentUtils;
use ET\Builder\Framework\Utility\Conditions;

/**
 * `HooksRegistration` class is consisted of WordPress hook functions used in Visual Builder, It registers them upon calling `load()`.
 *
 * This is a dependency class and can be used as dependency for `DependencyTree`.
 *
 * @since ??
 */
class HooksRegistration implements DependencyInterface {

	/**
	 * Check the file type and extension for font files.
	 *
	 * Filters the "real" file type of the given font file.
	 *
	 * @since ??
	 *
	 * @param array  $checked_filetype_and_ext {
	 *     Values for the extension, mime type, and corrected filename.
	 *     An associative array containing the file extension and file type.
	 *
	 *     @type string|false $ext             File extension, or false if the file doesn't match a mime type.
	 *     @type string|false $type            File mime type, or false if the file doesn't match a mime type.
	 *     @type string|false $proper_filename File name with its correct extension, or false if it cannot be determined.
	 * }
	 * @param string $file                     The full path to the font file.
	 * @param string $filename                 The name of the font file. (may differ from `$file` due to
	 *                                          `$file` being in a tmp directory).
	 *
	 * @return array An associative array containing the file extension, file type, and the sanitized file name.
	 *
	 * @example
	 * ```php
	 *      $checked_filetype_and_ext = array(
	 *          'ext'  => 'ttf',
	 *          'type' => 'application/octet-stream',
	 *      );
	 *      $file = '/path/to/file.ttf';
	 *      $filename = 'font.ttf';
	 *
	 *      FontsUtility::check_filetype_and_ext_font( $checked_filetype_and_ext, $file, $filename );
	 * ```
	 *
	 * @example:
	 * ```php
	 *      $checked_filetype_and_ext = array(
	 *          'ext'  => false,
	 *          'type' => false,
	 *      );
	 *      $file = '/path/to/invalid_file.ttf';
	 *      $filename = 'invalid_font.ttf';
	 *
	 *      FontsUtility::check_filetype_and_ext_font( $checked_filetype_and_ext, $file, $filename );
	 * ```
	 */
	public static function check_filetype_and_ext_font( array $checked_filetype_and_ext, string $file, string $filename ): array {
		$mimes_font = FontsUtility::mime_types_font();

		// Only process if the file exist and PHP extension "fileinfo" is loaded.
		if ( file_exists( $file ) && extension_loaded( 'fileinfo' ) ) {
			$ext = pathinfo( $filename, PATHINFO_EXTENSION );
			// Normalize extension to lowercase to handle uppercase extensions from cameras/Windows systems.
			$ext = $ext ? strtolower( $ext ) : $ext;

			if ( $ext && $ext !== $filename && isset( $mimes_font[ $ext ] ) ) {
				// Get the real mime type.
				$finfo     = finfo_open( FILEINFO_MIME_TYPE );
				$real_mime = finfo_file( $finfo, $file );
				finfo_close( $finfo );

				if ( $real_mime && in_array( $real_mime, $mimes_font[ $ext ], true ) ) {
					return [
						'ext'             => $ext,
						'type'            => $real_mime,
						'proper_filename' => sanitize_file_name( $filename ),
					];
				}
			}

			return [
				'ext'             => false,
				'type'            => false,
				'proper_filename' => false,
			];
		}

		$ext  = isset( $checked_filetype_and_ext['ext'] ) ? $checked_filetype_and_ext['ext'] : false;
		$type = isset( $checked_filetype_and_ext['type'] ) ? $checked_filetype_and_ext['type'] : false;
		// Normalize extension to lowercase to handle uppercase extensions from cameras/Windows systems.
		$ext = $ext ? strtolower( $ext ) : $ext;

		if ( $ext && $type && isset( $mimes_font[ $ext ] ) && in_array( $type, $mimes_font[ $ext ], true ) ) {
			return $checked_filetype_and_ext;
		}

		return [
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		];
	}

	/**
	 * Filters the "real" file type of the given JSON file.
	 *
	 * @since ??
	 *
	 * @param array  $checked_filetype_and_ext {
	 *     Values for the extension, mime type, and corrected filename.
	 *
	 *     @type string|false $ext             File extension, or false if the file doesn't match a mime type.
	 *     @type string|false $type            File mime type, or false if the file doesn't match a mime type.
	 *     @type string|false $proper_filename File name with its correct extension, or false if it cannot be determined.
	 * }
	 *
	 * @param string $file                      Full path to the file.
	 * @param string $filename                  The name of the file (may differ from $file due to
	 *                                          $file being in a tmp directory).
	 *
	 * @return array
	 */
	public static function check_filetype_and_ext_json( array $checked_filetype_and_ext, string $file, string $filename ): array {
		$mimes_json = PortabilityController::mime_types_json();

		// Only process if the file exist and PHP extension "fileinfo" is loaded.
		if ( file_exists( $file ) && extension_loaded( 'fileinfo' ) ) {
			$ext = pathinfo( $filename, PATHINFO_EXTENSION );
			// Normalize extension to lowercase to handle uppercase extensions from cameras/Windows systems.
			$ext = $ext ? strtolower( $ext ) : $ext;

			if ( $ext && $ext !== $filename && isset( $mimes_json[ $ext ] ) ) {
				// Get the real mime type.
				$finfo     = finfo_open( FILEINFO_MIME_TYPE );
				$real_mime = finfo_file( $finfo, $file );
				finfo_close( $finfo );

				// sometimes finfo_file() returns "text/html" or similar for JSON files/JSON content.
				// in this case, we need to check if the file has valid JSON content.
				// if it is, we can safely assume that the file is a JSON file.
				// see https://github.com/elegantthemes/Divi/issues/39203.
				if ( ! in_array( $real_mime, $mimes_json[ $ext ], true ) && 'json' === $ext ) {
					global $wp_filesystem;

					json_decode( $wp_filesystem->get_contents( $file ) );

					if ( json_last_error() === JSON_ERROR_NONE ) {
						$real_mime = 'application/json';
					}
				}

				if ( $real_mime && in_array( $real_mime, $mimes_json[ $ext ], true ) ) {
					return [
						'ext'             => $ext,
						'type'            => $real_mime,
						'proper_filename' => sanitize_file_name( $filename ),
					];
				}
			}

			return [
				'ext'             => false,
				'type'            => false,
				'proper_filename' => false,
			];
		}

		$ext  = $checked_filetype_and_ext['ext'] ?? false;
		$type = $checked_filetype_and_ext['type'] ?? false;
		// Normalize extension to lowercase to handle uppercase extensions from cameras/Windows systems.
		$ext = $ext ? strtolower( $ext ) : $ext;

		if ( $ext && $type && isset( $mimes_json[ $ext ] ) && in_array( $type, $mimes_json[ $ext ], true ) ) {
			return $checked_filetype_and_ext;
		}

		return [
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		];
	}

	/**
	 * Set uploads dir for the custom font files.
	 *
	 * Adds a custom subdirectory '/et-fonts' to the upload directory paths and URLs for font file uploads.
	 * If the $directory argument is passed with a 'basedir' key, the function will append the '/et-fonts' subdirectory to the directory path.
	 * If the $directory argument is passed with a 'baseurl' key, the function will append the '/et-fonts' subdirectory to the directory URL.
	 * Additionally, it sets the 'subdir' key in the $directory array to '/et-fonts'.
	 *
	 * @since ??
	 *
	 * @param array $directory {
	 *     An array of upload directory information.
	 *
	 *     @type string $basedir The base directory path for the upload directory.
	 *     @type string $path    The full path to the upload directory including the subdirectory '/et-fonts'.
	 *     @type string $url     The full URL to the upload directory including the subdirectory '/et-fonts'.
	 *     @type string $subdir  The subdirectory '/et-fonts'.
	 * }
	 *
	 * @return array The modified $directory array with the 'path', 'url', and 'subdir' keys.
	 *
	 * @example:
	 * ```php
	 *   Example 1: Adding '/et-fonts' subdirectory to the upload directory
	 *
	 *   $directory = array(
	 *       'basedir' => '/var/www/uploads',
	 *       'baseurl' => 'http://example.com/uploads'
	 *   );
	 *
	 *   $modified_directory = HooksRegistration::upload_dir_font( $directory );
	 * ```
	 *
	 * @output:
	 * ```php
	 *   // The $modified_directory array will be:
	 *   array (
	 *       'basedir' => '/var/www/uploads',
	 *       'path'    => '/var/www/uploads/et-fonts',
	 *       'baseurl' => 'http://example.com/uploads',
	 *       'url'     => 'http://example.com/uploads/et-fonts',
	 *       'subdir'  => '/et-fonts',
	 *   )
	 * ```
	 */
	public static function upload_dir_font( array $directory ): array {
		$subdir = '/et-fonts';

		if ( isset( $directory['basedir'] ) ) {
			$directory['path'] = $directory['basedir'] . $subdir;
		}

		if ( isset( $directory['baseurl'] ) ) {
			$directory['url'] = $directory['baseurl'] . $subdir;
		}

		$directory['subdir'] = $subdir;

		return $directory;
	}

	/**
	 * Load and register hook functions used in Visual Builder.
	 *
	 * Adds actions to update cached assets when custom fonts are added or removed.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		// Add action to update cached assets because custom fonts are included in static helpers.
		add_action( 'divi_visual_builder_fonts_custom_font_added', 'et_fb_delete_builder_assets' );
		add_action( 'divi_visual_builder_fonts_custom_font_removed', 'et_fb_delete_builder_assets' );

		// Dynamic Content Resolved Value.
		add_filter( 'divi_module_dynamic_content_resolved_value', [ $this, 'divi_module_theme_builder_default_dynamic_content_resolved_value' ], 15, 2 );

		// SVG Support: Enable SVG uploads when SVG plugins are detected.
		add_filter( 'upload_mimes', [ $this, 'enable_svg_upload_mimes' ], 10, 1 );

		// Initialize ACF taxonomy field processing hooks.
		DynamicContentACFUtils::init_hooks();

		// Override admin bar link logic for non-singular pages with Theme Builder templates.
		add_action( 'admin_bar_menu', [ $this, 'add_edit_with_divi_button_for_theme_builder' ], 1000 );

		// Handle Visual Builder boot for non-singular pages with Theme Builder templates.
		// Run at priority 0 (before legacy et_fb_app_boot at priority 1).
		add_filter( 'the_content', [ $this, 'boot_vb_for_non_singular_theme_builder' ], 0 );
		add_filter( 'et_builder_render_layout', [ $this, 'boot_vb_for_non_singular_theme_builder' ], 0 );
		// Prevent legacy code from running for non-singular pages with TB templates.
		// Remove the legacy hook before content filters run.
		add_action( 'template_redirect', [ $this, 'prevent_legacy_vb_boot_for_non_singular' ], 1 );
		// Ensure non-singular Theme Builder VB sessions do not keep 404 response headers.
		add_action( 'template_redirect', [ $this, 'force_success_status_for_non_singular_theme_builder_vb' ], 2 );

		/**
		 * D4→D5 Migration: Add app container for regular VB pages.
		 *
		 * D4's et_fb_app_boot() (view.php:72) adds the #et-fb-app container for all VB pages.
		 * Since D4 is gutted, this filter was never registered. D5 needs to add this for
		 * regular singular posts/pages (non-singular TB pages are handled above).
		 */
		add_filter( 'the_content', [ $this, 'add_vb_app_container' ], 1 );
		add_filter( 'et_builder_render_layout', [ $this, 'add_vb_app_container' ], 1 );

		/**
		 * D4→D5 Migration: Critical initialization checks.
		 *
		 * The following hooks were previously registered in D4's frontend-builder (view.php,
		 * assets.php, rtl.php) but were never migrated to D5. These are essential for
		 * proper Visual Builder functionality.
		 */

		// Remove D4's et_fb_app_boot filter to prevent conflicts with D5's implementation.
		// D4's init.php returns early, but the filter might still be added by other code.
		remove_filter( 'the_content', 'et_fb_app_boot', 1 );
		remove_filter( 'et_builder_render_layout', 'et_fb_app_boot', 1 );

		// Fix unclosed HTML tags in menus - prevents broken VB rendering.
		add_filter( 'wp_nav_menu', [ $this, 'fix_nav_menu_unclosed_tags' ] );

		// Sidebar output buffering - ensures balanced HTML tags in sidebars.
		add_action( 'dynamic_sidebar', [ $this, 'dynamic_sidebar_ob_start' ] );
		add_action( 'dynamic_sidebar_after', [ $this, 'dynamic_sidebar_after_ob_get_clean' ] );

		// Admin bar style handling - disable built-in admin bar styling in VB context.
		add_action( 'wp', [ $this, 'disable_admin_bar_style' ], 15 );

		// WP Auth check HTML - handles session expiry in Visual Builder.
		add_action( 'wp_auth_check_html', [ $this, 'output_wp_auth_check_html' ] );

		// RTL handling - remove RTL stylesheet and direction for VB contexts.
		add_filter( 'locale_stylesheet_uri', [ $this, 'remove_rtl_stylesheet' ] );
		add_filter( 'language_attributes', [ $this, 'remove_html_rtl_dir' ] );

		// Divi Builder `et_fb_add_body_class()` CSS scopes under `body.et-fb`.
		add_filter( 'body_class', [ $this, 'add_visual_builder_body_class' ] );

		// Cloud item block layout preview template.
		add_filter( 'template_include', [ $this, 'maybe_include_cloud_item_preview_template' ], 99 );

		// Divi Builder library admin body classes.
		add_filter( 'admin_body_class', [ $this, 'add_visual_builder_admin_body_class' ] );
	}

	/**
	 * Add Visual Builder body classes.
	 *
	 * @since ??
	 *
	 * @param string[] $classes Existing body classes.
	 *
	 * @return string[]
	 */
	public function add_visual_builder_body_class( array $classes ): array {
		if ( ! Conditions::is_vb_enabled() ) {
			return $classes;
		}

		$classes[] = 'et-fb';

		if ( is_rtl() && 'on' === et_get_option( 'divi_disable_translations', 'off' ) ) {
			$classes[] = 'et-fb-no-rtl';
		}

		if ( et_builder_tb_enabled() ) {
			$classes[] = 'et-tb';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL context for body class.
		if ( isset( $_GET['cloudItem'] ) ) {
			$classes[] = 'et-cloud-item-editor';
		}

		return $classes;
	}

	/**
	 * Add admin body classes.
	 *
	 * @since ??
	 *
	 * @param string $classes Space-separated list of CSS classes.
	 *
	 * @return string
	 */
	public function add_visual_builder_admin_body_class( string $classes ): string {
		if ( is_rtl() && 'on' === et_get_option( 'divi_disable_translations', 'off' ) ) {
			$classes .= ' et-fb-no-rtl';
		}
		return $classes;
	}

	/**
	 * Swap the active template for Cloud item block layout preview.
	 *
	 * @since ??
	 *
	 * @param string $template Absolute path to the template file.
	 *
	 * @return string
	 */
	public function maybe_include_cloud_item_preview_template( string $template ): string {
		// Divi Builder top window swaps the template at priority 10; do not override that at priority 99.
		if ( Conditions::is_vb_top_window() ) {
			return $template;
		}

		// phpcs:ignore WordPress.Security.NonceVerification -- Read-only URL context for template routing (matches D4 view.php).
		if ( isset( $_GET['cloudItem'] ) ) {
			if ( current_user_can( 'manage_options' ) || current_user_can( 'editor' ) ) {
				wp_admin_bar_render();
			}

			return ET_BUILDER_DIR . 'templates/block-layout-preview.php';
		}

		return $template;
	}

	/**
	 * Resolve placeholder content for built-in dynamic content fields for Theme Builder layouts.
	 *
	 * @since ??
	 *
	 * @param mixed $content     The current value of the post featured image option.
	 * @param array $args {
	 *     Optional. An array of arguments for retrieving the post featured image.
	 *     Default `[]`.
	 *
	 *     @type string  $name       Optional. Option name. Default empty string.
	 *     @type array   $settings   Optional. Option settings. Default `[]`.
	 *     @type integer $post_id    Optional. Post Id. Default `null`.
	 *     @type string  $context    Optional. Context. Default `''`.
	 *     @type array   $overrides  Optional. An associative array of `option_name => value` to override option value.
	 *                               Default `[]`.
	 * }
	 *
	 * @return string
	 */
	public static function divi_module_theme_builder_default_dynamic_content_resolved_value( $content, array $args = [] ) {
		$name     = $args['name'] ?? '';
		$settings = $args['settings'] ?? [];
		$post_id  = $args['post_id'] ?? null;

		// Get post type from post id.
		$post_type = get_post_type( $post_id );

		if ( ! et_theme_builder_is_layout_post_type( $post_type ) && ! is_et_theme_builder_template_preview() ) {
			return $content;
		}

		// For search results, use real dynamic content instead of placeholders.
		if ( is_search() && 'post_title' === $name ) {
			return $content;
		}

		$placeholders = [
			'post_title'          => __( 'Your Dynamic Post Title Will Display Here', 'et_builder_5' ),
			'post_excerpt'        => __( 'Your dynamic post excerpt will display here. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus auctor urna eleifend diam eleifend sollicitudin a fringilla turpis. Curabitur lectus enim.', 'et_builder_5' ),
			'post_date'           => time(),
			'post_comment_count'  => 12,
			'post_categories'     => [
				__( 'Category 1', 'et_builder_5' ),
				__( 'Category 2', 'et_builder_5' ),
				__( 'Category 3', 'et_builder_5' ),
			],
			'post_tags'           => [
				__( 'Tag 1', 'et_builder_5' ),
				__( 'Tag 2', 'et_builder_5' ),
				__( 'Tag 3', 'et_builder_5' ),
			],
			'post_author'         => [
				'display_name'    => __( 'John Doe', 'et_builder_5' ),
				'first_last_name' => __( 'John Doe', 'et_builder_5' ),
				'last_first_name' => __( 'Doe, John', 'et_builder_5' ),
				'first_name'      => __( 'John', 'et_builder_5' ),
				'last_name'       => __( 'Doe', 'et_builder_5' ),
				'nickname'        => __( 'John', 'et_builder_5' ),
				'username'        => __( 'johndoe', 'et_builder_5' ),
			],
			'post_author_bio'     => __( 'Your dynamic author bio will display here. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus auctor urna eleifend diam eleifend sollicitudin a fringilla turpis. Curabitur lectus enim.', 'et_builder_5' ),
			'post_featured_image' => ET_BUILDER_PLACEHOLDER_LANDSCAPE_IMAGE_DATA,
			'term_description'    => __( 'Your dynamic category description will display here. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus auctor urna eleifend diam eleifend sollicitudin a fringilla turpis. Curabitur lectus enim.', 'et_builder_5' ),
			'site_logo'           => 'https://www.elegantthemes.com/img/divi.png',
		];

		$_        = et_();
		$wrapped  = false;
		$defaults = static function ( $post_id, $option, $setting ) {
			return DynamicContentUtils::get_default_setting_value(
				[
					'post_id' => $post_id,
					'name'    => $option,
					'setting' => $setting,
				]
			);
		};

		switch ( $name ) {
			case 'post_title':
				$content = et_core_intentionally_unescaped( $placeholders[ $name ], 'cap_based_sanitized' );
				break;

			case 'post_excerpt':
				$words     = (int) ( $settings['words'] ?? $defaults( $post_id, $name, 'words' ) );
				$read_more = $settings['read_more_label'] ?? $defaults( $post_id, $name, 'read_more_label' );
				$content   = esc_html( $placeholders[ $name ] );

				if ( $words > 0 ) {
					$content = wp_trim_words( $content, $words );
				}

				if ( ! empty( $read_more ) ) {
					$content .= sprintf(
						' <a href="%1$s">%2$s</a>',
						'#',
						esc_html( $read_more )
					);
				}
				break;

			case 'post_date':
				$format        = $settings['date_format'] ?? $defaults( $post_id, $name, 'date_format' );
				$custom_format = $settings['custom_date_format'] ?? $defaults( $post_id, $name, 'custom_date_format' );

				if ( 'default' === $format ) {
					$format = strval( get_option( 'date_format' ) );
				}

				if ( 'custom' === $format ) {
					$format = $custom_format;
				}

				$content = esc_html( gmdate( $format, $placeholders[ $name ] ) );
				break;

			case 'post_comment_count':
				$link    = $settings['link_to_comments_page'] ?? $defaults( $post_id, $name, 'link_to_comments_page' );
				$link    = 'on' === $link;
				$content = esc_html( $placeholders[ $name ] );

				if ( $link ) {
					$wrapped_content = DynamicContentElements::get_wrapper_element(
						[
							'post_id'  => $post_id,
							'name'     => $name,
							'value'    => $content,
							'settings' => $settings,
						]
					);
					$content         = sprintf(
						'<a href="%1$s">%2$s</a>',
						'#',
						et_core_esc_previously( $wrapped_content )
					);
					$wrapped         = true;
				}
				break;

			case 'post_categories': // Intentional fallthrough.
			case 'post_tags':
				$link      = $settings['link_to_term_page'] ?? $defaults( $post_id, $name, 'link_to_term_page' );
				$link      = 'on' === $link;
				$url       = '#';
				$separator = $settings['separator'] ?? $defaults( $post_id, $name, 'separator' );
				$separator = ! empty( $separator ) ? $separator : $defaults( $post_id, $name, 'separator' );
				$content   = $placeholders[ $name ];

				foreach ( $content as $index => $item ) {
					$content[ $index ] = esc_html( $item );

					if ( $link ) {
						$content[ $index ] = sprintf(
							'<a href="%1$s" target="%2$s">%3$s</a>',
							esc_url( $url ),
							esc_attr( '_blank' ),
							et_core_esc_previously( $content[ $index ] )
						);
					}
				}

				$content = implode( esc_html( $separator ), $content );
				break;

			case 'post_link':
				$text        = $settings['text'] ?? $defaults( $post_id, $name, 'text' );
				$custom_text = $settings['custom_text'] ?? $defaults( $post_id, $name, 'custom_text' );
				$label       = 'custom' === $text ? $custom_text : $placeholders['post_title'];
				$content     = sprintf(
					'<a href="%1$s">%2$s</a>',
					'#',
					esc_html( $label )
				);
				break;

			case 'post_author':
				$name_format = $settings['name_format'] ?? $defaults( $post_id, $name, 'name_format' );
				$link        = $settings['link'] ?? $defaults( $post_id, $name, 'link' );
				$link        = 'on' === $link;
				$label       = isset( $placeholders[ $name ][ $name_format ] ) ? $placeholders[ $name ][ $name_format ] : '';
				$url         = '#';

				$content = esc_html( $label );

				if ( $link && ! empty( $url ) ) {
					$content = sprintf(
						'<a href="%1$s" target="%2$s">%3$s</a>',
						esc_url( $url ),
						esc_attr( '_blank' ),
						et_core_esc_previously( $content )
					);
				}
				break;

			case 'post_author_bio':
				$content = et_core_intentionally_unescaped( $placeholders[ $name ], 'cap_based_sanitized' );
				break;

			case 'term_description':
				$content = et_core_intentionally_unescaped( $placeholders[ $name ], 'cap_based_sanitized' );
				break;

			case 'post_link_url':
				$content = '#';
				break;

			case 'post_author_url':
				$content = '#';
				break;

			case 'post_featured_image':
				$content = et_core_intentionally_unescaped( $placeholders[ $name ], 'fixed_string' );
				break;

			case 'site_logo':
				if ( empty( $content ) ) {
					$content = esc_url( $placeholders[ $name ] );
				} else {
					$wrapped = true;
				}
				break;

			default:
				// Avoid unhandled cases being wrapped twice by the default resolve and this one.
				$wrapped = true;
				break;
		}

		if ( $_->starts_with( $name, 'custom_meta_' ) ) {
			$meta_key   = substr( $name, strlen( 'custom_meta_' ) );
			$meta_value = get_post_meta( $post_id, $meta_key, true );
			if ( empty( $meta_value ) ) {
				$content = DynamicContentUtils::get_custom_meta_label( $meta_key );
			} else {
				$wrapped = true;
			}
		}

		if ( ! $wrapped ) {
			$content = DynamicContentElements::get_wrapper_element(
				[
					'post_id'  => $post_id,
					'name'     => $name,
					'value'    => $content,
					'settings' => $settings,
				]
			);
			$wrapped = true;
		}

		return $content;
	}

	/**
	 * Filters the "real" file type of the given image file.
	 *
	 * @since ??
	 *
	 * @param array  $checked_filetype_and_ext {
	 *     Values for the extension, mime type, and corrected filename.
	 *
	 *     @type string|false $ext             File extension, or false if the file doesn't match a mime type.
	 *     @type string|false $type            File mime type, or false if the file doesn't match a mime type.
	 *     @type string|false $proper_filename File name with its correct extension, or false if it cannot be determined.
	 * }
	 *
	 * @param string $file                      Full path to the file.
	 * @param string $filename                  The name of the file (may differ from $file due to
	 *                                          $file being in a tmp directory).
	 *
	 * @return array
	 */
	public static function check_filetype_and_ext_image( array $checked_filetype_and_ext, string $file, string $filename ): array {
		// Supported media mime types (images and videos) for portability import.
		$mimes_media = [
			// Images.
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpe'  => 'image/jpeg',
			'gif'  => 'image/gif',
			'png'  => 'image/png',
			'bmp'  => 'image/bmp',
			'tiff' => 'image/tiff',
			'tif'  => 'image/tiff',
			'webp' => 'image/webp',
			'avif' => 'image/avif',
			'ico'  => 'image/x-icon',
			'heic' => 'image/heic',
			// Videos.
			'mp4'  => 'video/mp4',
			'webm' => 'video/webm',
			'ogv'  => 'video/ogg',
			'avi'  => 'video/avi',
			'mov'  => 'video/quicktime',
			'wmv'  => 'video/x-ms-wmv',
			'flv'  => 'video/x-flv',
		];

		$allowed_mimes = get_allowed_mime_types();

		if ( in_array( 'image/svg+xml', $allowed_mimes, true ) ) {
			$mimes_media['svg']  = 'image/svg+xml';
			$mimes_media['svgz'] = 'image/svg+xml';
		}

		// Only process if the file exists and PHP extension "fileinfo" is loaded.
		if ( file_exists( $file ) && extension_loaded( 'fileinfo' ) ) {
			$ext = pathinfo( $filename, PATHINFO_EXTENSION );
			// Normalize extension to lowercase to handle uppercase extensions from cameras/Windows systems.
			$ext = $ext ? strtolower( $ext ) : $ext;

			if ( $ext && isset( $mimes_media[ $ext ] ) ) {
				// Get the real mime type.
				$finfo     = finfo_open( FILEINFO_MIME_TYPE );
				$real_mime = finfo_file( $finfo, $file );
				finfo_close( $finfo );

				$is_valid_mime = ( $real_mime === $mimes_media[ $ext ] );

				if ( ! $is_valid_mime && in_array( $ext, [ 'svg', 'svgz' ], true ) ) {
					$is_valid_mime = in_array( $real_mime, [ 'image/svg+xml', 'text/xml', 'application/xml' ], true );
				}

				if ( $real_mime && $is_valid_mime ) {
					return [
						'ext'             => $ext,
						'type'            => $real_mime,
						'proper_filename' => sanitize_file_name( $filename ),
					];
				}
			}

			return [
				'ext'             => false,
				'type'            => false,
				'proper_filename' => false,
			];
		}

		$ext  = $checked_filetype_and_ext['ext'] ?? false;
		$type = $checked_filetype_and_ext['type'] ?? false;
		// Normalize extension to lowercase to handle uppercase extensions from cameras/Windows systems.
		$ext = $ext ? strtolower( $ext ) : $ext;

		$is_valid_type = ( isset( $mimes_media[ $ext ] ) && $type === $mimes_media[ $ext ] );

		if ( ! $is_valid_type && in_array( $ext, [ 'svg', 'svgz' ], true ) ) {
			$is_valid_type = in_array( $type, [ 'image/svg+xml', 'text/xml', 'application/xml' ], true );
		}

		if ( $ext && $type && isset( $mimes_media[ $ext ] ) && $is_valid_type ) {
			return $checked_filetype_and_ext;
		}

		return [
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		];
	}

	/**
	 * Filters the "real" file type of portability media files.
	 *
	 * This validates portability imports against image/video rules first, then
	 * allows JSON files (used by Lottie assets) through the existing JSON validator.
	 *
	 * @since ??
	 *
	 * @param array  $checked_filetype_and_ext {
	 *     Values for the extension, mime type, and corrected filename.
	 *
	 *     @type string|false $ext             File extension, or false if the file doesn't match a mime type.
	 *     @type string|false $type            File mime type, or false if the file doesn't match a mime type.
	 *     @type string|false $proper_filename File name with its correct extension, or false if it cannot be determined.
	 * }
	 * @param string $file                      Full path to the file.
	 * @param string $filename                  The name of the file (may differ from $file due to
	 *                                          $file being in a tmp directory).
	 *
	 * @return array
	 */
	public static function check_filetype_and_ext_portability_media( array $checked_filetype_and_ext, string $file, string $filename ): array {
		$validated_image = self::check_filetype_and_ext_image( $checked_filetype_and_ext, $file, $filename );

		if ( isset( $validated_image['ext'] ) && false !== $validated_image['ext'] ) {
			return $validated_image;
		}

		return self::check_filetype_and_ext_json( $checked_filetype_and_ext, $file, $filename );
	}

	/**
	 * Enable SVG uploads when SVG plugins are detected.
	 *
	 * This method ensures SVG files are properly supported in all contexts, including Visual Builder,
	 * when SVG plugins are available and the user has permission to upload SVG files.
	 *
	 * @since ??
	 *
	 * @param array $mimes Allowed mime types.
	 *
	 * @return array Modified mime types array.
	 */
	public function enable_svg_upload_mimes( array $mimes ): array {
		// Check if any SVG plugin is available.
		if ( ! $this->_is_svg_plugin_available() ) {
			return $mimes;
		}

		// Check user permission and allow site owners to override.
		$can_upload = (bool) apply_filters(
			'divi_current_user_can_upload_svg',
			(bool) apply_filters( 'safe_svg_current_user_can_upload', current_user_can( 'upload_files' ) )
		);

		if ( ! $can_upload ) {
			return $mimes;
		}

		// Add SVG mime types.
		if ( ! isset( $mimes['svg'] ) ) {
			$mimes['svg'] = 'image/svg+xml';
		}
		if ( ! isset( $mimes['svgz'] ) ) {
			$mimes['svgz'] = 'image/svg+xml';
		}

		return $mimes;
	}


	/**
	 * Add "Edit With Divi" button for non-singular pages with Theme Builder templates.
	 *
	 * This overrides the legacy admin bar logic to allow the button on archive/non-singular
	 * pages when Theme Builder templates are assigned, even if the Visual Theme Builder
	 * preference is disabled.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function add_edit_with_divi_button_for_theme_builder(): void {
		// Only on frontend, not admin.
		if ( is_admin() ) {
			return;
		}

		if ( ! Conditions::is_non_singular_theme_builder_context() ) {
			return;
		}

		// Check permissions.
		if ( ! et_pb_is_allowed( 'use_visual_builder' ) || ! et_pb_is_allowed( 'theme_builder' ) || ! et_pb_is_allowed( 'divi_builder_control' ) ) {
			return;
		}

		// Don't add if VB is already enabled (legacy code handles "Exit" button).
		if ( et_fb_is_enabled() ) {
			return;
		}

		global $wp_admin_bar;

		// Get page URL.
		$page_url = et_fb_get_page_url();

		// Build Visual Builder URL.
		$use_visual_builder_url = et_fb_get_builder_url( $page_url );

		// Add our button.
		$wp_admin_bar->add_menu(
			[
				'id'    => 'et-use-visual-builder',
				'title' => esc_html__( 'Edit With Divi', 'et_builder_5' ),
				'href'  => esc_url( $use_visual_builder_url ),
			]
		);
	}

	/**
	 * Boot Visual Builder for non-singular pages with Theme Builder templates.
	 *
	 * The legacy `et_fb_app_boot()` function checks `et_pb_is_pagebuilder_used( get_the_ID() )`
	 * which fails for non-singular pages. This hook handles non-singular pages with Theme Builder
	 * templates by booting the Visual Builder app wrapper.
	 *
	 * @since ??
	 *
	 * @param string $content The content being filtered.
	 *
	 * @return string The content (possibly wrapped with VB app container).
	 */
	public function boot_vb_for_non_singular_theme_builder( string $content ): string {
		if ( ! Conditions::is_non_singular_theme_builder_vb_context() ) {
			return $content;
		}

		// Get Theme Builder layouts to determine what to render.
		$theme_builder_layouts = et_theme_builder_get_template_layouts();

		$has_header_layout = ! empty( $theme_builder_layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ]['enabled'] ) && ! empty( $theme_builder_layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ]['override'] );
		$has_body_layout   = ! empty( $theme_builder_layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ]['enabled'] ) && ! empty( $theme_builder_layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ]['override'] );
		$has_footer_layout = ! empty( $theme_builder_layouts[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ]['enabled'] ) && ! empty( $theme_builder_layouts[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ]['override'] );

		// If no Theme Builder templates are assigned, don't boot VB.
		if ( ! $has_header_layout && ! $has_body_layout && ! $has_footer_layout ) {
			return $content;
		}

		$class = apply_filters( 'et_fb_app_preloader_class', 'et-fb-page-preloading' );
		$class = '' !== $class ? sprintf( ' class="%1$s"', et_core_esc_previously( esc_attr( $class ) ) ) : '';

		// et_builder_render_layout is called for individual Theme Builder layout rendering.
		// When a body template exists on non-singular pages, bootstrap VB from the body layout render path
		// to avoid duplicate non-editable frontend output.
		if ( doing_filter( 'et_builder_render_layout' ) ) {
			if ( ! $has_body_layout ) {
				return $content;
			}

			$rendering_post      = \ET_Post_Stack::get();
			$rendering_post_type = $rendering_post instanceof \WP_Post ? $rendering_post->post_type : get_post_type();

			// Only replace body layout render output. Header and footer layout output must remain intact.
			if ( ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE !== $rendering_post_type ) {
				return $content;
			}

			// Check if content is already wrapped (legacy code might have handled it).
			if ( str_contains( $content, 'id="et-fb-app"' ) ) {
				return $content;
			}

			return sprintf( '<div id="et-fb-app"%1$s><div id="et-fb-app-header-root" class="et-fb-root-area"></div><div id="et-fb-app-body-root" class="et-fb-root-area"></div><div id="et-fb-app-footer-root" class="et-fb-root-area"></div></div>', $class );
		}

		// For the_content path:
		// - only process main query,
		// - skip when body template exists (handled above in et_builder_render_layout path),
		// - keep legacy sidebar behavior unchanged.
		if ( ! is_main_query() || $has_body_layout ) {
			return $content;
		}

		// Check if content is already wrapped (legacy code might have handled it).
		if ( str_contains( $content, 'id="et-fb-app"' ) ) {
			return $content;
		}

		// Check if legacy code already handled this (it might have for WC shop, etc.).
		// We'll let legacy code handle sidebar layouts.
		if ( $this->should_render_app_wrapper_around_main_content() ) {
			// Exception: For non-singular pages (archives) where we're processing individual post content
			// in the loop, we should return the content as-is for excerpt rendering, not the app wrapper.
			// The app wrapper should only be used when rendering the actual page structure.
			if ( in_the_loop() && is_main_query() ) {
				$current_post = get_post();
				// For non-builder posts in the loop, return content unchanged (for excerpts).
				if ( $current_post && 'on' !== get_post_meta( $current_post->ID, '_et_pb_use_builder', true ) ) {
					return $content;
				}
			}
			// Mirror legacy app boot behavior: when wrapper is rendered around #main-content
			// via et_before_main_content/et_after_main_content, the_content should only output
			// body root placeholder.
			return '<div id="et-fb-app-body-root" class="et-fb-root-area"></div>';
		}

		// For non-singular pages without a body template, we need to preserve the normal WordPress content.
		// The header and footer templates will be rendered via portals in the React app,
		// but the normal archive/content needs to stay visible in the body area.
		// The React app will detect non-singular pages without body templates and preserve existing content.
		// Return app wrapper with normal content preserved in body-root.
		// The React component checks for non-singular pages (archive/home/404) without body templates
		// and skips rendering RootModules, preserving the existing WordPress content.
		return sprintf( '<div id="et-fb-app"%1$s><div id="et-fb-app-header-root" class="et-fb-root-area"></div><div id="et-fb-app-body-root" class="et-fb-root-area">%2$s</div><div id="et-fb-app-footer-root" class="et-fb-root-area"></div></div>', $class, $content );
	}

	/**
	 * Determine if the app wrapper should be rendered around #main-content.
	 *
	 * This mirrors the legacy D4 `et_fb_should_render_app_wrapper_around_main_content()` helper,
	 * but is implemented locally so the logic is available in WPUnit environments where
	 * the legacy frontend file might not be loaded.
	 *
	 * @return bool
	 */
	private function should_render_app_wrapper_around_main_content(): bool {
		// Mirror legacy guard: only in VB app-window and when fb is enabled.
		if ( ! Conditions::is_vb_app_window() || ! et_core_is_fb_enabled() ) {
			return false;
		}

		// This path is valid only when legacy wrapper callbacks are available.
		$has_before_wrapper_action = false !== has_action( 'et_before_main_content', 'et_fb_print_app_wrapper_before_main_content' );
		$has_after_wrapper_action  = false !== has_action( 'et_after_main_content', 'et_fb_print_app_wrapper_after_main_content' );
		$has_footer_close_action   = false !== has_action( 'get_footer', 'et_fb_ensure_app_wrapper_closed_before_footer' );

		if ( ! $has_before_wrapper_action || ! $has_after_wrapper_action || ! $has_footer_close_action ) {
			return false;
		}

		$computed_body_classes = et_divi_sidebar_class( [] );

		$has_sidebar_class    = in_array( 'et_right_sidebar', $computed_body_classes, true ) || in_array( 'et_left_sidebar', $computed_body_classes, true );
		$has_no_sidebar_class = in_array( 'et_no_sidebar', $computed_body_classes, true );

		// Fallback when theme helper isn't available.
		if ( ! $has_sidebar_class && ! $has_no_sidebar_class ) {
			$main_post_id = \ET_Post_Stack::get_main_post_id();
			$post_id      = $main_post_id ? $main_post_id : get_queried_object_id();
			$page_layout  = $post_id ? get_post_meta( $post_id, '_et_pb_page_layout', true ) : '';

			if ( ! $page_layout ) {
				$default_sidebar = et_get_option( 'divi_sidebar' );
				$page_layout     = $default_sidebar ? $default_sidebar : ( is_rtl() ? 'et_left_sidebar' : 'et_right_sidebar' );
			}

			$has_sidebar_class = in_array( $page_layout, [ 'et_left_sidebar', 'et_right_sidebar' ], true );
		}

		if ( ! $has_sidebar_class ) {
			return false;
		}

		$has_editable_theme_builder_template = et_fb_is_enabled_on_any_template();

		$is_non_singular_theme_builder_context = ! is_singular()
			&& $has_editable_theme_builder_template
			&& et_pb_is_allowed( 'theme_builder' );

		$main_post_id = \ET_Post_Stack::get_main_post_id();
		$post_id      = $main_post_id ? $main_post_id : get_the_ID();

		$should_wrap = $is_non_singular_theme_builder_context || ( $post_id && et_pb_is_pagebuilder_used( $post_id ) );

		return (bool) $should_wrap;
	}

	/**
	 * Add Visual Builder app container for regular singular posts/pages.
	 *
	 * This replicates D4's et_fb_app_boot() functionality for regular VB pages.
	 * Non-singular Theme Builder pages are handled by boot_vb_for_non_singular_theme_builder().
	 *
	 * @since ??
	 *
	 * @param string $content The content being filtered.
	 *
	 * @return string The content wrapped with #et-fb-app container.
	 */
	public function add_vb_app_container( string $content ): string {
		// Only apply in VB context.
		if ( ! Conditions::is_visual_builder_context() ) {
			return $content;
		}

		// Skip if doing excerpt (prevents app wrapper in excerpts).
		if ( doing_filter( 'get_the_excerpt' ) ) {
			return $content;
		}

		// Only for singular pages (non-singular is handled by boot_vb_for_non_singular_theme_builder).
		if ( ! is_singular() ) {
			return $content;
		}

		// Check if already wrapped.
		if ( str_contains( $content, 'id="et-fb-app"' ) ) {
			return $content;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- No need to use nonce in read-only URL context.
		$is_new_page = isset( $_GET['is_new_page'] ) && '1' === $_GET['is_new_page'];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$main_query_post      = \ET_Post_Stack::get_main_post();
		$main_query_post_type = $main_query_post ? $main_query_post->post_type : '';

		// Avoid boot when a Theme Builder layout is being rendered for a different WP query post.
		if (
			class_exists( '\ET_Theme_Builder_Layout' )
			&& \ET_Theme_Builder_Layout::is_theme_builder_layout()
			&& ! et_theme_builder_is_layout_post_type( $main_query_post_type )
			&& is_singular()
		) {
			return $content;
		}

		// Skip if the current post isn't using the page builder.
		// Note: for singular routes, the D4 "non-singular Theme Builder context" exemption is not applicable.
		if ( ! et_pb_is_pagebuilder_used( get_the_ID() ) ) {
			return $content;
		}

		$class = apply_filters( 'et_fb_app_preloader_class', 'et-fb-page-preloading' );
		$class = '' !== $class ? sprintf( ' class="%1$s"', esc_attr( $class ) ) : '';

		// Track instances because `is_main_query()` can be true multiple times for the same request.
		static $instances = 0;

		if ( is_main_query() ) {
			// Check if wrapper may already be printed around #main-content.
			if ( $this->should_render_app_wrapper_around_main_content() ) {
				return '<div id="et-fb-app-body-root" class="et-fb-root-area"></div>';
			}

			++$instances;
			$output = sprintf( '<div id="et-fb-app"%1$s></div>', $class );

			// Fallback for multi-render main query scenarios.
			if ( $instances > 1 && ! $is_new_page ) {
				$output .= sprintf(
					'<div class="et_fb_fallback_content" style="display: none">%s</div>',
					$content
				);
				et_fb_reset_shortcode_object_processing();
			}

			return $output;
		}

		// Outside main query, ensure shortcode processing state is reset.
		et_fb_reset_shortcode_object_processing();

		return $content;
	}

	/**
	 * Prevent legacy VB boot hook from running for non-singular pages with Theme Builder templates.
	 *
	 * This removes the legacy `et_fb_app_boot` hook for non-singular pages with TB templates,
	 * preventing it from creating fallback wrappers that interfere with our D5 implementation.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function prevent_legacy_vb_boot_for_non_singular(): void {
		if ( ! Conditions::is_non_singular_theme_builder_vb_context() ) {
			return;
		}

		$theme_builder_layouts = et_theme_builder_get_template_layouts();

		$has_body_layout = ! empty( $theme_builder_layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ]['enabled'] ) && ! empty( $theme_builder_layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ]['override'] );

		// Remove the legacy hook to prevent it from creating fallback wrappers.
		remove_filter( 'the_content', 'et_fb_app_boot', 1 );
		remove_filter( 'et_builder_render_layout', 'et_fb_app_boot', 1 );

		// Legacy main-content app wrapper actions are still useful for no-body-template
		// non-singular pages (archive/category loops) because they wrap at page level
		// rather than inside individual loop item content. Disable them only when body
		// template exists to avoid nested wrappers.
		if ( $has_body_layout ) {
			remove_action( 'et_before_main_content', 'et_fb_print_app_wrapper_before_main_content', 1 );
			remove_action( 'et_after_main_content', 'et_fb_print_app_wrapper_after_main_content', 999 );
			remove_action( 'get_footer', 'et_fb_ensure_app_wrapper_closed_before_footer', 0 );
		}
	}

	/**
	 * Force HTTP 200 for non-singular Theme Builder Visual Builder sessions on 404 pages.
	 *
	 * When VB is opened from a 404 frontend page that has assigned Theme Builder templates,
	 * WordPress can keep the main query and response status as 404. This normalizes the
	 * request to 200 so editor loading and related requests do not inherit the 404 status.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function force_success_status_for_non_singular_theme_builder_vb(): void {
		if ( ! Conditions::is_non_singular_theme_builder_vb_context() || ! is_404() ) {
			return;
		}
		// Keep the request context as 404 so Theme Builder can resolve 404 template assignments.
		// Only normalize the actual HTTP status code for Visual Builder loading.
		status_header( 200 );
	}

	/**
	 * Fix unclosed HTML tags in nav menu output.
	 *
	 * Ensures proper HTML structure by fixing unclosed tags in menu output,
	 * preventing broken Visual Builder rendering due to malformed HTML.
	 *
	 * @since ??
	 *
	 * @param string $menu The HTML content of the menu.
	 *
	 * @return string The fixed menu HTML.
	 */
	public function fix_nav_menu_unclosed_tags( string $menu ): string {
		if ( ! Conditions::is_visual_builder_context() ) {
			return $menu;
		}

		if ( function_exists( 'et_core_fix_unclosed_html_tags' ) ) {
			return et_core_fix_unclosed_html_tags( $menu );
		}

		return $menu;
	}

	/**
	 * Store sidebar output buffering state.
	 *
	 * @since ??
	 *
	 * @var bool
	 */
	private $_dynamic_sidebar_buffering = false;

	/**
	 * Start output buffering for dynamic sidebar.
	 *
	 * Captures sidebar output for HTML tag balancing before rendering.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function dynamic_sidebar_ob_start(): void {
		if ( ! Conditions::is_visual_builder_context() ) {
			return;
		}

		if ( $this->_dynamic_sidebar_buffering ) {
			echo et_core_intentionally_unescaped( force_balance_tags( ob_get_clean() ), 'html' ); // Content comes from dynamic_sidebar() and is balanced before output to preserve widget markup.

		}

		$this->_dynamic_sidebar_buffering = true;
		ob_start();
	}

	/**
	 * End output buffering for dynamic sidebar and output balanced HTML.
	 *
	 * Processes buffered sidebar content through force_balance_tags() to ensure
	 * properly closed HTML tags.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function dynamic_sidebar_after_ob_get_clean(): void {
		if ( ! Conditions::is_visual_builder_context() ) {
			return;
		}

		if ( $this->_dynamic_sidebar_buffering ) {
			echo et_core_intentionally_unescaped( force_balance_tags( ob_get_clean() ), 'html' ); // Content comes from dynamic_sidebar() and is balanced before output to preserve widget markup.
			$this->_dynamic_sidebar_buffering = false;
		}
	}

	/**
	 * Disable admin bar style for Visual Builder.
	 *
	 * Disables the built-in admin bar styling in VB context because the admin bar
	 * is loaded on the top window and affected by top window width instead of
	 * app window width (which changes based on preview mode).
	 *
	 * @see _admin_bar_bump_cb()
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function disable_admin_bar_style(): void {
		if ( ! Conditions::is_visual_builder_context() ) {
			return;
		}

		add_theme_support( 'admin-bar', [ 'callback' => '__return_false' ] );
	}

	/**
	 * Output WP auth check HTML for Visual Builder.
	 *
	 * Outputs the WordPress authentication check HTML for session expiry handling.
	 * Replaces the default `<button>` element with `<a>` for better styling in Chrome.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function output_wp_auth_check_html(): void {
		if ( ! Conditions::is_visual_builder_context() ) {
			return;
		}

		ob_start();
		wp_auth_check_html();
		$output = ob_get_contents();
		ob_end_clean();

		// Replace <button> with <a> element as the close button looks ugly in Chrome.
		$output = str_replace(
			[ '<button type="button"', '</button>' ],
			[ '<a href="#"', '</a>' ],
			$output
		);

		echo et_core_intentionally_unescaped( $output, 'html' ); // Content comes from wp_auth_check_html() and is safe to output after the specific string replacement.
	}

	/**
	 * Remove RTL stylesheet for Visual Builder.
	 *
	 * Removes the RTL stylesheet in Visual Builder context to ensure
	 * proper LTR rendering in the builder interface.
	 *
	 * @since ??
	 *
	 * @param string $uri The stylesheet URI.
	 *
	 * @return string The modified stylesheet URI.
	 */
	public function remove_rtl_stylesheet( string $uri ): string {
		if ( ! Conditions::is_visual_builder_context() ) {
			return $uri;
		}

		$template_dir_uri = get_template_directory_uri();
		$uri              = str_replace( $template_dir_uri . '/rtl.css', '', $uri );

		return $uri;
	}

	/**
	 * Remove RTL direction attribute from HTML tag for Visual Builder.
	 *
	 * Removes the dir="rtl" attribute from the HTML tag in Visual Builder context
	 * to ensure proper LTR rendering in the builder interface.
	 *
	 * @since ??
	 *
	 * @param string $attributes The HTML attributes string.
	 *
	 * @return string The modified attributes string.
	 */
	public function remove_html_rtl_dir( string $attributes ): string {
		if ( ! Conditions::is_visual_builder_context() ) {
			return $attributes;
		}

		$attributes = str_replace( 'dir="rtl"', '', $attributes );

		return $attributes;
	}

	/**
	 * Check if any SVG plugin is available (not necessarily active in current context).
	 *
	 * @since ??
	 *
	 * @return bool True if SVG plugin is available.
	 */
	private function _is_svg_plugin_available(): bool {
		// Check for Safe SVG plugin.
		if ( class_exists( 'SafeSvg\\safe_svg' ) ) {
			return true;
		}

		// Check for SVG Support plugin.
		if ( function_exists( 'bodhi_svgs_init' ) ) {
			return true;
		}

		// Check for Enable SVG Upload plugin.
		if ( function_exists( 'wp_svg_allowed' ) ) {
			return true;
		}

		// Check for Easy SVG Support plugin.
		if ( function_exists( 'esw_add_support' ) ) {
			return true;
		}

		// Do not fallback to generic allowed mime detection to avoid enabling
		// SVG without a known sanitizer plugin present.
		return false;
	}
}
