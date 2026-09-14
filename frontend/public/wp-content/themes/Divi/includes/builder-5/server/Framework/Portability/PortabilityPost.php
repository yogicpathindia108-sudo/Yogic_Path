<?php
/**
 * PortabilityPost
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Portability;

use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\VisualBuilder\Hooks\HooksRegistration;
use ET_Builder_Module_Settings_Migration;
use ET\Builder\Packages\Conversion\Conversion;
use ET\Builder\Packages\Conversion\ShortcodeMigration;
use ET\Builder\Framework\Utility\Filesystem;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Packages\GlobalData\Utils\PresetContentUtils;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElementsUtils;
use ET\Builder\Packages\Conversion\Utils\ConversionUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleUtils\CanvasUtils;
use ET\Builder\Packages\ModuleUtils\ImageUtils;
use ET\Builder\VisualBuilder\OffCanvas\OffCanvasHooks;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\VisualBuilder\Saving\SavingUtility;
use ET_Core_Portability;
use WP_Error;
use WP_Filesystem_Direct;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Handles the portability of Posts for D5 Visual Builder.
 *
 * @since ??
 */
class PortabilityPost {

	use PortabilityPostTraits\GetGlobalColorsDataTrait;

	/**
	 * Params data holder.
	 *
	 * Holds the current request parameters.
	 *
	 * @since ??
	 *
	 * @var array $_params Defaults to `[]`.
	 */
	private $_params = [];

	/**
	 * Cached current site host for portability URL checks.
	 *
	 * @since ??
	 *
	 * @var string|null
	 */
	private $_site_host = null;

	/**
	 * Whether or not an import is in progress.
	 *
	 * @since 3.0.99
	 *
	 * @var bool
	 */
	protected static $_doing_import = false;

	/**
	 * Current instance.
	 *
	 * @since 2.7.0
	 *
	 * @var object $instance The current instance of the parent class (`PortabilityPost`) that uses this trait.
	 */
	public $instance;

	/**
	 * Create an instance of the `PortabilityPost` class.
	 *
	 * @since ??
	 *
	 * @param string $context Portability context previously registered.
	 *
	 * @return void
	 */
	public function __construct( string $context ) {
		$this->instance = et_core_cache_get( $context, 'et_core_portability' );
	}


	/**
	 * Injects the given Global Presets settings into the imported layout.
	 *
	 * @since 3.26
	 *
	 * @param array $shortcode_object {
	 *     The multidimensional array representing a page/module structure.
	 *     Note: Passed by reference.
	 *
	 *     @type array  attrs   Module attributes.
	 *     @type string content Module content.
	 *     @type string type    Module type.
	 * }
	 * @param array $global_presets   - The Global Presets to be applied.
	 *
	 * @return void
	 */
	public function apply_global_presets( &$shortcode_object, $global_presets ) {
		$global_presets_manager  = \ET_Builder_Global_Presets_Settings::instance();
		$module_preset_attribute = \ET_Builder_Global_Presets_Settings::MODULE_PRESET_ATTRIBUTE;

		foreach ( $shortcode_object as &$module ) {
			$module_type = $global_presets_manager->maybe_convert_module_type( $module['type'], $module['attrs'] );

			if ( isset( $global_presets[ $module_type ] ) ) {
				$default_preset_id = et_()->array_get( $global_presets, "{$module_type}.default", null );
				$module_preset_id  = et_()->array_get( $module, "attrs.{$module_preset_attribute}", $default_preset_id );

				if ( 'default' === $module_preset_id ) {
					$module_preset_id = $default_preset_id;
				}

				if ( isset( $global_presets[ $module_type ]['presets'][ $module_preset_id ] ) ) {
					$module['attrs'] = array_merge( $global_presets[ $module_type ]['presets'][ $module_preset_id ]['settings'], $module['attrs'] );
				} elseif ( isset( $global_presets[ $module_type ]['presets'][ $default_preset_id ]['settings'] ) ) {
						$module['attrs'] = array_merge( $global_presets[ $module_type ]['presets'][ $default_preset_id ]['settings'], $module['attrs'] );
				}
			}

			if ( isset( $module['content'] ) && is_array( $module['content'] ) ) {
				$this->apply_global_presets( $module['content'], $global_presets );
			}
		}
	}

	/**
	 * Restrict data according the argument registered.
	 *
	 * @since 2.7.0
	 *
	 * @param array  $data   Array of data the query is applied on.
	 * @param string $method Whether data should be set or reset. Accepts `set` or `unset` which
	 *                       should be used when treating existing data in the db.
	 *
	 * @return array
	 */
	public function apply_query( $data, $method ) {
		$operator = ( 'set' === $method ) ? true : false;
		$ids      = array_keys( $data );

		foreach ( $ids as $id ) {
			if ( ! empty( $this->instance->exclude ) && isset( $this->instance->exclude[ $id ] ) === $operator ) {
				unset( $data[ $id ] );
			}

			if ( ! empty( $this->instance->include ) && isset( $this->instance->include[ $id ] ) === ! $operator ) {
				unset( $data[ $id ] );
			}
		}

		return $data;
	}

	/**
	 * Serialize images in chunks.
	 *
	 * @since 4.0
	 *
	 * @param array   $images A list of all the images to be processed.
	 * @param string  $method Method applied on images.
	 * @param string  $id     Unique ID to use for temporary files.
	 * @param integer $chunk  Optional. Current chunk. Defaults to 0.
	 *
	 * @return array {
	 *     @type bool  ready  Whether we have iterated over all the available chunks.
	 *     @type int   chunks The number of chunks.
	 *     @type array images The serialized images.
	 * }
	 */
	public function chunk_images( array $images, string $method, string $id, int $chunk = 0 ): array {
		$images_per_chunk = 5;
		$chunks           = 1;

		// Whether to paginate images.
		$paginate_images = true;

		/**
		 * Filters whether or not images in the file being imported should be paginated.
		 *
		 * @since 3.0.99
		 * @deprecated 5.0.0 Use `divi_framework_portability_paginate_images` hook instead.
		 *
		 * @param bool $paginate_images Whether to paginate images. Default is `true`.
		 */
		$paginate_images = apply_filters(
			'et_core_portability_paginate_images',
			$paginate_images
		);

		// Type cast for the filter hook.
		$paginate_images = (bool) $paginate_images;

		/**
		 * Filters the error message shown when `ET_Core_Portability::import()` fails.
		 *
		 * @since ??
		 *
		 * @param bool $paginate_images Whether to paginate images. Default is `true`.
		 */
		$paginate_images = apply_filters( 'divi_framework_portability_paginate_images', $paginate_images );

		if ( $paginate_images && count( $images ) > $images_per_chunk ) {
			$chunks       = ceil( count( $images ) / $images_per_chunk );
			$slice        = $images_per_chunk * $chunk;
			$images       = array_slice( $images, $slice, $images_per_chunk );
			$images       = $this->$method( $images );
			$filesystem   = $this->get_filesystem();
			$temp_file_id = sanitize_file_name( "images_{$id}" );
			$temp_file    = $this->temp_file( $temp_file_id, 'et_core_export' );
			$temp_images  = json_decode( $filesystem->get_contents( $temp_file ), true );

			if ( is_array( $temp_images ) ) {
				$images = array_merge( $temp_images, $images );
			}

			if ( $chunk + 1 < $chunks ) {
				$filesystem->put_contents( $temp_file, wp_json_encode( (array) $images ) );
			} else {
				$this->delete_temp_files( 'et_core_export', [ $temp_file_id => $temp_file ] );
			}
		} else {
			$images = $this->$method( $images );
		}

		return [
			'ready'  => $chunk + 1 >= $chunks,
			'chunks' => $chunks,
			'images' => $images,
		];
	}

