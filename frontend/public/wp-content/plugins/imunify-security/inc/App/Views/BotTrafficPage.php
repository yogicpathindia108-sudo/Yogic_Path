<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Views;

use CloudLinux\Imunify\App\Bot\BotIpStats;
use CloudLinux\Imunify\App\Bot\BotTrafficStats;
use CloudLinux\Imunify\App\Bot\BotTrafficSummary;
use CloudLinux\Imunify\App\Bot\Category;
use CloudLinux\Imunify\App\Bot\HourlyIpStatsStorage;
use CloudLinux\Imunify\App\Bot\HourlyStatsStorage;
use CloudLinux\Imunify\App\Bot\OptOutFlag;
use CloudLinux\Imunify\App\Bot\Preset;
use CloudLinux\Imunify\App\Bot\RateLimitDecision;
use CloudLinux\Imunify\App\AccessManager;
use CloudLinux\Imunify\App\DataStore;

/**
 * The "Bot Traffic" wp-admin page — the plugin's first real submenu, under the
 * Imunify Security top-level menu.
 *
 * Self-contained and PHP-rendered: all figures are aggregated server-side at
 * render time from the durable hourly rollup ({@see HourlyStatsStorage}) plus
 * the per-IP detail ({@see HourlyIpStatsStorage}), and embedded in the page.
 * The KPI tiles, "What happened" notes, the classification bars, and the
 * top-bots table are plain HTML; the classification and top-bots accordions
 * reveal server-rendered panels (down to the per-IP drill-down). Only the
 * stacked 24h timeline is drawn client-side by Vanilla JS from the embedded
 * JSON, so the page degrades gracefully without JS and needs no read-time
 * AJAX. The only AJAX is the stats opt-out toggle, which reuses the
 * {@see BotProtectionWidgetSection} action + nonce.
 *
 * @since 4.1.0
 */
class BotTrafficPage {

	const PARENT_SLUG     = 'imunify-security';
	const PAGE_SLUG       = 'imunify-bot-traffic';
	const RETENTION_HOURS = HourlyStatsStorage::RETENTION_HOURS;
	const DATA_ELEMENT_ID = 'imunify-bot-traffic-data';

	/**
	 * IPs shown per bot in the drill-down (the rest fold into a "+ N more" line).
	 */
	const TOP_IPS_PER_BOT = 10;

	/**
	 * DataStore for the server-level feature gate and preset resolution.
	 *
	 * @var DataStore
	 */
	private $dataStore;

	/**
	 * Absolute path to wp-content.
	 *
	 * @var string
	 */
	private $wpContentDir;

	/**
	 * Admin-capability gate.
	 *
	 * @var AccessManager
	 */
	private $accessManager;

	/**
	 * Menu hook suffix returned by add_submenu_page(), used by the asset
	 * loader to enqueue this page's bundle only on this screen. Null until
	 * the menu is registered.
	 *
	 * @var string|null
	 */
	private $hookSuffix = null;

	/**
	 * Wire the page to its dependencies and register the submenu.
	 *
	 * @param DataStore          $dataStore      Server-level gate + preset source.
	 * @param string             $wp_content_dir Absolute path to wp-content.
	 * @param AccessManager|null $access_manager Admin-capability gate; defaults to a new one.
	 */
	public function __construct( DataStore $dataStore, $wp_content_dir, AccessManager $access_manager = null ) {
		$this->dataStore     = $dataStore;
		$this->wpContentDir  = rtrim( (string) $wp_content_dir, '/' );
		$this->accessManager = null !== $access_manager ? $access_manager : new AccessManager();
		// Priority 11 (after AdminPage's default-10 add_menu_page): a submenu
		// can only attach once its parent top-level menu exists, and this page
		// is constructed before AdminPage in the plugin bootstrap.
		add_action( 'admin_menu', array( $this, 'addSubmenu' ), 11 );
	}

	/**
	 * Register the Bot Traffic submenu under the Imunify Security menu.
	 *
	 * Hidden when the hosting provider has turned the whole bot-protection
	 * feature off (server-level gate), matching how the dashboard widget's
	 * Bot Protection row hides itself in that state — the page would only ever
	 * show empty data there. Site-owner-level disables still show the page.
	 *
	 * @return void
	 */
	public function addSubmenu() {
		if ( ! $this->dataStore->isDataAvailable() ) {
			return;
		}
		if ( ! $this->dataStore->getPluginConfig()->isAiBotProtectionEnabled() ) {
			return;
		}
		$this->hookSuffix = add_submenu_page(
			self::PARENT_SLUG,
			esc_html__( 'Bot Traffic', 'imunify-security' ),
			esc_html__( 'Bot Traffic', 'imunify-security' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'renderPage' )
		);
	}

	/**
	 * The menu hook suffix for this page (or null before registration).
	 *
	 * @return string|null
	 */
	public function hookSuffix() {
		return $this->hookSuffix;
	}

