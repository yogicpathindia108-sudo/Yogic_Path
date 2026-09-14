<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Manages the mu-plugin shim file lifecycle.
 *
 * The shim lives at WPMU_PLUGIN_DIR/imunify-security-bots.php and is the
 * only file this plugin installs outside of its own plugin directory.
 * WordPress auto-loads files in wp-content/mu-plugins/ at every request
 * before regular plugins, which is what gives us pre-WordPress
 * interception.
 *
 * Design of the shim itself:
 *   - Short plugin header so WP lists it correctly in Must-Use Plugins.
 *   - Resolves the main-plugin location via WP_PLUGIN_DIR.
 *   - file_exists() guard so a deleted/renamed main plugin falls back
 *     to pass-through instead of fatal.
 *   - try/catch around require_once + MuLoader::run() so any exception
 *     from bot-protection code fails the request open.
 *   - PHP 5.6 compatible — no short-array syntax, no type hints.
 *
 * @since 4.0.0
 */
class MuPluginInstaller {

	/**
	 * Physical filename under WPMU_PLUGIN_DIR.
	 */
	const SHIM_FILENAME = 'imunify-security-bots.php';

	/**
	 * Pre-4.0.1 filename, removed on install/uninstall for sites that upgrade.
	 */
	const LEGACY_SHIM_FILENAME = 'imunify-security-bot.php';

	/**
	 * Verbatim shim content. Kept as a class constant so tests can
	 * assert on the exact bytes written to disk.
	 */
	const SHIM_CONTENT = "<?php\n"
		. "/**\n"
		. " * Plugin Name: Imunify Security Bot Protection (mu-plugin)\n"
		. " * Description: Pre-WordPress bot classification + rate limiting. Installed by the Imunify Security plugin.\n"
		. " * Version: 1.0.0\n"
		. " * Author: CloudLinux\n"
		. " */\n"
		. "\n"
		. "if ( ! defined( 'ABSPATH' ) ) { return; }\n"
		. "\n"
		. "// Load + run the bot-protection pipeline, catching every Throwable\n"
		. "// so PHP 7+ Error types (e.g. class-not-found / type errors) don't\n"
		. "// escape. PHP 5.6 only has Exception, so we branch on whether the\n"
		. "// Throwable interface is available, matching SafeInclude::load().\n"
		. "if ( interface_exists( 'Throwable' ) ) {\n"
		. "    try {\n"
		. "        \$imunify_security_bot_loader = WP_PLUGIN_DIR . '/imunify-security/inc/App/Bot/MuLoader.php';\n"
		. "        if ( file_exists( \$imunify_security_bot_loader ) ) {\n"
		. "            require_once \$imunify_security_bot_loader;\n"
		. "            if ( class_exists( '\\\\CloudLinux\\\\Imunify\\\\App\\\\Bot\\\\MuLoader' ) ) {\n"
		. "                \\CloudLinux\\Imunify\\App\\Bot\\MuLoader::run();\n"
		. "            }\n"
		. "        }\n"
		. "    } catch ( \\Throwable \$t ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch\n"
		. "        // Fail-open: any error in bot protection passes the request through.\n"
		. "    }\n"
		. "} else {\n"
		. "    try {\n"
		. "        \$imunify_security_bot_loader = WP_PLUGIN_DIR . '/imunify-security/inc/App/Bot/MuLoader.php';\n"
		. "        if ( file_exists( \$imunify_security_bot_loader ) ) {\n"
		. "            require_once \$imunify_security_bot_loader;\n"
		. "            if ( class_exists( '\\\\CloudLinux\\\\Imunify\\\\App\\\\Bot\\\\MuLoader' ) ) {\n"
		. "                \\CloudLinux\\Imunify\\App\\Bot\\MuLoader::run();\n"
		. "            }\n"
		. "        }\n"
		. "    } catch ( \\Exception \$e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch\n"
		. "        // Fail-open: any error in bot protection passes the request through.\n"
		. "    }\n"
		. "}\n";

	/**
	 * Absolute path to the wp-content/mu-plugins directory.
	 *
	 * @var string
	 */
	private $muPluginDir;

	/**
	 * Point the installer at a specific mu-plugins directory.
	 *
	 * @param string $mu_plugin_dir Absolute path to wp-content/mu-plugins.
	 */
	public function __construct( $mu_plugin_dir ) {
		$this->muPluginDir = rtrim( (string) $mu_plugin_dir, '/' );
	}

	/**
	 * Write the shim into WPMU_PLUGIN_DIR, creating the dir if allowed.
	 *
	 * @return bool True iff the file exists at the target path after the call.
	 */
	public function install() {
		if ( '' === $this->muPluginDir ) {
			return false;
		}
		if ( ! is_dir( $this->muPluginDir ) ) {
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! @mkdir( $this->muPluginDir, 0755, true ) && ! is_dir( $this->muPluginDir ) ) {
				return false;
			}
		}
		$target = $this->shimPath();
		if ( ! AtomicFileWriter::write( $target, self::SHIM_CONTENT ) ) {
			return false;
		}
		// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@unlink( $this->muPluginDir . '/' . self::LEGACY_SHIM_FILENAME );
		return true;
	}

	/**
	 * Remove the shim file. Idempotent.
	 *
	 * @return bool True iff the file no longer exists after the call.
	 */
	public function uninstall() {
		// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@unlink( $this->muPluginDir . '/' . self::LEGACY_SHIM_FILENAME );
		$target = $this->shimPath();
		if ( ! is_file( $target ) ) {
			return true;
		}
		// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@unlink( $target );
		// Bust PHP's stat cache so the post-delete check sees the new
		// state. Using file_exists() (not is_file()) avoids PHPStan's
		// purity narrowing from the is_file() check above.
		clearstatcache( true, $target );
		return ! file_exists( $target );
	}

	/**
	 * Whether the shim is currently present at its canonical path.
	 *
	 * @return bool
	 */
	public function isInstalled() {
		return is_file( $this->shimPath() );
	}

	/**
	 * Absolute path to the shim file under the configured mu-plugins dir.
	 *
	 * @return string
	 */
	public function shimPath() {
		return $this->muPluginDir . '/' . self::SHIM_FILENAME;
	}
}
