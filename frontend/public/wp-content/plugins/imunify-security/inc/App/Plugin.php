<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App;

use CloudLinux\Imunify\App\Api\AjaxHandler;
use CloudLinux\Imunify\App\Bot\OptOutFlag;
use CloudLinux\Imunify\App\Defender\ChangelogWriter;
use CloudLinux\Imunify\App\Defender\Defender;
use CloudLinux\Imunify\App\Defender\DisabledRulesManager;
use CloudLinux\Imunify\App\Defender\IncidentRecorder;
use CloudLinux\Imunify\App\Defender\RateLimiter;
use CloudLinux\Imunify\App\Defender\Request;
use CloudLinux\Imunify\App\Defender\RuleHitTracker;
use CloudLinux\Imunify\App\Defender\RuleProvider;
use CloudLinux\Imunify\App\Integration\WpDefenderScanExclusions;
use CloudLinux\Imunify\App\Views\AdminPage;
use CloudLinux\Imunify\App\Views\BotProtectionWidgetSection;
use CloudLinux\Imunify\App\Views\BotTrafficPage;
use CloudLinux\Imunify\App\Views\Widget;

/**
 * Initial class
 */
class Plugin {
	/**
	 * Self instance
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Container.
	 *
	 * @var array
	 */
	private $container = array();

	/**
	 * Private constructor
	 */
	private function __construct() {
		// Empty constructor - no instantiation here.
	}

	/**
	 * Private clone
	 */
	private function __clone() {
	}

	/**
	 * Get instance
	 *
	 * @return self
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get service
	 *
	 * @param string $key class.
	 *
	 * @return mixed
	 */
	public function get( $key ) {
		if ( array_key_exists( $key, $this->container ) ) {
			return $this->container[ $key ];
		}

		return null;
	}

	/**
	 * Setup container.
	 *
	 * @return void
	 */
	private function coreSetup() {
		$environment                             = new Environment();
		$this->container[ Environment::class ]   = $environment;
		$this->container[ Debug::class ]         = new Debug( $environment );
		$this->container[ DataStore::class ]     = new DataStore( $this->container[ Debug::class ] );
		$this->container[ AccessManager::class ] = new AccessManager();

		// Create ChangelogWriter and DisabledRulesManager for rule management.
		$dataDirectory        = $this->container[ DataStore::class ]->getDataDirectory();
		$changelogWriter      = new ChangelogWriter( $dataDirectory );
		$disabledRulesManager = new DisabledRulesManager(
			$this->container[ DataStore::class ],
			$changelogWriter
		);

		$this->container[ ChangelogWriter::class ]      = $changelogWriter;
		$this->container[ DisabledRulesManager::class ] = $disabledRulesManager;

		// Create AjaxHandler with DisabledRulesManager for local rule management.
		$this->container[ AjaxHandler::class ] = new AjaxHandler(
			$this->container[ DataStore::class ],
			$disabledRulesManager
		);

		$ruleProvider                             = new RuleProvider( $this->container[ Debug::class ], $this->container[ DataStore::class ] );
		$this->container[ RuleProvider::class ]   = $ruleProvider;
		$this->container[ RuleHitTracker::class ] = new RuleHitTracker();
		$rules                                    = $ruleProvider->loadRules();

		if ( ! empty( $rules ) ) {
			$request = new Request();

			// A JSON body that fails closed records a throttled-error payload but
			// cannot emit it (no Debug handle in the Request constructor); send it
			// from here, the same way DataStore::handleError() reports errors.
			if ( $request->isRawBodyFailClosed() ) {
				$this->container[ Debug::class ]->sendThrottledError(
					$request->failClosedMessage(),
					$request->failClosedCode(),
					$request->failClosedContext()
				);
			}

			$rateLimiter      = new RateLimiter();
			$incidentRecorder = new IncidentRecorder( $rateLimiter );
			$defender         = new Defender( $ruleProvider, $incidentRecorder, $this->container[ RuleHitTracker::class ], $disabledRulesManager );
			$defender->processRules( $request );

			$this->container[ RateLimiter::class ]      = $rateLimiter;
			$this->container[ IncidentRecorder::class ] = $incidentRecorder;
			$this->container[ Defender::class ]         = $defender;
		}

		$muPluginDirectory = defined( 'WPMU_PLUGIN_DIR' ) ? (string) WPMU_PLUGIN_DIR : '';

		$this->container[ WpDefenderScanExclusions::class ] = new WpDefenderScanExclusions(
			$dataDirectory,
			IMUNIFY_SECURITY_PATH,
			$muPluginDirectory
		);

		add_action( 'init', array( $this, 'load_translations' ) );
	}

	/**
	 * Additional setup for WP Admin env.
	 *
	 * @return void
	 */
	private function adminSetup() {
		// Phase-1 bot protection section (DEF-42031). Owns state reads and
		// the AJAX handler that writes bot-settings.php when the site owner
		// changes preset or toggles the opt-out. No admin-post.php fallback
		// — the detail pane is JS-only.
		$botProtection  = null;
		$botTrafficPage = null;
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$botProtection                                        = new BotProtectionWidgetSection(
				$this->container[ DataStore::class ],
				(string) WP_CONTENT_DIR,
				$this->container[ AccessManager::class ]
			);
			$this->container[ BotProtectionWidgetSection::class ] = $botProtection;
			add_action(
				'wp_ajax_' . BotProtectionWidgetSection::AJAX_ACTION,
				array( $botProtection, 'handleAjaxSubmission' )
			);

			// Bot Traffic dashboard page. Self-registers its submenu on
			// admin_menu; reuses the AJAX action above for the stats opt-out.
			$botTrafficPage                           = new BotTrafficPage(
				$this->container[ DataStore::class ],
				(string) WP_CONTENT_DIR,
				$this->container[ AccessManager::class ]
			);
			$this->container[ BotTrafficPage::class ] = $botTrafficPage;
		}