	/**
	 * Check whether or not an import is in progress.
	 *
	 * This is a getter function for the protected variable `$_doing_import`.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function doing_import() {
		return self::$_doing_import;
	}

	/**
	 * Encode an image attachment.
	 *
	 * If a given Post ID has a valid attached file, return that file as a Base64 encoded string.
	 *
	 * @since 3.22.3
	 *
	 * @param int $id Attachment image ID.
	 *
	 * @return string The encoded image, or empty string if attachment is not found.
	 */
	public function encode_attachment_image( $id ) {
		global $wp_filesystem;

		if ( ! current_user_can( 'read_post', $id ) ) {
			return '';
		}

		$file = get_attached_file( $id );

		if ( ! $wp_filesystem->exists( $file ) ) {
			return '';
		}

		$image = $wp_filesystem->get_contents( $file );

		if ( empty( $image ) ) {
			return '';
		}

		return base64_encode( $image ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Intentionally encoding the image during export process.
	}

	/**
	 * Encode image(s) in a base64 format.
	 *
	 * @since 2.7.0
	 *
	 * @param array $images {
	 *     Array of URLs for images to encode.
	 *
	 *     @type string|int    $key The key for the image.
	 *     @type string $value The URL for the image.
	 * }
	 *
	 * @return array {
	 *     An array of the the encoded image(s).
	 *     If an image is not found, it is not added to the result array hence the result array can be empty if none of the images were found.
	 *
	 *     @type array {
	 *         @type string|int $key The key.
	 *         @type string encoded The encoded image data.
	 *         @type string url The URL of the image.
	 *     }
	 * }
	 */
	public function encode_images( $images ) {
		$encoded = [];

		foreach ( $images as $url ) {
			$id    = 0;
			$image = '';

			// Skip invalid values - must be integer (image ID) or valid URL.
			if ( ! is_int( $url ) && ! is_string( $url ) ) {
				continue;
			}

			// Skip empty strings.
			if ( is_string( $url ) && empty( trim( $url ) ) ) {
				continue;
			}

			// Skip non-URL strings that aren't integers (e.g., admin labels, HTML content, shortcodes).
			if ( is_string( $url ) && ! wp_http_validate_url( $url ) && ! is_numeric( $url ) ) {
				continue;
			}

			if ( is_int( $url ) ) {
				$id  = $url;
				$url = wp_get_attachment_url( $id );
			} else {
				$id = $this->get_attachment_id_by_url( $url );
			}

			if ( $id > 0 ) {
				$image = $this->encode_attachment_image( $id );
			}

			if ( empty( $image ) ) {
				// Case 1: No attachment found.
				// Case 2: Attachment found, but file does not exist (may be stored on a CDN, for example).
				$image = $this->encode_remote_image( $url );
			}

			if ( empty( $image ) ) {
				// All fetching methods have failed - bail on encoding.
				continue;
			}

			$encoded[ $url ] = [
				'encoded' => $image,
				'url'     => $url,
			];

			// Add image id for replacement purposes.
			if ( $id > 0 ) {
				$encoded[ $url ]['id'] = $id;
			}
		}

		return $encoded;
	}

	/**
	 * Base64 encode a remote image.
	 *
	 * This function uses `wp_remote_get` and associated methods to retrieve the image and process the result.
	 *
	 * @since 3.22.3
	 *
	 * @param string $url URL to be encoded.
	 *
	 * @return string The encoded image, or empty string if the remote image could not be retrieved.
	 */
	public function encode_remote_image( $url ) {
		$request = wp_remote_get(
			esc_url_raw( $url ),
			[
				'timeout'     => 2,
				'redirection' => 2,
			]
		);

		if ( ! is_array( $request ) || is_wp_error( $request ) ) {
			return '';
		}

		$content_type = isset( $request['headers']['content-type'] ) ? $request['headers']['content-type'] : 'unknown';

		if ( ! str_contains( $content_type, 'image' ) ) {
			return '';
		}

		$image = wp_remote_retrieve_body( $request );

		if ( ! $image ) {
			return '';
		}

		return base64_encode( $image );  // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Intentionally encoding the image during export process.
	}

	/**
	 * Get selected posts data.
	 *
	 * The post type retrieved is based on the current instance target value: `$this->instance->target`
	 * If post ID(s) are provided via `URL PARAMS -> selection` are provided, the retrieved posts will be limited to these ID(s).
	 *
	 * @since 2.7.0
	 *
	 * @return array An array of WP_Post objects.
	 */
	public function export_posts_query(): array {
		et_core_nonce_verified_previously();

		$args = [
			'post_type'      => $this->instance->target,
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		];

		// Only include selected posts if set and not empty.
		$selection = $this->get_param( 'selection' );

		if ( null !== $selection ) {
			$include = json_decode( stripslashes( $selection ), true );

			if ( ! empty( $include ) ) {
				$include          = array_map( 'intval', array_values( $include ) );
				$args['post__in'] = $include;
			}
		}

		// Context from the current instance.
		$context = $this->instance->context;

		/**
		 * Filters the posts/layout export WP_Query.
		 *
		 * @since 4.x
		 * @deprecated 5.0.0 Use `divi_framework_portability_export_wp_query_{$current_instance_context}` hook instead.
		 *
		 * @param string $context Context of the current instance.
		 * @param array  $args    WP_Query arguments.
		 */
		$context = apply_filters(
			"et_core_portability_export_wp_query_{$context}",
			$args
		);

		/**
		 * Filters the posts/layout export WP_Query.
		 *
		 * @since ??
		 *
		 * @param string $context Context of the current instance.
		 * @param array  $args    WP_Query arguments.
		 */
		$export_wp_query = apply_filters( "divi_framework_portability_export_wp_query_{$context}", $args );

		$get_posts  = get_posts( $export_wp_query );
		$taxonomies = get_object_taxonomies( $this->instance->target );
		$posts      = [];

		foreach ( $get_posts as $post ) {
			unset(
				$post->post_author,
				$post->guid
			);

			$posts[ $post->ID ] = $post;

			// Include post meta.
			$post_meta = (array) get_post_meta( $post->ID );

			if ( isset( $post_meta['_edit_lock'] ) ) {
				unset(
					$post_meta['_edit_lock'],
					$post_meta['_edit_last']
				);
			}

			$posts[ $post->ID ]->post_meta = $post_meta;

			// Include terms.
			$get_terms = (array) wp_get_object_terms( $post->ID, $taxonomies );
			$terms     = [];

			// Order terms to make sure children are after the parents.
			while ( true ) {
				$term = array_shift( $get_terms );

				if ( ! $term ) {
					break;
				}

				if ( 0 === $term->parent || isset( $terms[ $term->parent ] ) ) {
					$terms[ $term->term_id ] = $term;
				} elseif ( $this->is_parent_term_included( $get_terms, $term->parent ) ) {
					// if parent category is also exporting then add the term to the end of the list and process it later.
					$get_terms[] = $term;
				} else {
					// otherwise add a term as usual.
					$terms[ $term->term_id ] = $term;
				}
			}

			$posts[ $post->ID ]->terms = [];

			foreach ( $terms as $term ) {
				$parents_data = [];

				if ( $term->parent ) {
					$parent_slug  = isset( $terms[ $term->parent ] ) ? $terms[ $term->parent ]->slug : $this->get_parent_slug( $term->parent, $term->taxonomy );
					$parents_data = $this->get_all_parents( $term->parent, $term->taxonomy );
				} else {
					$parent_slug = 0;
				}

				$posts[ $post->ID ]->terms[ $term->term_id ] = [
					'name'        => $term->name,
					'slug'        => $term->slug,
					'taxonomy'    => $term->taxonomy,
					'parent'      => $parent_slug,
					'all_parents' => $parents_data,
					'description' => $term->description,
				];
			}
		}

		return $posts;
	}

	/**
	 * Proxy method for set_filesystem() to avoid calling it multiple times.
	 *
	 * @since ??
	 *
	 * @return WP_Filesystem_Direct
	 */
	public function get_filesystem(): WP_Filesystem_Direct {
		return $this->set_filesystem();
	}

	/**
	 * Set WP filesystem to direct. This should only be use to create a temporary file.
	 *
	 * @since ??
	 *
	 * @return WP_Filesystem_Direct
	 */
	public function set_filesystem(): WP_Filesystem_Direct {
		return Filesystem::set();
	}

	/**
	 * Check if a temporary file is registered. Returns temporary file if it exists.
	 *
	 * @since ??
	 *
	 * @param string $id    Unique id used when the temporary file was created.
	 * @param string $group Group name in which one or more files are grouped.
	 *
	 * @return bool|string Returns false if the temporary file does not exist, otherwise returns the file.
	 */
	public function has_temp_file( string $id, string $group ) {
		$temp_files = get_option( '_et_core_portability_temp_files', [] );

		if ( isset( $temp_files[ $group ][ $id ] ) && file_exists( $temp_files[ $group ][ $id ] ) ) {
			return $temp_files[ $group ][ $id ];
		}

		return false;
	}

	/**
	 * Create a temp file and register it.
	 *
	 * @since 2.7.0
	 * @since 4.0 Made method public. Added $content parameter.
	 *
	 * @param string      $id        Unique id reference for the temporary file.
	 * @param string      $group     Group name in which files are grouped.
	 * @param string|bool $temp_file Optional. Path to the temporary file.
	 *                               Passing `false` will create a new temporary file.
	 *                               Defaults to `false`.
	 * @param string      $content   Optional. The temporary file content. Defaults to empty string.
	 *
	 * @return bool|string
	 */
	public function temp_file( string $id, string $group, $temp_file = false, string $content = '' ) {
		$temp_files = get_option( '_et_core_portability_temp_files', [] );

		if ( ! isset( $temp_files[ $group ] ) ) {
			$temp_files[ $group ] = [];
		}

		if ( isset( $temp_files[ $group ][ $id ] ) && file_exists( $temp_files[ $group ][ $id ] ) ) {
			return $temp_files[ $group ][ $id ];
		}

		$temp_file                   = $temp_file ? $temp_file : wp_tempnam();
		$temp_files[ $group ][ $id ] = $temp_file;

		update_option( '_et_core_portability_temp_files', $temp_files, false );

		if ( ! empty( $content ) ) {
			$this->get_filesystem()->put_contents( $temp_file, $content );
		}

		return $temp_file;
	}

	/**
	 * Import a previously exported layout.
	 *
	 * @since 2.7.0
	 * @since 3.10 Return the result of the import instead of dying.
	 * @since ?? Removed `$file_context` because 'upload' is the only file context used.
	 *
	 * @param array                $files        Array of file objects.
	 * @param WP_Filesystem_Direct $filesystem   The filesystem object.
	 * @param string               $temp_file_id The ID of the temp file for upload.
	 *
	 * @return array {
	 *   Array of import result.
	 *
	 *   @type string $message The import result message.
	 * }
	 */
	public function upload_file( array $files, WP_Filesystem_Direct $filesystem, string $temp_file_id ): array {
		if ( ! isset( $files['file']['name'] ) || ! et_()->ends_with( sanitize_file_name( $files['file']['name'] ), '.json' ) ) {
			return [ 'message' => 'invalideFile' ];
		}

		// phpcs:ignore ET.Functions.DangerousFunctions.ET_handle_upload -- test_type is enabled and proper type and extension checking are implemented.
		$upload = wp_handle_upload(
			$files['file'],
			[
				'test_size' => false,
				'test_type' => true,
				'test_form' => false,
			]
		);

		// The absolute path to the uploaded JSON file's temporary location.
		$file = $upload['file'];

		/**
		 * Fires before an uploaded Portability JSON file is processed.
		 *
		 * This is for backward compatibility with hooks written for Divi version <5.0.0.
		 *
		 * @since 3.0.99
		 * @deprecated 5.0.0 Use `divi_framework_portability_import_file` hook instead.
		 *
		 * @param string $file The absolute path to the uploaded JSON file's temporary location.
		 */
		do_action(
			'et_core_portability_import_file',
			$file
		);

		/**
		 * Fires before an uploaded Portability JSON file is processed.
		 *
		 * @param string $file The absolute path to the uploaded JSON file's temporary location.
		 *
		 * @since ??
		 */
		do_action( 'divi_framework_portability_import_file', $file );

		$temp_file = $this->temp_file( $temp_file_id, 'et_core_import', $upload['file'] );
		$import    = json_decode( $filesystem->get_contents( $temp_file ), true );
		$import    = $this->validate( $import );

		$import['data'] = $this->apply_query( $import['data'], 'set' );

		$filesystem->put_contents( $upload['file'], wp_json_encode( (array) $import ) );

		return [ 'message' => 'success' ];
	}

	/**
	 * Delete all the temp files.
	 *
	 * @since 2.7.0
	 *
	 * @param bool|string $group         Optional. Group name in which files are grouped.
	 *                                   Set to `true` to remove all groups and files. Defaults to `false`.
	 * @param array|bool  $defined_files Optional. Array or temporary files to delete.
	 *                                   Passing `false`/no argument deletes all temp files. Defaults to `false`.
	 *
	 * @return void
	 */
	public function delete_temp_files( $group = false, $defined_files = false ) {
		$filesystem = $this->get_filesystem();
		$temp_files = get_option( '_et_core_portability_temp_files', [] );

		// Remove all temp files accross all groups if group is true.
		if ( true === $group ) {
			foreach ( $temp_files as $group_id => $_group ) {
				$this->delete_temp_files( $group_id );
			}
		}

		if ( ! isset( $temp_files[ $group ] ) ) {
			return;
		}

		$delete_files = ( is_array( $defined_files ) && ! empty( $defined_files ) ) ? $defined_files : $temp_files[ $group ];

		foreach ( $delete_files as $id => $temp_file ) {
			if ( isset( $temp_files[ $group ][ $id ] ) && $filesystem->delete( $temp_files[ $group ][ $id ] ) ) {
				unset( $temp_files[ $group ][ $id ] );
			}
		}

		if ( empty( $temp_files[ $group ] ) ) {
			unset( $temp_files[ $group ] );
		}

		if ( empty( $temp_files ) ) {
			delete_option( '_et_core_portability_temp_files' );
		} else {
			update_option( '_et_core_portability_temp_files', $temp_files, false );
		}
	}

	/**
	 * Decode base64 formatted image and upload it to WP media.
	 *
	 * @since 2.7.0
	 *
	 * @param array $images {
	 *     Array of encoded images which needs to be uploaded.
	 *
	 *     @type array $image {
	 *         Array of  image attributes.
	 *
	 *         @type string|int $id      The image ID.
	 *         @type string     $url     The image URL.
	 *         @type string     $encoded The encoded value of the image.
	 *     }
	 * }
	 *
	 * @return array  {
	 *     Array of encoded images which needs to be uploaded.
	 *
	 *     @type array $image {
	 *         Array of  image attributes.
	 *
	 *         @type string|int $id              The image ID.
	 *         @type string     $url             The image URL.
	 *         @type string     $encoded         The encoded value of the image.
	 *         @type string     $replacement_url Optional. The replacement URL.
	 *     }
	 * }
	 */
	public function upload_images( array $images ): array {
		$filesystem = $this->get_filesystem();

		// Whether to allow duplicates. Default `false`.
		$allow_duplicates = false;

		/**
		 * Filters whether or not to allow duplicate images to be uploaded during Portability import.
		 *
		 * This is for backward compatibility with hooks written for Divi version <5.0.0.
		 *
		 * @since 4.14.8
		 * @deprecated 5.0.0 Use `divi_framework_portability_import_attachment_allow_duplicates` hook instead.
		 *
		 * @param bool $allow_duplicates Whether to allow duplicates. Default `false`.
		 */
		$allow_duplicates = apply_filters(
			'et_core_portability_import_attachment_allow_duplicates',
			$allow_duplicates
		);

		// Type cast for the filter hook.
		$allow_duplicates = (bool) $allow_duplicates;

		/**
		 * Filters whether or not to allow duplicate images to be uploaded during Portability import.
		 *
		 * @since ??
		 *
		 * @param bool $allow_duplicates Whether or not to allow duplicates. Default `false`.
		 */
		$allow_duplicates = apply_filters( 'divi_framework_portability_import_attachment_allow_duplicates', $allow_duplicates );

		foreach ( $images as $key => $image ) {
			$basename = sanitize_file_name( wp_basename( $image['url'] ) );
			$id       = 0;
			$url      = '';

			if ( ! $allow_duplicates ) {
				$attachments = get_posts(
					[
						'posts_per_page' => -1,
						'post_type'      => 'attachment',
						'meta_key'       => '_wp_attached_file',
						'meta_value'     => pathinfo( $basename, PATHINFO_FILENAME ),
						'meta_compare'   => 'LIKE',
					]
				);

				// Avoid duplicates.
				if ( ! is_wp_error( $attachments ) && ! empty( $attachments ) ) {
					foreach ( $attachments as $attachment ) {
						$attachment_url = wp_get_attachment_url( $attachment->ID );
						$file           = get_attached_file( $attachment->ID );
						$filename       = sanitize_file_name( wp_basename( $file ) );

						// Use existing image only if the content matches.
						if ( $filesystem->get_contents( $file ) === base64_decode( $image['encoded'] ) ) { // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Intentionally decoding the image during import process.
							$id  = isset( $image['id'] ) ? $attachment->ID : 0;
							$url = $attachment_url;

							break;
						}
					}
				}
			}

			// Create new image.
			if ( empty( $url ) ) {
				$temp_file = wp_tempnam();
				$filesystem->put_contents( $temp_file, base64_decode( $image['encoded'] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Intentionally decoding the image during import process.
				$filetype = wp_check_filetype_and_ext( $temp_file, $basename );

				if ( ! $allow_duplicates && ! empty( $attachments ) && ! is_wp_error( $attachments ) ) {
					// Avoid further duplicates if the proper_filename matches an existing image.
					if ( isset( $filetype['proper_filename'] ) && $filetype['proper_filename'] !== $basename ) {
						foreach ( $attachments as $attachment ) {
							$attachment_url = wp_get_attachment_url( $attachment->ID );
							$file           = get_attached_file( $attachment->ID );
							$filename       = sanitize_file_name( wp_basename( $file ) );

							if ( isset( $filename ) && $filename === $filetype['proper_filename'] ) {
								// Use existing image only if the basenames and content match.
								if ( $filesystem->get_contents( $file ) === $filesystem->get_contents( $temp_file ) ) {
									$id  = isset( $image['id'] ) ? $attachment->ID : 0;
									$url = $attachment_url;

									$filesystem->delete( $temp_file );

									break;
								}
							}
						}
					}
				}

				$file = [
					'name'     => $basename,
					'tmp_name' => $temp_file,
				];

				// Require necessary files for media_handle_sideload to work.
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';

				$upload        = media_handle_sideload( $file );
				$attachment_id = is_wp_error( $upload ) ? 0 : $upload;

				/**
				 * Fires when image attachments are created during portability import.
				 *
				 * @since 4.14.6
				 * @deprecated 5.0.0 Use `divi_framework_portability_import_attachment_created` hook instead.
				 *
				 * @param int $attachment_id The attachment id or 0 if attachment upload failed.
				 */
				do_action(
					'et_core_portability_import_attachment_created',
					$attachment_id
				);

				/**
				 * Fires when image attachments are created during portability import.
				 *
				 * @param int $attachment_id The attachment id or 0 if attachment upload failed.
				 *
				 * @since ??
				 */
				do_action( 'divi_framework_portability_import_attachment_created', $attachment_id );

				if ( ! is_wp_error( $upload ) ) {
					// Set the replacement as an id if the original image was set as an id (for gallery).
					$id  = isset( $image['id'] ) ? $upload : 0;
					$url = wp_get_attachment_url( $upload );
				} else {
					// Make sure the temporary file is removed if media_handle_sideload didn't take care of it.
					$filesystem->delete( $temp_file );
				}
			}

			// Only declare the replace if a url is set.
			if ( $id > 0 ) {
				$images[ $key ]['replacement_id'] = $id;
			}

			if ( ! empty( $url ) ) {
				$images[ $key ]['replacement_url'] = $url;
			}

			unset( $url );
		}

		return $images;
	}

	/**
	 * Filters a variable with string filter
	 *
	 * @since ??
	 *
	 * @param mixed $data - Value to filter.
	 *
	 * @return mixed
	 */
	public function filter_post_data( $data ) {
		return filter_var( $data, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES );
	}

	/**
	 * Decode and validate JSON parameter.
	 *
	 * Handles JSON parameters that may come from REST API already decoded (as arrays)
	 * or as JSON strings. Validates the decoded structure using the validate() method.
	 *
	 * @since ??
	 *
	 * @param mixed $param        The parameter value (can be array, string, or null).
	 * @param mixed $default_value Default value to return if parameter is null or invalid. Default null.
	 *
	 * @return mixed Returns validated array if successful, or $default_value if parameter is null/invalid.
	 */
	private function _decode_and_validate_json_param( $param, $default_value = null ) {
		if ( null === $param ) {
			return $default_value;
		}

		// If already decoded (from REST API sanitize callback), just validate.
		if ( is_array( $param ) ) {
			return $this->validate( $param );
		}

		// If it's a string, decode JSON and validate the structure.
		if ( is_string( $param ) && ! empty( $param ) ) {
			$decoded = json_decode( $param, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return $this->validate( $decoded );
			}
		}

		// Return default if decode fails or param is not a valid type.
		return $default_value;
	}

	/**
	 * Get and decode a global parameter (presets, colors, or variables).
	 *
	 * @since 5.0.0
	 *
	 * @param string $param_name The parameter name to retrieve.
	 * @param mixed  $default_value Default value to return if parameter is not set.
	 * @return mixed Decoded parameter value or default value.
	 */
	private function _get_and_decode_global_param( $param_name, $default_value = null ) {
		$param = $this->get_param( $param_name );
		if ( null !== $param ) {
			return $this->_decode_and_validate_json_param( $param, $default_value );
		}
		return $default_value;
	}

	/**
	 * Extract images from an array of canvases.
	 *
	 * @since 5.0.0
	 *
	 * @param array $canvas_array Array of canvas objects with 'content' property.
	 * @return array Array of extracted image data.
	 */
	private function _extract_images_from_canvas_array( $canvas_array ) {
		$images = [];
		if ( empty( $canvas_array ) ) {
			return $images;
		}

		foreach ( $canvas_array as $canvas ) {
			if ( ! empty( $canvas['content'] ) ) {
				$canvas_images = $this->get_data_images( [ $canvas['content'] ] );
				$images        = array_merge( $images, $canvas_images );
			}
		}

		return $images;
	}

	/**
	 * Prepare array of all parents so the correct hierarchy can be restored during the import.
	 *
	 * @since 2.7.0
	 *
	 * @param int    $parent_id .
	 * @param string $taxonomy  .
	 *
	 * @return array
	 */
	public function get_all_parents( $parent_id, $taxonomy ) {
		$parents_data_array = [];
		$parent             = $parent_id;

		// retrieve data for all parent categories.
		if ( 0 !== $parent ) {
			while ( 0 !== $parent ) {
				$parent_term_data                              = get_term( $parent, $taxonomy );
				$parents_data_array[ $parent_term_data->slug ] = [
					'name'        => $parent_term_data->name,
					'description' => $parent_term_data->description,
					'parent'      => 0 !== $parent_term_data->parent ? $this->get_parent_slug( $parent_term_data->parent, $taxonomy ) : 0,
				];

				$parent = $parent_term_data->parent;
			}
		}

		// Reverse order of items, to simplify the restoring process.
		return array_reverse( $parents_data_array );
	}

	/**
	 * Get the attachment post id for the given url.
	 *
	 * @since 3.22.3
	 *
	 * @param string $url The url of an attachment file.
	 *
	 * @return int
	 */
	public function get_attachment_id_by_url( $url ) {
		global $wpdb;

		// Remove any thumbnail size suffix from the filename and use that as a fallback.
		$fallback_url = preg_replace( '/-\d+x\d+(\.[^.]+)$/i', '$1', $url );

		// Scenario: Trying to find the attachment for a file called x-150x150.jpg.
		// 1. Since WordPress adds the -150x150 suffix for thumbnail sizes we cannot be
		// sure if this is an attachment or an attachment's generated thumbnail.
		// 2. Since both x.jpg and x-150x150.jpg can be uploaded as separate attachments
		// we must decide which is a better match.
		// 3. The above is why we order by guid length and use the first result.
		$attachment_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT id
				FROM $wpdb->posts
				WHERE `post_type` = %s
				AND `guid` IN ( %s, %s )
				ORDER BY CHAR_LENGTH( `guid` ) DESC
			",
				'attachment',
				esc_url_raw( $url ),
				esc_url_raw( $fallback_url )
			)
		);

		return $attachment_id;
	}

	/**
	 * Get all images in the data given.
	 *
	 * @since 2.7.0
	 *
	 * @param array $data  Array of data.
	 * @param bool  $force Set whether the value should be added by force. Usually used for image ids.
	 *
	 * @return array
	 */
	public function get_data_images( $data, $force = false ) {
		if ( empty( $data ) ) {
			return [];
		}

		$images     = [];
		$images_src = [];
		$basenames  = [
			'src',
			'image_url',
			'background_image',
			'image',
			'url',
			'bg_img_?\d?',
			'value',  // Add 'value' for video.innerContent.*.value patterns.
		];
		$suffixes   = [
			'__hover',
			'_tablet',
			'_phone',
		];

		foreach ( $basenames as $basename ) {
			$images_src[] = $basename;
			foreach ( $suffixes as $suffix ) {
				$images_src[] = $basename . $suffix;
			}
		}

		foreach ( $data as $value ) {
			// If the $value is an object and there is no post_content property,
			// it's unlikely to contain any image data so we can continue with the next iteration.
			if ( is_object( $value ) && ! property_exists( $value, 'post_content' ) ) {
				continue;
			}

			if ( is_array( $value ) || is_object( $value ) ) {
				// If the $value contains the post_content property, set $value to use
				// this object's property value instead of the entire object.
				if ( is_object( $value ) && property_exists( $value, 'post_content' ) ) {
					$value = $value->post_content;
				}

				$images = array_merge( $images, $this->get_data_images( (array) $value ) );
				continue;
			}

			// Extract images from Gutenberg formatted content.
			// Gutenberg format uses HTML comments similar to this one to store the blocks:
			// <!-- wp:divi/section [module JSON goes here] -->
			// We test the passed value against the "<!-- wp:" string.
			$maybe_gutenberg_format = preg_match( '/<!-- wp:/', $value );

			// If there is a match the content is tested for all the image attributes in JSON format.
			// The regex tests for all the attributes in the $images_src array.
			// For example: "src":"https://url-goes-here/image.png"
			// $matches[2] holds an array of all the image URLS matches this way.
			if ( $maybe_gutenberg_format && preg_match_all( '/"(' . implode( '|', $images_src ) . ')":"(.*?)"/i', $value, $matches ) && $matches[2] ) {
				foreach ( array_unique( $matches[2] ) as $extracted_value ) {
					// Skip empty values.
					if ( empty( $extracted_value ) ) {
						continue;
					}

					// Only add if it's a transferable media URL, or a positive attachment ID.
					if ( $this->is_transferable_media_url( $extracted_value ) ) {
						$images[] = $extracted_value;
					} elseif ( is_numeric( $extracted_value ) && (int) $extracted_value > 0 ) {
						$images[] = (int) $extracted_value;
					}
				}
			}

			// Extract images from HTML or shortcodes.
			if ( preg_match_all( '/(' . implode( '|', $images_src ) . ')="(?P<src>\w+[^"]*)"/i', $value, $matches ) ) {
				foreach ( array_unique( $matches['src'] ) as $key => $src ) {
					// Only process if it's a valid URL or integer.
					if ( wp_http_validate_url( $src ) || ( is_numeric( $src ) && (int) $src > 0 ) ) {
						$images = array_merge( $images, $this->get_data_images( [ $key => $src ] ) );
					}
				}
			}

			// Extract images from gutenberg/shortcodes gallery.
			if ( $maybe_gutenberg_format ) {
				preg_match_all( '/galleryIds":\{"(?P<type>[^"]+)":\{"value":"(?P<ids>[^"]+)"\}\}/i', $value, $matches );

				// Also check for gallery_ids patterns within Gutenberg content.
				// Divi 4 plugins use shortcode format even within Gutenberg blocks.
				// Regex101 link: https://regex101.com/r/FLWzYw/1.
				if ( preg_match_all( '/gallery_ids=\\\\u0022([0-9,]+)\\\\u0022/i', $value, $gallery_ids_matches ) ) {
					if ( empty( $matches['ids'] ) ) {
						$matches['ids'] = $gallery_ids_matches[1];
					} else {
						$matches['ids'] = array_merge( $matches['ids'], $gallery_ids_matches[1] );
					}
				}

				// Also check for DiviGear gallery pattern (gallery="...").
				// Regex101 link: https://regex101.com/r/gtzJZp/1.
				if ( preg_match_all( '/gallery=\\\\u0022([0-9,]+)\\\\u0022/i', $value, $divigear_matches ) ) {
					if ( empty( $matches['ids'] ) ) {
						$matches['ids'] = $divigear_matches[1];
					} else {
						$matches['ids'] = array_merge( $matches['ids'], $divigear_matches[1] );
					}
				}
			} else {
				preg_match_all( '/gallery_ids="(?P<ids>\w+[^"]*)"/i', $value, $matches );
			}

			if ( ! empty( $matches['ids'] ) ) {
				// Collect all individual image IDs first, then apply array_unique.
				$all_image_ids = [];
				foreach ( $matches['ids'] as $galleries ) {
					$explode       = explode( ',', str_replace( ' ', '', $galleries ) );
					$all_image_ids = array_merge( $all_image_ids, $explode );
				}

				// Now apply array_unique to individual image IDs, not gallery strings.
				$unique_image_ids = array_unique( $all_image_ids );

				foreach ( $unique_image_ids as $image_id ) {
					$result = $this->get_data_images( [ (int) $image_id ], true );
					if ( ! empty( $result ) ) {
						$images = array_merge( $images, $result );
					}
				}
			}

			$is_positive_id = is_numeric( $value ) && (int) $value > 0;

			if ( $force ) {
				// Force mode accepts transferable URLs and positive IDs.
				if ( ! ( $is_positive_id || ( is_string( $value ) && wp_http_validate_url( $value ) ) ) ) {
					continue;
				}
			} elseif ( ! ( is_string( $value ) && $this->is_transferable_media_url( $value ) ) ) {
				continue;
			}

			// Skip if the images array already contains the value to avoid duplicates.
			if ( isset( $images[ $value ] ) ) {
				continue;
			}

			$images[ $value ] = $value;
		}

		return $images;
	}

	/**
	 * Determine if a URL is transferable by portability.
	 *
	 * Portability supports image/video URLs globally and JSON URLs only when hosted on
	 * the current site (used for Lottie JSON assets).
	 *
	 * @since ??
	 *
	 * @param string $url URL to validate.
	 *
	 * @return bool
	 */
	private function is_transferable_media_url( string $url ): bool {
		if ( ! wp_http_validate_url( $url ) ) {
			return false;
		}

		if ( ImageUtils::is_media_url( $url ) ) {
			return true;
		}

		return ImageUtils::is_file_extension( $url, 'json' ) && $this->is_same_site_url( $url );
	}

	/**
	 * Check whether a URL host matches the current site host.
	 *
	 * @since ??
	 *
	 * @param string $url URL to compare.
	 *
	 * @return bool
	 */
	private function is_same_site_url( string $url ): bool {
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );
		$site_host = $this->get_site_host();

		if ( ! is_string( $url_host ) || ! is_string( $site_host ) ) {
			return false;
		}

		return strtolower( $url_host ) === strtolower( $site_host );
	}

