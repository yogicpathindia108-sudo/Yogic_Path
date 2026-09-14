<?php

/**
 * Manages a frontend inline CSS or JavaScript resource.
 *
 * If possible, the resource will be served as a static file for better performance. It can be
 * tied to a specific post or it can be 'global'. The resource can be output, static or inline,
 * to one of four locations on the page:
 *
 *   * `head-early`: right AFTER theme styles have been enqueued
 *   * `head`      : right BEFORE the theme and wp's inline custom css
 *   * `head-late` : right AFTER the theme and wp's inline custom css
 *   * `footer`    : in the footer
 *
 * The first time the class is instantiated, a static callback method will be registered for each
 * output location. Inside each callback, we'll iterate over any/all instances that are assigned
 * to the current output location and perform the following steps:
 *
 *   1. If a static file exists for the resource, go to the next step. Otherwise, try to create
 *      a static file for the resource if it has `data`. If it doesn't have `data`, assign it to
 *      the next output location and then move on to the next resource (continue).
 *   2. If a static file exists for the resource, enqueue it (via WP or manually) and then move on
 *      to the next resource (continue). If no static file exists, go to the next step.
 *   3. Output the resource inline.
 *
 * @since   2.0
 *
 * @package ET\Core
 */
class ET_Core_PageResource {
	/**
	 * Lock file.
	 *
	 * @var string[]
	 */
	protected static $_lock_file;

	/**
	 * Track if global timestamp has been updated in this request.
	 *
	 * @var bool
	 */
	protected static $_global_timestamp_updated = false;

	/**
	 * Whether a WooCommerce bulk product edit cache clear is scheduled for shutdown.
	 *
	 * @var bool
	 */
	protected static $_woocommerce_bulk_edit_cache_clear_scheduled = false;

	/**
	 * Onload attribute for stylesheet output.
	 *
	 * @var string[]
	 */
	private static $_onload = '';

	/**
	 * Output locations.
	 *
	 * @var string[]
	 */
	protected static $_output_locations = array(
		'head-early',
		'head',
		'head-late',
		'footer',
	);

	/**
	 * Resource owners.
	 *
	 * @var string[]
	 */
	protected static $_owners = array(
		'divi',
		'builder',
		'epanel',
		'epanel_temp',
		'extra',
		'core',
		'bloom',
		'monarch',
		'custom',
		'all',
	);

	/**
	 * Resource scopes.
	 *
	 * @var string[]
	 */
	protected static $_scopes = array(
		'global',
		'post',
	);

	/**
	 * Temp DIRS.
	 *
	 * @var array
	 */
	protected static $_temp_dirs = array();

	/**
	 * Directories where files were created during this request.
	 * Used to track which directories need stale file cleanup.
	 *
	 * @var array
	 */
	public static $_directories_with_new_files = array();

	/**
	 * Cached taxonomy folder name for the current request.
	 * Used to ensure taxonomy pages always use the taxonomy folder,
	 * even when is_tax() becomes unreliable later in the request.
	 *
	 * @var string|null
	 */
	protected static $_cached_taxonomy_folder = null;

	/**
	 * Cache for resolved folder names to avoid repeated lookups.
	 * Stores folder names keyed by post_id for performance optimization.
	 *
	 * @var array
	 */
	protected static $_cache_folder_cache = array();

