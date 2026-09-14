<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Write-temp-then-rename helper shared by MuPluginInstaller and BotSettingsWriter.
 *
 * @since 4.0.1
 */
class AtomicFileWriter {

	/**
	 * Write $body to $target via a temp file + rename so concurrent
	 * readers never see a partially-written file.
	 *
	 * @param string   $target Absolute destination path.
	 * @param string   $body   File bytes.
	 * @param int|null $mode   Optional chmod applied to the temp file
	 *                         before rename (e.g. 0440). Null skips.
	 * @return bool True iff the file exists at $target after the call.
	 */
	public static function write( $target, $body, $mode = null ) {
		$tmp = $target . '.tmp-' . getmypid();

		// @phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen,WordPress.PHP.NoSilencedErrors.Discouraged
		$handle = @fopen( $tmp, 'w' );
		if ( false === $handle ) {
			return false;
		}
		// @phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite
		$bytes = fwrite( $handle, $body );
		// @phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		fclose( $handle );

		if ( false === $bytes || strlen( $body ) !== $bytes ) {
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $tmp );
			return false;
		}

		if ( null !== $mode ) {
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@chmod( $tmp, $mode );
		}

		if ( ! rename( $tmp, $target ) ) {
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $tmp );
			return false;
		}

		if ( function_exists( 'opcache_invalidate' ) ) {
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@opcache_invalidate( $target, true );
		}

		clearstatcache( true, $target );
		return is_file( $target );
	}

	/**
	 * Write .htaccess, index.php, and index.html into $dir to prevent direct
	 * web access and directory listing. Skips any file that already exists.
	 * No-op when $dir does not exist.
	 *
	 * @param string $dir Absolute path to the directory to protect.
	 */
	public static function ensureDirectoryProtection( $dir ) {
		$dir = rtrim( (string) $dir, '/' );
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$files = array(
			'.htaccess'  => "DirectoryIndex index.php index.html\ndeny from all\n",
			'index.php'  => "<?php\n// This file is intentionally blank.\n",
			'index.html' => "<!-- This file is intentionally blank. -->\n",
		);
		foreach ( $files as $filename => $content ) {
			$path = $dir . '/' . $filename;
			if ( ! file_exists( $path ) ) {
				// @phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents,WordPress.PHP.NoSilencedErrors.Discouraged
				@file_put_contents( $path, $content );
			}
		}
	}
}