	/**
	 * Get current site host, cached for repeated same-site checks.
	 *
	 * @since ??
	 *
	 * @return string|null
	 */
	private function get_site_host(): ?string {
		if ( null === $this->_site_host ) {
			$site_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
			$this->_site_host = is_string( $site_host ) ? $site_host : '';
		}

		return '' === $this->_site_host ? null : $this->_site_host;
	}

	/**
	 * Retrieve the layout content.
	 *
	 * @since ??
	 *
	 * @param array $data Layout content data.
	 *
	 * @return string The layout content.
	 */
	public function get_layout_content( $data ) {
		$first_data     = reset( $data );
		$layout_content = '';

		if ( is_string( $first_data ) || ! array_key_exists( 'post_content', $first_data ) ) {
			// D4 cloud item has no post_content.
			$layout_content = $first_data;
		} else {
			$layout_content = $first_data['post_content'];
		}

		return $layout_content;
	}

	/**
	 * Retrieve the term slug.
	 *
	 * @since 2.7.0
	 *
	 * @param int    $parent_id The ID of the parent term.
	 * @param string $taxonomy  The taxonomy name that the term is part of.
	 *
	 * @return int|string
	 */
	public function get_parent_slug( $parent_id, $taxonomy ) {
		$term_data = get_term( $parent_id, $taxonomy );
		$slug      = '' === $term_data->slug ? 0 : $term_data->slug;

		return $slug;
	}

	/**
	 * Get all thumbnail images in the data given.
	 *
	 * @since 4.7.4
	 *
	 * @param array $data Array of WP_Post objects.
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_post/ Definition of the core class used to implement the WP_Post object.
	 *
	 * @return array
	 */
	public function get_thumbnail_images( $data ) {
		$thumbnails = [];

		foreach ( $data as $post_data ) {
			// If post has thumbnail.
			if ( ! empty( $post_data->post_meta ) && ! empty( $post_data->post_meta->_thumbnail_id ) ) {
				$post_thumbnail = get_the_post_thumbnail_url( $post_data->ID );

				// If thumbnail image found in the WP Media library.
				if ( $post_thumbnail ) {
					$thumbnail_id    = (int) $post_data->post_meta->_thumbnail_id[0];
					$thumbnail_image = $this->encode_images( [ $thumbnail_id ] );

					$thumbnails[ $thumbnail_id ] = $thumbnail_image;
				}
			}
		}

		return $thumbnails;
	}

	/**
	 * Get timestamp or create one if it isn't set.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public function get_timestamp() {
		$timestamp = $this->get_param( 'timestamp' );

		if ( $timestamp ) {
			return sanitize_text_field( $timestamp );
		}

		return (string) microtime( true );
	}

	/**
	 * Get List of global colors used in shortcode.
	 *
	 * @since 4.10.8
	 *
	 * @param array $shortcode_object {
	 *     The multidimensional array representing a page structure.
	 *     Note: Passed by reference.
	 *
	 *     @type array  attrs    Module attributes.
	 *     @type string content Module content.
	 * }
	 * @param array $used_global_colors List of global colors to merge with.
	 *
	 * @return array - The list of the Global Colors.
	 */
	public function get_used_global_colors( $shortcode_object, $used_global_colors = [] ) {
		foreach ( $shortcode_object as $module ) {
			if ( isset( $module['attrs']['global_colors_info'] ) ) {
				// Retrieve global_colors_info from post meta, which saved as string[][].
				$gc_info_prepared   = str_replace(
					[ '&#91;', '&#93;' ],
					[ '[', ']' ],
					$module['attrs']['global_colors_info']
				);
				$used_global_colors = array_merge( $used_global_colors, json_decode( $gc_info_prepared, true ) );
			}

			if ( isset( $module['content'] ) && is_array( $module['content'] ) ) {
				$used_global_colors = array_merge( $used_global_colors, $this->get_used_global_colors( $module['content'], $used_global_colors ) );
			}
		}

		return $used_global_colors;
	}

	/**
	 * Returns Global Presets used for a given shortcode only
	 *
	 * @since 3.26
	 *
	 * @param array $shortcode_object {
	 *     The multidimensional array representing a page structure.
	 *     Note: Passed by reference.
	 *
	 *     @type array  attrs    Module attributes.
	 *     @type string content Module content.
	 *     @type string type    Module type.
	 * }
	 * @param array $used_global_presets The multidimensional array representing used global presets.
	 *
	 * @return array - The list of the Global Presets
	 */
	public function get_used_global_presets( $shortcode_object, $used_global_presets = [] ) {
		$global_presets_manager = \ET_Builder_Global_Presets_Settings::instance();

		foreach ( $shortcode_object as $module ) {
			$module_type = $global_presets_manager->maybe_convert_module_type( $module['type'], $module['attrs'] );
			$preset_id   = $global_presets_manager->get_module_preset_id( $module_type, $module['attrs'] );
			$preset      = $global_presets_manager->get_module_preset( $module_type, $preset_id );

			if ( 'default' !== $preset_id && count( (array) $preset ) !== 0 && count( (array) $preset->settings ) !== 0 ) {
				if ( ! isset( $used_global_presets[ $module_type ] ) ) {
					$used_global_presets[ $module_type ] = (object) [
						'presets' => (object) [],
					];
				}

				if ( ! isset( $used_global_presets[ $module_type ]->presets->$preset_id ) ) {
					$used_global_presets[ $module_type ]->presets->{$preset_id} = (object) [
						'name'     => $preset->name,
						'version'  => $preset->version,
						'settings' => $preset->settings,
					];
				}

				if ( ! isset( $used_global_presets[ $module_type ]->default ) ) {
					$used_global_presets[ $module_type ]->default = $global_presets_manager->get_module_default_preset_id( $module_type );
				}
			}

			if ( isset( $module['content'] ) && is_array( $module['content'] ) ) {
				$used_global_presets = array_merge( $used_global_presets, $this->get_used_global_presets( $module['content'], $used_global_presets ) );
			}
		}

		return $used_global_presets;
	}

	/**
	 * Convert global colors data from Import file.
	 *
	 * @since ??
	 *
	 * @param array $incoming_global_colors Global Colors Array.
	 *
	 * @return array
	 */
	public function import_global_colors( array $incoming_global_colors ): array {
		// Sanity check.
		if ( empty( $incoming_global_colors ) ) {
			$incoming_global_colors = [];
		}

		// Convert global colors data format from the $incoming_global_colors.
		return GlobalData::get_imported_global_colors( $incoming_global_colors );
	}

	/**
	 * Import Global Variables from Import file.
	 *
	 * @since ??
	 *
	 * @param array $incoming_global_variables Global Variables Array.
	 *
	 * @return array
	 */
	public function import_global_variables( array $incoming_global_variables ): array {
		// Convert global variables data format from the $incoming_global_variables.
		return GlobalData::import_global_variables( $incoming_global_variables );
	}


	/**
	 * Import post(s).
	 *
	 * Applies `et_core_portability_import_posts` and `divi_framework_portability_import_posts` filters before processing the posts.
	 *
	 * @since 2.7.0
	 *
	 * @param array $posts          {
	 *     Array of data formatted by the portability exporter.
	 *
	 *     @type string $post_status The status of the post e.g `auto-draft`, `published`.
	 *                              Posts with `auto-draft` status will not be imported.
	 *     @type string $post_name   The slug of the post.
	 *     @type string $post_title  The title of the post.
	 *     @type string $post_type   The post type e.g `post`, `page`.
	 *     @type int    $ID          The post ID.
	 *     @type int    $post_author The ID of the author.
	 *     @type int    $import_id   The post import ID.
	 *     @type array  $terms       The post taxonomy terms.
	 *     @type array  $post_meta   The post meta data.
	 *     @type string $thumbnail   The post thumbnail.
	 * }
	 *
	 * @return bool Returns `false` if the posts array is empty,
	 */
	public function import_posts( array $posts ): bool {
		// Type cast for the filter hook.
		$posts = (array) $posts;

		/**
		 * Filters the array of builder layouts to import.
		 *
		 * Returning an empty value will short-circuit the import process.
		 *
		 * This is for backward compatibility with hooks written for Divi version <5.0.0.
		 *
		 * @since 3.0.99
		 * @deprecated 5.0.0 Use `divi_framework_portability_import_error_message` hook instead.
		 *
		 * @param array $posts The posts to be imported.
		 */
		$posts = apply_filters(
			'et_core_portability_import_posts',
			$posts
		);

		/**
		 * Filters the array of builder layouts to import.
		 *
		 * Returning an empty value will short-circuit the import process.
		 *
		 * @since ??
		 *
		 * @param array $posts The posts to be imported.
		 */
		$posts = apply_filters( 'divi_framework_portability_import_posts', $posts );

		if ( empty( $posts ) ) {
			return false;
		}

		foreach ( $posts as $post ) {
			if ( isset( $post['post_status'] ) && 'auto-draft' === $post['post_status'] ) {
				continue;
			}

			$fields_validation = [
				'ID'         => 'intval',
				'post_title' => 'sanitize_text_field',
				'post_type'  => 'sanitize_text_field',
			];

			$post = $this->validate( $post, $fields_validation );

			if ( ! $post ) {
				continue;
			}

			$layout_exists = self::layout_exists( $post['post_title'], $post['post_name'] );

			if ( $layout_exists && get_post_type( $layout_exists ) === $post['post_type'] ) {
				// Make sure the post is published.
				if ( 'publish' !== get_post_status( $layout_exists ) ) {
					wp_update_post(
						[
							'ID'          => intval( $layout_exists ),
							'post_status' => 'publish',
						]
					);
				}

				continue;
			}

			$post['import_id'] = $post['ID'];
			unset( $post['ID'] );

			$post['post_author'] = (int) get_current_user_id();

			// Insert or update post.
			$post_id = wp_insert_post( $post, true );

			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}

			// Insert and set terms.
			if ( isset( $post['terms'] ) && is_array( $post['terms'] ) ) {
				$processed_terms = [];

				foreach ( $post['terms'] as $term ) {
					$fields_validation = [
						'name'        => 'sanitize_text_field',
						'slug'        => 'sanitize_title',
						'taxonomy'    => 'sanitize_title',
						'parent'      => 'sanitize_title',
						'description' => 'wp_kses_post',
					];

					$term = $this->validate( $term, $fields_validation );

					if ( ! $term ) {
						continue;
					}

					if ( empty( $term['parent'] ) ) {
						$parent = 0;
					} else {
						if ( isset( $term['all_parents'] ) && ! empty( $term['all_parents'] ) ) {
							$this->restore_parent_categories( $term['all_parents'], $term['taxonomy'] );
						}

						$parent = term_exists( $term['parent'], $term['taxonomy'] );

						if ( is_array( $parent ) ) {
							$parent = $parent['term_id'];
						}
					}

					$insert = term_exists( $term['slug'], $term['taxonomy'] );

					if ( ! $insert ) {
						$insert = wp_insert_term(
							$term['name'],
							$term['taxonomy'],
							[
								'slug'        => $term['slug'],
								'description' => $term['description'],
								'parent'      => intval( $parent ),
							]
						);
					}

					if ( is_array( $insert ) && ! is_wp_error( $insert ) ) {
						$processed_terms[ $term['taxonomy'] ][] = $term['slug'];
					}
				}

				// Set post terms.
				foreach ( $processed_terms as $taxonomy => $ids ) {
					wp_set_object_terms( $post_id, $ids, $taxonomy );
				}
			}

			// Insert or update post meta.
			if ( isset( $post['post_meta'] ) && is_array( $post['post_meta'] ) ) {
				foreach ( $post['post_meta'] as $meta_key => $meta ) {

					$meta_key = sanitize_text_field( $meta_key );

					if ( count( $meta ) < 2 ) {
						$meta = wp_kses_post( $meta[0] );
					} else {
						$meta = array_map( 'wp_kses_post', $meta );
					}

					update_post_meta( $post_id, $meta_key, $meta );
				}
			}

			// Assign new thumbnail if provided.
			if ( isset( $post['thumbnail'] ) ) {
				set_post_thumbnail( $post_id, $post['thumbnail'] );
			}
		}