	/**
	 * Check if we're using a taxonomy folder for the current request.
	 *
	 * @return bool
	 */
	public static function is_using_taxonomy_folder() {
		if ( null !== self::$_cached_taxonomy_folder ) {
			return true;
		}
		if ( is_tax() || is_category() || is_tag() ) {
			$queried = get_queried_object();
			if ( $queried && isset( $queried->taxonomy, $queried->term_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get cache folder name for a given post_id or object_id.
	 * Consolidates logic used by both PageResource and DynamicAssets.
	 *
	 * @param int|string $post_id_or_object_id The post ID or object ID.
	 * @return string Cache folder name (e.g., 'taxonomy/product_brand/417', 'search', 'author/1', etc.).
	 */
	public static function get_cache_folder_name( $post_id_or_object_id ) {
		// Check cache first for positive numeric IDs to avoid repeated expensive lookups.
		if ( is_numeric( $post_id_or_object_id ) && $post_id_or_object_id > 0 ) {
			if ( isset( self::$_cache_folder_cache[ $post_id_or_object_id ] ) ) {
				return self::$_cache_folder_cache[ $post_id_or_object_id ];
			}
		}

		$folder_name = $post_id_or_object_id;

		// Check cached taxonomy folder first (set early in request when is_tax() was reliable).
		if ( null !== self::$_cached_taxonomy_folder ) {
			return self::$_cached_taxonomy_folder;
		}

		// Check taxonomy pages.
		if ( is_tax() || is_category() || is_tag() ) {
			$queried = get_queried_object();
			if ( $queried && isset( $queried->taxonomy, $queried->term_id ) ) {
				$taxonomy    = sanitize_key( $queried->taxonomy );
				$term_id     = intval( $queried->term_id );
				$folder_name = "taxonomy/{$taxonomy}/{$term_id}";
				// Cache the taxonomy folder for later use when is_tax() becomes unreliable.
				self::$_cached_taxonomy_folder = $folder_name;
				return $folder_name;
			}
		}

		// Check author pages (must come before empty check to handle positive author IDs).
		if ( is_author() ) {
			$author_id   = get_queried_object_id();
			$folder_name = "author/{$author_id}";
			// Cache the result for positive numeric IDs.
			if ( is_numeric( $post_id_or_object_id ) && $post_id_or_object_id > 0 ) {
				self::$_cache_folder_cache[ $post_id_or_object_id ] = $folder_name;
			}
			return $folder_name;
		}

		// Check other archive page types (for post_id = 0, -1, null, or empty).
		// This handles cases where post_id is not set for archive pages like search, etc.
		if ( empty( $post_id_or_object_id ) || '0' === (string) $post_id_or_object_id || 0 === $post_id_or_object_id || -1 === $post_id_or_object_id ) {
			if ( is_search() ) {
				return 'search';
			} elseif ( is_archive() ) {
				return 'archive';
			} elseif ( is_home() ) {
				return 'home';
			} elseif ( is_404() ) {
				return 'notfound';
			}
		}

		// Cache the result for positive numeric IDs.
		if ( is_numeric( $post_id_or_object_id ) && $post_id_or_object_id > 0 ) {
			self::$_cache_folder_cache[ $post_id_or_object_id ] = $folder_name;
		}

		return $folder_name;
	}

	/**
	 * Check if a folder name indicates an archive/taxonomy folder.
	 *
	 * @param string $folder_name The folder name to check.
	 * @return bool True if the folder name indicates an archive folder.
	 */
	public static function is_archive_folder( $folder_name ) {
		return (
			strpos( $folder_name, 'taxonomy/' ) === 0 ||
			'search' === $folder_name ||
			strpos( $folder_name, 'author/' ) === 0 ||
			'archive' === $folder_name ||
			'home' === $folder_name ||
			'notfound' === $folder_name
		);
	}

	/**
	 * Determine if post_id should be excluded from filename.
	 * Consolidates logic used by both PageResource and DynamicAssets.
	 *
	 * @param int|string $post_id The post ID.
	 * @param string     $slug The resource slug (may contain TB IDs).
	 * @param string     $folder_name Optional. The cache folder name. If not provided, will be determined.
	 * @return bool True if post_id should be excluded from filename.
	 */
	public static function should_exclude_post_id_from_filename( $post_id, $slug, $folder_name = null ) {
		// Exclude if post_id is 0, 'global', or 'all' (not meaningful identifiers).
		if ( '0' === (string) $post_id || 0 === $post_id || 'global' === $post_id || 'all' === $post_id ) {
			return true;
		}

		// Exclude if using archive/taxonomy folders (folder structure handles page context).
		if ( null === $folder_name ) {
			$folder_name = self::get_cache_folder_name( $post_id );
		}
		if ( self::is_archive_folder( $folder_name ) ) {
			return true;
		}

		// For singular pages, always include post_id in filename for precise cache clearing.
		// Even if TB layout IDs are in the slug, the post_id is needed to clear the correct file
		// when the post is saved (cache clearing uses pattern: {post_id}/et-{owner}-{slug}*).
		// Note: TB IDs in slug are still included for identification, but post_id is also included.

		return false;
	}

	/**
	 * Resource types.
	 *
	 * @var string[]
	 */
	protected static $_types = array(
		'style',
		'script',
	);

	/**
	 * Whether or not we have write access to the filesystem.
	 *
	 * @var bool
	 */
	protected static $_can_write;

	/**
	 * Request ID.
	 *
	 * @var int
	 */
	protected static $_request_id;

	/**
	 * Request time.
	 *
	 * @var string
	 */
	protected static $_request_time;

	/**
	 * All instances of this class.
	 *
	 * @var ET_Core_PageResource[] {
	 *
	 *     @type ET_Core_PageResource $slug
	 * }
	 */
	protected static $_resources;

	/**
	 * All instances of this class organized by output location and sorted by priority.
	 *
	 * @var array[] {
	 *
	 *     @type array[] $location {@see self::$_output_locations} {
	 *
	 *         @type ET_Core_PageResource[] $priority {
	 *
	 *             @type ET_Core_PageResource $slug
	 *         }
	 *     }
	 * }
	 */
	protected static $_resources_by_location;

	/**
	 * All instances of this class organized by scope.
	 *
	 * @var array[] {
	 *
	 *     @type ET_Core_PageResource[] $post|$global {
	 *
	 *         @type ET_Core_PageResource $slug
	 *     }
	 * }
	 */
	protected static $_resources_by_scope;

	/**
	 * @var string
	 */
	public static $WP_CONTENT_DIR;

	/**
	 * @var string
	 */
	public static $current_output_location;

	/**
	 * @var ET_Core_Data_Utils
	 */
	public static $data_utils;

	/**
	 * @var \WP_Filesystem_Base|null
	 */
	public static $wpfs;

	/**
	 * The absolute path to the directory where the static resource will be stored.
	 *
	 * @var string
	 */
	public $base_dir;

	/**
	 * The absolute path to the static resource on the server.
	 *
	 * @var string
	 */
	public $path;

	/**
	 * Temp DIR.
	 *
	 * @var array
	 */
	public $temp_dir;

	/**
	 * The absolute URL through which the static resource can be downloaded.
	 *
	 * @var string
	 */
	public $url;

	/**
	 * The data/contents for/of the static resource sorted by priority.
	 *
	 * @var array
	 */
	public $data;

	/**
	 * Whether or not this resource has been disabled.
	 *
	 * @var bool
	 */
	public $disabled;

	/**
	 * Whether or not the static resource file has been enqueued.
	 *
	 * @var bool
	 */
	public $enqueued;

	/**
	 * Whether or not this resource is forced inline.
	 *
	 * @var bool
	 */
	public $forced_inline;

	/**
	 * @var string
	 */
	public $filename;

	/**
	 * Whether or not the resource has already been output to the page inline.
	 *
	 * @var bool
	 */
	public $inlined;

	/**
	 * The owner of this instance.
	 *
	 * @var string
	 */
	public $owner;

	/**
	 * The id of the post to which this resource belongs.
	 *
	 * @var string
	 */
	public $post_id;

	/**
	 * The priority of this resource.
	 *
	 * @var int
	 */
	public $priority;

	/**
	 * A unique identifier for this resource.
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * The resource type (style|script).
	 *
	 * @var string
	 */
	public $type;

	/**
	 * The output location during which this resource's static file should be generated.
	 *
	 * @var string
	 */
	public $write_file_location;

	/**
	 * The output location where this resource should be output.
	 *
	 * @var string
	 */
	public $location;

	/**
	 * ET_Core_PageResource constructor
	 *
	 * @param string     $owner    The owner of the instance (core|divi|builder|bloom|monarch|custom).
	 * @param string     $slug     A string that uniquely identifies the resource.
	 * @param string|int $post_id  The post id that the resource is associated with or `global`.
	 *                             If `null`, {@link get_the_ID()} will be used.
	 * @param string     $type     The resource type (style|script). Default: `style`.
	 * @param string     $location Where the resource should be output (head|footer). Default: `head`.
	 */
	public function __construct( $owner, $slug, $post_id = null, $priority = 10, $location = 'head-late', $type = 'style' ) {
		$this->owner   = self::_validate_property( 'owner', $owner );
		// Use null check instead of truthy check to preserve 0 as a valid post_id value.
		$raw_post_id = null !== $post_id ? $post_id : et_core_page_resource_get_the_ID();
		$this->post_id = self::_validate_property( 'post_id', $raw_post_id );

		$this->type     = self::_validate_property( 'type', $type );
		$this->location = self::_validate_property( 'location', $location );

		$this->write_file_location = $this->location;

		// Generate filename. Use consolidated method to determine if post_id should be excluded.
		$filename_post_id = '';
		if ( ! self::should_exclude_post_id_from_filename( $this->post_id, $slug ) ) {
			$filename_post_id = '-' . $this->post_id;
		}
		$this->filename = sanitize_file_name( "et-{$this->owner}-{$slug}{$filename_post_id}" );
		$this->slug     = "{$this->filename}-cached-inline-{$this->type}s";

		$this->data     = array();
		$this->priority = $priority;

		self::startup();

		$this->_initialize_resource();
	}

	/**
	 * Activates the class
	 */
	public static function startup() {
		if ( null !== self::$_resources ) {
			// Class has already been initialized
			return;
		}

		$time = (string) microtime( true );
		$time = str_replace( '.', '', $time );
		$rand = (string) mt_rand();

		self::$_request_time = $time;
		self::$_request_id   = "{$time}-{$rand}";
		self::$_resources    = array();
		self::$data_utils    = new ET_Core_Data_Utils();
		self::$_directories_with_new_files = array();

		foreach ( self::$_output_locations as $location ) {
			self::$_resources_by_location[ $location ] = array();
		}

		foreach ( self::$_scopes as $scope ) {
			self::$_resources_by_scope[ $scope ] = array();
		}
		// phpcs:enable

		self::$WP_CONTENT_DIR = self::$data_utils->normalize_path( WP_CONTENT_DIR );
		self::$_lock_file     = self::$_request_id . '~';

		self::_register_callbacks();
		self::_setup_wp_filesystem();

		self::$_can_write = et_core_cache_dir()->can_write;
	}

	/**
	 * Clean up stale CSS files in directories where new files were created.
	 *
	 * After regenerating CSS files for a page, old stale CSS files that weren't
	 * regenerated should be removed. This prevents accumulation of unused stale files.
	 *
	 * @return void
	 */
	protected static function _cleanup_stale_files_in_directories() {
		if ( empty( self::$_directories_with_new_files ) || ! self::can_write_to_filesystem() ) {
			return;
		}

		$cache_dir = self::get_cache_directory();

		foreach ( array_keys( self::$_directories_with_new_files ) as $directory ) {
			// Security check: Only clean up files within the cache directory.
			if ( ! et_()->starts_with( $directory, $cache_dir ) ) {
				continue;
			}

			if ( ! is_dir( $directory ) ) {
				continue;
			}

			// Find all CSS files in this directory with Divi naming convention.
			// Only match files starting with 'et-' prefix for additional security.
			// Include both .css and .min.css files (DynamicAssets uses .css, PageResource uses .min.css).
			// Note: The .css pattern will also match .min.css files, so we deduplicate.
			$css_files     = glob( $directory . '/et-*.min.css' );
			$css_files_alt = glob( $directory . '/et-*.css' );
			$files         = array_unique( array_merge( (array) $css_files, (array) $css_files_alt ) );

			if ( empty( $files ) ) {
				continue;
			}

			foreach ( $files as $file ) {
				// Only delete files that are marked as stale.
				if ( ! self::is_file_stale( $file ) ) {
					continue;
				}

				// Delete the stale file.
				if ( is_file( $file ) && self::_is_valid_divi_css_file( $file ) ) {
					self::$wpfs->delete( $file );
				}

				// Delete the companion .stale marker if it exists.
				$stale_marker = $file . '.stale';
				if ( file_exists( $stale_marker ) ) {
					self::$wpfs->delete( $stale_marker );
				}
			}
		}
	}

	/**
	 * Cleanup and save
	 */
	public static function shutdown() {
		if ( ! self::$_resources || ! self::$_can_write ) {
			return;
		}

		// Remove any leftover temporary directories that belong to this request
		foreach ( self::$_temp_dirs as $temp_directory ) {
			if ( file_exists( $temp_directory . '/' . self::$_lock_file ) ) {
				@self::$wpfs->delete( $temp_directory, true );
			}
		}

		// Clean up stale CSS files in directories where new files were created.
		self::_cleanup_stale_files_in_directories();

		// Reset $_resources property; Mostly useful for unit test big request which needs to make
		// each test*() method act like it is different page request
		self::$_resources = null;

		if ( et_()->WPFS()->exists( self::$WP_CONTENT_DIR . '/cache/et' ) ) {
			// Remove old cache directory
			et_()->WPFS()->rmdir( self::$WP_CONTENT_DIR . '/cache/et', true );
		}
	}

	protected static function _assign_output_location( $location, $resource ) {
		$priority_existed = isset( self::$_resources_by_location[ $location ][ $resource->priority ] );

		self::$_resources_by_location[ $location ][ $resource->priority ][ $resource->slug ] = $resource;

		if ( ! $priority_existed ) {
			// We've added a new priority to the list, so put them back in sorted order.
			ksort( self::$_resources_by_location[ $location ], SORT_NUMERIC );
		}
	}

	/**
	 * Enqueues static file for provided script resource.
	 *
	 * @param ET_Core_PageResource $resource page resources.
	 */
	protected static function _enqueue_script( $resource ) {
		// Bust PHP's stats cache for the resource file to ensure we get the latest timestamp.
		clearstatcache( true, $resource->path );

		$can_enqueue = 0 === did_action( 'wp_print_scripts' );
		$timestamp   = filemtime( $resource->path );

		if ( $can_enqueue ) {
			wp_enqueue_script( $resource->slug, set_url_scheme( $resource->url ), array(), $timestamp, true );
		} else {
			$timestamp = $timestamp ? $timestamp : ET_CORE_VERSION;

			printf(
				'<script id="%1$s" src="%2$s"></script>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
				esc_attr( $resource->slug ),
				esc_url( set_url_scheme( $resource->url . "?ver={$timestamp}" ) )
			);
		}

		$resource->enqueued = true;
	}

	/**
	 * Enqueues static file for provided style resource.
	 *
	 * @param ET_Core_PageResource $resource
	 */
	protected static function _enqueue_style( $resource ) {
		if ( 'footer' === self::$current_output_location ) {
			return;
		}

		// Bust PHP's stats cache for the resource file to ensure we get the latest timestamp.
		clearstatcache( true, $resource->path );

		$can_enqueue = 0 === did_action( 'wp_print_scripts' );
		// reason: We do this on purpose when a style can't be enqueued.
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$template = '<link rel="stylesheet" id="%1$s" href="%2$s" />';
		// phpcs:enable
		$timestamp = filemtime( $resource->path );

		if ( $can_enqueue ) {
			wp_enqueue_style( $resource->slug, set_url_scheme( $resource->url ), array(), $timestamp );
		} else {
			// reason: this whole file needs to be converted.
			// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase
			$timestamp = $timestamp ?: ET_CORE_VERSION;
			$slug      = esc_attr( $resource->slug );
			$scheme    = esc_url( set_url_scheme( $resource->url . "?ver={$timestamp}" ) );
			$tag       = sprintf( $template, $slug, $scheme );
			$onload    = et_core_esc_previously( self::$_onload );
			// phpcs:enable

			$tag = apply_filters( 'et_core_page_resource_tag', $tag, $slug, $scheme, $onload );

			print( et_core_esc_previously( $tag ) );
		}

		$resource->enqueued = true;
	}

	/**
	 * Returns the next output location.
	 *
	 * @see self::$_output_locations
	 *
	 * @return string
	 */
	protected static function _get_next_output_location() {
		$current_index = array_search( self::$current_output_location, self::$_output_locations, true );

		if ( false === $current_index || ! is_int( $current_index ) ) {
			ET_Core_Logger::error( '$current_output_location is invalid!' );
		}

		$current_index += 1;

		return self::$_output_locations[ $current_index ];
	}

	/**
	 * Creates static resource files for an output location if needed.
	 *
	 * @param string $location {@link self::$_output_locations}.
	 */
	protected static function _maybe_create_static_resources( $location ) {
		self::$current_output_location = $location;

		// Disable for footer inside builder if page uses Theme Builder Editor to avoid conflict with critical CSS.
		if ( 'footer' === $location && et_core_is_fb_enabled() && et_fb_is_theme_builder_used_on_page() ) {
			return false;
		}

		$sorted_resources = self::get_resources_by_output_location( $location );

		foreach ( $sorted_resources as $priority => $resources ) {
			foreach ( $resources as $slug => $resource ) {
				// Mirror StaticCSS paginated-loop inline behavior to avoid cache poisoning
				// where paginated-specific CSS overwrites base page CSS files.
				if ( 'style' === $resource->type && self::should_force_inline_for_paginated_request() ) {
					$resource->forced_inline = true;
				}

				if ( $resource->write_file_location !== $location ) {
					// This resource's static file needs to be generated later on.
					self::_assign_output_location( $resource->write_file_location, $resource );
					continue;
				}

				if ( ! self::$_can_write ) {
					// The reason we don't simply check this before looping through resources and
					// bail if it fails is because we need to perform the output location assignment
					// in the previous conditional regardless (otherwise builder styles will break).
					continue;
				}

				if ( $resource->forced_inline || $resource->has_file() ) {
					continue;
				}

				$data = $resource->get_data( 'file' );

				if ( empty( $data ) && 'footer' !== $location ) {
					// This resource doesn't have any data yet so we'll assign it to the next output location.
					$next_location = self::_get_next_output_location();

					$resource->set_output_location( $next_location );

					continue;
				}

				$force_write = apply_filters( 'et_core_page_resource_force_write', false, $resource );

				if ( ! $force_write && empty( $data ) ) {
					continue;
				}

				// Make sure directory exists.
				if ( ! self::$data_utils->ensure_directory_exists( $resource->base_dir ) ) {
					// Directory creation failed. Mark resource for inline output as fallback.
					$resource->forced_inline = true;
					self::$_can_write        = false;
					// Continue processing other resources, but mark this one for inline output.
					continue;
				}

				// Try to create a temporary directory which we'll use as a pseudo file lock.
				if ( self::$wpfs->mkdir( $resource->temp_dir, 0755 ) ) {
					self::$_temp_dirs[] = $resource->temp_dir;

				// Make sure another request doesn't delete our temp directory.
				$lock_file = $resource->temp_dir . '/' . self::$_lock_file;
				self::$wpfs->put_contents( $lock_file, '' );

				// Create the static resource file.
				if ( ! self::$wpfs->put_contents( $resource->path, $data, 0644 ) ) {
					// File write failed. Mark resource for inline output as fallback.
					$resource->forced_inline = true;
					self::$_can_write        = false;
					// Continue processing other resources, but mark this one for inline output.
					continue;
				} else {
					// Remove the temporary directory.
					self::$wpfs->delete( $resource->temp_dir, true );

					// Remove stale marker if it exists.
					self::remove_stale_marker( $resource->path );

					// Track this directory for stale file cleanup.
					if ( ! empty( $resource->base_dir ) ) {
						self::$_directories_with_new_files[ $resource->base_dir ] = true;
					}

					/**
					 * Fires when the static resource file is created.
					 *
					 * @since 4.10.8
					 *
					 * @param object $resource The resource object.
					 */
					do_action( 'et_core_static_file_created', $resource );

					if ( ! defined( 'DONOTCACHEPAGE' ) ) {
						define( 'DONOTCACHEPAGE', true );
					}
				}
				} elseif ( null !== $resource->temp_dir && self::$wpfs->exists( $resource->temp_dir ) ) {
					// The static resource file is currently being created by another request.
					continue;
				} else {
					// Failed for some other reason. Mark resource for inline output as fallback.
					$resource->forced_inline = true;
					self::$_can_write        = false;
					// Continue processing other resources, but mark this one for inline output.
					continue;
				}
			}
		}
	}

	/**
	 * Enqueues static files for an output location if available.
	 *
	 * @param string $location {@link self::$_output_locations}.
	 */
	protected static function _maybe_enqueue_static_resources( $location ) {
		$sorted_resources = self::get_resources_by_output_location( $location );

		foreach ( $sorted_resources as $priority => $resources ) {
			foreach ( $resources as $slug => $resource ) {
				if ( 'style' === $resource->type && self::should_force_inline_for_paginated_request() ) {
					continue;
				}

				if ( $resource->disabled ) {
					// Resource is disabled. Remove it from the queue.
					self::_unassign_output_location( $location, $resource );
					continue;
				}

				if ( $resource->forced_inline || ! $resource->url || ! $resource->has_file() ) {
					continue;
				}

				if ( 'style' === $resource->type ) {
					self::_enqueue_style( $resource );
				} elseif ( 'script' === $resource->type ) {
					self::_enqueue_script( $resource );
				}

				if ( $resource->enqueued ) {
					self::_unassign_output_location( $location, $resource );
				}
			}
		}
	}

	/**
	 * Outputs all non-enqueued resources for an output location inline.
	 *
	 * @param string $location {@link self::$_output_locations}.
	 */
	protected static function _maybe_output_inline_resources( $location ) {
		$sorted_resources = self::get_resources_by_output_location( $location );

		foreach ( $sorted_resources as $priority => $resources ) {
			foreach ( $resources as $slug => $resource ) {
				if ( $resource->disabled ) {
					// Resource is disabled. Remove it from the queue.
					self::_unassign_output_location( $location, $resource );
					continue;
				}

				$data = $resource->get_data( 'inline' );

				$same_write_file_location = $resource->write_file_location === $resource->location;

				if ( empty( $data ) && 'footer' !== $location && $same_write_file_location ) {
					// This resource doesn't have any data yet so we'll assign it to the next output location.
					$next_location = self::_get_next_output_location();
					$resource->set_output_location( $next_location );
					continue;
				} elseif ( empty( $data ) ) {
					continue;
				}

				printf(
					'<%1$s id="%2$s">%3$s</%1$s>',
					esc_html( $resource->type ),
					esc_attr( $resource->slug ),
					et_core_esc_previously( wp_strip_all_tags( $data ) )
				);

				if ( $same_write_file_location ) {
					// File wasn't created during this location's callback and it won't be created later
					$resource->inlined = true;
				}
			}
		}
	}

	/**
	 * Registers necessary callbacks.
	 */
	protected static function _register_callbacks() {
		$class = 'ET_Core_PageResource';

		// Output Location: head-early, right after theme styles have been enqueued.
		add_action( 'wp_enqueue_scripts', array( $class, 'head_early_output_cb' ), 11 );

		// Output Location: head, right BEFORE the theme and wp's custom css.
		add_action( 'wp_head', array( $class, 'head_output_cb' ), 99 );

		// Output Location: head-late, right AFTER the theme and wp's custom css.
		add_action( 'wp_head', array( $class, 'head_late_output_cb' ), 103 );

		// Output Location: footer.
		add_action( 'wp_footer', array( $class, 'footer_output_cb' ), 20 );

		// Always delete cached resources for a post upon saving.
		add_action( 'save_post', array( $class, 'save_post_cb' ), 10, 3 );

		add_action( 'admin_init', array( $class, 'maybe_schedule_woocommerce_bulk_edit_cache_clear' ), 999 );

		// Always delete cached resources for theme customizer upon saving.
		add_action( 'customize_save_after', array( $class, 'customize_save_after_cb' ) );

		/*
		 * Always delete dynamic css when saving widgets.
		 * `widget_update_callback` fires on save for any of the present widgets,
		 * `delete_widget` fires on save for any deleted widget.
		 */
		add_filter( 'widget_update_callback', array( $class, 'widget_update_callback_cb' ) );
		add_filter( 'delete_widget', array( $class, 'widget_update_callback_cb' ) );
	}

	/**
	 * Initializes the WPFilesystem class.
	 */
	protected static function _setup_wp_filesystem() {
		// The wpfs instance will always exists at this point because the cache dir class initializes it beforehand
		self::$wpfs = $GLOBALS['wp_filesystem'];
	}

	/**
	 * Unassign a resource from an output location.
	 *
	 * @param string               $location {@link self::$_output_locations}.
	 * @param ET_Core_PageResource $resource
	 */
	protected static function _unassign_output_location( $location, $resource ) {
		unset( self::$_resources_by_location[ $location ][ $resource->priority ][ $resource->slug ] );
	}

	protected static function _validate_property( $property, $value ) {
		$valid_values = array(
			'location' => self::$_output_locations,
			'owner'    => self::$_owners,
			'type'     => self::$_types,
		);

		switch ( $property ) {
			case 'path':
				$value    = et_()->normalize_path( realpath( $value ) );
				$is_valid = et_()->starts_with( $value, et_core_cache_dir()->path );
				break;
			case 'url':
				$base_url = et_core_cache_dir()->url;
				$is_valid = et_()->starts_with( $value, set_url_scheme( $base_url, 'http' ) );
				$is_valid = $is_valid ? $is_valid : et_()->starts_with( $value, set_url_scheme( $base_url, 'https' ) );
				break;
			case 'post_id':
				$is_valid = 'global' === $value || 'all' === $value || is_numeric( $value );
				break;
			default:
				$is_valid = isset( $valid_values[ $property ] ) && in_array( $value, $valid_values[ $property ] );
				break;
		}

		return $is_valid ? $value : '';
	}

	/**
	 * Determine if static CSS should be forced inline for cache-unsafe requests.
	 *
	 * @return bool
	 */
	public static function should_force_inline_for_paginated_request() {
		// PageResource does not know loop settings, so this guard applies to:
		// 1. actual loop pagination requests (loop-* page > 1),
		// 2. post-filter param requests (loop_filter-{loopId}[…]),
		// 3. WordPress auto-update scrape requests.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request inspection for cache-safety behavior.
		if ( ! isset( $_GET ) || ! is_array( $_GET ) ) {
			return false;
		}

		// WordPress auto-update fatal-error scrape requests should never rewrite cache files.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request inspection for cache-safety behavior.
		if ( isset( $_GET['wp_scrape_key'], $_GET['wp_scrape_nonce'] ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request inspection for cache-safety behavior.
		foreach ( $_GET as $param => $value ) {
			if ( is_string( $param ) && 0 === strpos( $param, 'loop-' ) && is_numeric( $value ) && (int) $value > 1 ) {
				return true;
			}
		}

		if ( self::_request_has_active_loop_filter_clauses() ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether the current request includes post-filter params for any loop.
	 *
	 * Duplicates builder post-filter detection for cache-safety only. Any non-empty params in a
	 * `loop_filter-{loopId}` namespace force inline styles, including sort params.
	 *
	 * @since 5.10.0
	 *
	 * @return bool
	 */
	private static function _request_has_active_loop_filter_clauses() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request inspection for cache-safety behavior.
		if ( ! isset( $_GET ) || ! is_array( $_GET ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request inspection for cache-safety behavior.
		foreach ( array_keys( $_GET ) as $namespace_key ) {
			if ( ! is_string( $namespace_key ) || ! str_starts_with( $namespace_key, 'loop_filter-' ) ) {
				continue;
			}

			if ( 'loop_filter-' === $namespace_key ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request inspection for cache-safety behavior.
			if ( self::_request_has_loop_filter_params_in_params( $_GET, $namespace_key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether params include active post-filter clauses for one loop filter namespace.
	 *
	 * Intentionally omits value sanitization; only non-empty presence is checked for cache safety.
	 *
	 * @since 5.10.0
	 *
	 * @param array<string,mixed> $params        Request params (for example `$_GET`).
	 * @param string              $namespace_key Loop filter namespace key (for example `loop_filter-loop-123`).
	 *
	 * @return bool
	 */
	private static function _request_has_loop_filter_params_in_params( array $params, string $namespace_key ) {
		if ( ! isset( $params[ $namespace_key ] ) || ! is_array( $params[ $namespace_key ] ) ) {
			return false;
		}

		return self::_request_has_post_filter_params( $params[ $namespace_key ] );
	}

	/**
	 * Whether submitted loop-filter params include any non-empty values.
	 *
	 * @since 5.10.0
	 *
	 * @param array<int|string,mixed> $filter_params Request params for one loop filter namespace.
	 *
	 * @return bool
	 */
	private static function _request_has_post_filter_params( array $filter_params ) {
		foreach ( $filter_params as $param_key => $param_value ) {
			// PHP casts numeric query-array keys to integers; cast back before skipping relation.
			if ( 'relation' === (string) $param_key ) {
				continue;
			}

			if ( is_array( $param_value ) ) {
				$has_value = ! empty(
					array_filter(
						$param_value,
						static function ( $item ) {
							return '' !== $item && null !== $item;
						}
					)
				);
			} else {
				$has_value = '' !== $param_value && null !== $param_value;
			}

			if ( $has_value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether or not we are able to write to the filesystem.
	 *
	 * @return bool
	 */
	public static function can_write_to_filesystem() {
		return ET_Core_Cache_Directory::instance()->can_write;
	}

	/**
	 * Output Location: footer
	 * {@see 'wp_footer' (20) Allow third-party extensions some room to do what they do}
	 */
	public static function footer_output_cb() {
		self::_maybe_create_static_resources( 'footer' );
		self::_maybe_enqueue_static_resources( 'footer' );
		self::_maybe_output_inline_resources( 'footer' );
	}

	/**
	 * Returns the absolute path to our cache directory.
	 *
	 * @since 4.0.8     Removed `$path_type` param b/c cache directory might not be located under wp-content.
	 * @since 3.0.52
	 *
	 * @return string
	 */
	public static function get_cache_directory() {
		return et_core_cache_dir()->path;
	}

	/**
	 * Returns all current resources.
	 *
	 * @return array {@link self::$_resources}
	 */
	public static function get_resources() {
		return self::$_resources;
	}

	/**
	 * Returns the current resources for the provided output location, sorted by priority.
	 *
	 * @param string $location The desired output location {@see self::$_output_locations}.
	 *
	 * @return array[] {
	 *
	 *     @type ET_Core_PageResource[] $priority {
	 *
	 *         @type ET_Core_PageResource $slug Resource.
	 *         ...
	 *     }
	 *     ...
	 * }
	 */
	public static function get_resources_by_output_location( $location ) {
		return self::$_resources_by_location[ $location ];
	}

	/**
	 * Returns the current resources for the provided scope.
	 *
	 * @param string $scope The desired scope (post|global).
	 *
	 * @return ET_Core_PageResource[]
	 */
	public static function get_resources_by_scope( $scope ) {
		return self::$_resources_by_scope[ $scope ];
	}

	/**
	 * Output Location: head-early
	 * {@see 'wp_enqueue_scripts' (11) Should run right after the theme enqueues its styles.}
	 */
	public static function head_early_output_cb() {
		self::_maybe_create_static_resources( 'head-early' );
		self::_maybe_enqueue_static_resources( 'head-early' );
		self::_maybe_output_inline_resources( 'head-early' );
	}

	/**
	 * Output Location: head
	 * {@see 'wp_head' (99) Must run BEFORE the theme and WP's custom css callbacks.}
	 */
	public static function head_output_cb() {
		self::_maybe_create_static_resources( 'head' );
		self::_maybe_enqueue_static_resources( 'head' );
		self::_maybe_output_inline_resources( 'head' );
	}

	/**
	 * Output Location: head-late
	 * {@see 'wp_head' (103) Must run AFTER the theme and WP's custom css callbacks.}
	 */
	public static function head_late_output_cb() {
		self::_maybe_create_static_resources( 'head-late' );
		self::_maybe_enqueue_static_resources( 'head-late' );
		self::_maybe_output_inline_resources( 'head-late' );
	}

	/**
	 * {@see 'widget_update_callback'}
	 *
	 * @param array $instance Widget settings being saved.
	 */
	public static function widget_update_callback_cb( $instance ) {
		self::remove_static_resources( 'all', 'all', false, 'dynamic' );
		return $instance;
	}

	/**
	 * {@see 'customize_save_after'}
	 *
	 * @param WP_Customize_Manager $manager
	 */
	public static function customize_save_after_cb( $manager ) {
		self::remove_static_resources( 'all', 'all' );
	}

	/**
	 * Detect WooCommerce product bulk edit admin request.
	 *
	 * @since 5.11.0
	 *
	 * @return bool
	 */
	protected static function _is_woocommerce_bulk_product_edit_request() {
		if ( ! is_admin() || ! et_is_woocommerce_plugin_active() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request inspection for cache-safety behavior.
		if ( ! isset( $_REQUEST['post_type'] ) || 'product' !== $_REQUEST['post_type'] ) {
			return false;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only request inspection for cache-safety behavior.
		$is_edit_action = ( isset( $_REQUEST['action'] ) && 'edit' === $_REQUEST['action'] )
			|| ( isset( $_REQUEST['action2'] ) && 'edit' === $_REQUEST['action2'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $is_edit_action ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request inspection for cache-safety behavior.
		if ( empty( $_REQUEST['woocommerce_bulk_edit'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Schedule a single cache clear at shutdown for WooCommerce bulk product edit.
	 *
	 * Mirrors Theme Builder's end-of-save clear in {@see et_theme_builder_api_save()}.
	 *
	 * @since 5.11.0
	 */
	public static function maybe_schedule_woocommerce_bulk_edit_cache_clear() {
		if ( self::$_woocommerce_bulk_edit_cache_clear_scheduled ) {
			return;
		}

		if ( ! self::_is_woocommerce_bulk_product_edit_request() ) {
			return;
		}

		self::$_woocommerce_bulk_edit_cache_clear_scheduled = true;

		add_action( 'shutdown', array( 'ET_Core_PageResource', 'woocommerce_bulk_edit_shutdown_cache_clear_cb' ) );
	}

	/**
	 * Clear all static resources once after WooCommerce bulk product edit.
	 *
	 * @since 5.11.0
	 */
	public static function woocommerce_bulk_edit_shutdown_cache_clear_cb() {
		self::remove_static_resources( 'all', 'all' );
	}

	/**
	 * {@see 'save_post'}
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 * @param bool    $update
	 */
	public static function save_post_cb( $post_id, $post, $update ) {
		// Skip if we already cleared all cache in this request.
		// This prevents creating individual .stale files when a "clear all" operation
		// triggers a save_post hook (e.g., when clearing cache from Theme Options).
		if ( self::$_global_timestamp_updated ) {
			return;
		}

		// WooCommerce bulk product edit saves many products in one request; skip per-product
		// cache clears and defer one global clear to shutdown (Theme Builder uses the same pattern).
		// See https://github.com/elegantthemes/Divi/issues/49033.
		if ( self::_is_woocommerce_bulk_product_edit_request() ) {
			return;
		}

		// In Dynamic CSS, we parse the layout content for generating styles and store it under the `object_id`, so clearing
		// only the layout assets won't update the page style if we made any changes to the layout/global modules etc.
		// Hence, we need to clear all static resources when we update a layout.
		// Also, we should only clear the cache if the layout being saved is a global module/row/section.
		if ( 'et_pb_layout' === $post->post_type ) {
			$taxonomies     = get_taxonomies( array( 'object_type' => array( 'et_pb_layout' ) ) );
			$tax_to_clear   = array( 'scope', 'layout_type' );
			$types_to_clear = array( 'module', 'row', 'section' );

			$scope_terms  = get_the_terms( $post_id, 'scope' );
			$layout_terms = get_the_terms( $post_id, 'layout_type' );

			if ( ! empty( $scope_terms ) && ! empty( $layout_terms ) ) {
				$scope_terms       = wp_list_pluck( $scope_terms, 'slug' );
				$layout_terms      = wp_list_pluck( $layout_terms, 'slug' );
				$is_global         = in_array( 'global', $scope_terms, true );
				$clearable_modules = array_intersect( $types_to_clear, $layout_terms );
				$remove_resource   = $is_global && ! empty( $clearable_modules );

				foreach ( $taxonomies as $taxonomy ) {
					if ( in_array( $taxonomy, $tax_to_clear, true ) && $remove_resource ) {
						$post_id = 'all';
						break;
					}
				}
			}
		}
		/**
		 * Filters the post ID used for cache clearing when a post is saved.
		 *
		 * Allows developers to override the cache clearing behavior for any post type.
		 * This is particularly useful for library layouts that may be rendered programmatically
		 * on other pages, where clearing all caches ensures fresh CSS is served.
		 *
		 * @since 4.21.1
		 *
		 * @param string|int $post_id The post ID to use for cache clearing. Use 'all' to clear all caches.
		 * @param WP_Post    $post    The post object being saved.
		 * @param bool       $update  Whether this is an existing post being updated.
		 */
		$post_id = apply_filters( 'et_core_page_resource_post_id_before_clear', $post_id, $post, $update );

		self::remove_static_resources( $post_id, 'all' );
	}

	/**
	 * Remove static resources for a post, or optionally all resources, if any exist.
	 *
	 * @param string $post_id id of post.
	 * @param string $owner owner of file.
	 * @param bool   $force remove all resources.
	 * @param string $slug file slug.
	 * @param bool   $preserve_vb_files Whether to preserve files containing "-vb-" string. Default false.
	 * @param bool   $delete_files Whether to delete files immediately instead of marking as stale. Default false.
	 */
	public static function remove_static_resources( $post_id, $owner = 'core', $force = false, $slug = 'all', $preserve_vb_files = false, $delete_files = false ) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! wp_doing_cron() && ! et_core_security_check_passed( 'edit_posts' ) ) {
			return;
		}

		if ( ! self::can_write_to_filesystem() ) {
			return;
		}

		if ( ! self::$data_utils ) {
			self::startup();
		}

		self::do_remove_static_resources( $post_id, $owner, $force, $slug, $preserve_vb_files, $delete_files );
	}

	/**
	 * Stale-mark Divi static CSS for all taxonomy archive pages.
	 *
	 * Archive CSS lives under `taxonomy/{taxonomy}/{term_id}/`, not under a post ID folder.
	 * Does not call et_core_clear_wp_cache() — third-party HTML cache for archives is handled
	 * by those plugins' own WordPress hooks; this only invalidates Divi CSS files.
	 *
	 * @since 5.8.0
	 *
	 * @return void
	 */
	public static function remove_all_taxonomy_archive_resources(): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! wp_doing_cron() && ! et_core_security_check_passed( 'edit_posts' ) ) {
			return;
		}

		if ( ! self::can_write_to_filesystem() ) {
			return;
		}

		if ( ! self::$data_utils ) {
			self::startup();
		}

		$cache_dir = self::get_cache_directory();

		$files = array_filter(
			(array) glob( "{$cache_dir}/taxonomy/*/*/et-*" ),
			fn( $f ) => ! str_ends_with( $f, '.stale' ) && is_file( $f )
		);

		self::_mark_files_stale( array_values( $files ) );
	}

	/**
	 * Remove static resources action.
	 *
	 * @param string $post_id id of post.
	 * @param string $owner owner of file.
	 * @param bool   $force remove all resources.
	 * @param string $slug file slug.
	 * @param bool   $preserve_vb_files Whether to preserve files containing "-vb-" string. Default false.
	 * @param bool   $delete_files Whether to delete files immediately instead of marking as stale. Default false.
	 */
	public static function do_remove_static_resources( $post_id, $owner = 'core', $force = false, $slug = 'all', $preserve_vb_files = false, $delete_files = false ) {
		$post_id = self::_validate_property( 'post_id', $post_id );
		$owner   = self::_validate_property( 'owner', $owner );
		$slug    = sanitize_key( $slug );

		if ( '' === $owner || '' === $post_id ) {
			return;
		}

		$cache_dir = self::get_cache_directory();

		// Decide whether to use global timestamp vs individual markers.
		// Use global timestamp for mass operations (faster than creating individual markers).
		$use_global_timestamp = self::_should_use_global_timestamp( $post_id, $owner, $slug );

		if ( $use_global_timestamp ) {
			// Just update global timestamp - instant operation.
			self::_mark_global_cache_cleared( $cache_dir, $delete_files );
		} else {
			// Surgical clear: Create individual .stale markers for matched files.
			$_post_id = 'all' === $post_id ? '*' : $post_id;
			$_owner   = 'all' === $owner ? '*' : $owner;
			$_slug    = 'all' === $slug ? '*' : $slug;

			$files = array_merge(
				// Remove any CSS files missing a parent folder.
				(array) glob( "{$cache_dir}/et-{$_owner}-*" ),
				// Remove CSS files for individual posts or all posts if $post_id set to 'all'.
				(array) glob( "{$cache_dir}/{$_post_id}/et-{$_owner}-{$_slug}*" ),
				// Remove CSS files that contain theme builder template CSS.
				// Multiple directories need to be searched through since * doesn't match / in the glob pattern.
				(array) glob( "{$cache_dir}/*/et-{$_owner}-{$_slug}-*tb-{$_post_id}*" ),
				(array) glob( "{$cache_dir}/*/*/et-{$_owner}-{$_slug}-*tb-{$_post_id}*" ),
				(array) glob( "{$cache_dir}/*/*/*/et-{$_owner}-{$_slug}-*tb-{$_post_id}*" ),
				(array) glob( "{$cache_dir}/*/et-{$_owner}-{$_slug}-*tb-for-{$_post_id}*" ),
				(array) glob( "{$cache_dir}/*/*/et-{$_owner}-{$_slug}-*tb-for-{$_post_id}*" ),
				(array) glob( "{$cache_dir}/*/*/*/et-{$_owner}-{$_slug}-*tb-for-{$_post_id}*" ),
				// WP Templates and Template Parts.
				(array) glob( "{$cache_dir}/*/et-{$_owner}-{$_slug}-*wpe-{$_post_id}*" ),
				(array) glob( "{$cache_dir}/*/*/et-{$_owner}-{$_slug}-*wpe-{$_post_id}*" ),
				(array) glob( "{$cache_dir}/*/*/*/et-{$_owner}-{$_slug}-*wpe-{$_post_id}*" )
			);

			// Only clear archive/author/taxonomy dynamic CSS files when clearing "all" cache.
			// These are archive-level files, not related to individual posts.
			if ( 'all' === $post_id ) {
				$files = array_merge(
					$files,
					// Remove Dynamic CSS files for categories, tags, authors, archives, homepage post feed and search results.
					(array) glob( "{$cache_dir}/taxonomy/*/*/et-{$_owner}-dynamic*" ),
					(array) glob( "{$cache_dir}/author/*/et-{$_owner}-dynamic*" ),
					(array) glob( "{$cache_dir}/archive/et-{$_owner}-dynamic*" ),
					(array) glob( "{$cache_dir}/search/et-{$_owner}-dynamic*" ),
					(array) glob( "{$cache_dir}/notfound/et-{$_owner}-dynamic*" ),
					(array) glob( "{$cache_dir}/home/et-{$_owner}-dynamic*" )
				);
			}

			// Filter out .stale files to prevent creating .stale.stale markers.
			$files = array_filter(
				$files,
				function( $file ) {
					return !str_ends_with( $file, '.stale' );
				}
			);

			// Filter out VB files if preservation is enabled.
			if ( $preserve_vb_files ) {
				$files = array_filter(
					$files,
					function( $file ) {
						return !str_contains( basename( $file ), '-vb-' );
					}
				);
			}

			// Deduplicate file paths to avoid redundant stale marker checks/writes.
			$files = array_values( array_unique( $files ) );

			// Create companion .stale markers or delete files.
			self::_mark_files_stale( $files, $delete_files );
		}

		// Remove empty directories.
		self::$data_utils->remove_empty_directories( $cache_dir );

		// Clear cache managed by 3rd-party cache plugins.
		$post_id = ! empty( $post_id ) && absint( $post_id ) > 0 ? $post_id : '';

		et_core_clear_wp_cache( $post_id );

		// Purge the module features cache.
		if ( class_exists( 'ET_Builder_Module_Features' ) ) {
			if ( ! empty( $post_id ) ) {
				ET_Builder_Module_Features::purge_cache( $post_id );
			} else {
				ET_Builder_Module_Features::purge_cache();
			}
		}

		// Purge the post features cache.
		if ( class_exists( 'ET_Builder_Post_Features' ) ) {
			if ( ! empty( $post_id ) ) {
				ET_Builder_Post_Features::purge_cache( $post_id );
			} else {
				ET_Builder_Post_Features::purge_cache();
			}
		}

		// Purge the google fonts cache.
		if ( empty( $post_id ) && class_exists( 'ET_Builder_Google_Fonts_Feature' ) ) {
			ET_Builder_Google_Fonts_Feature::purge_cache();
		}

		// Purge the dynamic assets cache.
		if ( empty( $post_id ) && class_exists( 'ET_Builder_Dynamic_Assets_Feature' ) ) {
			ET_Builder_Dynamic_Assets_Feature::purge_cache();
		}

		// Clear post meta caches.
		self::clear_post_meta_caches( $post_id );

		// Set our DONOTCACHEPAGE file for the next request.
		self::$data_utils->ensure_directory_exists( $cache_dir );
		self::$wpfs->put_contents( $cache_dir . '/DONOTCACHEPAGE', '' );

		if ( $force ) {
			delete_option( 'et_core_page_resource_remove_all' );
		}

		/**
		 * Fires when the static resources are removed.
		 *
		 * @since 4.21.1
		 *
		 * @param mixed $post_id The post ID.
		 */
		do_action( 'et_core_static_resources_removed', $post_id );
	}

	/**
	 * Clear post meta caches for dynamic assets.
	 *
	 * Clears post meta caches like _divi_dynamic_assets_cached_feature_used for the given post ID(s).
	 * This is used when clearing caches after saving posts or Theme Builder templates.
	 *
	 * @since 5.0.0
	 *
	 * @param string|int|array $post_ids Post ID(s) to clear caches for. Can be:
	 *                                   - A single post ID (int)
	 *                                   - 'all' (string) to clear all posts
	 *                                   - An array of post IDs
	 *                                   - Empty string to clear all posts
	 *
	 * @return void
	 */
	public static function clear_post_meta_caches( $post_ids ) {
		$post_meta_caches = array(
			'et_enqueued_post_fonts',
			'_et_dynamic_cached_shortcodes', // Legacy D4 Dynamic Assets Cache.
			'_et_dynamic_cached_attributes', // Legacy D4 Dynamic Assets Cache.
			'_et_builder_module_features_cache',
			'_divi_dynamic_assets_cached_modules',
			'_divi_dynamic_assets_cached_feature_used',
			'_divi_dynamic_assets_canvases_used',
		);

		// Handle 'all' or empty string.
		if ( 'all' === $post_ids || ( empty( $post_ids ) && ! is_numeric( $post_ids ) ) ) {
			foreach ( $post_meta_caches as $post_meta_cache ) {
				delete_post_meta_by_key( $post_meta_cache );
			}
			return;
		}

		// Handle array of post IDs.
		if ( is_array( $post_ids ) ) {
			foreach ( $post_ids as $post_id ) {
				if ( empty( $post_id ) || ! is_numeric( $post_id ) ) {
					continue;
				}

				foreach ( $post_meta_caches as $post_meta_cache ) {
					delete_post_meta( $post_id, $post_meta_cache );
				}
			}
			return;
		}

		// Handle single post ID.
		if ( ! empty( $post_ids ) && is_numeric( $post_ids ) ) {
			foreach ( $post_meta_caches as $post_meta_cache ) {
				delete_post_meta( $post_ids, $post_meta_cache );
			}
		}
	}

	/**
	 * Decide whether to use global timestamp vs individual markers.
	 *
	 * Use global timestamp ONLY for true "clear all" operations.
	 * Use individual markers for specific posts/owners to avoid marking entire site as stale.
	 *
	 * @param string $post_id Post ID or 'all'.
	 * @param string $owner Owner or 'all'.
	 * @param string $slug Slug or 'all'.
	 *
	 * @return bool True to use global timestamp, false to use individual markers.
	 */
	protected static function _should_use_global_timestamp( $post_id, $owner, $slug ) {
		// Only use global timestamp for true "clear all" operations.
		// This ensures we don't mark the entire site as stale when clearing individual posts.
		if ( 'all' === $post_id && 'all' === $owner && 'all' === $slug ) {
			return true;
		}

		// For specific posts or owners, use individual markers.
		// This preserves the selective clearing behavior of the original system.
		return false;
	}

	/**
	 * Check if a file is a valid Divi CSS file that can be deleted.
	 *
	 * @param string $file_path Full path to the file.
	 *
	 * @return bool True if file is a valid Divi CSS file, false otherwise.
	 */
	protected static function _is_valid_divi_css_file( $file_path ) {
		$basename = basename( $file_path );
		return strpos( $basename, 'et-' ) === 0 && ( str_ends_with( $basename, '.css' ) || str_ends_with( $basename, '.min.css' ) );
	}

	/**
	 * Mark global cache as cleared using a timestamp file.
	 *
	 * O(1) operation - just write one timestamp file regardless of cache size.
	 *
	 * @param string $cache_dir Cache directory path.
	 * @param bool   $delete_files Whether to delete files immediately instead of marking as stale. Default false.
	 */
	protected static function _mark_global_cache_cleared( $cache_dir, $delete_files = false ) {
		// Avoid writing the same timestamp multiple times in one request.
		if ( self::$_global_timestamp_updated && ! $delete_files ) {
			return;
		}

		if ( $delete_files ) {
			// Delete all CSS files in the cache directory recursively.
			if ( is_dir( $cache_dir ) ) {
				$iterator = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $cache_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
					RecursiveIteratorIterator::CHILD_FIRST
				);

				foreach ( $iterator as $file ) {
					if ( ! $file->isFile() ) {
						continue;
					}

					// Normalize path before security check.
					$file_path = self::$data_utils->normalize_path( $file->getPathname() );

					// Security check: Only delete files within the cache directory.
					if ( ! et_()->starts_with( $file_path, $cache_dir ) ) {
						continue;
					}

					// Only delete CSS files with Divi naming convention.
					if ( self::_is_valid_divi_css_file( $file_path ) ) {
						self::$wpfs->delete( $file_path );

						// Delete the companion .stale marker if it exists.
						$stale_marker = $file_path . '.stale';
						if ( file_exists( $stale_marker ) ) {
							self::$wpfs->delete( $stale_marker );
						}
					}
				}
			}
		} else {
			$timestamp_file = $cache_dir . '/.cache-cleared-at';
			self::$wpfs->put_contents( $timestamp_file, (string) time() );

			self::$_global_timestamp_updated = true;
		}
	}

	/**
	 * Mark specific files as stale using companion marker files.
	 *
	 * Creates a .stale file next to each CSS file. Only used for surgical clears.
	 *
	 * @param array $files Array of file paths to mark as stale.
	 * @param bool  $delete_files Whether to delete files immediately instead of marking as stale. Default false.
	 */
	protected static function _mark_files_stale( $files, $delete_files = false ) {
		$cache_dir = self::get_cache_directory();

		foreach ( $files as $file ) {
			// Normalize path before security check.
			$file = self::$data_utils->normalize_path( $file );

			// Security check: Only mark files within the cache directory as stale.
			// This prevents accidental modification of files outside the cache directory.
			if ( ! et_()->starts_with( $file, $cache_dir ) ) {
				continue;
			}

			$stale_marker = $file . '.stale';

			// Skip per-file work when marker already exists.
			if ( ! $delete_files && file_exists( $stale_marker ) ) {
				continue;
			}

			if ( is_file( $file ) ) {
				if ( $delete_files ) {
					// Only delete CSS files with Divi naming convention.
					if ( self::_is_valid_divi_css_file( $file ) ) {
						// Delete the file immediately.
						self::$wpfs->delete( $file );

						// Delete the companion .stale marker if it exists.
						if ( file_exists( $stale_marker ) ) {
							self::$wpfs->delete( $stale_marker );
						}
					}
				} else {
					// Create companion .stale marker next to the file.
					self::$wpfs->put_contents( $stale_marker, '' );
				}
			}
		}
	}

	/**
	 * Check if a CSS file is marked as stale and needs regeneration.
	 *
	 * Uses two-layer detection:
	 * 1. Global timestamp check (fastest, catches all mass operations).
	 * 2. Companion .stale marker check (for surgical clears).
	 *
	 * @since 5.0.0
	 *
	 * @param string $file_path File path to check.
	 *
	 * @return bool True if file is marked as stale, false otherwise.
	 */
	public static function is_file_stale( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		// === LAYER 1: Global clear timestamp (check FIRST) ===
		// This catches ALL mass operations instantly without checking individual markers.
		// Note: We check the timestamp file on every call to handle mid-request timestamp updates.
		$cache_dir             = self::get_cache_directory();
		$global_timestamp_file = $cache_dir . '/.cache-cleared-at';

		if ( file_exists( $global_timestamp_file ) ) {
			$global_clear_time = (int) file_get_contents( $global_timestamp_file );

			// If global clear timestamp exists and file is older, it's stale.
			if ( $global_clear_time > 0 && filemtime( $file_path ) < $global_clear_time ) {
				return true;
			}
		}

		// === LAYER 2: Companion .stale marker (only for surgical clears) ===
		// This is only checked if global timestamp didn't catch it.
		if ( file_exists( $file_path . '.stale' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Remove stale marker after file regeneration.
	 *
	 * Called after successfully writing a new CSS file.
	 *
	 * @since 5.0.0
	 *
	 * @param string $file_path File path to remove stale marker from.
	 *
	 * @return void
	 */
	public static function remove_stale_marker( $file_path ) {
		// Remove companion .stale marker if it exists.
		// This handles cleanup for surgical clears.
		$stale_marker = $file_path . '.stale';
		if ( file_exists( $stale_marker ) ) {
			self::$wpfs->delete( $stale_marker );
		}

		// Note: Global timestamp doesn't need cleanup.
		// The newly written file has a newer mtime than the global clear timestamp,
		// so it automatically passes the staleness check.
	}

	public static function wpfs() {
		if ( null !== self::$wpfs ) {
			return self::$wpfs;
		}

		self::startup();

		return self::$wpfs = et_core_cache_dir()->wpfs;
	}

	protected function _initialize_resource() {
		if ( ! self::can_write_to_filesystem() ) {
			$this->base_dir = $this->temp_dir = $this->path = $this->url = ''; //phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found -- Just ignore this since it's an internal use.

			$this->_register_resource();
			return;
		}

		$file_extension = 'style' === $this->type ? '.min.css' : '.min.js';
		$path           = self::get_cache_directory();
		$url            = et_core_cache_dir()->url;

		// Determine the cache directory folder name using consolidated method.
		// This matches DynamicAssets::get_folder_name() behavior and ensures
		// taxonomy pages have their own cache directories, not shared with layout IDs.
		$cache_folder = self::get_cache_folder_name( $this->post_id );

		$file = et_()->path( $path, $cache_folder, $this->filename . $file_extension );

		// Check if file exists and is not marked as stale.
		$file_exists  = file_exists( $file );
		$is_not_stale = $file_exists && ! self::is_file_stale( $file );

		if ( $file_exists && $is_not_stale ) {
			// Static resource file exists and is not stale.
			$this->path     = self::$data_utils->normalize_path( $file );
			$this->base_dir = dirname( $this->path );
			$this->url      = et_()->path( $url, $cache_folder, basename( $this->path ) );

		} else {
			// Static resource file doesn't exist or is marked as stale.
			$url  .= "/{$cache_folder}/{$this->filename}{$file_extension}";
			$path .= "/{$cache_folder}/{$this->filename}{$file_extension}";

			$this->base_dir = self::$data_utils->normalize_path( dirname( $path ) );
			$this->temp_dir = $this->base_dir . "/{$this->slug}~";
			$this->path     = $path;
			$this->url      = $url;
		}

		$this->_register_resource();
	}

	protected function _register_resource() {
		$this->enqueued = false;
		$this->inlined  = false;

		$scope = 'global' === $this->post_id ? 'global' : 'post';

		self::$_resources[ $this->slug ] = $this;

		self::$_resources_by_scope[ $scope ][ $this->slug ] = $this;

		self::_assign_output_location( $this->location, $this );
	}

	public function get_data( $context ) {
		$result = '';

		ksort( $this->data, SORT_NUMERIC );

		/**
		 * Filters the resource's data array.
		 *
		 * @since 3.0.52
		 *
		 * @param array[]              $data {
		 *
		 *     @type string[] $priority Resource data.
		 *     ...
		 * }
		 * @param string               $context  Where the data will be used. Accepts 'inline', 'file'.
		 * @param ET_Core_PageResource $resource The resource instance.
		 */
		$resource_data = apply_filters( 'et_core_page_resource_get_data', $this->data, $context, $this );

		foreach ( $resource_data as $priority => $data_part ) {
			foreach ( $data_part as $data ) {
				$result .= $data;
			}
		}

		return $result;
	}

	/**
	 * Whether or not a static resource exists on the filesystem for this instance.
	 *
	 * Checks both that the file exists and that it's not marked as stale.
	 *
	 * @return bool
	 */
	public function has_file() {
		if ( ! self::$wpfs || empty( $this->path ) || ! self::can_write_to_filesystem() ) {
			return false;
		}

		// File must exist and not be marked as stale.
		if ( ! self::$wpfs->exists( $this->path ) ) {
			return false;
		}

		// Check if file is marked as stale in the single stale marker file.
		if ( self::is_file_stale( $this->path ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Set the resource's data.
	 *
	 * @param string $data
	 * @param int    $priority
	 */
	public function set_data( $data, $priority = 10 ) {
		if ( 'style' === $this->type ) {
			$data = et_core_data_utils_minify_css( $data );
			// Remove empty media queries
			//           @media   only..and  (feature:value)    { }
			$pattern = '/@media\s+([\w\s]+)?\([\w-]+:[\w\d-]+\)\{\s*\}/';
			$data    = preg_replace( $pattern, '', $data );
		}

		$this->data[ $priority ][] = trim( strip_tags( str_replace( '\n', '', $data ) ) );
	}

	public function set_output_location( $location ) {
		if ( ! self::_validate_property( 'location', $location ) ) {
			return;
		}

		$current_location = $this->location;

		self::_unassign_output_location( $current_location, $this );
		self::_assign_output_location( $location, $this );

		$this->location = $location;
	}

	public function unregister_resource() {
		$scope = 'global' === $this->post_id ? 'global' : 'post';

		unset( self::$_resources[ $this->slug ], self::$_resources_by_scope[ $scope ][ $this->slug ] );

		self::_unassign_output_location( $this->location, $this );
	}
}