		// Create widget first.
		$this->container[ Widget::class ] = new Widget(
			$this->container[ AccessManager::class ],
			$this->container[ DataStore::class ],
			$this->container[ RuleProvider::class ],
			$this->container[ RuleHitTracker::class ],
			$botProtection
		);

		// Instantiate AdminPage.
		$this->container[ AdminPage::class ] = new AdminPage(
			$this->container[ AccessManager::class ],
			$this->container[ DataStore::class ]
		);

		// Create asset loader with widget + bot-traffic page dependencies so it
		// can enqueue each screen's bundle only on that screen's hook.
		$this->container[ AssetLoader::class ] = new AssetLoader(
			$this->container[ Widget::class ],
			$botTrafficPage
		);

		$this->container[ PluginUpdateManager::class ] = new PluginUpdateManager();
	}

	/**
	 * Init plugin.
	 *
	 * @return void
	 */
	public function init() {
		$this->coreSetup();
		if ( is_admin() ) {
			$this->adminSetup();
		}
		if ( $this->isBotProtectionActive() ) {
			$this->registerHoneypotHooks();
		}
	}

	/**
	 * Whether the bot-protection feature is active for the current request.
	 *
	 * The mu-plugin Pipeline respects three gates — the server-level
	 * `ai_bot_protection` flag (from DEF-41872's plugin_config.php), the
	 * site-owner `IMUNIFY_AI_BOT_PROTECTION` wp-config constant, and the
	 * site-owner `bot-settings.php::enabled` flag (DEF-42031 widget). The
	 * honeypot footer link and robots.txt Disallow must respect the same
	 * gates so the trap is only visible when it's actually armed.
	 *
	 * @since 4.0.0
	 *
	 * @return bool
	 */
	public function isBotProtectionActive() {
		if ( defined( 'IMUNIFY_AI_BOT_PROTECTION' )
			&& false === (bool) constant( 'IMUNIFY_AI_BOT_PROTECTION' ) ) {
			return false;
		}
		$dataStore = $this->get( DataStore::class );
		if ( null === $dataStore ) {
			return false;
		}
		if ( ! $dataStore->getPluginConfig()->isAiBotProtectionEnabled() ) {
			return false;
		}
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$opt_out = OptOutFlag::load( (string) WP_CONTENT_DIR );
			if ( ! $opt_out->isEnabled() ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Register the two honeypot front-end hooks.
	 *
	 * Kept as a public method rather than private init-flow wiring so
	 * unit tests can call it in isolation. The caller (init()) gates
	 * this on isBotProtectionActive().
	 *
	 * @since 4.0.0
	 *
	 * @return void
	 */
	public function registerHoneypotHooks() {
		add_action( 'wp_footer', array( $this, 'printHoneypotFooterLink' ) );
		add_filter( 'robots_txt', array( $this, 'filterRobotsTxt' ), 10, 2 );
	}

	/**
	 * WP footer callback — emits the hidden honeypot link.
	 *
	 * @since 4.0.0
	 *
	 * @return void
	 */
	public function printHoneypotFooterLink() {
		// @phpcs:ignore WordPress.Security.EscapeOutput -- href is escaped in footerLinkHtml(); home_url() is trusted site config.
		echo \CloudLinux\Imunify\App\Bot\Honeypot::footerLinkHtml( $this->honeypotBasePath() );
	}

	/**
	 * Robots.txt filter — appends the honeypot Disallow fragment.
	 *
	 * @since 4.0.0
	 *
	 * @param string $output    Current robots.txt content.
	 * @param bool   $is_public Whether the site is set to public.
	 * @return string
	 */
	public function filterRobotsTxt( $output, $is_public ) {
		return $output . "\n" . \CloudLinux\Imunify\App\Bot\Honeypot::robotsTxtFragment( $this->honeypotBasePath() );
	}

	/**
	 * Resolve the honeypot base path from the site's home URL.
	 *
	 * On a subdirectory install (home_url() = "https://example.com/blog/")
	 * the footer link and robots.txt Disallow must point at
	 * "/blog/imunify-bot-check", not the parent-root "/imunify-bot-check".
	 * Protected so the wiring is exercisable with a stubbed base in tests
	 * (home_url() is defined too early for the function mocker to redefine).
	 *
	 * Detection matches only when this base equals what the pre-WordPress
	 * matcher derives from dirname($_SERVER['SCRIPT_NAME']). Any layout where
	 * the two differ is NOT detected: subdirectory multisite (subsites served by
	 * the network-root index.php), a path-rewriting reverse proxy or Apache
	 * alias, or "giving WordPress its own directory". All such cases fail open —
	 * the honeypot simply does not fire and nothing is wrongly blocked.
	 *
	 * @since 4.1.0
	 *
	 * @return string Path component of home_url(); '' on root installs.
	 */
	protected function honeypotBasePath() {
		return \CloudLinux\Imunify\App\Bot\Honeypot::basePathFromUrl( home_url( '/' ) );
	}

	/**
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function load_translations() {
		load_plugin_textdomain( 'imunify-security', false, dirname( plugin_basename( IMUNIFY_SECURITY_FILE_PATH ) ) . '/languages' );
	}
}