		return true;
	}

	/**
	 * Check whether the provided `$parent_id` is included in the $terms_list.
	 *
	 * @since 2.7.0
	 *
	 * @param array $terms_list Array of term objects.
	 * @param int   $parent_id  The ID of the parent term.
	 *
	 * @return bool
	 */
	public function is_parent_term_included( $terms_list, $parent_id ) {
		$is_parent_found = false;

		foreach ( $terms_list as $term_details ) {
			if ( $parent_id === $term_details->term_id ) {
				$is_parent_found = true;
				break;
			}
		}

		return $is_parent_found;
	}

	/**
	 * Check if a layout exists in the database already based on both its title and its slug.
	 *
	 * @param string $title The title of the layout.
	 * @param string $slug  The slug of the layout.
	 *
	 * @return int $post_id The post id if it exists, zero otherwise.
	 */
	public static function layout_exists( $title, $slug ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_name = %s",
				[
					wp_unslash( sanitize_post_field( 'post_title', $title, 0, 'db' ) ),
					wp_unslash( sanitize_post_field( 'post_name', $slug, 0, 'db' ) ),
				]
			)
		);
	}

	/**
	 * Paginate images processing.
	 *
	 * @since    1.0.0
	 *
	 * @param array  $images    A list of all the images to be processed.
	 * @param string $method    Method applied on images.
	 * @param int    $timestamp Timestamp used to store data upon pagination.
	 *
	 * @return array
	 * @internal param array $data Array of images.
	 */
	public function maybe_paginate_images( $images, $method, $timestamp ) {
		et_core_nonce_verified_previously();

		$page   = $this->get_param( 'page' );
		$page   = isset( $page ) ? (int) $page : 1;
		$result = $this->chunk_images( $images, $method, $timestamp, max( $page - 1, 0 ) );

		if ( ! $result['ready'] ) {
			wp_send_json(
				[
					'page'       => strval( $page ),
					'totalPages' => strval( $result['chunks'] ),
					'timestamp'  => $timestamp,
				]
			);
		}

		return $result['images'];
	}

	/**
	 * Prevent import and export timeout or memory failure.
	 *
	 * Sets request time limit to `infinity` and increases memory limit to `256M`
	 * It doesn't need to be reset as in both cases the request will exit.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public static function prevent_failure() {
		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Custom memory limit needed.

		$memory_limit = et_core_get_memory_limit();

		// Increase memory which is safe at this stage of the request.
		if ( $memory_limit > -1 && $memory_limit < 256 ) {
			@ini_set( 'memory_limit', '256M' ); // phpcs:ignore -- Custom memory limit needed. Cannot use wp_set_memory_limit.
		}
	}

	/**
	 * Set param data.
	 *
	 * Save a request parameter to the `$_params` member variable.
	 *
	 * @since ??
	 *
	 * @param string $key       Param key.
	 * @param mixed  $value     Param value.
	 */
	public function set_param( $key, $value ) {
		$this->_params[ $key ] = $value;
	}

	/**
	 * Get param data.
	 *
	 * Retrieves a value previously saved via `set-param` from the `$_params` member variable.
	 * This function does not sanitize values. Sanitization is required for further usage.
	 *
	 * @since ??
	 *
	 * @param string $key      Param key.
	 * @param mixed  $fallback Fallback value used when the parameter `$key` does not exist. Default is null.
	 *
	 * @return mixed
	 */
	public function get_param( $key, $fallback = null ) {
		if ( $this->has_param( $key ) ) {
			return $this->_params[ $key ];
		}

		return $fallback;
	}

	/**
	 * Check if param exists.
	 *
	 * Checks if the provided parameter key/value exists in the request parameters saved in `$_params`
	 *
	 * @since ??
	 *
	 * @param string $key Parameter key to look for.
	 *
	 * @return bool
	 */
	public function has_param( $key ) {
		return isset( $this->_params[ $key ] );
	}

	/**
	 * Generates UUIDs for the presets to avoid collisions.
	 *
	 * @since 4.5.0
	 *
	 * @param array $global_presets - The Global Presets to be imported.
	 *
	 * @return array The list of module types whose preset IDs changed.
	 */
	public function prepare_to_import_layout_presets( &$global_presets ) {
		$preset_rewrite_map = [];
		$initial_preset_id  = \ET_Builder_Global_Presets_Settings::MODULE_INITIAL_PRESET_ID;

		foreach ( $global_presets as $component_type => &$component_presets ) {
			$preset_rewrite_map[ $component_type ] = [];
			foreach ( $component_presets['presets'] as $preset_id => $preset ) {
				$new_id                                  = \ET_Core_Data_Utils::uuid_v4();
				$component_presets['presets'][ $new_id ] = $preset;
				$preset_rewrite_map[ $component_type ][ $preset_id ] = $new_id;
				unset( $component_presets['presets'][ $preset_id ] );
			}

			if ( $component_presets['default'] === $initial_preset_id && ! isset( $preset_rewrite_map[ $component_type ][ $initial_preset_id ] ) ) {
				$new_id                       = \ET_Core_Data_Utils::uuid_v4();
				$component_presets['default'] = $new_id;
				if ( isset( $component_presets['presets'][ $initial_preset_id ] ) ) {
					$component_presets['presets'][ $new_id ] = $component_presets['presets'][ $initial_preset_id ];
					unset( $component_presets['presets'][ $initial_preset_id ] );
				}
				$preset_rewrite_map[ $component_type ][ $initial_preset_id ] = $new_id;
			} else {
				$component_presets['default'] = $preset_rewrite_map[ $component_type ][ $component_presets['default'] ];
			}
		}

		return $preset_rewrite_map;
	}

	/**
	 * Replace post(s) image URLs with newly uploaded images.
	 *
	 * @since 2.7.0
	 *
	 * @param array $images {
	 *     Array of new images uploaded.
	 *
	 *     @type string|int $key   The key
	 *     @type string     $value The newly uploaded image URL.
	 * }
	 * @param array $data Array of post objects.
	 *
	 * @return array|mixed|object
	 */
	public function replace_images_urls( $images, $data ) {
		foreach ( $data as $post_id => &$post_data ) {
			foreach ( $images as $image ) {
				if ( is_array( $post_data ) ) {
					foreach ( $post_data as $post_param => &$param_value ) {
						if ( ! is_array( $param_value ) ) {
							$data[ $post_id ][ $post_param ] = $this->replace_image_url( $param_value, $image );
						}
					}
					unset( $param_value );
				} else {
					$data[ $post_id ] = $this->replace_image_url( $post_data, $image );
				}
			}
		}

		unset( $post_data );

		return $data;
	}

	/**
	 * Replace encoded image URL with a real URL.
	 *
	 * @param string $subject The string to perform replacing for.
	 * @param array  $image {
	 *     The image settings.
	 *
	 *     @type string|int id              The image ID.
	 *     @type string     url             The image URL.
	 *     @type string     replacement_id  The image replacement ID.
	 * }
	 *
	 * @return string|null
	 */
	public function replace_image_url( $subject, $image ) {
		$maybe_gutenberg_format = preg_match( '/<!-- wp:/', $subject );

		if ( isset( $image['replacement_id'] ) && isset( $image['id'] ) ) {
			$search      = $image['id'];
			$replacement = $image['replacement_id'];

			if ( $maybe_gutenberg_format ) {
				// Replace the image id in the innerContent attribute.
				// Regex101 link https://regex101.com/r/tdLNke/1.
				$pattern = '/("innerContent":\{"(?:desktop|ultraWide|widescreen|tabletWide|tablet|phoneWide|phone)":\{"(?:value|hover|sticky)":\{.*?"id":")' . $search . '(")/';
				$subject = preg_replace( $pattern, '${1}' . $replacement . '${2}', $subject );
			}

			if ( $maybe_gutenberg_format ) {
				// Gutenberg format - handles standard Divi galleryIds pattern.
				// Use word boundaries to match complete IDs only (prevents "12" matching within "123").
				// https://regex101.com/r/0i8W9Y/2 - Regex.
				$pattern = "/(galleryIds.*?\"[^\"]*\".*?\"value\":\".*?\\b){$search}(\\b.*?\")/";
			} else {
				// Non-Gutenberg format - use precise quote boundary pattern for gallery_ids.
				// Use word boundaries to match complete IDs only (prevents "12" matching within "123").
				// Regex101 link https://regex101.com/r/RAN4II/2.
				$pattern = "/(\\bgallery_ids=\"[^\"]*\\b){$search}(\\b[^\"]*\")/";
			}

			$subject = preg_replace( $pattern, "\${1}{$replacement}\${2}", $subject );

			// Also check for DiviGear gallery="..." pattern (works in both formats).
			// Regex101 link: https://regex101.com/r/FG7DtU/1.
			$gallery_pattern = "/(\\bgallery=\"[^\"]*){$search}([^\"]*\")/";
			$subject         = preg_replace( $gallery_pattern, "\${1}{$replacement}\${2}", $subject );
		}

		if ( isset( $image['url'] ) && isset( $image['replacement_url'] ) && $image['url'] !== $image['replacement_url'] ) {
			$search      = $image['url'];
			$replacement = $image['replacement_url'];
			$subject     = str_replace( $search, $replacement, $subject );
		}

		return $subject;
	}

	/**
	 * Restore the categories hierarchy in library.
	 *
	 * @since 2.7.0
	 *
	 * @param array  $parents_array {
	 *     Array of parent categories data.
	 *
	 *     @type string $slug   The category slug/key.
	 *     @type string name   The category name.
	 *     @type string parent The category parent name.
	 *     @type string description The category description.
	 * }
	 * @param string $taxonomy      Current taxonomy slug.
	 *
	 * @return void
	 */
	public function restore_parent_categories( $parents_array, $taxonomy ) {
		foreach ( $parents_array as $slug => $category_data ) {
			$current_category = term_exists( $slug, $taxonomy );

			if ( ! is_array( $current_category ) ) {
				$parent_id = 0 !== $category_data['parent'] ? term_exists( $category_data['parent'], $taxonomy ) : 0;
				wp_insert_term(
					$category_data['name'],
					$taxonomy,
					[
						'slug'        => $slug,
						'description' => $category_data['description'],
						'parent'      => is_array( $parent_id ) ? $parent_id['term_id'] : $parent_id,
					]
				);
			} elseif ( ( ! isset( $current_category['parent'] ) || 0 === $current_category['parent'] ) && 0 !== $category_data['parent'] ) {
				$parent_id = 0 !== $category_data['parent'] ? term_exists( $category_data['parent'], $taxonomy ) : 0;
				wp_update_term( $current_category['term_id'], $taxonomy, [ 'parent' => is_array( $parent_id ) ? $parent_id['term_id'] : $parent_id ] );
			}
		}
	}

	/**
	 * Injects the given Global Presets settings into the imported layout
	 *
	 * @since 4.5.0
	 *
	 * @param array $shortcode_object {
	 *     The multidimensional array representing a page/module structure.
	 *     Note: Passed by reference.
	 *
	 *     @type array  attrs    Module attributes.
	 *     @type string content Module content.
	 *     @type string type    Module type.
	 * }
	 * @param array $global_presets     The Global Presets to be imported.
	 * @param array $preset_rewrite_map The list of module types for which preset ids have been changed.
	 *
	 * @return void
	 */
	public function rewrite_module_preset_ids( &$shortcode_object, $global_presets, $preset_rewrite_map ) {
		$global_presets_manager  = \ET_Builder_Global_Presets_Settings::instance();
		$module_preset_attribute = \ET_Builder_Global_Presets_Settings::MODULE_PRESET_ATTRIBUTE;

		foreach ( $shortcode_object as &$module ) {
			$module_type      = $global_presets_manager->maybe_convert_module_type( $module['type'], $module['attrs'] );
			$module_preset_id = et_()->array_get( $module, "attrs.{$module_preset_attribute}", 'default' );

			if ( 'default' === $module_preset_id ) {
				$module['attrs'][ $module_preset_attribute ] = et_()->array_get( $global_presets, "{$module_type}.default", 'default' );
			} elseif ( isset( $preset_rewrite_map[ $module_type ][ $module_preset_id ] ) ) {
					$module['attrs'][ $module_preset_attribute ] = $preset_rewrite_map[ $module_type ][ $module_preset_id ];
			} else {
				$module['attrs'][ $module_preset_attribute ] = et_()->array_get( $global_presets, "{$module_type}.default", 'default' );
			}

			if ( isset( $module['content'] ) && is_array( $module['content'] ) ) {
				$this->rewrite_module_preset_ids( $module['content'], $global_presets, $preset_rewrite_map );
			}
		}
	}

	/**
	 * Validate data and remove any malicious code.
	 *
	 * If the provided data contains nested arrays, this function will call itself.
	 *
	 * @since 2.7.0
	 *
	 * @param array $data {
	 *     Array of data which needs to be validated.
	 *
	 *     @type string|int                  $key The key.
	 *     @type string|int|float|bool|array $value The data to be validated,
	 * }
	 * @param array $fields_validation {
	 *     Array of field and validation callback.
	 *
	 *     @type string|int $key   The key.
	 *     @type string     $value The function to be used to validate.
	 *                              The key should match the respective key of the value from `$data`.
	 * }
	 * @return array|bool Returns `false` if the data is not an array, otherwise returns an array if validated data.
	 */
	public function validate( $data, $fields_validation = [] ) {
		if ( ! is_array( $data ) ) {
			return false;
		}

		foreach ( $data as $key => $value ) {
			if ( '_et_pb_custom_css' === $key ) {
				// Use CSS-specific sanitization to preserve escape sequences.
				$data[ $key ] = et_core_sanitize_custom_css_meta_value( $value );

				continue;
			}

			if ( is_array( $value ) ) {
				$data[ $key ] = $this->validate( $value, $fields_validation );
			} elseif ( isset( $fields_validation[ $key ] ) ) {
					$data[ $key ] = call_user_func( $fields_validation[ $key ], $value ); // @phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- The callable function is hard-coded.
			} elseif ( current_user_can( 'unfiltered_html' ) ) {
					$data[ $key ] = $value;
			} else {
				$data[ $key ] = wp_kses_post( $value );
			}
		}

		return $data;
	}

	/**
	 * Initiate Import.
	 *
	 * @link https://developer.wordpress.org/reference/functions/wp_handle_upload/ WordPress handler for the `upload` file context.
	 * @since 2.7.0
	 * @since ?? Removed `$file_context` because 'upload' is the only file context used.
	 *
	 * @param array      $files                         File objects.
	 * @param string     $layout                        Layout data.
	 * @param int        $post_id                       Post ID.
	 * @param null|bool  $include_global_presets_option Optional. Whether global options should be included.
	 * @param null|array $overrides                     Optional. Argument overrides passed to the WordPress handler for the given file context.
	 *
	 * @return null|array Data to be returned to the client, or null if the import failed.
	 */
	public function import( array $files, string $layout, int $post_id, ?bool $include_global_presets_option, ?array $overrides ): ?array {
		$this->prevent_failure();

		self::$_doing_import = true;

		$timestamp  = $this->get_timestamp();
		$filesystem = $this->get_filesystem();

		// phpcs:disable ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
		/**
		 * TODO fix(D5, Cloud App): Implement local MIME Type validation in
		 *  https://github.com/elegantthemes/Divi/issues/34198 so that valid
		 *  JSON files are handled consistently.
		 */
		// phpcs:enable ET.Comments.Todo.TodoFound
		// phpcs:ignore ET.Functions.DangerousFunctions.ET_handle_upload -- test_type is enabled and proper type and extension checking are implemented.
		$upload = $layout ? null : wp_handle_upload(
			$files['file'],
			wp_parse_args(
				[
					'test_size' => false,
					'test_type' => true,
					'test_form' => false,
				],
				$overrides
			)
		);

		// If there is an error at this point, exit early and return the error message.
		if ( isset( $upload['error'] ) ) {
			$this->delete_temp_files( 'et_core_import' );

			$error_message = $upload['error'];
			/**
			 * Filters the error message shown when {@see ET_Core_Portability::import()} fails at the file upload stage.
			 *
			 * @since ??
			 *
			 * @param string $error_message Default is empty string.
			 *
			 * @return string Error message when import fails at the file upload stage.
			 */
			$error_message = apply_filters( 'divi_framework_portability_import_upload_error_message', $error_message );

			if ( $error_message ) {
				$error_message = [ 'message' => $error_message ];
			}

			return $error_message;
		}

		$temp_file_id = sanitize_file_name( $timestamp . ( $upload ? $upload['file'] : 'layout' ) );
		$temp_file    = $layout ? null : $this->temp_file( $temp_file_id, 'et_core_import', $upload ? $upload['file'] : 'layout' );

		$import = $layout ? json_decode( $layout, true ) : json_decode( $filesystem->get_contents( $temp_file ), true );
		$import = $this->validate( $import );

		// phpcs:disable ET.ValidatedSanitizedInput.InputNotSanitized -- it is passed to wp_validate_boolean().
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verification is done in et_core_security_check_passed.
		$include_global_presets = isset( $include_global_presets_option ) && wp_validate_boolean( $include_global_presets_option );

		$global_presets = '';

		// Handle file upload if content is not provided to Portability.
		if ( ! $layout && $temp_file ) {
			$import = json_decode( $filesystem->get_contents( $temp_file ), true );
		} elseif ( ! $layout ) {
			$this->upload_file( $files, $filesystem, $temp_file_id );
		}

		if ( ! isset( $import['context'] ) || $import['context'] !== $this->instance->context ) {
			$this->delete_temp_files( 'et_core_import', [ $temp_file_id => $temp_file ] );

			return [ 'message' => esc_html__( 'This file should not be imported in this context.', 'et_builder_5' ) ];
		}

		$post_type        = get_post_type( $post_id );
		$is_theme_builder = et_theme_builder_is_layout_post_type( $post_type );

		if ( ! $is_theme_builder ) {
			// https://regex101.com/r/Wm0BRb/1 - Regex for both D5 and D4.
			$pattern = '/<!-- wp:divi\/post-content.*?-->|\[et_pb_post_content.*?\/et_pb_post_content]/s';

			// Do not import post content module if it is not a theme builder -> visual builder.
			if ( is_array( $import['data'] ) && ! empty( $import['data'] ) ) {
				$post_content_data_id = key( $import['data'] );

				if ( null !== $post_content_data_id && isset( $import['data'][ $post_content_data_id ] ) ) {
					if ( isset( $import['data'][ $post_content_data_id ]['post_content'] ) ) {
						$import['data'][ $post_content_data_id ]['post_content'] = preg_replace( $pattern, '', $import['data'][ $post_content_data_id ]['post_content'] );
					} else {
						$import['data'][ $post_content_data_id ] = preg_replace( $pattern, '', $import['data'][ $post_content_data_id ] );
					}
				}
			} elseif ( ! is_array( $import['data'] ) && ! empty( $import['data'] ) ) {
				$import['data'] = preg_replace( $pattern, '', $import['data'] );
			}
		}

		// Add filter to allow importing portability media assets.
		add_filter( 'wp_check_filetype_and_ext', [ HooksRegistration::class, 'check_filetype_and_ext_portability_media' ], 999, 3 );

		// Upload images and replace current urls.
		if ( isset( $import['images'] ) ) {
			$images         = $this->maybe_paginate_images( (array) $import['images'], 'upload_images', $timestamp );
			$import['data'] = $this->replace_images_urls( $images, $import['data'] );
		}

		$data                   = $import['data'];
		$success                = [ 'timestamp' => $timestamp ];
		$global_colors_imported = [];

		$this->delete_temp_files( 'et_core_import' );

		// Import global colors BEFORE any conversion logic runs so that all import types can access them.
		// Only import customizer colors when importing in the variable context from the variable manager modal.
		// In other cases (layouts, presets), we don't want to replace the website's existing theme customizer colors.
		$export_context      = $this->get_param( 'export_context' );
		$is_variable_context = is_array( $export_context ) && isset( $export_context['type'] ) && 'variables' === $export_context['type'];

		if ( ! empty( $import['global_colors'] ) ) {
			$global_colors_imported = $this->import_global_colors( $import['global_colors'] );
			// Actually save the imported global colors to the database.
			if ( ! empty( $global_colors_imported ) ) {
				// Filter out customizer colors if not in variable context.
				if ( ! $is_variable_context ) {
					foreach ( GlobalData::$customizer_colors as $color_id => $color_data ) {
						if ( isset( $global_colors_imported[ $color_id ] ) ) {
							unset( $global_colors_imported[ $color_id ] );
						}
					}
				}
				GlobalData::set_global_colors( $global_colors_imported, true );
			}
		}

		// Pass the post content and let js save the post.
		if ( 'post' === $this->instance->type ) {
			if ( $layout ) {
				$success['postContent'] = $this->get_layout_content( $data );
			} elseif ( ! empty( $data ) ) {
				// For preset-only backups, data is empty array, so reset() returns false.
				// We should handle this case - preset-only imports don't need post content.
				$success['postContent'] = reset( $data );
			} else {
				// Preset-only backup - set empty post content.
				$success['postContent'] = '';
			}

			// this will strip unwanted `<p>` tags that are added by `wpautop()` in some library/pre-made layouts.
			$success['postContent'] = HTMLUtility::fix_shortcodes( $success['postContent'] );

			if ( isset( $import['page_settings_meta'] ) && is_array( $import['page_settings_meta'] ) ) {
				$page_settings_meta = $import['page_settings_meta'];

				if ( array_key_exists( '_et_pb_custom_css', $page_settings_meta ) ) {
					$custom_css_meta  = $page_settings_meta['_et_pb_custom_css'];
					$custom_css_value = is_array( $custom_css_meta ) ? ( $custom_css_meta[0] ?? '' ) : $custom_css_meta;

					update_post_meta( $post_id, '_et_pb_custom_css', wp_slash( $custom_css_value ) );

					$success['pageSettings'] = [
						'customCss' => $custom_css_value,
					];
				}
			}

			if ( ! class_exists( 'ET_Builder_Module_Settings_Migration' ) ) {
				require_once ET_BUILDER_DIR . 'module/settings/Migration.php';
			}

			$post_content = ShortcodeMigration::maybe_migrate_legacy_shortcode( $success['postContent'] );

			// Apply full D4-to-D5 conversion for imported layouts (if needed).
			// This ensures D4 content is converted to D5 format before D5 migrations run.
			$has_shortcode = Conditions::has_shortcode( '', $post_content );
			if ( $post_content && $has_shortcode ) {
				// Initialize shortcode framework and prepare for conversion.
				Conversion::initialize_shortcode_framework();

				/**
				 * Fires before D4 to D5 content conversion during portability import.
				 *
				 * This action hook allows plugins and themes to prepare for Divi 4 to Divi 5
				 * content conversion by ensuring all necessary module definitions, assets, and
				 * dependencies are loaded and available before the conversion process begins.
				 *
				 * The hook is triggered when importing content that contains D4 shortcodes that
				 * need to be converted to D5 format. This is critical for ensuring that all
				 * modules are properly registered and their definitions are available during
				 * the conversion process.
				 *
				 * Use cases include:
				 * - Loading third-party module definitions
				 * - Registering custom post types or taxonomies needed for conversion
				 * - Preparing any assets or configuration required by modules
				 * - Setting up necessary hooks for custom module conversion logic
				 *
				 * @since ??
				 *
				 * @hook divi_visual_builder_before_d4_conversion
				 */
				do_action( 'divi_visual_builder_before_d4_conversion' );

				// Apply full conversion (includes migration + format conversion).
				$post_content = Conversion::maybeConvertContent( $post_content, true, null, true );
			}

			// Prepare preset data for storage before content migrations run.
			// This ensures that content migrations can access preset data when needed.
			$success['presets'] = isset( $import['presets'] ) && is_array( $import['presets'] ) ? $import['presets'] : (object) [];

			// Import and migrate presets BEFORE content migrations run.
			// This is critical because content migrations (like NestedModuleMigration) may need
			// to read preset attributes from the layout option group to perform correct migrations.
			// Processing presets first ensures GlobalPreset::find_preset_data_by_id() can find
			// imported presets during content migration.
			$preset_processing_result = [];
			if ( $include_global_presets && is_array( $success['presets'] ) && ! empty( $success['presets'] ) ) {
				// Process presets through unified D5 system and get ID mappings for content replacement.
				$preset_processing_result = GlobalPreset::process_presets_for_import( $success['presets'] );

				// Always update response with migrated presets from D5 system.
				$migrated_presets = GlobalPreset::get_data();
				if ( ! empty( $migrated_presets ) ) {
					$success['presets'] = $migrated_presets;
				}
			}

			// Apply remapped preset IDs to imported content BEFORE migrations run.
			// PresetStackMigration (hooked to the filter below) unsets modulePreset when
			// its value is a reserved ID like `_initial`. Replacing the reserved ID with
			// the new valid ID first ensures the migration preserves the reference.
			if ( ! empty( $preset_processing_result['preset_id_mappings'] ) ) {
				$post_content = PresetContentUtils::apply_preset_id_mappings_to_content(
					$post_content,
					$preset_processing_result['preset_id_mappings']
				);
			}

			// Populate width/height for imported image innerContent before builder consumes content.
			$post_content = $this->_populate_imported_layout_image_dimensions( $post_content );

			/**
			 * Filters the post content after migration has been applied during portability import.
			 *
			 * This hook allows developers to modify the post content after shortcode migration
			 * has been processed but before the final content is saved during import operations.
			 *
			 * Note: Presets are now processed and available in the database before this filter runs,
			 * so content migrations can access preset data via GlobalPreset::find_preset_data_by_id().
			 *
			 * @since ??
			 *
			 * @param string $post_content The post content after migration has been applied.
			 */
			$post_content = apply_filters( 'divi_framework_portability_import_migrated_post_content', $post_content );

			// Apply remapped default preset IDs to imported content when default preset conflicts occur.
			// This ensures imported modules reference the remapped preset instead of the target site default.
			if ( ! empty( $preset_processing_result['defaultImportedModulePresetIds'] ) ) {
				$post_content = PresetContentUtils::apply_default_imported_presets_to_content(
					$post_content,
					$preset_processing_result['defaultImportedModulePresetIds']
				);
			}

			$success['postContent'] = $post_content;
			$success['migrations']  = ET_Builder_Module_Settings_Migration::$migrated;

			// Include preset ID mappings and default preset IDs in the response if presets were imported.
			if ( ! empty( $preset_processing_result ) ) {
				// Include preset ID mappings for client-side content replacement.
				if ( ! empty( $preset_processing_result['preset_id_mappings'] ) ) {
					$success['preset_id_mappings'] = $preset_processing_result['preset_id_mappings'];
				}

				// Include default imported preset IDs for client-side default assignment.
				if ( ! empty( $preset_processing_result['defaultImportedModulePresetIds'] ) ) {
					$success['defaultImportedModulePresetIds'] = $preset_processing_result['defaultImportedModulePresetIds'];
				}

				if ( ! empty( $preset_processing_result['defaultImportedGroupPresetIds'] ) ) {
					$success['defaultImportedGroupPresetIds'] = $preset_processing_result['defaultImportedGroupPresetIds'];
				}
			}

				// Import canvases if present in import data.
				$imported_canvases = null;
			if ( isset( $import['canvases'] ) && is_array( $import['canvases'] ) ) {
				$this->_import_canvases( $post_id, $import['canvases'] );

				// Convert imported canvas data to format expected by frontend and include in response.
				$imported_canvases = $this->_prepare_canvas_data_for_response( $post_id, $import['canvases'] );
			}
		}

		if ( 'post_type' === $this->instance->type ) {
			$preset_rewrite_map = [];
			if ( ! empty( $import['presets'] ) && $include_global_presets ) {
				$preset_rewrite_map = $this->prepare_to_import_layout_presets( $import['presets'] );
				$global_presets     = $import['presets'];
			}
			foreach ( $data as &$post ) {
				$shortcode_object = et_fb_process_shortcode( $post['post_content'] );

				if ( ! empty( $import['presets'] ) ) {
					if ( $include_global_presets ) {
						$this->rewrite_module_preset_ids( $shortcode_object, $import['presets'], $preset_rewrite_map );
					} else {
						$this->apply_global_presets( $shortcode_object, $import['presets'] );
					}
				}

				$post_content = et_fb_process_to_shortcode( $shortcode_object, [], '', false );
				// Add slashes for post content to avoid unwanted un-slashing (by wp_un-slash) while post is inserting.
				$post['post_content'] = wp_slash( $post_content );

				// Upload thumbnail image if exist.
				if ( ! empty( $post['post_meta'] ) && ! empty( $post['post_meta']['_thumbnail_id'] ) ) {
					$post_thumbnail_origin_id = (int) $post['post_meta']['_thumbnail_id'][0];

					if ( ! empty( $import['thumbnails'] ) && ! empty( $import['thumbnails'][ $post_thumbnail_origin_id ] ) ) {
						$post_thumbnail_new = $this->upload_images( $import['thumbnails'][ $post_thumbnail_origin_id ] );
						$new_thumbnail_data = reset( $post_thumbnail_new );

						// New thumbnail image was uploaded and it should be updated.
						if ( isset( $new_thumbnail_data['replacement_id'] ) ) {
							$new_thumbnail_id  = $new_thumbnail_data['replacement_id'];
							$post['thumbnail'] = $new_thumbnail_id;
							if ( ! function_exists( 'wp_crop_image' ) ) {
								include ABSPATH . 'wp-admin/includes/image.php';
							}

							$thumbnail_path = get_attached_file( $new_thumbnail_id );

							// Generate all the image sizes and update thumbnail metadata.
							$new_metadata = wp_generate_attachment_metadata( $new_thumbnail_id, $thumbnail_path );
							wp_update_attachment_metadata( $new_thumbnail_id, $new_metadata );
						}
					}
				}
			}

			if ( ! empty( $global_presets ) ) {
				// Process and import presets.
				GlobalPreset::process_presets_for_import( $global_presets );
			}

			if ( ! $this->import_posts( $data ) ) {
				// Default value for error message.
				$error_message = false;

				/**
				 * Filters the error message shown when `ET_Core_Portability::import()` fails.
				 *
				 * This is for backward compatibility with hooks written for Divi version <5.0.0.
				 *
				 * @since 3.0.99 This filter was introduced.
				 * @deprecated 5.0.0 Use `divi_framework_portability_import_error_message` hook instead.
				 *
				 * @param mixed $error_message The error message. Default `false`.
				 */
				$error_message = apply_filters(
					'et_core_portability_import_error_message',
					$error_message
				);

				// D4 Compatibility measure. Change `$error_message` to empty string (D5 default filter value) if it is set to `false` (D4 default filter value).
				$error_message = false === $error_message ? '' : $error_message;

				/**
				 * Filters the error message shown when [ET_Core_Portability::import()](/api/php/Framework/Portability/PortabilityPost#import) fails.
				 *
				 * @since ??
				 *
				 * @param string $error_message The error message. Default empty string.
				 */
				$error_message = apply_filters( 'divi_framework_portability_import_error_message', $error_message );

				if ( $error_message ) {
					$error_message = [ 'message' => $error_message ];
				}

				return $error_message;
			}
		}

		// Reset the `wp_check_filetype_and_ext` filter after uploading portability media files.
		remove_filter( 'wp_check_filetype_and_ext', [ HooksRegistration::class, 'check_filetype_and_ext_portability_media' ], 999, 3 );

		// Set global colors response data (already imported before D4-to-D5 conversion).
		if ( ! empty( $global_colors_imported ) ) {
			$success['globalColors'] = $global_colors_imported;
		}

		if ( ! empty( $import['global_variables'] ) ) {
			// Extract and save customizer fonts before importing global variables.
			// Only import customizer fonts when importing in the variable context from the variable manager modal.
			// In other cases (layouts, presets), we don't want to replace the website's existing theme customizer fonts.
			if ( $is_variable_context && is_array( $import['global_variables'] ) ) {
				foreach ( $import['global_variables'] as $variable_data ) {
					if ( ! is_array( $variable_data ) || 'fonts' !== ( $variable_data['type'] ?? '' ) ) {
						continue;
					}

					$variable_id    = $variable_data['id'] ?? '';
					$variable_value = $variable_data['value'] ?? '';

					// Save customizer fonts to WordPress options.
					if ( '--et_global_heading_font' === $variable_id && ! empty( $variable_value ) ) {
						et_update_option( 'heading_font', sanitize_text_field( $variable_value ) );
					} elseif ( '--et_global_body_font' === $variable_id && ! empty( $variable_value ) ) {
						et_update_option( 'body_font', sanitize_text_field( $variable_value ) );
					}
				}
			}

			$success['globalVariables'] = $this->import_global_variables( $import['global_variables'] );
		}

		// Include imported canvas data in response if canvases were imported.
		if ( null !== $imported_canvases ) {
			$success['canvases'] = $imported_canvases;
		}

		return $success;
	}

	/**
	 * Populate image dimensions in imported Divi block content.
	 *
	 * @since ??
	 *
	 * @param string $post_content Imported post content.
	 *
	 * @return string
	 */
	private function _populate_imported_layout_image_dimensions( string $post_content ): string {
		if ( '' === $post_content || ! str_contains( $post_content, '<!-- wp:divi/' ) ) {
			return $post_content;
		}

		$blocks = parse_blocks( $post_content );
		if ( ! is_array( $blocks ) || empty( $blocks ) ) {
			return $post_content;
		}

		$updated_blocks = $this->_populate_image_dimensions_in_blocks( $blocks );
		$serialized     = serialize_blocks( $updated_blocks );

		return '' !== $serialized ? $serialized : $post_content;
	}

	/**
	 * Recursively populate image dimensions in parsed blocks.
	 *
	 * @since ??
	 *
	 * @param array $blocks Parsed blocks.
	 *
	 * @return array
	 */
	private function _populate_image_dimensions_in_blocks( array $blocks ): array {
		foreach ( $blocks as $index => $block ) {
			if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
				$blocks[ $index ]['attrs'] = $this->_populate_image_dimensions_in_attrs( $block['attrs'] );
			}

			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$blocks[ $index ]['innerBlocks'] = $this->_populate_image_dimensions_in_blocks( $block['innerBlocks'] );
			}
		}

		return $blocks;
	}

	/**
	 * Recursively populate image dimensions in attrs tree.
	 *
	 * @since ??
	 *
	 * @param array $attrs Attr tree.
	 *
	 * @return array
	 */
	private function _populate_image_dimensions_in_attrs( array $attrs ): array {
		foreach ( $attrs as $attr_key => $attr_value ) {
			if ( ! is_array( $attr_value ) ) {
				continue;
			}

			if ( isset( $attr_value['innerContent'] ) && is_array( $attr_value['innerContent'] ) ) {
				$attr_value['innerContent'] = ModuleElementsUtils::populate_image_element_attrs( $attr_value['innerContent'] );
			}

			$attrs[ $attr_key ] = $this->_populate_image_dimensions_in_attrs( $attr_value );
		}

		return $attrs;
	}

	/**
	 * Initiate Export.
	 *
	 * @since 2.7.0
	 *
	 * @return null|array|WP_Error
	 */
	public function export() {
		$this->prevent_failure();
		et_core_nonce_verified_previously();

		$data                 = [];
		$timestamp            = $this->get_timestamp();
		$filesystem           = $this->get_filesystem();
		$temp_file_id         = sanitize_file_name( $timestamp );
		$temp_file            = $this->has_temp_file( $temp_file_id, 'et_core_export' );
		$apply_global_presets = $this->has_param( 'apply_global_presets' ) && wp_validate_boolean( $this->get_param( 'apply_global_presets' ) );
		$export_canvas_id     = $this->get_param( 'export_canvas_id' );
		// Initialize based on export type - single canvas export uses arrays/objects, regular export uses empty strings.
		$global_presets     = $export_canvas_id ? null : '';
		$global_colors      = $export_canvas_id ? [] : '';
		$global_variables   = $export_canvas_id ? [] : '';
		$page_settings_meta = null;

		if ( $temp_file ) {
			$file_data          = json_decode( $filesystem->get_contents( $temp_file ) );
			$data               = (array) $file_data->data;
			$global_presets     = $file_data->presets;
			$global_colors      = $file_data->global_colors;
			$global_variables   = $file_data->global_variables;
			$page_settings_meta = isset( $file_data->page_settings_meta ) ? (array) $file_data->page_settings_meta : null;
		} else {
			$temp_file = $this->temp_file( $temp_file_id, 'et_core_export' );

			if ( 'post' === $this->instance->type ) {
				$post    = $this->get_param( 'post' );
				$content = $this->get_param( 'content' );

				// Check for variables-only or presets-only export marker before fetching post content.
				$is_variables_only_export = '__VARIABLES_ONLY_EXPORT__' === $content;
				$is_presets_only_export   = '__PRESETS_ONLY_EXPORT__' === $content;
				$is_special_export        = $is_variables_only_export || $is_presets_only_export;

				if ( $post && ! $content && ! $is_special_export ) {
					$content = get_post_field( 'post_content', $post );
				}

				// For variables-only or presets-only export, content can be the marker string.
				if ( null === $post || ( null === $content && ! $is_special_export ) ) {
					return new WP_Error( 'broke', __( 'No post ID or content provided.', 'et_builder_5' ) );
				}

				$fields_validation = [
					'ID' => 'intval',
					// no post_content as the default case for no fields_validation will run it through perms based wp_kses_post, which is exactly what we want.
				];

				$post_data = [
					'post_content' => $content, // No need to run this through stripcslashes() like in D4 as the page content is saved in Gutenberg block format.
					'ID'           => $post,
				];

				$post_data = $this->validate( $post_data, $fields_validation );

				// Export canvases for 'post' type exports.
				// Check if exporting a single canvas (from right-click menu).
				if ( $export_canvas_id ) {
					// Export only the specified canvas and canvases it targets.
					// Don't include main post content in data section when exporting a single canvas.
					$data     = [];
					$canvases = $this->_export_single_canvas( $post_data['ID'], $export_canvas_id, $content );

					// Get global presets/colors/variables from frontend (already extracted and prepared).
					$global_presets   = $this->_get_and_decode_global_param( 'global_presets', null );
					$global_colors    = $this->_get_and_decode_global_param( 'global_colors', [] );
					$global_variables = $this->_get_and_decode_global_param( 'global_variables', [] );
				} elseif ( '__VARIABLES_ONLY_EXPORT__' === $post_data['post_content'] ) {
					// Variables-only export: don't include post content or canvases.
					$data     = [];
					$canvases = [];

					// Get global presets/colors/variables from frontend (already extracted and prepared).
					$global_presets   = $this->_get_and_decode_global_param( 'global_presets', null );
					$global_colors    = $this->_get_and_decode_global_param( 'global_colors', [] );
					$global_variables = $this->_get_and_decode_global_param( 'global_variables', [] );
				} elseif ( '__PRESETS_ONLY_EXPORT__' === $post_data['post_content'] ) {
					// Presets-only export: don't include post content or canvases.
					$data     = [];
					$canvases = [];

					// Get global presets/colors/variables from frontend (already extracted and prepared).
					$global_presets   = $this->_get_and_decode_global_param( 'global_presets', null );
					$global_colors    = $this->_get_and_decode_global_param( 'global_colors', [] );
					$global_variables = $this->_get_and_decode_global_param( 'global_variables', [] );
				} else {
					// Regular export: include main post content.
					$data = [ $post_data['ID'] => $post_data['post_content'] ];

					$global_presets   = $this->_get_and_decode_global_param( 'include_global_presets', null );
					$global_colors    = $this->_get_and_decode_global_param( 'include_global_colors', [] );
					$global_variables = $this->_get_and_decode_global_param( 'include_global_variables', [] );

						// Regular export: export all local canvases and optionally all global canvases.
						// Explicitly check for 'on' since wp_validate_boolean might not handle 'off' correctly.
					$include_all_global_canvases_param = $this->get_param( 'include_all_global_canvases' );
					$include_all_global_canvases       = $this->has_param( 'include_all_global_canvases' ) && 'on' === $include_all_global_canvases_param;
					$canvases                          = $this->_export_canvases( $post_data['ID'], $content, $include_all_global_canvases );

					$custom_css_meta = get_post_meta( $post_data['ID'], '_et_pb_custom_css', false );

					if ( [] !== $custom_css_meta ) {
						$page_settings_meta = [
							'_et_pb_custom_css' => $custom_css_meta,
						];
					}
				}
			}

			if ( 'post_type' === $this->instance->type ) {
				$data = $this->export_posts_query();
			}

			// Skip apply_query if data is empty (single canvas export).
			if ( ! empty( $data ) ) {
				$data = $this->apply_query( $data, 'set' );
			}

			if ( 'post_type' === $this->instance->type ) {
				$used_global_presets   = [];
				$used_global_colors    = [];
				$used_global_variables = [];
				$options               = [
					'apply_global_presets' => true,
				];

				// Skip processing if data is empty (single canvas export).
				if ( ! empty( $data ) ) {
					foreach ( $data as $post ) {
						$shortcode_object = et_fb_process_shortcode( $post->post_content );

						if ( 'post_type' === $this->instance->type ) {
							$used_global_colors = $this->get_used_global_colors( $shortcode_object, $used_global_colors );
						}

						if ( $apply_global_presets ) {
							$post->post_content = et_fb_process_to_shortcode( $shortcode_object, $options, '', false );
						} else {
							$used_global_presets = array_merge(
								$this->get_used_global_presets( $shortcode_object, $used_global_presets ),
								$used_global_presets
							);
						}
					}

					if ( ! empty( $used_global_presets ) ) {
						$global_presets = (object) $used_global_presets;
					}

					if ( ! empty( $used_global_colors ) ) {
						$global_colors = $this->get_global_colors_data( $used_global_colors );
					}
				}
			}

			// Initialize canvases array if not set (for post_type exports).
			if ( ! isset( $canvases ) ) {
				$canvases = [];
			}

			// put contents into file, this is temporary,
			// if images get paginated, this content will be brought back out
			// of a temp file in paginated request.
			$file_data = [
				'data'               => $data,
				'presets'            => $global_presets,
				'global_colors'      => $global_colors,
				'global_variables'   => $global_variables,
				'page_settings_meta' => $page_settings_meta,
				'canvases'           => $canvases,
			];

			$filesystem->put_contents( $temp_file, wp_json_encode( $file_data ) );
		}

		$thumbnails = $this->get_thumbnail_images( $data );

		$images = $this->get_data_images( $data );

		// Get canvases from temp file if it exists (for paginated exports).
		$canvases = [];
		if ( $temp_file ) {
			$file_data = json_decode( $filesystem->get_contents( $temp_file ), true );
			$canvases  = $file_data['canvases'] ?? [];
		}

		// Extract images from canvas content when exporting a single canvas.
		// For single canvas exports, $data is empty, so we need to extract images from canvases.
		if ( $export_canvas_id && ! empty( $canvases ) ) {
			$canvas_images = [];

			// Extract images from all exported canvases (local and global).
			if ( ! empty( $canvases['local'] ) ) {
				$canvas_images = array_merge( $canvas_images, $this->_extract_images_from_canvas_array( $canvases['local'] ) );
			}

			if ( ! empty( $canvases['global'] ) ) {
				$canvas_images = array_merge( $canvas_images, $this->_extract_images_from_canvas_array( $canvases['global'] ) );
			}

			// Merge canvas images with existing images (remove duplicates).
			if ( ! empty( $canvas_images ) ) {
				$images = array_merge( $images, $canvas_images );
			}
		}

		$data = [
			'context'            => $this->instance->context,
			'data'               => $data,
			'presets'            => $global_presets,
			'global_colors'      => $global_colors,
			'global_variables'   => $global_variables,
			'page_settings_meta' => $page_settings_meta,
			'canvases'           => $canvases,
			'images'             => $this->maybe_paginate_images( $images, 'encode_images', $timestamp ),
			'thumbnails'         => $thumbnails,
		];

		$filesystem->put_contents( $temp_file, wp_json_encode( $data ) );

		if ( $this->get_param( 'return_content' ) ) {
			return $data;
		}

		return [
			'timestamp' => $timestamp,
		];
	}

	/**
	 * Export canvases for a post.
	 *
	 * Exports local canvases from postmeta and global canvases that are targeted by interactions
	 * or that append to the main canvas. If $include_all_global_canvases is true, exports all
	 * global canvases without checking for interactions or appended canvases.
	 *
	 * @since ??
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Post content (serialized Gutenberg blocks).
	 * @param bool   $include_all_global_canvases Whether to include all global canvases.
	 *
	 * @return array Canvas data structure with 'local' and 'global' keys.
	 */
	private function _export_canvases( $post_id, $content, $include_all_global_canvases = false ) {
		$exported_canvases = [
			'local'  => [],
			'global' => [],
		];

		// Get local canvases from et_pb_canvas posts.
		$local_posts = CanvasUtils::get_local_canvas_posts( $post_id );

		foreach ( $local_posts as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			$canvas_id = get_post_meta( $post->ID, '_divi_canvas_id', true );
			if ( ! $canvas_id ) {
				continue;
			}

			$canvas_created_at = get_post_meta( $post->ID, '_divi_canvas_created_at', true );
			$canvas_created_at = $canvas_created_at ? $canvas_created_at : $post->post_date;

			$append_to_main = get_post_meta( $post->ID, '_divi_canvas_append_to_main', true );
			$append_to_main = '' === $append_to_main ? null : $append_to_main;

			$z_index = get_post_meta( $post->ID, '_divi_canvas_z_index', true );
			$z_index = '' === $z_index ? null : $z_index;

			$exported_canvases['local'][ $canvas_id ] = $this->_build_local_canvas_export_array(
				$canvas_id,
				[
					'id'                 => $canvas_id,
					'name'               => $post->post_title,
					'isMain'             => false,
					'isGlobal'           => false,
					'appendToMainCanvas' => $append_to_main,
					'zIndex'             => $z_index,
					'createdAt'          => $canvas_created_at,
				],
				$post->post_content
			);
		}

		if ( $include_all_global_canvases ) {
			// Export all global canvases without checking for interactions or appended canvases.
			$this->_export_all_global_canvases( $exported_canvases );
		} else {
			// Export global canvases targeted by interactions.
			$this->_export_canvases_targeted_by_interactions( $content, $exported_canvases );

			// Include canvases that append to main canvas (both local and global).
			// These canvases will be rendered with the main canvas on the frontend.
			$this->_export_canvases_that_append_to_main( $post_id, $exported_canvases );

			// Export canvases used in Canvas Portal modules.
			// These canvases are injected into the layout via Canvas Portal modules.
			$this->_export_canvases_used_in_canvas_portals( $post_id, $content, $exported_canvases );
		}

		return $exported_canvases;
	}

	/**
	 * Export a single canvas and canvases it targets with interactions.
	 *
	 * Exports only the specified canvas (local or global) and any canvases that contain
	 * modules with interactionTarget attributes matching the target IDs extracted from
	 * interactions in the specified canvas content.
	 *
	 * @since ??
	 *
	 * @param int    $post_id Post ID.
	 * @param string $canvas_id Canvas ID to export.
	 * @param string $canvas_content Canvas content (serialized Gutenberg blocks).
	 *
	 * @return array Canvas data structure with 'local' and 'global' keys.
	 */
	private function _export_single_canvas( $post_id, $canvas_id, $canvas_content ) {
		$exported_canvases = [
			'local'  => [],
			'global' => [],
		];

		// Get canvas metadata from frontend (current state from Redux store).
		// This ensures we export the current state, not what's in the database.
		// Similar to how regular post content export works - frontend always sends current content.
		$canvas_metadata_json = $this->get_param( 'export_canvas_metadata' );
		if ( ! $canvas_metadata_json ) {
			// Metadata is required - if not provided, return empty (should not happen in normal operation).
			return $exported_canvases;
		}

		$canvas_metadata = $this->_decode_and_validate_json_param( $canvas_metadata_json, null );
		if ( ! $canvas_metadata ) {
			// Invalid metadata - return empty (should not happen in normal operation).
			return $exported_canvases;
		}

		$is_global         = isset( $canvas_metadata['isGlobal'] ) && $canvas_metadata['isGlobal'];
		$canvas_name       = isset( $canvas_metadata['name'] ) ? $canvas_metadata['name'] : '';
		$is_main           = isset( $canvas_metadata['isMain'] ) && $canvas_metadata['isMain'];
		$append_to_main    = isset( $canvas_metadata['appendToMainCanvas'] ) ? $canvas_metadata['appendToMainCanvas'] : null;
		$append_to_main    = '' === $append_to_main ? null : $append_to_main;
		$z_index           = isset( $canvas_metadata['zIndex'] ) ? $canvas_metadata['zIndex'] : null;
		$z_index           = '' === $z_index ? null : $z_index;
		$canvas_created_at = current_time( 'mysql' );

		if ( $is_global ) {
			// Export as global canvas using frontend metadata.
			$exported_canvases['global'][ $canvas_id ] = [
				'id'                 => $canvas_id,
				'name'               => $canvas_name,
				'isMain'             => false,
				'isGlobal'           => true,
				'appendToMainCanvas' => $append_to_main,
				'zIndex'             => $z_index,
				'createdAt'          => $canvas_created_at,
				'content'            => $canvas_content,
			];
		} else {
			// Export as local canvas using frontend metadata.
			$exported_canvases['local'][ $canvas_id ] = $this->_build_local_canvas_export_array(
				$canvas_id,
				[
					'id'                 => $canvas_id,
					'name'               => $canvas_name,
					'isMain'             => $is_main,
					'isGlobal'           => false,
					'appendToMainCanvas' => $append_to_main,
					'zIndex'             => $z_index,
					'createdAt'          => $canvas_created_at,
				],
				$canvas_content
			);
		}

		// Extract interaction target IDs from the canvas content.
		$target_ids = OffCanvasHooks::extract_interaction_target_ids_from_content( $canvas_content );

		if ( ! empty( $target_ids ) ) {
			// Export global canvases that contain modules with these target IDs.
			$this->_export_global_canvases_matching_condition(
				$exported_canvases,
				function ( $post, $target_canvas_content ) use ( $target_ids, $canvas_id ) {
					// Skip the canvas we're already exporting.
					$post_canvas_id = get_post_meta( $post->ID, '_divi_canvas_id', true );
					if ( $post_canvas_id === $canvas_id ) {
						return false;
					}

					return $this->_canvas_content_contains_target( $target_canvas_content, $target_ids );
				}
			);

			// Also check local canvases for target IDs.
			$local_posts = CanvasUtils::get_local_canvas_posts( $post_id );

			foreach ( $local_posts as $target_post ) {
				$target_canvas_id = get_post_meta( $target_post->ID, '_divi_canvas_id', true );
				if ( ! $target_canvas_id ) {
					continue;
				}

				// Skip the canvas we're already exporting.
				if ( $target_canvas_id === $canvas_id ) {
					continue;
				}

				$target_canvas_content = $target_post->post_content;
				if ( ! $target_canvas_content ) {
					continue;
				}

				// Check if this canvas contains any of the target IDs.
				if ( $this->_canvas_content_contains_target( $target_canvas_content, $target_ids ) ) {
					$canvas_created_at = get_post_meta( $target_post->ID, '_divi_canvas_created_at', true );
					$canvas_created_at = $canvas_created_at ? $canvas_created_at : $target_post->post_date;

					$append_to_main = get_post_meta( $target_post->ID, '_divi_canvas_append_to_main', true );
					$append_to_main = '' === $append_to_main ? null : $append_to_main;

					$z_index = get_post_meta( $target_post->ID, '_divi_canvas_z_index', true );
					$z_index = '' === $z_index ? null : $z_index;

					$exported_canvases['local'][ $target_canvas_id ] = $this->_build_local_canvas_export_array(
						$target_canvas_id,
						[
							'id'                 => $target_canvas_id,
							'name'               => $target_post->post_title,
							'isMain'             => false,
							'isGlobal'           => false,
							'appendToMainCanvas' => $append_to_main,
							'zIndex'             => $z_index,
							'createdAt'          => $canvas_created_at,
						],
						$target_canvas_content
					);
				}
			}
		}

		// Extract canvas IDs from Canvas Portal modules within this canvas.
		// The frontend should send all canvas data, so we just extract IDs to ensure
		// they're included if they're already in the exported canvases list.
		$canvas_portal_ids = DynamicAssetsUtils::extract_canvas_portal_canvas_ids_from_content( $canvas_content );

		if ( ! empty( $canvas_portal_ids ) ) {
			// Check if any canvas portal canvases are already in the export list.
			// The frontend should send all necessary canvas data during export.
			foreach ( $canvas_portal_ids as $canvas_portal_id ) {
				// Skip the canvas we're already exporting.
				if ( $canvas_portal_id === $canvas_id ) {
					continue;
				}

				// Check if this canvas is already in the export list.
				if ( $this->_is_canvas_exported( $exported_canvases, $canvas_portal_id ) ) {
					// Already exported, nothing to do.
					continue;
				}

				// Canvas portal canvas ID found but not in export list.
				// The frontend should send this canvas data during export.
			}
		}

		return $exported_canvases;
	}

	/**
	 * Export global canvases targeted by interactions.
	 *
	 * Finds global canvases that contain modules with interactionTarget attributes matching
	 * the target IDs extracted from interactions in the main content.
	 *
	 * @since ??
	 *
	 * @param string $content Post content to extract interaction targets from.
	 * @param array  $exported_canvases Canvas data structure (passed by reference to add to it).
	 *
	 * @return void
	 */
	private function _export_canvases_targeted_by_interactions( $content, &$exported_canvases ) {
		// Extract interaction target IDs from content.
		$target_ids = OffCanvasHooks::extract_interaction_target_ids_from_content( $content );

		if ( empty( $target_ids ) ) {
			return;
		}

		// Export global canvases that match the condition.
		$this->_export_global_canvases_matching_condition(
			$exported_canvases,
			function ( $post, $canvas_content ) use ( $target_ids ) {
				// Check if this canvas contains any of the target IDs by searching for interactionTarget attribute.
				return $this->_canvas_content_contains_target( $canvas_content, $target_ids );
			}
		);
	}

	/**
	 * Export global canvases that have appendToMainCanvas set.
	 *
	 * These canvases will be appended to the main canvas on the frontend, so they should be included in exports.
	 * Note: Local canvases are already exported, so we only need to check global canvases.
	 *
	 * @since ??
	 *
	 * @param int   $post_id Post ID (unused but kept for consistency).
	 * @param array $exported_canvases Canvas data structure (passed by reference to add to it).
	 *
	 * @return void
	 */
	private function _export_canvases_that_append_to_main( $post_id, &$exported_canvases ) {
		// Export global canvases that match the condition.
		$this->_export_global_canvases_matching_condition(
			$exported_canvases,
			function ( $post ) {
				// Check if this canvas appends to main canvas.
				$append_to_main = get_post_meta( $post->ID, '_divi_canvas_append_to_main', true );
				$append_to_main = '' === $append_to_main ? null : $append_to_main;

				// Only include canvases that append to main canvas.
				return 'above' === $append_to_main || 'below' === $append_to_main;
			}
		);
	}

	/**
	 * Export canvases used in Canvas Portal modules.
	 *
	 * Finds canvases that are referenced by Canvas Portal modules in the content
	 * and ensures they are included in the export. This method extracts canvas IDs
	 * from canvas portal blocks and marks those canvases for export if they're
	 * already in the exported canvases list (from local canvases that were already
	 * included). This ensures that when a layout is imported, all canvases referenced
	 * by Canvas Portal modules are available.
	 *
	 * Note: This method does not query the database. It only extracts IDs from content
	 * and ensures canvases already in the export list are included. The frontend should
	 * send all necessary canvas data during export.
	 *
	 * @since ??
	 *
	 * @param int    $post_id Post ID (unused, kept for consistency with other methods).
	 * @param string $content Post content to extract canvas portal references from.
	 * @param array  $exported_canvases Canvas data structure (passed by reference to add to it).
	 *
	 * @return void
	 */
	private function _export_canvases_used_in_canvas_portals( $post_id, $content, &$exported_canvases ) {
		// Extract canvas IDs from canvas portal blocks in content.
		$canvas_portal_ids = DynamicAssetsUtils::extract_canvas_portal_canvas_ids_from_content( $content );

		if ( empty( $canvas_portal_ids ) ) {
			return;
		}

		// Export global canvases that match the canvas portal IDs.
		// This uses the same pattern as interaction-targeted canvases.
		$this->_export_global_canvases_matching_condition(
			$exported_canvases,
			function ( $post, $canvas_content ) use ( $canvas_portal_ids ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Required by callback signature.
				$canvas_id = get_post_meta( $post->ID, '_divi_canvas_id', true );
				return $canvas_id && in_array( $canvas_id, $canvas_portal_ids, true );
			}
		);

		// Also check if any canvas portal canvases are already in the local canvases export.
		// Local canvases are already queried and exported at the start of _export_canvases,
		// so if a canvas portal canvas is local, it should already be included.
		// This loop ensures we don't miss any that might have been filtered out.
		foreach ( $canvas_portal_ids as $canvas_portal_id ) {
			// Check if this canvas is already in the export list (local or global).
			if ( $this->_is_canvas_exported( $exported_canvases, $canvas_portal_id ) ) {
				// Already exported, nothing to do.
				continue;
			}

			// Canvas portal canvas ID found but not in export list.
			// This could happen if:
			// 1. The canvas is a global canvas that wasn't found by the query above
			// 2. The canvas is a local canvas that wasn't included in the initial query
			// 3. The canvas doesn't exist in the database yet (unsaved canvas)
			// In these cases, the frontend should send the canvas data, but we can't query here.
		}
	}

	/**
	 * Check if a canvas is already exported (either as local or global).
	 *
	 * Helper method to check if a canvas ID exists in the exported canvases array.
	 *
	 * @since ??
	 *
	 * @param array  $exported_canvases Canvas data structure with 'local' and 'global' keys.
	 * @param string $canvas_id Canvas ID to check.
	 *
	 * @return bool True if canvas is already exported, false otherwise.
	 */
	private function _is_canvas_exported( $exported_canvases, $canvas_id ) {
		return isset( $exported_canvases['local'][ $canvas_id ] ) || isset( $exported_canvases['global'][ $canvas_id ] );
	}

	/**
	 * Export all global canvases.
	 *
	 * Exports all global canvases without any condition checks.
	 *
	 * @since ??
	 *
	 * @param array $exported_canvases Canvas data structure (passed by reference to add to it).
	 *
	 * @return void
	 */
	private function _export_all_global_canvases( &$exported_canvases ) {
		$this->_export_global_canvases_matching_condition(
			$exported_canvases,
			function () {
				// Always return true to include all canvases.
				return true;
			}
		);
	}

	/**
	 * Check if canvas content contains any of the target IDs.
	 *
	 * Searches for interactionTarget attributes in canvas content that match any of the provided target IDs.
	 * Handles both simple and nested interactionTarget structures.
	 *
	 * @since ??
	 *
	 * @param string   $canvas_content Canvas content (serialized Gutenberg blocks).
	 * @param string[] $target_ids Array of target IDs to search for.
	 *
	 * @return bool True if canvas content contains any of the target IDs, false otherwise.
	 */
	private function _canvas_content_contains_target( $canvas_content, $target_ids ) {
		foreach ( $target_ids as $target_id ) {
			$escaped_target_id = preg_quote( $target_id, '/' );
			// Match interactionTarget with quoted value (most common case).
			if ( preg_match( '/"interactionTarget"\s*:\s*"' . $escaped_target_id . '"/', $canvas_content ) ) {
				return true;
			}
			// Match interactionTarget with nested structure (e.g., desktop breakpoint with value property).
			if ( preg_match( '/"interactionTarget"\s*:\s*\{[^}]*"value"\s*:\s*"' . $escaped_target_id . '"/', $canvas_content ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Build global canvas export array structure.
	 *
	 * Helper function to create a consistent global canvas export data structure.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Canvas ID.
	 * @param object $post WordPress post object.
	 * @param string $canvas_content Canvas content (serialized Gutenberg blocks).
	 *
	 * @return array Global canvas export data structure.
	 */
	private function _build_global_canvas_export_array( $canvas_id, $post, $canvas_content ) {
		$canvas_created_at = get_post_meta( $post->ID, '_divi_canvas_created_at', true );
		$canvas_created_at = $canvas_created_at ? $canvas_created_at : $post->post_date;

		$append_to_main = get_post_meta( $post->ID, '_divi_canvas_append_to_main', true );
		$append_to_main = '' === $append_to_main ? null : $append_to_main;

		$z_index = get_post_meta( $post->ID, '_divi_canvas_z_index', true );
		$z_index = '' === $z_index ? null : $z_index;

		return [
			'id'                 => $canvas_id,
			'name'               => $post->post_title,
			'isMain'             => false,
			'isGlobal'           => true,
			'appendToMainCanvas' => $append_to_main,
			'zIndex'             => $z_index,
			'createdAt'          => $canvas_created_at,
			'content'            => $canvas_content,
		];
	}

	/**
	 * Build local canvas export array structure.
	 *
	 * Helper function to create a consistent local canvas export data structure.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Canvas ID.
	 * @param array  $canvas_meta Canvas metadata array.
	 * @param string $canvas_content Canvas content (serialized Gutenberg blocks).
	 *
	 * @return array Local canvas export data structure.
	 */
	private function _build_local_canvas_export_array( $canvas_id, $canvas_meta, $canvas_content ) {
		return [
			'id'                 => $canvas_meta['id'] ?? $canvas_id,
			'name'               => $canvas_meta['name'] ?? '',
			'isMain'             => $canvas_meta['isMain'] ?? false,
			'isGlobal'           => false,
			'appendToMainCanvas' => $canvas_meta['appendToMainCanvas'] ?? null,
			'zIndex'             => $canvas_meta['zIndex'] ?? null,
			'createdAt'          => $canvas_meta['createdAt'] ?? current_time( 'mysql' ),
			'content'            => $canvas_content,
		];
	}

	/**
	 * Export global canvases matching a condition.
	 *
	 * Helper function to reduce code duplication when exporting global canvases.
	 * Handles common logic: fetching global canvas posts, filtering existing canvases,
	 * and building the canvas data structure.
	 *
	 * @since ??
	 *
	 * @param array    $exported_canvases Canvas data structure (passed by reference to add to it).
	 * @param callable $condition_callback Callback function that receives ($post, $canvas_content) and returns bool.
	 *                                     Should return true if the canvas should be exported.
	 *
	 * @return void
	 */
	private function _export_global_canvases_matching_condition( &$exported_canvases, callable $condition_callback ) {
		// Get all global canvases (exclude local canvases).
		// Local canvases have _divi_canvas_parent_post_id set.
		// Global canvases do not have _divi_canvas_parent_post_id.
		$global_posts = CanvasUtils::get_canvas_posts( true );

		foreach ( $global_posts as $post ) {
			$canvas_id = get_post_meta( $post->ID, '_divi_canvas_id', true );
			if ( ! $canvas_id ) {
				continue;
			}

			// Skip if already exported (either as local or global).
			if ( $this->_is_canvas_exported( $exported_canvases, $canvas_id ) ) {
				continue;
			}

			// Check if canvas matches condition.
			$canvas_content = $post->post_content;
			if ( ! $condition_callback( $post, $canvas_content ) ) {
				continue;
			}

			// Build canvas data structure.
			$exported_canvases['global'][ $canvas_id ] = $this->_build_global_canvas_export_array(
				$canvas_id,
				$post,
				$canvas_content
			);
		}
	}

	/**
	 * Import canvases for a post.
	 *
	 * Imports local canvases as postmeta and global canvases as et_pb_canvas post types.
	 *
	 * @since ??
	 *
	 * @param int   $post_id Post ID.
	 * @param array $canvases Canvas data structure with 'local' and 'global' keys.
	 *
	 * @return void
	 */
	private function _import_canvases( $post_id, $canvases ) {
		if ( ! is_array( $canvases ) ) {
			return;
		}

		// Security check: Verify user has permission to edit the post.
		// This ensures only users with edit privileges for the post can import canvases.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Import local canvases.
		if ( isset( $canvases['local'] ) && is_array( $canvases['local'] ) ) {
			foreach ( $canvases['local'] as $canvas_id => $canvas_data ) {
				if ( ! is_array( $canvas_data ) || ! isset( $canvas_data['content'] ) ) {
					continue;
				}

				// Check if local canvas already exists (by canvas ID and parent post ID).
				$existing_posts = get_posts(
					[
						'post_type'      => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
						'posts_per_page' => 1,
						'meta_query'     => [
							[
								'key'   => '_divi_canvas_id',
								'value' => $canvas_id,
							],
							[
								'key'   => '_divi_canvas_parent_post_id',
								'value' => $post_id,
							],
						],
					]
				);

				$post_data = [
					'post_title'   => $canvas_data['name'] ?? 'Local Canvas',
					'post_status'  => 'publish',
					'post_type'    => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
					'post_content' => wp_slash( $canvas_data['content'] ),
					'meta_input'   => [
						'_divi_canvas_id'             => $canvas_id,
						'_divi_canvas_parent_post_id' => $post_id,
						'_divi_canvas_created_at'     => $canvas_data['createdAt'] ?? current_time( 'mysql' ),
						'_divi_canvas_append_to_main' => $canvas_data['appendToMainCanvas'] ?? null,
						'_divi_canvas_z_index'        => $canvas_data['zIndex'] ?? null,
					],
				];

				if ( ! empty( $existing_posts ) ) {
					// Update existing post.
					$post_data['ID'] = $existing_posts[0]->ID;
					wp_update_post( $post_data );
					// Update meta separately since wp_update_post doesn't always handle meta_input reliably.
					update_post_meta( $existing_posts[0]->ID, '_divi_canvas_append_to_main', $canvas_data['appendToMainCanvas'] ?? null );
					update_post_meta( $existing_posts[0]->ID, '_divi_canvas_z_index', $canvas_data['zIndex'] ?? null );
				} else {
					// Create new post.
					wp_insert_post( $post_data );
				}
			}
		}

		// Import global canvases.
		if ( isset( $canvases['global'] ) && is_array( $canvases['global'] ) ) {
			foreach ( $canvases['global'] as $canvas_id => $canvas_data ) {
				if ( ! is_array( $canvas_data ) || ! isset( $canvas_data['content'] ) ) {
					continue;
				}

				// Check if global canvas already exists (by canvas ID).
				$existing_posts = get_posts(
					[
						'post_type'      => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
						'posts_per_page' => 1,
						'meta_query'     => [
							[
								'key'   => '_divi_canvas_id',
								'value' => $canvas_id,
							],
						],
					]
				);

				$post_data = [
					'post_title'   => $canvas_data['name'] ?? 'Global Canvas',
					'post_status'  => 'publish',
					'post_type'    => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
					'post_content' => wp_slash( $canvas_data['content'] ),
					'meta_input'   => [
						'_divi_canvas_id'             => $canvas_id,
						'_divi_canvas_created_at'     => $canvas_data['createdAt'] ?? current_time( 'mysql' ),
						'_divi_canvas_append_to_main' => $canvas_data['appendToMainCanvas'] ?? null,
						'_divi_canvas_z_index'        => $canvas_data['zIndex'] ?? null,
					],
				];

				if ( ! empty( $existing_posts ) ) {
					$existing_post_id = $existing_posts[0]->ID;

					// Update existing post.
					$post_data['ID'] = $existing_post_id;
					wp_update_post( $post_data );
					// Update meta separately since wp_update_post doesn't always handle meta_input reliably.
					update_post_meta( $existing_post_id, '_divi_canvas_append_to_main', $canvas_data['appendToMainCanvas'] ?? null );
					update_post_meta( $existing_post_id, '_divi_canvas_z_index', $canvas_data['zIndex'] ?? null );
				} else {
					// Create new post.
					wp_insert_post( $post_data );
				}
			}
		}
	}

	/**
	 * Prepare canvas data for frontend response.
	 *
	 * Converts imported canvas content from Gutenberg format to module flat object format
	 * expected by the frontend, matching the format returned by the REST API endpoint.
	 *
	 * @since ??
	 *
	 * @param int   $post_id Post ID.
	 * @param array $canvases Canvas data structure with 'local' and 'global' keys.
	 *
	 * @return array|null Canvas data in format expected by frontend, or null if no canvases.
	 */
	private function _prepare_canvas_data_for_response( $post_id, $canvases ) {
		if ( ! is_array( $canvases ) ) {
			return null;
		}

		$response_canvases = [];

		// Process local canvases.
		if ( isset( $canvases['local'] ) && is_array( $canvases['local'] ) ) {
			foreach ( $canvases['local'] as $canvas_id => $canvas_data ) {
				if ( ! is_array( $canvas_data ) || ! isset( $canvas_data['content'] ) ) {
					continue;
				}

				// Convert Gutenberg block format to module data format.
				$module_data = null;
				if ( ! empty( $canvas_data['content'] ) ) {
					try {
						// Unwrap divi/placeholder block before parsing.
						$unwrapped_content = ModuleUtils::maybe_unwrap_placeholder_block( $canvas_data['content'] );
						$module_data       = ConversionUtils::parseSerializedPostIntoFlatModuleObject( $unwrapped_content );
					} catch ( \Exception $e ) {
						$module_data = null;
					}
				}

				$response_canvases[ $canvas_id ] = [
					'id'                 => $canvas_data['id'] ?? $canvas_id,
					'name'               => $canvas_data['name'] ?? '',
					'isMain'             => false,
					'isGlobal'           => false,
					'appendToMainCanvas' => $canvas_data['appendToMainCanvas'] ?? null,
					'zIndex'             => $canvas_data['zIndex'] ?? null,
					'createdAt'          => $canvas_data['createdAt'] ?? current_time( 'mysql' ),
					'content'            => $module_data,
				];
			}
		}

		// Process global canvases (they're already saved, but we include them in response).
		if ( isset( $canvases['global'] ) && is_array( $canvases['global'] ) ) {
			foreach ( $canvases['global'] as $canvas_id => $canvas_data ) {
				if ( ! is_array( $canvas_data ) || ! isset( $canvas_data['content'] ) ) {
					continue;
				}

				// Convert Gutenberg block format to module data format.
				$module_data = null;
				if ( ! empty( $canvas_data['content'] ) ) {
					try {
						// Unwrap divi/placeholder block before parsing.
						$unwrapped_content = ModuleUtils::maybe_unwrap_placeholder_block( $canvas_data['content'] );
						$module_data       = ConversionUtils::parseSerializedPostIntoFlatModuleObject( $unwrapped_content );
					} catch ( \Exception $e ) {
						$module_data = null;
					}
				}

				$response_canvases[ $canvas_id ] = [
					'id'                 => $canvas_data['id'] ?? $canvas_id,
					'name'               => $canvas_data['name'] ?? '',
					'isMain'             => false,
					'isGlobal'           => true,
					'appendToMainCanvas' => $canvas_data['appendToMainCanvas'] ?? null,
					'zIndex'             => $canvas_data['zIndex'] ?? null,
					'createdAt'          => $canvas_data['createdAt'] ?? current_time( 'mysql' ),
					'content'            => $module_data,
				];
			}
		}

		if ( empty( $response_canvases ) ) {
			return null;
		}

		// Get canvas metadata to include activeCanvasId and mainCanvasName.
		$canvas_metadata = get_post_meta( $post_id, '_divi_off_canvas_data', true );

		return [
			'canvases'       => $response_canvases,
			'activeCanvasId' => $canvas_metadata['activeCanvasId'] ?? '',
			'mainCanvasName' => $canvas_metadata['mainCanvasName'] ?? '',
		];
	}

	/**
	 * Deglobalize nested global module references in content.
	 *
	 * CONTEXT: Global modules CANNOT be nested inside other global modules by design.
	 * This is a fundamental architectural constraint that should never be violated.
	 *
	 * THE BUG: Early versions of Divi 5 (prior to public beta 8) had a bug in the
	 * Divi Library admin interface that allowed users to insert a global module inside
	 * another global module when exporting/importing via the Divi Library admin interface.
	 * This bug has since been patched in public beta 8.
	 *
	 * DAMAGE CONTROL: This method exists solely as damage control to handle invalid
	 * export files that may have been created during the buggy period. These invalid
	 * files would have been created ONLY through the Divi Library admin interface,
	 * NOT through the D5 Visual Builder UI (which never had this bug).
	 *
	 * SCOPE: This method should ONLY be called when processing imports from the
	 * Divi Library admin interface (ET_Core_Portability), NOT from D5 Visual Builder
	 * operations (PortabilityPost), since the bug never existed in the D5 VB UI.
	 *
	 * IMPLEMENTATION: Converts `divi/global-layout` blocks (global module references)
	 * to their actual module types (e.g., divi/row) by extracting localAttrs and
	 * removing global-related attributes. Uses string manipulation because WordPress
	 * parse_blocks() doesn't work with blocks that have the globalModule attribute.
	 *
	 * @since ??
	 *
	 * @param string $content Serialized block content (Gutenberg format).
	 *
	 * @return string Processed content with nested global module references converted to regular modules.
	 */
	public function maybe_deglobalize_nested_global_modules( $content ) {
		// Only process D5 Gutenberg block content.
		if ( ! str_contains( $content, '<!-- wp:divi/' ) ) {
			return $content;
		}

		// Only process if there are global-layout blocks (global module references).
		if ( ! str_contains( $content, 'divi/global-layout' ) ) {
			return $content;
		}

		// Find all divi/global-layout blocks and replace them.
		$offset            = 0;
		$processed_content = $content;
		$pos               = strpos( $processed_content, '<!-- wp:divi/global-layout ', $offset );

		while ( false !== $pos ) {
			// Find the end of the opening tag.
			$tag_end = strpos( $processed_content, '-->', $pos );
			if ( false === $tag_end ) {
				break;
			}

			// Extract the full opening tag.
			$opening_tag = substr( $processed_content, $pos, $tag_end - $pos + 3 );

			// Extract JSON using brace counting (handles nested braces).
			$json_start = strpos( $opening_tag, '{' );
			if ( false === $json_start ) {
				$offset = $tag_end + 3;
				continue;
			}

			$json_str   = $this->_extract_json_from_tag( $opening_tag, $json_start );
			$self_close = str_contains( $opening_tag, '/-->' );

			// Decode the JSON attributes.
			$attrs = json_decode( $json_str, true );

			if ( null === $attrs || ! isset( $attrs['blockName'] ) ) {
				// If JSON is invalid or blockName is missing, skip this block.
				$offset = $tag_end + 3;
				continue;
			}

			// Extract the actual module type.
			$block_name = $attrs['blockName'];

			// Extract localAttrs JSON string directly to preserve key order.
			// Use regex to find "localAttrs":{...} in the original JSON string.
			$local_attrs_json = $this->_extract_local_attrs_json( $json_str );

			// Use the extracted JSON string directly (preserves key order).
			$new_attrs_json = ! empty( $local_attrs_json ) ? ' ' . $local_attrs_json : '';

			// Construct the replacement block opening tag.
			if ( $self_close ) {
				$replacement = '<!-- wp:' . $block_name . $new_attrs_json . ' /-->';
			} else {
				$replacement = '<!-- wp:' . $block_name . $new_attrs_json . ' -->';
			}

			// Replace the opening tag.
			$processed_content = substr_replace( $processed_content, $replacement, $pos, strlen( $opening_tag ) );

			// If not self-closing, also replace the corresponding closing tag.
			if ( ! $self_close ) {
				$closing_tag     = '<!-- /wp:divi/global-layout -->';
				$closing_pos     = strpos( $processed_content, $closing_tag, $pos + strlen( $replacement ) );
				$new_closing_tag = '<!-- /wp:' . $block_name . ' -->';

				if ( false !== $closing_pos ) {
					$processed_content = substr_replace( $processed_content, $new_closing_tag, $closing_pos, strlen( $closing_tag ) );
				}
			}

			// Move offset forward and continue searching.
			$offset = $pos + strlen( $replacement );
			$pos    = strpos( $processed_content, '<!-- wp:divi/global-layout ', $offset );
		}

		// Remove globalParent attributes from all blocks.
		$processed_content = $this->_remove_global_parent_attributes( $processed_content );

		return $processed_content;
	}

	/**
	 * Extract localAttrs JSON string from attributes JSON.
	 *
	 * Extracts the localAttrs value as a JSON string without decoding/re-encoding
	 * to preserve the original key order.
	 *
	 * @since ??
	 *
	 * @param string $json_str The full attributes JSON string.
	 *
	 * @return string The localAttrs JSON string, or empty string if not found.
	 */
	private function _extract_local_attrs_json( $json_str ) {
		// Find "localAttrs": in the JSON string.
		$local_attrs_pos = strpos( $json_str, '"localAttrs":' );
		if ( false === $local_attrs_pos ) {
			return '';
		}

		// Find the opening brace after "localAttrs":.
		$start_pos = strpos( $json_str, '{', $local_attrs_pos );
		if ( false === $start_pos ) {
			return '';
		}

		// Extract the JSON object using brace counting.
		return $this->_extract_json_object_from_position( $json_str, $start_pos );
	}

	/**
	 * Extract JSON from a block tag using brace counting.
	 *
	 * Handles nested braces to extract complete JSON object.
	 *
	 * @since ??
	 *
	 * @param string $tag        The block tag.
	 * @param int    $json_start Position of the opening brace.
	 *
	 * @return string The extracted JSON string.
	 */
	private function _extract_json_from_tag( $tag, $json_start ) {
		return $this->_extract_json_object_from_position( $tag, $json_start );
	}

	/**
	 * Extract JSON object from a string starting at a given position.
	 *
	 * Uses brace counting to handle nested objects.
	 *
	 * @since ??
	 *
	 * @param string $str   The string containing JSON.
	 * @param int    $start Position of the opening brace.
	 *
	 * @return string The extracted JSON string.
	 */
	private function _extract_json_object_from_position( $str, $start ) {
		$brace_count = 0;
		$in_string   = false;
		$escape_next = false;
		$json_end    = $start;
		$str_length  = strlen( $str );

		for ( $i = $start; $i < $str_length; $i++ ) {
			$char = $str[ $i ];

			if ( $escape_next ) {
				$escape_next = false;
				continue;
			}

			if ( '\\' === $char ) {
				$escape_next = true;
				continue;
			}

			if ( '"' === $char ) {
				$in_string = ! $in_string;
				continue;
			}

			if ( $in_string ) {
				continue;
			}

			if ( '{' === $char ) {
				++$brace_count;
			} elseif ( '}' === $char ) {
				--$brace_count;
				if ( 0 === $brace_count ) {
					$json_end = $i;
					break;
				}
			}
		}

		return substr( $str, $start, $json_end - $start + 1 );
	}


	/**
	 * Remove globalParent attributes from serialized content.
	 *
	 * Removes `globalParent` attribute from all blocks in the serialized content.
	 *
	 * @since ??
	 *
	 * @param string $content Serialized block content.
	 *
	 * @return string Content with globalParent attributes removed.
	 */
	private function _remove_global_parent_attributes( $content ) {
		// Pattern to match and remove "globalParent":"value" from JSON attributes.
		// This handles both with and without trailing comma.
		$content = preg_replace( '/"globalParent"\s*:\s*"[^"]*"\s*,\s*/', '', $content );
		$content = preg_replace( '/,\s*"globalParent"\s*:\s*"[^"]*"/', '', $content );

		return $content;
	}
}