	/**
	 * Build the view-model for the page. Exposed so tests can assert on the
	 * computed figures without parsing HTML.
	 *
	 * @param int|null $now Unix timestamp; defaults to time(). Injectable for tests.
	 * @return array
	 */
	public function computeState( $now = null ) {
		$now           = null === $now ? time() : (int) $now;
		$opt_out       = OptOutFlag::load( $this->wpContentDir );
		$stats_enabled = $opt_out->isStatsEnabled();
		$storage       = $this->storage();

		$state = array(
			'stats_enabled'      => $stats_enabled,
			'protection_active'  => $this->isProtectionActive( $opt_out ),
			'storage_healthy'    => null !== $storage,
			'storage_engine'     => null,
			'preset'             => Preset::resolve( $opt_out, $this->dataStore->getPluginConfig() ),
			'window_hours'       => self::RETENTION_HOURS,
			'can_edit'           => $this->accessManager->isUserAdmin(),
			'has_data'           => false,
			'total_requests'     => 0,
			'counters'           => array(
				RateLimitDecision::ACTION_ALLOW      => 0,
				RateLimitDecision::ACTION_RATE_LIMIT => 0,
				RateLimitDecision::ACTION_BLOCK      => 0,
			),
			'category_breakdown' => array(),
			'timeline'           => $this->fillTimeline( array(), $now ),
			'top_bots'           => array(),
			'summaries'          => array(),
			'human_visitors'     => 0,
			'bot_requests'       => 0,
			'ip_by_bot'          => array(),
			'ip_by_category'     => array(),
			'bots_by_category'   => array(),
		);

		// No data section when the owner opted out or the DB is unreachable.
		if ( ! $stats_enabled || null === $storage ) {
			return $state;
		}

		$cutoff = HourlyStatsStorage::hourBucket( $now ) - ( self::RETENTION_HOURS - 1 );
		$stats  = new BotTrafficStats( $storage->fetchSince( $cutoff ) );

		$state['storage_engine']     = $storage->tableEngine();
		$state['total_requests']     = $stats->totalRequests();
		$state['has_data']           = $state['total_requests'] > 0;
		$state['counters']           = $stats->verdictCounters();
		$state['category_breakdown'] = $stats->categoryBreakdown();
		$state['timeline']           = $this->fillTimeline( $stats->timeline(), $now );
		$state['top_bots']           = $stats->topBots();
		$state['bots_by_category']   = $stats->botsByCategory();
		$state['summaries']          = BotTrafficSummary::forTopBots( $state['top_bots'] );

		// Split human out of the bot verdict tiles. Humans are recorded in the
		// rollup (one aggregate row per hour, always ALLOW) to power "Human
		// visitors", but the Allowed / Rate-limited / Blocked tiles describe bot
		// dispositions, so the human allows are subtracted back out.
		$human                   = isset( $state['category_breakdown'][ Category::HUMAN ] )
			? (int) $state['category_breakdown'][ Category::HUMAN ]
			: 0;
		$state['human_visitors'] = $human;
		$state['bot_requests']   = max( 0, $state['total_requests'] - $human );
		$state['counters'][ RateLimitDecision::ACTION_ALLOW ] = max(
			0,
			(int) $state['counters'][ RateLimitDecision::ACTION_ALLOW ] - $human
		);

		// Per-IP drill-down data, embedded in the page (no read-time AJAX):
		// top IPs per named bot, keyed by category+bot for O(1) render lookup.
		$ip_storage = $this->ipStorage();
		if ( null !== $ip_storage ) {
			$ip_stats                = new BotIpStats( $ip_storage->fetchSince( $cutoff ) );
			$state['ip_by_bot']      = $this->indexIpsByBotCategory( $ip_stats->byBotCategory( self::TOP_IPS_PER_BOT ) );
			$state['ip_by_category'] = $this->indexIpsByCategory( $ip_stats->byCategory( self::TOP_IPS_PER_BOT ) );
		}

		return $state;
	}

	/**
	 * Whether bot protection is actually running — the same condition that
	 * gates the mu-plugin pipeline (wp-config constant, host feature gate, and
	 * the site-owner flag). When false the pipeline records nothing, so the
	 * dashboard must not present its figures as live collection.
	 *
	 * @param OptOutFlag $opt_out Loaded site-owner flags.
	 * @return bool
	 */
	private function isProtectionActive( OptOutFlag $opt_out ) {
		if ( defined( 'IMUNIFY_AI_BOT_PROTECTION' ) && false === (bool) constant( 'IMUNIFY_AI_BOT_PROTECTION' ) ) {
			return false;
		}
		return $this->dataStore->getPluginConfig()->isAiBotProtectionEnabled()
			&& $opt_out->isEnabled();
	}

	/**
	 * Per-IP storage handle, or null when no DB is available.
	 *
	 * @return HourlyIpStatsStorage|null
	 */
	private function ipStorage() {
		return HourlyIpStatsStorage::forGlobalWpdb();
	}

	/**
	 * Key {@see BotIpStats::byBotCategory()} groups by (bot, category) for render
	 * lookup, matching the per-(bot,category) rows in the top-bots table and the
	 * classification accordion, so each row drills to only its category's IPs.
	 *
	 * @param array $groups byBotCategory() output.
	 * @return array
	 */
	private function indexIpsByBotCategory( $groups ) {
		$out = array();
		foreach ( $groups as $group ) {
			$out[ self::ipDrillKey( $group['bot'], $group['category'] ) ] = $group;
		}
		return $out;
	}

	/**
	 * Composite lookup key pairing a bot slug with a category, so the per-IP
	 * drill-down for one (bot, category) row never collides with the same slug
	 * in another category.
	 *
	 * @param string $bot      Bot slug.
	 * @param string $category Category slug.
	 * @return string
	 */
	private static function ipDrillKey( $bot, $category ) {
		return (string) $bot . "\0" . (string) $category;
	}

	/**
	 * Per-IP drill-down for a (bot, category) row, or null when it carried no
	 * IP detail. Shared by the top-bots table and the classification accordion.
	 *
	 * @param array $ip_by_bot Output of {@see indexIpsByBotCategory()}.
	 * @param array $bot       A bot entry from {@see BotTrafficStats::topBots()}.
	 * @return array|null
	 */
	private static function botDetail( $ip_by_bot, $bot ) {
		$key = self::ipDrillKey(
			isset( $bot['bot'] ) ? $bot['bot'] : '',
			isset( $bot['category'] ) ? $bot['category'] : ''
		);
		return isset( $ip_by_bot[ $key ] ) ? $ip_by_bot[ $key ] : null;
	}

	/**
	 * Key {@see BotIpStats::byCategory()} groups by category for render lookup,
	 * so a category with no named bots (UNKNOWN_AUTOMATED, honeypot MALICIOUS_BOT)
	 * can expand straight to its IPs in the classification accordion.
	 *
	 * @param array $groups byCategory() output.
	 * @return array
	 */
	private function indexIpsByCategory( $groups ) {
		$out = array();
		foreach ( $groups as $group ) {
			$out[ $group['category'] ] = $group;
		}
		return $out;
	}

