<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender;

use CloudLinux\Imunify\App\DataStore;

/**
 * Abstract base class for file-based data providers with caching.
 *
 * Provides common functionality for classes that load data from PHP files
 * managed by the Imunify agent, with transient caching and file change detection.
 *
 * @since 3.0.0
 */
abstract class CachedFileProvider {

	/**
	 * Transient lifetime in seconds (6 hours).
	 */
	const TRANSIENT_LIFETIME = 21600;

	/**
	 * Data store instance.
	 *
	 * @var DataStore
	 */
	protected $dataStore;

	/**
	 * Get the filename for this provider.
	 *
	 * @return string The filename (e.g., 'rules.php', 'disabled-rules.php').
	 */
	abstract protected function getFileName();

	/**
	 * Get the full file path.
	 *
	 * @return string The full path to the data file.
	 */
	public function getFilePath() {
		return $this->dataStore->getDataDirectory() . DIRECTORY_SEPARATOR . $this->getFileName();
	}

	/**
	 * Get file statistics for the data file.
	 *
	 * @return array|false File stat array with 'mtime' and 'size', or false if file doesn't exist.
	 *
	 * @phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
	 */
	protected function getFileStat() {
		$filePath = $this->getFilePath();
		if ( ! file_exists( $filePath ) ) {
			return false;
		}
		return @stat( $filePath );
	}

	/**
	 * Invalidate OPcache for a file.
	 *
	 * This ensures we get the latest version of the file when it's been
	 * modified by the Imunify agent.
	 *
	 * @param string $filePath The path to the file to invalidate.
	 *
	 * @return bool True if successful, false otherwise.
	 */
	protected function invalidateOpcache( $filePath ) {
		return opcache_invalidate( $filePath, true );
	}

	/**
	 * Load data from file with OPcache invalidation.
	 *
	 * @return mixed The data from the file, or null if not available.
	 */
	protected function loadDataFromFile() {
		if ( ! $this->dataStore->isDataFileAvailable( $this->getFileName() ) ) {
			return null;
		}

		// Invalidate OPcache to ensure we get the latest version.
		if ( function_exists( 'opcache_invalidate' ) ) {
			$filePath = $this->getFilePath();
			$this->invalidateOpcache( $filePath );
		}

		return $this->dataStore->load( $this->getFileName() );
	}

	/**
	 * Force reload data from file.
	 *
	 * Clears caches and reloads data from the source file.
	 *
	 * @return mixed The reloaded data.
	 */
	abstract public function forceReload();
}
