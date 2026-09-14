<?php
/**
 * Dynamic Assets Cache Handler.
 *
 * Handles cache directory operations, file generation, and metadata management.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets\DynamicAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\FrontEnd\Assets\DynamicAssets\State\CacheState;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\DetectionState;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET_Builder_Dynamic_Assets_Feature;
use ET_Core_PageResource;

/**
 * Dynamic Assets Cache class.
 *
 * Handles cache directory operations, file generation, and metadata management.
 *
 * @since ??
 */
class DynamicAssetsCache {

	/**
	 * Dynamic Assets Store.
	 *
	 * @var DynamicAssetsStore
	 */
	private DynamicAssetsStore $_store;

	/**
	 * Constructor.
	 *
	 * @since ??
	 *
	 * @param DynamicAssetsStore $store Dynamic Assets Store instance.
	 */
	public function __construct( DynamicAssetsStore $store ) {
		$this->_store = $store;
	}

	/**
	 * Get cache state from store.
	 *
	 * @since ??
	 *
	 * @return CacheState
	 */
	private function _get_cache(): CacheState {
		return $this->_store->cache();
	}

	/**
	 * Get detection state from store.
	 *
	 * @since ??
	 *
	 * @return DetectionState
	 */
	private function _get_detection_state(): DetectionState {
		return $this->_store->detection();
	}

	/**
	 * Gets folder name for cache directory.
	 *
	 * @since ??
	 *
	 * @return string Folder name.
	 */
	public function get_folder_name(): string {
		// Use consolidated PageResource method for standard folder name determination.
		$cache_state = $this->_get_cache();
		return ET_Core_PageResource::get_cache_folder_name( $cache_state->object_id );
	}

	/**
	 * Generate dynamic assets files.
	 *
	 * @since ??
	 *
	 * @param array  $assets_data Asset data to write to file.
	 * @param string $suffix      Optional suffix for filename.
	 *
	 * @return void
	 */
	public function generate_dynamic_assets_files( array $assets_data = [], string $suffix = '' ): void {
		global $wp_filesystem;

		$cache_state     = $this->_get_cache();
		$detection_state = $this->_get_detection_state();

		$tb_ids                  = '';
		$current_tb_template_ids = $cache_state->tb_template_ids;
		$late_suffix             = '';
		$file_contents           = '';

		if ( $detection_state->need_late_generation ) {
			$late_suffix = '-late';
		}

		if ( ! empty( $current_tb_template_ids ) ) {
			foreach ( $current_tb_template_ids as $key => $value ) {
				$current_tb_template_ids[ $key ] = 'tb-' . $value;
			}
			$tb_ids = '-' . implode( '-', $current_tb_template_ids );
		}

		$ds       = DIRECTORY_SEPARATOR;
		$file_dir = "{$cache_state->cache_dir_path}{$ds}{$cache_state->folder_name}{$ds}";

		// Determine if we should exclude post_id from filename using consolidated PageResource logic.
		// For archive/taxonomy pages, post_id is excluded (folder structure handles page context).
		// For singular pages, post_id is always included for precise cache clearing.
		$maybe_post_id = '';

		// Only include post_id if it shouldn't be excluded (for singular pages).
		if ( is_singular() && ! ET_Core_PageResource::should_exclude_post_id_from_filename( $cache_state->post_id, '', $cache_state->folder_name ) ) {
			$maybe_post_id = '-' . $cache_state->post_id;
		}

		// Ensure directory exists before writing file.
		et_()->ensure_directory_exists( $file_dir );

		$suffix    = empty( $suffix ) ? '' : "-{$suffix}";
		$file_name = "et-{$cache_state->owner}-dynamic{$tb_ids}{$maybe_post_id}{$late_suffix}{$suffix}.css";
		$file_path = et_()->normalize_path( "{$file_dir}{$file_name}" );

		// Check if file exists and is not stale.
		if ( file_exists( $file_path ) && ! ET_Core_PageResource::is_file_stale( $file_path ) ) {
			return;
		}

		// Iterate over all the asset data to generate dynamic asset files.
		foreach ( $assets_data as $file_type => $data ) {
			$file_contents .= implode( "\n", array_unique( $data['content'] ) );
		}

		if ( empty( $file_contents ) ) {
			return;
		}

		$write_success = $wp_filesystem->put_contents( $file_path, $file_contents, FS_CHMOD_FILE );

		// Only remove stale marker if file write succeeded.
		// If write fails, keep the stale marker so file is regenerated on next request.
		if ( false !== $write_success ) {
			ET_Core_PageResource::remove_stale_marker( $file_path );

			// Track this directory for stale file cleanup.
			$directory = dirname( $file_path );
			if ( ! empty( $directory ) ) {
				ET_Core_PageResource::$_directories_with_new_files[ $directory ] = true;
			}
		}
	}

	/**
	 * Check if metadata exists.
	 *
	 * @since ??
	 *
	 * @param string $key Meta key to check.
	 *
	 * @return bool True if metadata exists, false otherwise.
	 */
	public function metadata_exists( string $key ): bool {
		$cache_state = $this->_get_cache();

		if ( is_singular() ) {
			return metadata_exists( 'post', $cache_state->post_id, $key );
		}

		$metadata_manager = ET_Builder_Dynamic_Assets_Feature::instance();
		$metadata_cache   = $metadata_manager->cache_get( $key, $cache_state->folder_name );

		return ! empty( $metadata_cache );
	}

	/**
	 * Get saved metadata.
	 *
	 * @since ??
	 *
	 * @param string $key Meta key to get data for.
	 *
	 * @return array Metadata array.
	 */
	public function metadata_get( string $key ): array {
		$cache_state = $this->_get_cache();

		if ( is_singular() ) {
			return metadata_exists( 'post', $cache_state->post_id, $key )
				? get_post_meta( $cache_state->post_id, $key, true )
				: [];
		}

		$metadata_manager = ET_Builder_Dynamic_Assets_Feature::instance();

		return $metadata_manager->cache_get( $key, $cache_state->folder_name ) ?? [];
	}

	/**
	 * Set metadata.
	 *
	 * @since ??
	 *
	 * @param string $key   Meta key to set data for.
	 * @param array  $value The data to be set.
	 *
	 * @return void
	 */
	public function metadata_set( string $key, array $value ): void {
		$cache_state = $this->_get_cache();

		if ( is_singular() ) {
			update_post_meta( $cache_state->post_id, $key, $value );

			return;
		}

		$metadata_manager = ET_Builder_Dynamic_Assets_Feature::instance();

		$metadata_manager->cache_set( $key, $value, $cache_state->folder_name );
	}
}
