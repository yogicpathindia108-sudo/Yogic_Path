<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Fail-open include helper used by classes that load PHP return files
 * from wp-content/imunify-security/.
 *
 * Wraps `include` in a dual-path `\Throwable` / `\Exception` catch so
 * parse errors, type errors, or corrupted state files degrade to a
 * null return instead of aborting the request. The PHP 5.6 branch is
 * kept because this plugin still supports that minimum. When we drop
 * 5.6 the inner `Exception` catch can go.
 *
 * @since 4.0.0
 */
class SafeInclude {

	/**
	 * Include $path and return its value, or null on any error.
	 *
	 * @param string $path Absolute file path.
	 * @return mixed
	 */
	public static function load( $path ) {
		if ( function_exists( 'opcache_invalidate' ) ) {
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@opcache_invalidate( $path, true );
		}
		if ( interface_exists( 'Throwable' ) ) {
			try {
				return include $path;
			} catch ( \Throwable $t ) {
				unset( $t );
				return null;
			}
		}
		try {
			return include $path;
		} catch ( \Exception $e ) {
			unset( $e );
			return null;
		}
	}
}