	/**
	 * Render the page (menu callback).
	 *
	 * @return void
	 */
	public function renderPage() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() escapes internally.
		echo $this->renderHtml( $this->computeState() );
	}

	/**
	 * Produce the page HTML for a given view-model. Separated from renderPage()
	 * so it can be asserted on in tests.
	 *
	 * @param array $state Output of {@see computeState()}.
	 * @return string
	 */
	public function renderHtml( $state ) {
		$html  = '<div class="wrap imunify-security__bot-traffic">';
		$html .= '<h1 class="imunify-security__bot-traffic-title">'
			. esc_html__( 'Bot Traffic', 'imunify-security' ) . '</h1>';
		$html .= '<p class="imunify-security__bot-traffic-subtitle">'
			. esc_html__( 'Automated traffic Imunify Security saw on this site — classified, counted, and acted on.', 'imunify-security' )
			. '</p>';

		if ( empty( $state['storage_healthy'] ) ) {
			$html .= $this->renderNotice(
				__( 'Bot-traffic statistics storage is currently unavailable. Protection is unaffected.', 'imunify-security' )
			);
			$html .= '</div>';
			return $html;
		}

		$html .= $this->renderStatusPanel( $state );

		if ( empty( $state['stats_enabled'] ) ) {
			$html .= $this->renderDisabledState();
		} else {
			// Always render the full skeleton; each section falls back to its
			// own empty state, so the page reads as a dashboard even with no
			// traffic yet (or with human visits but no bots).
			$html .= $this->renderKpis( $state );
			$html .= $this->renderTimeline( $state );
			$html .= $this->renderWhatHappened( $state );
			$html .= $this->renderClassification( $state );
			$html .= $this->renderTopBots( $state['top_bots'], $state['ip_by_bot'] );
		}

		$html .= $this->renderOptOutFooter( $state );
		$html .= $this->renderDataBlock( $state );
		$html .= '</div>';
		return $html;
	}

	/**
	 * Protection-status panel: active preset and the reporting window.
	 *
	 * @param array $state View-model.
	 * @return string
	 */
	private function renderStatusPanel( $state ) {
		$preset   = (string) $state['preset'];
		$stats_on = ! empty( $state['stats_enabled'] );

		$preset_value = esc_html( Preset::label( $preset ) )
			. ' <span class="imunify-security__bot-traffic-status-dot imunify-security__bot-traffic-status-dot--'
			. esc_attr( $preset ) . '"></span>'
			. $this->presetInfo( $preset );

		/* translators: %d: number of hours in the reporting window. */
		$window_value = esc_html( sprintf( __( 'Last %d hours', 'imunify-security' ), (int) $state['window_hours'] ) );

		// "Collecting" means data is actually flowing: the opt-in is on AND
		// protection is running. With protection off the pipeline records
		// nothing, so the card says so rather than implying a live feed.
		$protection_active = ! empty( $state['protection_active'] );
		$collecting        = $stats_on && $protection_active;

		$stats_value = esc_html( $stats_on ? __( 'On', 'imunify-security' ) : __( 'Off', 'imunify-security' ) )
			. ' <span class="imunify-security__bot-traffic-status-pill imunify-security__bot-traffic-status-pill--'
			. ( $collecting ? 'on' : 'off' ) . '">'
			. esc_html( $collecting ? __( 'Collecting', 'imunify-security' ) : __( 'Paused', 'imunify-security' ) )
			. '</span>';

		// The 24h total and the storage-health line only make sense while data
		// is actually being collected; they fold into the window / collection
		// cards. When protection is off the card states that instead.
		$window_sub = '';
		$stats_sub  = '';
		if ( $collecting ) {
			$total      = (int) $state['human_visitors'] + (int) $state['bot_requests'];
			$window_sub = esc_html(
				sprintf(
					/* translators: %s: formatted total request count. */
					__( '%s total requests', 'imunify-security' ),
					number_format_i18n( $total )
				)
			);
			$stats_sub = $this->storageHealthLine( $state );
		} elseif ( $stats_on && ! $protection_active ) {
			$stats_sub = esc_html__( 'Protection is off', 'imunify-security' );
		}

		$html  = '<div class="imunify-security__bot-traffic-status">';
		$html .= $this->statusCard( self::iconSliders(), __( 'Preset', 'imunify-security' ), $preset_value );
		$html .= $this->statusCard( self::iconClock(), __( 'Reporting window', 'imunify-security' ), $window_value, $window_sub );
		$html .= $this->statusCard( self::iconChart(), __( 'Statistics collection', 'imunify-security' ), $stats_value, $stats_sub );
		$html .= '</div>';
		return $html;
	}

	/**
	 * The storage-health sub-line for the collection card: a health dot and the
	 * table's real storage engine. The engine is omitted (rather than assumed)
	 * when it could not be read, so the label never claims an engine it did not
	 * verify.
	 *
	 * @param array $state View-model.
	 * @return string
	 */
	private function storageHealthLine( $state ) {
		$engine = isset( $state['storage_engine'] ) ? (string) $state['storage_engine'] : '';
		if ( '' !== $engine ) {
			$text = sprintf(
				/* translators: %s: database storage engine name, e.g. InnoDB. */
				__( 'Storage healthy · %s', 'imunify-security' ),
				$engine
			);
		} else {
			$text = __( 'Storage healthy', 'imunify-security' );
		}
		return '<span class="imunify-security__bot-traffic-status-health"></span>' . esc_html( $text );
	}

	/**
	 * One status card: an icon tile, an uppercase label and a value. $icon is a
	 * trusted inline SVG constant and $value_html is pre-escaped by the caller
	 * (dynamic parts run through esc_html; the rest is static markup).
	 *
	 * @param string $icon       Inline SVG markup.
	 * @param string $label      Card label.
	 * @param string $value_html Escaped value markup.
	 * @param string $sub_html   Escaped secondary line, or '' to omit it.
	 * @return string
	 */
	private function statusCard( $icon, $label, $value_html, $sub_html = '' ) {
		$html = '<article class="imunify-security__bot-traffic-status-card">'
			. '<span class="imunify-security__bot-traffic-status-icon">' . $icon . '</span>'
			. '<span class="imunify-security__bot-traffic-status-body">'
			. '<span class="imunify-security__bot-traffic-status-label">' . esc_html( $label ) . '</span>'
			. '<span class="imunify-security__bot-traffic-status-value">' . $value_html . '</span>';
		if ( '' !== $sub_html ) {
			$html .= '<span class="imunify-security__bot-traffic-status-sub">' . $sub_html . '</span>';
		}
		$html .= '</span></article>';
		return $html;
	}

	/**
	 * Sliders glyph for the Preset card.
	 *
	 * @return string
	 */
	private static function iconSliders() {
		return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="4" y1="8" x2="20" y2="8"/><line x1="4" y1="16" x2="20" y2="16"/><circle cx="10" cy="8" r="2.6"/><circle cx="15" cy="16" r="2.6"/></svg>';
	}

	/**
	 * Clock glyph for the Reporting-window card.
	 *
	 * @return string
	 */
	private static function iconClock() {
		return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2"/></svg>';
	}

	/**
	 * Bar-chart glyph for the Statistics-collection card.
	 *
	 * @return string
	 */
	private static function iconChart() {
		return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 20V11M12 20V5M19 20v-6"/><path d="M3 20h18"/></svg>';
	}

	/**
	 * Info glyph for the preset rate-limits popover.
	 *
	 * @return string
	 */
	private static function iconInfo() {
		return '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>';
	}

	/**
	 * The preset card's info affordance: an icon that reveals the active
	 * preset's per-category rate limits on hover/focus. Embedded in the page
	 * (no request) and sharing Preset::limitLabel() with the widget so the
	 * wording stays identical across both surfaces.
	 *
	 * @param string $preset Active preset identifier.
	 * @return string
	 */
	private function presetInfo( $preset ) {
		$order = array(
			Category::VERIFIED_SEARCH_ENGINE,
			Category::VERIFIED_AI_CRAWLER,
			Category::VERIFIED_SEO_CRAWLER,
			Category::UNKNOWN_AUTOMATED,
			Category::UNVERIFIED_BOT,
			Category::MALICIOUS_BOT,
		);
		$rows  = '';
		foreach ( $order as $cat ) {
			$rows .= '<span class="imunify-security__bot-traffic-preset-pop-row">'
				. '<span>' . esc_html( Category::label( $cat ) ) . '</span>'
				. '<b>' . esc_html( Preset::limitLabel( $preset, $cat ) ) . '</b>'
				. '</span>';
		}
		return '<span class="imunify-security__bot-traffic-preset-info" tabindex="0" role="button" aria-label="'
			. esc_attr( __( 'Preset rate limits', 'imunify-security' ) ) . '">'
			. self::iconInfo()
			. '<span class="imunify-security__bot-traffic-preset-pop" role="tooltip">'
			. '<span class="imunify-security__bot-traffic-preset-pop-title">'
			. esc_html__( 'Rate limits (requests / minute)', 'imunify-security' ) . '</span>'
			. $rows
			. '</span></span>';
	}

	/**
	 * The KPI row: human visitors, bot requests, then the three bot verdict
	 * tiles (allowed / rate-limited / blocked).
	 *
	 * @param array $state View-model.
	 * @return string
	 */
	private function renderKpis( $state ) {
		$counters = $state['counters'];
		$human    = (int) $state['human_visitors'];
		$bots     = (int) $state['bot_requests'];
		$all      = $human + $bots;
		$cats     = 0;
		foreach ( $state['category_breakdown'] as $cat => $count ) {
			if ( Category::HUMAN !== (string) $cat && (int) $count > 0 ) {
				$cats++;
			}
		}

		/* translators: %d: percentage of all traffic. */
		$of_all = __( '%d%% of all traffic', 'imunify-security' );
		/* translators: %d: percentage of bot traffic. */
		$of_bot = __( '%d%% of bot traffic', 'imunify-security' );
		$tiles  = array(
			array( 'human', __( 'Human visitors', 'imunify-security' ), $human, $this->percentOf( $human, $all, $of_all ) ),
			/* translators: %d: number of bot categories. */
			array( 'bots', __( 'Bot requests', 'imunify-security' ), $bots, sprintf( _n( 'across %d bot category', 'across %d bot categories', $cats, 'imunify-security' ), $cats ) ),
			array( RateLimitDecision::ACTION_ALLOW, __( 'Allowed', 'imunify-security' ), (int) $counters[ RateLimitDecision::ACTION_ALLOW ], $this->percentOf( (int) $counters[ RateLimitDecision::ACTION_ALLOW ], $bots, $of_bot ) ),
			array( RateLimitDecision::ACTION_RATE_LIMIT, __( 'Rate-limited (429)', 'imunify-security' ), (int) $counters[ RateLimitDecision::ACTION_RATE_LIMIT ], $this->percentOf( (int) $counters[ RateLimitDecision::ACTION_RATE_LIMIT ], $bots, $of_bot ) ),
			array( RateLimitDecision::ACTION_BLOCK, __( 'Blocked (403)', 'imunify-security' ), (int) $counters[ RateLimitDecision::ACTION_BLOCK ], $this->percentOf( (int) $counters[ RateLimitDecision::ACTION_BLOCK ], $bots, $of_bot ) ),
		);
		$html = '<div class="imunify-security__bot-traffic-counters">';
		foreach ( $tiles as $tile ) {
			$html .= '<div class="imunify-security__bot-traffic-counter imunify-security__bot-traffic-counter--' . esc_attr( $tile[0] ) . '">';
			$html .= '<span class="imunify-security__bot-traffic-counter-label">'
				. '<span class="imunify-security__bot-traffic-counter-dot"></span>' . esc_html( $tile[1] ) . '</span>';
			$html .= '<span class="imunify-security__bot-traffic-counter-value">' . $this->num( $tile[2] ) . '</span>';
			$html .= '<span class="imunify-security__bot-traffic-counter-sub">' . esc_html( $tile[3] ) . '</span>';
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Format "N% of …" for a KPI sub-caption; 0% when the whole is empty.
	 *
	 * @param int    $part   Numerator.
	 * @param int    $whole  Denominator.
	 * @param string $format printf format taking one %d (already translated).
	 * @return string
	 */
	private function percentOf( $part, $whole, $format ) {
		if ( $whole <= 0 ) {
			return '—';
		}
		return sprintf( $format, (int) round( 100 * $part / $whole ) );
	}

	/**
	 * Escaped, locale-formatted integer — the count shown in a tile, table cell
	 * or bar. Every figure on the page goes through here.
	 *
	 * @param int|string $value Numeric value.
	 * @return string
	 */
	private function num( $value ) {
		return esc_html( number_format_i18n( (int) $value ) );
	}

	/**
	 * Open an accordion row: a toggle <button> when it has a panel to reveal,
	 * else a plain <div>. Pairs with {@see toggleClose()}. $extra_class is added
	 * only in the button case (e.g. the per-IP toggle marker).
	 *
	 * @param bool   $expandable  Whether the row reveals a panel.
	 * @param string $base_class  The row's element class.
	 * @param string $panel_id    id of the panel the toggle controls.
	 * @param string $extra_class Optional extra class for the button.
	 * @return string
	 */
	private function toggleOpen( $expandable, $base_class, $panel_id, $extra_class = '' ) {
		if ( ! $expandable ) {
			return '<div class="' . $base_class . '">';
		}
		return '<button type="button" class="' . trim( $base_class . ' js-bot-traffic-toggle ' . $extra_class )
			. '" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '">';
	}

	/**
	 * Close a row opened with {@see toggleOpen()}.
	 *
	 * @param bool $expandable Whether the row was a toggle.
	 * @return string
	 */
	private function toggleClose( $expandable ) {
		return $expandable ? '</button>' : '</div>';
	}

	/**
	 * The "Bot requests over time" section. The Vanilla JS draws the stacked
	 * SVG and its legend from the embedded data block; falls back to an empty
	 * state when there is no bot traffic to plot.
	 *
	 * @param array $state View-model.
	 * @return string
	 */
	private function renderTimeline( $state ) {
		$label = __( 'Bot requests over time', 'imunify-security' );
		$html  = '<section class="imunify-security__bot-traffic-canvas">';
		$html .= '<h2>' . esc_html( $label ) . '</h2>';
		$html .= '<p class="imunify-security__bot-traffic-cap">'
			. esc_html__( 'Hourly, last 24h · stacked by action taken', 'imunify-security' ) . '</p>';
		if ( (int) $state['bot_requests'] <= 0 ) {
			$html .= $this->renderEmpty(
				__( 'No bot requests in the last 24 hours.', 'imunify-security' ),
				__( 'The timeline plots bot requests only.', 'imunify-security' )
			);
		} else {
			$html .= '<div class="js-bot-traffic-timeline-legend imunify-security__bot-traffic-legend"></div>';
			$html .= '<div class="imunify-security__bot-traffic-chart-scroll">';
			// data-label gives the JS-drawn SVG an accessible name (aria-label).
			$html .= '<div class="js-bot-traffic-timeline-chart" data-chart="timeline" data-label="'
				. esc_attr( $label ) . '"></div>';
			$html .= '</div>';
		}
		$html .= '</section>';
		return $html;
	}

	/**
	 * "What happened" — a human line plus one plain-English line per top bot,
	 * each with a status-coloured dot.
	 *
	 * @param array $state View-model.
	 * @return string
	 */
	private function renderWhatHappened( $state ) {
		$notes      = array();
		$human_line = BotTrafficSummary::forHuman(
			(int) $state['human_visitors'],
			(int) $state['human_visitors'] + (int) $state['bot_requests']
		);
		if ( '' !== $human_line ) {
			$notes[] = array( 'human', $human_line );
		}
		foreach ( $state['top_bots'] as $bot ) {
			$line = BotTrafficSummary::forBot( $bot );
			if ( '' === $line ) {
				continue;
			}
			$notes[] = array( RateLimitDecision::dominantAction( $bot['verdicts'] ), $line );
		}
		if ( empty( $notes ) ) {
			return '';
		}

		$html  = '<section class="imunify-security__bot-traffic-canvas">';
		$html .= '<h2>' . esc_html__( 'What happened', 'imunify-security' ) . '</h2>';
		$html .= '<p class="imunify-security__bot-traffic-cap">'
			. esc_html__( 'A short summary, generated from the aggregates', 'imunify-security' ) . '</p>';
		$html .= '<ul class="imunify-security__bot-traffic-notes">';
		foreach ( $notes as $note ) {
			$html .= '<li class="imunify-security__bot-traffic-note imunify-security__bot-traffic-note--' . esc_attr( $note[0] ) . '">'
				. '<span class="imunify-security__bot-traffic-note-dot"></span>'
				. '<span>' . esc_html( $note[1] ) . '</span></li>';
		}
		$html .= '</ul></section>';
		return $html;
	}

	/**
	 * "By classification" — horizontal category bars for all traffic (Human is
	 * a non-drillable aggregate). Each other category expands to its named bots,
	 * and each bot with per-IP detail expands to its top IPs — a two-level
	 * accordion, server-rendered and revealed client-side (no read-time AJAX).
	 *
	 * @param array $state View-model.
	 * @return string
	 */
	private function renderClassification( $state ) {
		$breakdown = $state['category_breakdown'];
		$html      = '<section class="imunify-security__bot-traffic-canvas">';
		$html     .= '<h2>' . esc_html__( 'By classification', 'imunify-security' ) . '</h2>';
		$html     .= '<p class="imunify-security__bot-traffic-cap">'
			. esc_html__( 'Share of all traffic by category · click a category to reveal its bots, then a bot for its top IPs', 'imunify-security' ) . '</p>';
		if ( empty( $breakdown ) ) {
			return $html
				. $this->renderEmpty( __( 'Nothing to classify yet — no traffic recorded.', 'imunify-security' ) )
				. '</section>';
		}
		$by_cat         = $state['bots_by_category'];
		$ip_by_bot      = $state['ip_by_bot'];
		$ip_by_category = isset( $state['ip_by_category'] ) ? $state['ip_by_category'] : array();
		$max            = 1;
		foreach ( $breakdown as $count ) {
			$max = max( $max, (int) $count );
		}
		$html .= '<div class="imunify-security__bot-traffic-bars">';

		$index = 0;
		foreach ( $breakdown as $category => $count ) {
			$category = (string) $category;
			$bots     = isset( $by_cat[ $category ] ) ? $by_cat[ $category ] : array();
			// A category with named bots expands to them (each drilling to its
			// IPs). One without named bots — UNKNOWN_AUTOMATED, honeypot
			// MALICIOUS_BOT — expands straight to its own IPs instead.
			$cat_ips     = isset( $ip_by_category[ $category ] ) ? $ip_by_category[ $category ] : null;
			$has_bots    = ! empty( $bots );
			$has_cat_ips = ( null !== $cat_ips && ! empty( $cat_ips['ips'] ) );
			$drillable   = $has_bots || $has_cat_ips;
			$width       = max( 2, (int) round( 100 * (int) $count / $max ) );
			$panel_id    = self::DATA_ELEMENT_ID . '-cat-' . $index;
			$dot_class   = 'imunify-security__bot-traffic-cat--' . esc_attr( $category );

			$html .= '<div class="imunify-security__bot-traffic-bar-group">';
			$html .= $this->toggleOpen( $drillable, 'imunify-security__bot-traffic-bar-row', $panel_id );
			$html .= '<span class="imunify-security__bot-traffic-bar-name">'
				. '<span class="imunify-security__bot-traffic-bar-dot ' . $dot_class . '"></span>'
				. '<span class="imunify-security__bot-traffic-bar-name-text">'
				. esc_html( Category::label( $category ) ) . '</span></span>';
			$html .= '<span class="imunify-security__bot-traffic-bar-track">'
				. '<span class="imunify-security__bot-traffic-bar-fill ' . $dot_class . '" style="width:' . (int) $width . '%"></span></span>';
			$html .= '<span class="imunify-security__bot-traffic-bar-val">' . $this->num( $count ) . '</span>';
			$html .= $this->toggleClose( $drillable );

			if ( $drillable && $has_bots ) {
				$html  .= '<div id="' . esc_attr( $panel_id ) . '" class="imunify-security__bot-traffic-cat-bots" hidden>';
				$html  .= '<table class="imunify-security__bot-traffic-bots-table imunify-security__bot-traffic-bots-table--nocat">';
				$html  .= '<thead><tr>'
					. '<th scope="col">' . esc_html__( 'Bot', 'imunify-security' ) . '</th>'
					. '<th scope="col">' . esc_html__( 'Peak / min', 'imunify-security' ) . '</th>'
					. '<th scope="col">' . esc_html__( 'Requests', 'imunify-security' ) . '</th>'
					. '<th scope="col">' . esc_html__( 'Action', 'imunify-security' ) . '</th>'
					. '</tr></thead><tbody>';
				$bindex = 0;
				foreach ( $bots as $bot ) {
					$detail = self::botDetail( $ip_by_bot, $bot );
					$html  .= $this->renderBotRow( $bot, $detail, false, $panel_id . '-bot-' . $bindex );
					$bindex++;
				}
				$html .= '</tbody></table></div>';
			} elseif ( $drillable ) {
				$html .= $this->renderCategoryIps( $cat_ips, $panel_id );
			}
			$html .= '</div>';
			$index++;
		}
		$html .= '</div></section>';
		return $html;
	}

	/**
	 * Top-bots table. Each bot with per-IP detail becomes an accordion: the bot
	 * name is a toggle that reveals its top IPs (server-rendered inline,
	 * revealed client-side — no AJAX).
	 *
	 * @param array $top_bots  Entries from BotTrafficStats::topBots().
	 * @param array $ip_by_bot Per-(bot,category) IP detail, keyed via ipDrillKey().
	 * @return string
	 */
	private function renderTopBots( $top_bots, $ip_by_bot ) {
		$html  = '<section class="imunify-security__bot-traffic-top imunify-security__bot-traffic-canvas">';
		$html .= '<h2>' . esc_html__( 'Top bots', 'imunify-security' ) . '</h2>';
		if ( empty( $top_bots ) ) {
			return $html
				. $this->renderEmpty( __( 'No named bots seen in the last 24 hours.', 'imunify-security' ) )
				. '</section>';
		}
		$html .= '<p class="imunify-security__bot-traffic-cap">'
			. esc_html__( 'By request volume · click a bot to reveal its top IPs', 'imunify-security' ) . '</p>';
		$html .= '<table class="imunify-security__bot-traffic-bots-table imunify-security__bot-traffic-bots-table--cat">';
		$html .= '<thead><tr>'
			. '<th scope="col">' . esc_html__( 'Bot', 'imunify-security' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Category', 'imunify-security' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Peak / min', 'imunify-security' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Requests', 'imunify-security' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Action', 'imunify-security' ) . '</th>'
			. '</tr></thead><tbody>';
		$index = 0;
		foreach ( $top_bots as $bot ) {
			$detail = self::botDetail( $ip_by_bot, $bot );
			$html  .= $this->renderBotRow( $bot, $detail, true, self::DATA_ELEMENT_ID . '-ips-' . $index );
			$index++;
		}
		$html .= '</tbody></table>';
		$html .= '</section>';
		return $html;
	}

	/**
	 * One bot as a table row plus its (hidden) per-IP rows — shared by the Top
	 * bots table and the expanded classification categories. $with_category
	 * adds the Category column (Top bots); classification omits it, since the
	 * parent row already names the category. The IP rows sit in the same table
	 * so their Requests / Action line up under the bot's columns.
	 *
	 * @param array      $bot           Bot entry from {@see BotTrafficStats}.
	 * @param array|null $ip_detail     Per-IP detail ({@see BotIpStats::byBot()}), or null.
	 * @param bool       $with_category Whether to render the Category column.
	 * @param string     $key           Unique id seeding the row's toggle group.
	 * @return string
	 */
	private function renderBotRow( $bot, $ip_detail, $with_category, $key ) {
		$has_ips  = ( null !== $ip_detail && ! empty( $ip_detail['ips'] ) );
		$cat_cell = $with_category
			? '<td>' . $this->catDot( (string) $bot['category'] ) . esc_html( Category::label( (string) $bot['category'] ) ) . '</td>'
			: '';

		$html  = $has_ips
			? '<tr class="imunify-security__bot-traffic-bot-row js-bot-traffic-toggle" role="button" tabindex="0" aria-expanded="false" data-target="' . esc_attr( $key ) . '">'
			: '<tr class="imunify-security__bot-traffic-bot-row">';
		$html .= '<td class="imunify-security__bot-traffic-c-bot">' . esc_html( $bot['bot'] ) . '</td>';
		$html .= $cat_cell;
		$html .= '<td>' . $this->num( $bot['peak_req_min'] ) . '</td>';
		$html .= '<td>' . $this->num( $bot['req_count'] ) . '</td>';
		$html .= '<td>' . $this->actionCell( RateLimitDecision::dominantAction( $bot['verdicts'] ) ) . '</td>';
		$html .= '</tr>';

		if ( ! $has_ips ) {
			return $html;
		}

		$empty_cat = $with_category ? '<td></td>' : '';
		$cols      = $with_category ? 5 : 4;
		foreach ( $ip_detail['ips'] as $ip ) {
			$html .= '<tr class="imunify-security__bot-traffic-ip-row" data-group="' . esc_attr( $key ) . '" hidden>'
				. '<td class="imunify-security__bot-traffic-c-ip">' . esc_html( $ip['ip'] ) . '</td>'
				. $empty_cat . '<td></td>'
				. '<td>' . $this->num( $ip['req_count'] ) . '</td>'
				. '<td>' . $this->actionCell( (string) $ip['verdict'] ) . '</td>'
				. '</tr>';
		}
		$shown = count( $ip_detail['ips'] );
		$total = isset( $ip_detail['total_ips'] ) ? (int) $ip_detail['total_ips'] : $shown;
		if ( $total > $shown ) {
			$more  = $total - $shown;
			$html .= '<tr class="imunify-security__bot-traffic-ip-row imunify-security__bot-traffic-ip-more" data-group="' . esc_attr( $key ) . '" hidden>'
				. '<td colspan="' . (int) $cols . '">'
				/* translators: %s: formatted count of further IP addresses. */
				. esc_html( sprintf( _n( '+ %s more IP address', '+ %s more IP addresses', $more, 'imunify-security' ), number_format_i18n( $more ) ) )
				. '</td></tr>';
		}
		return $html;
	}

	/**
	 * Render a category's top IPs directly, for a category with no named bots
	 * (UNKNOWN_AUTOMATED, honeypot-triggered MALICIOUS_BOT). Sits in the
	 * classification accordion where the per-bot table would be; the IP rows
	 * are visible the moment the category is expanded — there is no intermediate
	 * bot layer to click through.
	 *
	 * @param array  $cat_ips  A {@see BotIpStats::byCategory()} group.
	 * @param string $panel_id Id of the panel the category row toggles open.
	 * @return string
	 */
	private function renderCategoryIps( $cat_ips, $panel_id ) {
		$html  = '<div id="' . esc_attr( $panel_id ) . '" class="imunify-security__bot-traffic-cat-bots" hidden>';
		$html .= '<table class="imunify-security__bot-traffic-bots-table imunify-security__bot-traffic-bots-table--nocat">';
		$html .= '<thead><tr>'
			. '<th scope="col">' . esc_html__( 'IP address', 'imunify-security' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Requests', 'imunify-security' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Action', 'imunify-security' ) . '</th>'
			. '</tr></thead><tbody>';
		foreach ( $cat_ips['ips'] as $ip ) {
			$html .= '<tr class="imunify-security__bot-traffic-ip-row">'
				. '<td class="imunify-security__bot-traffic-c-ip">' . esc_html( $ip['ip'] ) . '</td>'
				. '<td>' . $this->num( $ip['req_count'] ) . '</td>'
				. '<td>' . $this->actionCell( (string) $ip['verdict'] ) . '</td>'
				. '</tr>';
		}
		$shown = count( $cat_ips['ips'] );
		$total = isset( $cat_ips['total_ips'] ) ? (int) $cat_ips['total_ips'] : $shown;
		if ( $total > $shown ) {
			$more  = $total - $shown;
			$html .= '<tr class="imunify-security__bot-traffic-ip-row imunify-security__bot-traffic-ip-more">'
				. '<td colspan="3">'
				/* translators: %s: formatted count of further IP addresses. */
				. esc_html( sprintf( _n( '+ %s more IP address', '+ %s more IP addresses', $more, 'imunify-security' ), number_format_i18n( $more ) ) )
				. '</td></tr>';
		}
		$html .= '</tbody></table></div>';
		return $html;
	}

	/**
	 * A category colour dot for a table cell.
	 *
	 * @param string $category Category constant.
	 * @return string
	 */
	private function catDot( $category ) {
		return '<span class="imunify-security__bot-traffic-dot imunify-security__bot-traffic-cat--' . esc_attr( $category ) . '"></span>';
	}

	/**
	 * An action cell: a verdict colour dot followed by its label.
	 *
	 * @param string $verdict One of the {@see RateLimitDecision} ACTION_* values.
	 * @return string
	 */
	private function actionCell( $verdict ) {
		return '<span class="imunify-security__bot-traffic-dot imunify-security__bot-traffic-act--' . esc_attr( $verdict ) . '"></span>'
			. esc_html( self::verdictLabel( $verdict ) );
	}

	/**
	 * Disabled-state block shown when the owner has opted out of stats.
	 *
	 * @return string
	 */
	private function renderDisabledState() {
		return $this->renderNotice(
			__( 'Bot-traffic statistics collection is turned off. Protection is still active.', 'imunify-security' )
		);
	}

	/**
	 * A friendly per-section empty state — a muted glyph, one line, and an
	 * optional hint. Used when a section has no data to show yet.
	 *
	 * @param string $message Primary line.
	 * @param string $hint    Optional secondary line.
	 * @return string
	 */
	private function renderEmpty( $message, $hint = '' ) {
		$html  = '<div class="imunify-security__bot-traffic-empty">';
		$html .= self::iconEmpty();
		$html .= '<p>' . esc_html( $message ) . '</p>';
		if ( '' !== $hint ) {
			$html .= '<span class="imunify-security__bot-traffic-empty-hint">' . esc_html( $hint ) . '</span>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Muted bar-chart glyph for empty states.
	 *
	 * @return string
	 */
	private static function iconEmpty() {
		return '<svg class="imunify-security__bot-traffic-empty-icon" viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 20V12M12 20V8M19 20v-5"/><path d="M3 20h18"/></svg>';
	}

	/**
	 * The subtle opt-out / resume link at the foot of the page.
	 *
	 * @param array $state View-model.
	 * @return string
	 */
	private function renderOptOutFooter( $state ) {
		if ( empty( $state['can_edit'] ) ) {
			return '';
		}
		$stats_on = ! empty( $state['stats_enabled'] );
		$value    = $stats_on
			? BotProtectionWidgetSection::SUBMIT_STATS_DISABLE
			: BotProtectionWidgetSection::SUBMIT_STATS_ENABLE;
		$label    = $stats_on
			? __( 'Stop collecting bot-traffic statistics', 'imunify-security' )
			: __( 'Resume collecting bot-traffic statistics', 'imunify-security' );

		$html  = '<div class="imunify-security__bot-traffic-optout">';
		$html .= '<form class="js-bot-traffic-optout-form">';
		$html .= '<button type="submit" class="button-link js-bot-traffic-optout-toggle" name="'
			. esc_attr( BotProtectionWidgetSection::SUBMIT_FIELD ) . '" value="' . esc_attr( $value ) . '">'
			. esc_html( $label ) . '</button>';
		$html .= '</form>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * The embedded JSON the charts read. Encoded with JSON_HEX_TAG so the
	 * payload is safe to inline inside a <script> element.
	 *
	 * @param array $state View-model.
	 * @return string
	 */
	private function renderDataBlock( $state ) {
		$payload = array(
			'timeline' => $this->timelinePayload( $state['timeline'] ),
		);
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_TAG makes the payload safe inside a script element.
		$json = wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		if ( false === $json ) {
			$json = '{}';
		}
		return '<script type="application/json" id="' . esc_attr( self::DATA_ELEMENT_ID ) . '">'
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see above.
			. $json . '</script>';
	}

	/**
	 * Timeline as an ordered list of stacked-column slots for the chart. Each
	 * slot carries the three verdict segments plus their total (for the label).
	 *
	 * @param array $timeline Hour bucket => { verdict => count } (24 ascending slots).
	 * @return array
	 */
	private function timelinePayload( $timeline ) {
		// Shift the UTC hour buckets to the site's local wall clock for display.
		// gmt_offset is available on WP 5.0 (our floor); wp_date() would not be.
		$offset = (int) round( (float) get_option( 'gmt_offset', 0 ) * 3600 );
		$out    = array();
		foreach ( $timeline as $hour_bucket => $verdicts ) {
			$allow      = isset( $verdicts[ RateLimitDecision::ACTION_ALLOW ] ) ? (int) $verdicts[ RateLimitDecision::ACTION_ALLOW ] : 0;
			$rate_limit = isset( $verdicts[ RateLimitDecision::ACTION_RATE_LIMIT ] ) ? (int) $verdicts[ RateLimitDecision::ACTION_RATE_LIMIT ] : 0;
			$block      = isset( $verdicts[ RateLimitDecision::ACTION_BLOCK ] ) ? (int) $verdicts[ RateLimitDecision::ACTION_BLOCK ] : 0;
			$total      = $allow + $rate_limit + $block;
			$start_ts   = ( (int) $hour_bucket * 3600 ) + $offset;
			$out[]      = array(
				'label'      => gmdate( 'H:i', $start_ts ),
				'rangeLabel' => gmdate( 'H:i', $start_ts ) . ' – ' . gmdate( 'H:i', $start_ts + 3600 ),
				'allow'      => $allow,
				'rate_limit' => $rate_limit,
				'block'      => $block,
				'count'      => $total,
				'countLabel' => number_format_i18n( $total ),
			);
		}
		return $out;
	}

	/**
	 * Zero-fill a sparse verdict-split timeline into RETENTION_HOURS ascending
	 * hour slots ending at the current hour.
	 *
	 * @param array $sparse Hour bucket => { verdict => count } (may have gaps).
	 * @param int   $now    Reference timestamp.
	 * @return array Hour bucket => { verdict => count }, exactly RETENTION_HOURS entries.
	 */
	private function fillTimeline( $sparse, $now ) {
		$current = HourlyStatsStorage::hourBucket( $now );
		$start   = $current - ( self::RETENTION_HOURS - 1 );
		$empty   = array(
			RateLimitDecision::ACTION_ALLOW      => 0,
			RateLimitDecision::ACTION_RATE_LIMIT => 0,
			RateLimitDecision::ACTION_BLOCK      => 0,
		);
		$out     = array();
		for ( $hour = $start; $hour <= $current; $hour++ ) {
			$out[ $hour ] = ( isset( $sparse[ $hour ] ) && is_array( $sparse[ $hour ] ) ) ? $sparse[ $hour ] : $empty;
		}
		return $out;
	}

	/**
	 * A dismissible-looking (static) notice block.
	 *
	 * @param string $message Notice text.
	 * @return string
	 */
	private function renderNotice( $message ) {
		return '<div class="imunify-security__bot-traffic-notice"><p>' . esc_html( $message ) . '</p></div>';
	}


	/**
	 * Human label for a single verdict.
	 *
	 * @param string $verdict A RateLimitDecision::ACTION_* value.
	 * @return string
	 */
	private static function verdictLabel( $verdict ) {
		if ( RateLimitDecision::ACTION_BLOCK === $verdict ) {
			return __( 'Blocked', 'imunify-security' );
		}
		if ( RateLimitDecision::ACTION_RATE_LIMIT === $verdict ) {
			return __( 'Rate-limited', 'imunify-security' );
		}
		return __( 'Allowed', 'imunify-security' );
	}

	/**
	 * Build the InnoDB rollup storage, or null when no DB handle is available.
	 *
	 * @return HourlyStatsStorage|null
	 */
	private function storage() {
		return HourlyStatsStorage::forGlobalWpdb();
	}
}
