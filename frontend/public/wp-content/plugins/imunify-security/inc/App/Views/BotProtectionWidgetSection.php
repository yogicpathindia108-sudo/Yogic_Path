<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Views;

use CloudLinux\Imunify\App\Bot\BotLifecycle;
use CloudLinux\Imunify\App\Bot\BotSettingsWriter;
use CloudLinux\Imunify\App\Bot\BotTrafficStats;
use CloudLinux\Imunify\App\Bot\Category;
use CloudLinux\Imunify\App\Bot\DailyCounter;
use CloudLinux\Imunify\App\Bot\RateLimitDecision;
use CloudLinux\Imunify\App\Bot\HourlyIpStatsStorage;
use CloudLinux\Imunify\App\Bot\HourlyStatsStorage;
use CloudLinux\Imunify\App\Bot\OptOutFlag;
use CloudLinux\Imunify\App\Bot\Preset;
use CloudLinux\Imunify\App\Bot\DbStorageFactory;
use CloudLinux\Imunify\App\Bot\LoopbackSafetyTest;
use CloudLinux\Imunify\App\Bot\LoopbackStatus;
use CloudLinux\Imunify\App\AccessManager;
use CloudLinux\Imunify\App\DataStore;
use CloudLinux\Imunify\App\Helpers\Documentation;
use CloudLinux\Imunify\App\Model\PluginConfig;

/**
 * Phase-1 bot protection surface on the Imunify Security dashboard widget.
 *
 * Renders a clickable row in the main widget pane plus a slide-in detail
 * pane (same pattern as Malware Protection / WAF incidents). The detail
 * pane carries status, 24h blocked counter, a read-mode preset display with
 * a subtle "Change" link that reveals preset picker + Save, a live limits
 * table keyed by the dropdown, and the "Turn off on this site" / "Turn
 * back on" action.
 *
 * All mutations go through {@see handleAjaxSubmission()}; the handler
 * returns the refreshed row + pane markup so the JS can swap both in place
 * without a page reload. No admin-post.php fallback — JS is required.
 *
 * Read layer:
 *   - {@see PluginConfig} via DataStore — host-level gate.
 *   - {@see OptOutFlag} — site-owner preference + persisted preset.
 *   - {@see DailyCounter} — 24h blocked counter (fail-open to 0).
 *
 * @since 4.0.0
 */
class BotProtectionWidgetSection {

	const AJAX_ACTION          = 'imunify_security_bot_update';
	const NONCE_ACTION         = 'imunify_security_bot_settings';
	const SUBMIT_FIELD         = 'imunify_security_bot_action';
	const SUBMIT_SAVE_PRESET   = 'save_preset';
	const SUBMIT_DISABLE       = 'disable';
	const SUBMIT_ENABLE        = 'enable';
	const SUBMIT_RECHECK       = 'recheck_loopback';
	const SUBMIT_DISMISS       = 'dismiss_loopback';
	const SUBMIT_STATS_ENABLE  = 'stats_enable';
	const SUBMIT_STATS_DISABLE = 'stats_disable';
	const PANE_ID              = 'bot-protection';

	/**
	 * DataStore instance used to read the server-level feature gate.
	 *
	 * @var DataStore
	 */
	private $dataStore;

	/**
	 * Absolute path to wp-content directory.
	 *
	 * @var string
	 */
	private $wpContentDir;

	/**
	 * Access manager used only for the AV monitoring upsell CTA (whether
	 * the current user may upgrade). Optional — null keeps the legacy
	 * 2-arg construction working and simply suppresses the upgrade link.
	 *
	 * @var AccessManager|null
	 */
	private $accessManager;

	/**
	 * Construct the widget section.
	 *
	 * @param DataStore          $dataStore      Needed for the server-level feature gate.
	 * @param string             $wp_content_dir Absolute path to wp-content.
	 * @param AccessManager|null $accessManager  Gates the AV monitoring upsell link;
	 *                                           null suppresses the link.
	 */
	public function __construct( $dataStore, $wp_content_dir, AccessManager $accessManager = null ) {
		$this->dataStore     = $dataStore;
		$this->wpContentDir  = rtrim( (string) $wp_content_dir, '/' );
		$this->accessManager = $accessManager;
	}

	/**
	 * Build the view-model consumed by {@see render()}. Exposed so unit
	 * tests can assert on the computed state without parsing HTML.
	 *
	 * @return array Keys: server_enabled, site_enabled, preset,
	 *               has_explicit_preset, blocked_today, effective_status
	 *               ('active'|'disabled_by_host'|'disabled_by_site'),
	 *               can_edit, limits_by_preset.
	 */
	public function computeState() {
		$plugin_config = $this->dataStore->getPluginConfig();
		$server_on     = $plugin_config->isAiBotProtectionEnabled();

		// Match MuLoader::runWith() and Plugin::isBotProtectionActive():
		// the wp-config constant is a site-admin opt-out. When set to
		// false it behaves like a locked "disabled on this site" — the
		// widget can't override it via a button since wp-config wins,
		// so surface that state and freeze the controls.
		$constant_off = defined( 'IMUNIFY_AI_BOT_PROTECTION' )
			&& false === (bool) constant( 'IMUNIFY_AI_BOT_PROTECTION' );

		$opt_out      = OptOutFlag::load( $this->wpContentDir );
		$file_enabled = $opt_out->isEnabled();
		$site_enabled = $file_enabled && ! $constant_off;

		if ( ! $server_on ) {
			$status = 'disabled_by_host';
		} elseif ( $constant_off ) {
			// wp-config takes precedence over the site-owner flag — this
			// branch fires regardless of what bot-settings.php says. Gets
			// its own status so the tooltip can point at wp-config.php
			// rather than offering controls the admin can't actually use.
			$status = 'disabled_by_wpconfig';
		} elseif ( ! $site_enabled ) {
			$status = 'disabled_by_site';
		} else {
			$status = 'active';
		}

		$blocked_today  = self::safeBlockedCount();
		$can_edit       = $server_on && ! $constant_off && self::currentUserCanEdit();
		$edition_locked = $plugin_config->isImunifyAvEdition();
		$stats_enabled  = $opt_out->isStatsEnabled();
		// Rolled-up traffic tiles only make sense while data is actually being
		// collected (protection active AND the stats opt-in on). Otherwise the
		// pane falls back to the always-on blocked counter.
		//
		// By design the always-on blocked_today (DailyCounter) and the rollup's
		// block tile measure different things: the counter keeps running while
		// stats capture is off, the rollup does not. They therefore agree in
		// normal operation and legitimately diverge only for blocks recorded
		// during a stats opt-out — not a bug to reconcile.
		$traffic = ( 'active' === $status && $stats_enabled ) ? self::safeTrafficSummary() : null;

		// Self-reachability only matters while the feature is live — that is
		// when its wp-cron jobs (bot-data refresh, storage cleanup) are
		// scheduled. Read the recorded status (a non-autoloaded option) only
		// when active; && short-circuits the read otherwise.
		$loopback_warning = ( 'active' === $status ) && ( new LoopbackStatus() )->read()['warning'];

		return array(
			'server_enabled'      => $server_on,
			'site_enabled'        => $site_enabled,
			'preset'              => self::resolveEffectivePreset( $opt_out, $plugin_config ),
			'has_explicit_preset' => $opt_out->hasExplicitPreset(),
			'blocked_today'       => $blocked_today,
			'stats_enabled'       => $stats_enabled,
			'traffic'             => $traffic,
			'effective_status'    => $status,
			// Controls are interactive only when the hosting admin hasn't
			// disabled the feature AND the wp-config constant hasn't
			// locked it off AND the current user can manage plugins.
			'can_edit'            => $can_edit,
			// A detail pane only shows up when it has something useful to
			// do: the feature is live (Active — even read-only viewers see
			// limits + counter) or the current user can flip it back on.
			// Disabled-by-host and wp-config-locked states collapse to a
			// static row + tooltip, since the pane would be read-only with
			// no actionable control.
			'has_pane'            => ( 'active' === $status ) || $can_edit,
			'limits_by_preset'    => self::limitsByPreset(),
			// Loopback self-reachability warning, surfaced as a row badge and
			// a pane callout. False unless the feature is active and a probe
			// recorded a non-OK, non-dismissed result.
			'loopback_warning'    => $loopback_warning,
			// True on an ImunifyAV / ImunifyAV+ edition, where bot blocking
			// is an Imunify360-only capability: the preset is frozen at
			// Monitor (Preset::resolve already returns it) and the preset
			// section renders read-only with an upgrade upsell.
			'edition_locked'      => $edition_locked,
			// Upsell CTA for the locked state. Only meaningful when locked;
			// canUserUpgrade + the upgrade URL come from scan_data.php on
			// this admin path (never on the bot hot path).
			'upsell'              => $this->computeUpsell( $edition_locked ),
		);
	}

	/**
	 * Build the monitoring-upsell CTA shown on a locked (AV) pane. Reads
	 * canUserUpgrade from scan_data.php via AccessManager — done only when
	 * the edition is locked, so a non-AV render never touches scan_data for
	 * this. Degrades to "cannot upgrade" when no AccessManager was injected.
	 *
	 * @param bool $edition_locked Whether the AV lock is in effect.
	 * @return array Keys: 'can_upgrade' (bool), 'url' (string).
	 */
	private function computeUpsell( $edition_locked ) {
		if ( ! $edition_locked || null === $this->accessManager ) {
			return array(
				'can_upgrade' => false,
				'url'         => '',
			);
		}
		$can_upgrade = $this->accessManager->canUserUpgrade( $this->dataStore );
		return array(
			'can_upgrade' => $can_upgrade,
			'url'         => $can_upgrade ? AdminPage::upgradeUrl() : '',
		);
	}

	/**
	 * Render both widget fragments for the given view-model.
	 *
	 * @param array $state Output of {@see computeState()} or an
	 *                     equivalent fixture in tests.
	 * @return array Keys: 'row' (clickable nav-link), 'pane' (slide-in
	 *               detail pane).
	 */
	public function render( $state ) {
		if ( 'disabled_by_host' === $state['effective_status'] ) {
			return array(
				'row'  => '',
				'pane' => '',
			);
		}

		return array(
			'row'  => $this->renderRow( $state ),
			'pane' => $this->renderPane( $state ),
		);
	}

	/**
	 * Convenience: compute + render in one call.
	 *
	 * @return array See {@see render()}.
	 */
	public function view() {
		return $this->render( $this->computeState() );
	}

	/**
	 * Pre-computed display rows for all three presets. Embedded in the
	 * pane so JS can swap the limits table the moment the user picks a
	 * different preset in the dropdown — no AJAX round-trip.
	 *
	 * @return array Map of preset identifier => array of rows. Each row
	 *               is array( 'label' => ..., 'value' => ... ).
	 */
	public static function limitsByPreset() {
		$out = array();
		foreach ( self::presetDisplayOrder() as $preset ) {
			$out[ $preset ] = self::limitsRows( $preset );
		}
		return $out;
	}

	/**
	 * Order presets surface in the widget UI (dropdown, limits tables).
	 * Runs strongest → weakest: Strict, Balanced, Monitor only. Separate
	 * from {@see Preset::all()} because that one's contract is logical
	 * identity, not display.
	 *
	 * @return array
	 */
	private static function presetDisplayOrder() {
		return array( Preset::STRICT, Preset::BALANCED, Preset::MONITOR );
	}

	/**
	 * Build the display rows for a single preset, translating raw
	 * requests-per-minute numbers into the widget's human-readable form
	 * (including the "Block" / "Monitor only" / "No limit" special cases).
	 *
	 * @param string $preset Preset identifier.
	 * @return array
	 */
	private static function limitsRows( $preset ) {
		$order = array(
			Category::VERIFIED_SEARCH_ENGINE => Category::label( Category::VERIFIED_SEARCH_ENGINE ),
			Category::VERIFIED_AI_CRAWLER    => Category::label( Category::VERIFIED_AI_CRAWLER ),
			Category::VERIFIED_SEO_CRAWLER   => Category::label( Category::VERIFIED_SEO_CRAWLER ),
			Category::UNKNOWN_AUTOMATED      => Category::label( Category::UNKNOWN_AUTOMATED ),
			Category::UNVERIFIED_BOT         => Category::label( Category::UNVERIFIED_BOT ),
			Category::MALICIOUS_BOT          => Category::label( Category::MALICIOUS_BOT ),
		);
		$rows  = array();
		foreach ( $order as $cat => $label ) {
			$rows[] = array(
				'label' => $label,
				'value' => Preset::limitLabel( $preset, $cat ),
			);
		}
		return $rows;
	}

	/**
	 * Clickable nav-link row. Lives inside .imunify-security__nav-links
	 * next to Malware Protection and WAF.
	 *
	 * @param array $state View-model.
	 * @return string
	 */
	private function renderRow( $state ) {
		$status    = isset( $state['effective_status'] ) ? $state['effective_status'] : 'active';
		$counter   = (int) $state['blocked_today'];
		$is_active = ( 'active' === $status );
		$has_pane  = ! empty( $state['has_pane'] );

		if ( $is_active ) {
			$label_text = sprintf(
				/* translators: %d: number of bot requests blocked in the last 24 hours. */
				__( 'Bot Protection (%d blocked in 24h)', 'imunify-security' ),
				$counter
			);
		} else {
			$label_text = __( 'Bot Protection', 'imunify-security' );
		}

		// On the main row, "active" broadcasts the currently-active preset
		// rather than a generic "Active" word — gives the user a single
		// glance signal of both state and intensity. Non-active collapses
		// to the short "Disabled" badge.
		$preset      = isset( $state['preset'] ) ? $state['preset'] : Preset::DEFAULT_PRESET;
		$status_word = $is_active
			? self::presetLabel( $preset )
			: __( 'Disabled', 'imunify-security' );
		// Preset-specific modifier lets each preset ship its own color —
		// green for Balanced, orange for Strict, light blue for Monitor.
		$status_mod = $is_active ? 'preset-' . $preset : 'disabled';

		$status_html = '<span class="imunify-security__nav-link-status imunify-security__nav-link-status--'
			. esc_attr( $status_mod ) . '">' . esc_html( $status_word );

		if ( ! $has_pane ) {
			// Dead-end states (disabled-by-host, wp-config-locked) can't
			// navigate into a pane, so the reason ships as a hover tooltip
			// alongside the status word. Re-uses the .js-custom-tooltip
			// pattern shared with the WAF monitoring badge.
			$tooltip      = self::statusDetailedLabel( $status );
			$status_html .= ' <span class="dashicons dashicons-info-outline imunify-security__info-tooltip js-custom-tooltip"'
				. ' data-tooltip="' . esc_attr( $tooltip ) . '"'
				. ' data-tooltip-nowrap="1"'
				. ' aria-label="' . esc_attr( $tooltip ) . '"></span>';
		}
		$status_html .= '</span>';

		if ( ! $has_pane ) {
			$html  = '<div class="imunify-security__nav-link imunify-security__nav-link--static js-bot-protection-row">';
			$html .= '<span class="imunify-security__nav-link-text">' . esc_html( $label_text ) . '</span>';
			$html .= $status_html;
			$html .= '</div>';
			return $html;
		}

		$html  = '<a href="#" class="imunify-security__nav-link js-nav-link js-bot-protection-row" data-pane="' . esc_attr( self::PANE_ID ) . '">';
		$html .= '<span class="imunify-security__nav-link-text">' . esc_html( $label_text ) . '</span>';
		$html .= $status_html;
		if ( ! empty( $state['loopback_warning'] ) ) {
			$tooltip = self::loopbackTooltip();
			$html   .= ' <span class="dashicons dashicons-warning imunify-security__nav-link-warning js-bot-loopback-badge js-custom-tooltip"'
				. ' data-tooltip="' . esc_attr( $tooltip ) . '"'
				. ' data-tooltip-nowrap="1"'
				. ' aria-label="' . esc_attr( $tooltip ) . '"></span>';
		}
		$html .= '<span class="imunify-security__nav-link-arrow dashicons dashicons-arrow-right-alt2"></span>';
		$html .= '</a>';
		return $html;
	}

	/**
	 * Slide-in detail pane. Lives as a sibling of .js-pane-main.
	 *
	 * @param array $state View-model.
	 * @return string
	 */
	private function renderPane( $state ) {
		// Dead-end states don't get a pane at all — the row's tooltip
		// carries the explanation. Keeps the widget DOM minimal and
		// avoids rendering controls the user couldn't act on anyway.
		if ( empty( $state['has_pane'] ) ) {
			return '';
		}

		$status      = isset( $state['effective_status'] ) ? $state['effective_status'] : 'active';
		$counter     = (int) $state['blocked_today'];
		$can_edit    = ! empty( $state['can_edit'] );
		$site_on     = ! empty( $state['site_enabled'] );
		$preset      = isset( $state['preset'] ) ? $state['preset'] : Preset::DEFAULT_PRESET;
		$is_active   = ( 'active' === $status );
		$status_word = self::statusDetailedLabel( $status );
		// The Status row is a plain on/off signal — always green when
		// active. The preset-specific color lives on the Preset value
		// below (renderPresetSection) so the two signals don't collide.
		$status_mod = $is_active ? 'enabled' : 'disabled';
		$limits     = isset( $state['limits_by_preset'] ) ? $state['limits_by_preset'] : self::limitsByPreset();

		$edition_locked = ! empty( $state['edition_locked'] );
		$upsell         = isset( $state['upsell'] ) && is_array( $state['upsell'] )
			? $state['upsell']
			: array(
				'can_upgrade' => false,
				'url'         => '',
			);

		$html = '<div class="imunify-security__pane js-pane js-pane-' . esc_attr( self::PANE_ID ) . ' js-bot-protection-pane" style="display: none;">';

		$html .= '<div class="imunify-security__pane-header">';
		$html .= '<a href="#" class="imunify-security__back-link js-back-link">';
		$html .= '<span class="dashicons dashicons-arrow-left-alt2"></span>';
		$html .= '</a>';
		// Title carries the monitoring badge + upsell on an AV lock, in the
		// same spot the WAF pane puts its "Monitoring" badge.
		$html .= '<span class="imunify-security__pane-title">' . esc_html__( 'Bot Protection', 'imunify-security' );
		if ( $edition_locked ) {
			$html .= ' ' . self::renderMonitoringBadge( $upsell );
		}
		$html .= '</span>';
		$html .= '</div>';

		$html .= '<div class="imunify-security__bot-pane">';

		// Self-reachability warning sits at the top of the pane so it reads
		// before the status/counter rows. Shown only when active + warning
		// (see computeState); Re-check / Dismiss appear only to editors.
		if ( ! empty( $state['loopback_warning'] ) ) {
			$html .= $this->renderLoopbackWarning( $can_edit );
		}

		// Status + counter — uses the same overview-row grid as the main
		// scan summary so the two read consistently.
		$traffic  = isset( $state['traffic'] ) && is_array( $state['traffic'] ) ? $state['traffic'] : null;
		$stats_on = ! empty( $state['stats_enabled'] );

		$html .= '<div class="imunify-security__overview-rows">';
		$html .= '<div class="imunify-security__overview-row">';
		$html .= '<span class="imunify-security__overview-label">' . esc_html__( 'Status', 'imunify-security' ) . '</span>';
		$html .= '<span class="imunify-security__overview-value imunify-security__overview-value--' . esc_attr( $status_mod ) . '">'
			. esc_html( $status_word ) . '</span>';
		$html .= '</div>';
		// While collection is live, the blocked count is one slice of the
		// verdict tiles below, so the standalone row would duplicate it. With
		// collection paused it stays as the always-on counter, tagged so the
		// user knows it keeps running.
		if ( null === $traffic ) {
			$html .= '<div class="imunify-security__overview-row">';
			$html .= '<span class="imunify-security__overview-label">' . esc_html__( 'Blocked (24h)', 'imunify-security' ) . '</span>';
			$html .= '<span class="imunify-security__overview-value">' . esc_html( (string) $counter );
			if ( $is_active && ! $stats_on ) {
				$html .= ' <span class="imunify-security__bot-still-counting">'
					. esc_html__( 'still counting', 'imunify-security' ) . '</span>';
			}
			$html .= '</span>';
			$html .= '</div>';
		}
		$html .= '</div>';

		if ( null !== $traffic ) {
			$html .= $this->renderTrafficTiles( $traffic );
		} elseif ( $is_active && ! $stats_on ) {
			$html .= $this->renderStatsPausedNote();
		}

		// Deep-link into the full Bot Traffic dashboard — this widget section
		// stays a compact summary; the page carries the charts and detail.
		$html .= '<p class="imunify-security__bot-traffic-link">';
		$html .= '<a href="' . esc_url( admin_url( 'admin.php?page=' . BotTrafficPage::PAGE_SLUG ) ) . '" class="button button-primary js-bot-traffic-link">'
			. esc_html__( 'View full bot traffic report', 'imunify-security' )
			. '</a>';
		$html .= '</p>';

		// Preset read/edit toggle + live limits table — only shown while
		// the feature is actually enforcing. A disabled pane exists only
		// so the user can turn it back on; the limits and preset picker
		// would be misleading there (they wouldn't be in effect).
		if ( $is_active ) {
			$html .= $this->renderPresetSection( $preset, $limits, $can_edit, $edition_locked );
		}

		// Documentation link + Turn off / Turn back on footer. Always rendered
		// so the doc link shows even for read-only viewers; the toggle stays
		// editor-only.
		$html .= $this->renderPaneFooter( $site_on, $can_edit );

		$html .= '</div>'; // .bot-pane
		$html .= '</div>'; // .pane
		return $html;
	}

	/**
	 * Preset read/edit + limits table markup. Read mode is shown by
	 * default; the "Change" link swaps in the edit form via JS. Edit mode
	 * lives in the DOM either way so the swap is just a class toggle.
	 *
	 * On an ImunifyAV / ImunifyAV+ edition ($edition_locked) the preset is
	 * frozen at Monitor: the change link and edit form are dropped. The
	 * monitoring badge + upsell lives in the pane header (see renderPane),
	 * mirroring the WAF monitoring-only presentation.
	 *
	 * @param string $preset         Saved preset identifier.
	 * @param array  $limits         Preset => rows map for the live table.
	 * @param bool   $can_edit       Whether the user may edit settings.
	 * @param bool   $edition_locked Whether the AV lock freezes the preset.
	 * @return string
	 */
	private function renderPresetSection( $preset, $limits, $can_edit, $edition_locked = false ) {
		$preset_label    = self::presetLabel( $preset );
		$preset_editable = $can_edit && ! $edition_locked;

		$html = '<div class="imunify-security__bot-preset js-bot-preset" data-current-preset="' . esc_attr( $preset ) . '">';

		// Read mode. Value + change link live in a right-aligned column so
		// the preset name lines up with the Status / Blocked values above,
		// and "change" sits directly under the preset name.
		$html .= '<div class="imunify-security__bot-preset-row js-bot-preset-read">';
		$html .= '<span class="imunify-security__bot-preset-label">' . esc_html__( 'Preset', 'imunify-security' ) . '</span>';
		$html .= '<div class="imunify-security__bot-preset-value-group">';
		$html .= '<span class="imunify-security__bot-preset-value imunify-security__bot-preset-value--preset-'
			. esc_attr( $preset ) . '">' . esc_html( $preset_label ) . '</span>';
		if ( $preset_editable ) {
			$html .= '<a href="#" class="imunify-security__bot-preset-change js-bot-preset-change">'
				. esc_html__( 'change', 'imunify-security' ) . '</a>';
		}
		$html .= '</div>';
		$html .= '</div>';

		// Edit mode — hidden by default, revealed by Change click.
		if ( $preset_editable ) {
			$html .= '<form class="imunify-security__bot-preset-edit js-bot-preset-edit js-bot-protection-form" style="display: none;">';
			$html .= '<label class="imunify-security__bot-preset-label" for="imunify-bot-preset-select">'
				. esc_html__( 'Preset', 'imunify-security' ) . '</label>';
			$html .= '<select id="imunify-bot-preset-select" name="preset" class="js-bot-preset-select">';
			// Display order runs from most aggressive to least — Strict,
			// Balanced, Monitor only — so users scanning the dropdown
			// from top down naturally encounter stronger protection first.
			foreach ( self::presetDisplayOrder() as $opt ) {
				$sel   = selected( $preset, $opt, false );
				$html .= '<option value="' . esc_attr( $opt ) . '" ' . $sel . '>'
					. esc_html( self::presetLabel( $opt ) ) . '</option>';
			}
			$html .= '</select>';
			$html .= '<button type="submit" name="' . esc_attr( self::SUBMIT_FIELD ) . '" value="'
				. esc_attr( self::SUBMIT_SAVE_PRESET ) . '" class="button button-primary js-bot-preset-save">'
				. esc_html__( 'Save', 'imunify-security' ) . '</button>';
			$html .= '<button type="button" class="button-link js-bot-preset-cancel">'
				. esc_html__( 'Cancel', 'imunify-security' ) . '</button>';
			$html .= '</form>';
		}

		// Limits table — one table per preset, only the current one visible.
		// Swapping is a pure CSS toggle driven by JS for zero flicker.
		$html .= '<div class="imunify-security__bot-limits">';
		$html .= '<h5 class="imunify-security__bot-limits-title">'
			. esc_html__( 'Rate limits (requests / minute)', 'imunify-security' ) . '</h5>';
		foreach ( $limits as $preset_name => $rows ) {
			$hidden = $preset_name === $preset ? '' : ' hidden';
			$html  .= '<table class="imunify-security__bot-limits-table js-bot-limits-table"'
				. ' data-preset="' . esc_attr( $preset_name ) . '"' . $hidden . '>';
			$html  .= '<tbody>';
			foreach ( $rows as $row ) {
				$html .= '<tr>';
				$html .= '<th scope="row">' . esc_html( $row['label'] ) . '</th>';
				$html .= '<td>' . esc_html( $row['value'] ) . '</td>';
				$html .= '</tr>';
			}
			$html .= '</tbody></table>';
		}
		$html .= '</div>';

		$html .= '</div>'; // .bot-preset
		return $html;
	}

	/**
	 * Monitoring badge + upsell tooltip for the locked (AV) preset, modeled
	 * on the WAF monitoring badge. The message renders as a hover tooltip via
	 * the shared .js-custom-tooltip handler; when the user can upgrade, the
	 * upgrade URL/label ride along as data attributes so the tooltip appends
	 * a clickable "Upgrade now" link.
	 *
	 * @param array $upsell Upsell CTA ('can_upgrade', 'url').
	 * @return string
	 */
	private static function renderMonitoringBadge( $upsell ) {
		$message = __(
			'Automated bad-bot traffic is logged but not blocked. Upgrade to Imunify360 to block bad bots.',
			'imunify-security'
		);

		$html = '<span class="imunify-security__badge imunify-security__badge--monitoring js-custom-tooltip"'
			. ' data-tooltip="' . esc_attr( $message ) . '"';
		if ( ! empty( $upsell['can_upgrade'] ) && ! empty( $upsell['url'] ) ) {
			$html .= ' data-tooltip-link-url="' . esc_url( $upsell['url'] ) . '"'
				. ' data-tooltip-link-text="' . esc_attr__( 'Upgrade now', 'imunify-security' ) . '"';
		}
		$html .= ' aria-label="' . esc_attr( $message ) . '">'
			. esc_html__( 'Monitoring', 'imunify-security' ) . '</span>';
		return $html;
	}

	/**
	 * The two rolled-up traffic tiles: bot share of all traffic, and the bot
	 * response split (Allowed / Rate-limited / Blocked) as a segmented bar with
	 * counts. Shown only while collection is live.
	 *
	 * @param array $traffic Output of {@see trafficSummary()}.
	 * @return string
	 */
	private function renderTrafficTiles( $traffic ) {
		$bot_pct    = (int) $traffic['bot_pct'];
		$allow      = (int) $traffic['allow'];
		$rate_limit = (int) $traffic['rate_limit'];
		$block      = (int) $traffic['block'];
		$total      = $allow + $rate_limit + $block;
		$den        = $total > 0 ? $total : 1;

		$segments = array(
			'allow'      => array( __( 'Allowed', 'imunify-security' ), $allow ),
			'rate_limit' => array( __( 'Rate-limited', 'imunify-security' ), $rate_limit ),
			'block'      => array( __( 'Blocked', 'imunify-security' ), $block ),
		);

		$bar    = '';
		$legend = '';
		foreach ( $segments as $key => $seg ) {
			$width   = (int) round( $seg[1] / $den * 100 );
			$bar    .= '<span class="imunify-security__bot-seg is-' . $key . '" style="width:' . $width . '%"></span>';
			$legend .= '<span><i class="imunify-security__bot-sw is-' . $key . '"></i>'
				. esc_html( $seg[0] ) . ' <b>' . esc_html( number_format_i18n( $seg[1] ) ) . '</b></span>';
		}

		$html  = '<div class="imunify-security__bot-tiles">';
		$html .= '<div class="imunify-security__bot-tile">';
		$html .= '<span class="imunify-security__bot-tile-label">' . esc_html__( 'Bots', 'imunify-security' ) . '</span>';
		$html .= '<span class="imunify-security__bot-tile-value">' . esc_html( $bot_pct . '%' ) . '</span>';
		$html .= '<span class="imunify-security__bot-tile-sub">' . esc_html__( 'of all traffic · 24h', 'imunify-security' ) . '</span>';
		$html .= '</div>';
		$html .= '<div class="imunify-security__bot-tile">';
		$html .= '<span class="imunify-security__bot-tile-label">' . esc_html__( 'Responses · 24h', 'imunify-security' ) . '</span>';
		$html .= '<span class="imunify-security__bot-segbar">' . $bar . '</span>';
		$html .= '<span class="imunify-security__bot-seg-legend">' . $legend . '</span>';
		$html .= '</div>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * The "Statistics paused" flag shown under the blocked counter when the
	 * owner opted out of collection. Hovering it reveals how to resume, without
	 * taking permanent space.
	 *
	 * @return string
	 */
	private function renderStatsPausedNote() {
		$link = '<a href="' . esc_url( admin_url( 'admin.php?page=' . BotTrafficPage::PAGE_SLUG ) ) . '">'
			. esc_html__( 'Bot Traffic page', 'imunify-security' ) . '</a>';
		return '<div class="imunify-security__bot-paused js-bot-paused">'
			. '<span class="imunify-security__bot-paused-flag">'
			. '<span class="imunify-security__bot-paused-dot"></span>'
			. esc_html__( 'Statistics paused', 'imunify-security' ) . '</span>'
			. '<span class="imunify-security__bot-paused-pop">'
			. sprintf(
				/* translators: %s: link to the Bot Traffic page. */
				esc_html__( 'You can enable statistics collection on the %s. Protection and the blocked counter keep running.', 'imunify-security' ),
				$link
			)
			. '</span></div>';
	}

	/**
	 * Pane footer: understated Documentation link (left) + the Turn off /
	 * Turn back on control (right, editors only). Mirrors the split footer on
	 * the Malware / WAF panes. The <form> groups the nonce-free submit — JS
	 * intercepts and posts AJAX.
	 *
	 * @param bool $site_on  Whether site-level protection is currently on.
	 * @param bool $can_edit Whether to render the toggle control.
	 * @return string
	 */
	private function renderPaneFooter( $site_on, $can_edit ) {
		$html  = '<div class="imunify-security__pane-footer imunify-security__pane-footer--split">';
		$html .= Documentation::link( Documentation::aiBotManagement() );
		if ( $can_edit ) {
			$html .= '<form class="js-bot-protection-form">';
			if ( $site_on ) {
				$html .= '<button type="submit" name="' . esc_attr( self::SUBMIT_FIELD ) . '" value="'
					. esc_attr( self::SUBMIT_DISABLE ) . '" class="button imunify-security__button--danger js-bot-toggle-site">'
					. esc_html__( 'Turn off on this site', 'imunify-security' ) . '</button>';
			} else {
				$html .= '<button type="submit" name="' . esc_attr( self::SUBMIT_FIELD ) . '" value="'
					. esc_attr( self::SUBMIT_ENABLE ) . '" class="button button-primary js-bot-toggle-site">'
					. esc_html__( 'Turn back on', 'imunify-security' ) . '</button>';
			}
			$html .= '</form>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Amber self-reachability callout. Leads with the user-facing framing
	 * ("Your site can't reach itself") and explains the consequence —
	 * scheduled tasks, including automatic bot-data updates, may not run.
	 * Re-check and Dismiss are rendered only for users who can manage the
	 * feature; everyone else sees the explanation alone.
	 *
	 * @param bool $can_edit Whether to render the Re-check / Dismiss actions.
	 * @return string
	 */
	private function renderLoopbackWarning( $can_edit ) {
		$html  = '<div class="imunify-security__bot-callout imunify-security__bot-callout--warning js-bot-loopback-warning">';
		$html .= '<p class="imunify-security__bot-callout-title">'
			. esc_html__( "Your site can't reach itself", 'imunify-security' ) . '</p>';
		$html .= '<p class="imunify-security__bot-callout-text">'
			. esc_html__(
				"Your site couldn't reach itself, so scheduled tasks — including automatic bot-data updates — may not run.",
				'imunify-security'
			) . '</p>';

		if ( $can_edit ) {
			$html .= '<div class="imunify-security__bot-callout-actions">';
			$html .= '<form class="js-bot-protection-form">';
			$html .= '<button type="submit" name="' . esc_attr( self::SUBMIT_FIELD ) . '" value="'
				. esc_attr( self::SUBMIT_RECHECK ) . '" class="button js-bot-recheck">'
				. esc_html__( 'Re-check', 'imunify-security' ) . '</button>';
			$html .= '<button type="submit" name="' . esc_attr( self::SUBMIT_FIELD ) . '" value="'
				. esc_attr( self::SUBMIT_DISMISS ) . '" class="button-link js-bot-dismiss">'
				. esc_html__( 'Dismiss', 'imunify-security' ) . '</button>';
			$html .= '</form>';
			$html .= '</div>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Short hover-tooltip copy for the row warning badge.
	 *
	 * @return string
	 */
	private static function loopbackTooltip() {
		return __( "Your site can't reach itself — scheduled updates may not run.", 'imunify-security' );
	}

	/**
	 * Status label shown on the Status row inside the pane. Unlike the
	 * one-word badge on the main row, this returns the full explanation
	 * so users see the reason without hovering a tooltip.
	 *
	 * @param string $status One of 'active', 'disabled_by_host',
	 *                       'disabled_by_site', 'disabled_by_wpconfig'.
	 * @return string
	 */
	private static function statusDetailedLabel( $status ) {
		if ( 'disabled_by_host' === $status ) {
			return __( 'Disabled by hosting provider', 'imunify-security' );
		}
		if ( 'disabled_by_wpconfig' === $status ) {
			return __( 'Disabled in wp-config.php', 'imunify-security' );
		}
		if ( 'disabled_by_site' === $status ) {
			return __( 'Disabled on this site', 'imunify-security' );
		}
		return __( 'Active', 'imunify-security' );
	}

	/**
	 * Human-readable preset label.
	 *
	 * @param string $preset Preset::* value.
	 * @return string
	 */
	private static function presetLabel( $preset ) {
		return Preset::label( $preset );
	}

	/**
	 * Resolve the effective preset via the shared chain in Preset::resolve().
	 *
	 * @param OptOutFlag   $opt_out        Site-owner preference.
	 * @param PluginConfig $plugin_config  Agent-written config.
	 * @return string One of Preset::BALANCED/STRICT/MONITOR.
	 */
	private static function resolveEffectivePreset( $opt_out, PluginConfig $plugin_config ) {
		return Preset::resolve( $opt_out, $plugin_config );
	}

	/**
	 * Whether the current user is allowed to mutate bot-settings.php.
	 * Tests stub the underlying current_user_can() via Brain\Monkey.
	 *
	 * @return bool
	 */
	private static function currentUserCanEdit() {
		return function_exists( 'current_user_can' )
			&& current_user_can( 'manage_options' );
	}

	/**
	 * Read the 24h blocked-request counter with fail-open wrapping.
	 *
	 * The dashboard widget runs inside wp-admin — a \Throwable from the
	 * storage backend must not crash the render path. Pipeline wraps its
	 * DailyCounter::recordDecision() call in the same dual-path catch for
	 * symmetry.
	 *
	 * @return int
	 */
	private static function safeBlockedCount() {
		global $wpdb;
		$db_storage = isset( $wpdb ) ? DbStorageFactory::detect( $wpdb ) : DbStorageFactory::nullPair();
		if ( interface_exists( 'Throwable' ) ) {
			try {
				$counter = new DailyCounter( $db_storage['counter'] );
				return (int) $counter->current();
			} catch ( \Throwable $t ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail open with 0.
				unset( $t );
				return 0;
			}
		}
		try {
			$counter = new DailyCounter( $db_storage['counter'] );
			return (int) $counter->current();
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail open with 0.
			unset( $e );
			return 0;
		}
	}

	/**
	 * 24h traffic summary for the pane tiles (bot share + verdict split), read
	 * from the hourly rollup with fail-open wrapping. Returns null when the DB
	 * is unavailable or there is nothing recorded yet, so the pane falls back
	 * to the always-on blocked counter.
	 *
	 * @return array|null Keys: bot_pct, allow, rate_limit, block.
	 */
	private static function safeTrafficSummary() {
		if ( interface_exists( 'Throwable' ) ) {
			try {
				return self::trafficSummary();
			} catch ( \Throwable $t ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail open with no tiles.
				unset( $t );
				return null;
			}
		}
		try {
			return self::trafficSummary();
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail open with no tiles.
			unset( $e );
			return null;
		}
	}

	/**
	 * Aggregate the last 24h of rollup rows into the tile figures. Human
	 * requests are split out of the bot verdict counts the same way the full
	 * Bot Traffic page does, so the two surfaces agree.
	 *
	 * @return array|null
	 */
	private static function trafficSummary() {
		$storage = HourlyStatsStorage::forGlobalWpdb();
		if ( null === $storage ) {
			return null;
		}
		$cutoff = HourlyStatsStorage::hourBucket( time() ) - ( BotTrafficPage::RETENTION_HOURS - 1 );
		$stats  = new BotTrafficStats( $storage->fetchSince( $cutoff ) );
		$total  = $stats->totalRequests();
		if ( $total <= 0 ) {
			return null;
		}
		$breakdown = $stats->categoryBreakdown();
		$human     = isset( $breakdown[ Category::HUMAN ] ) ? (int) $breakdown[ Category::HUMAN ] : 0;
		$counters  = $stats->verdictCounters();
		$allow     = isset( $counters[ RateLimitDecision::ACTION_ALLOW ] ) ? (int) $counters[ RateLimitDecision::ACTION_ALLOW ] : 0;
		$bot       = max( 0, $total - $human );
		return array(
			'bot_pct'    => (int) round( $bot / $total * 100 ),
			'allow'      => max( 0, $allow - $human ),
			'rate_limit' => isset( $counters[ RateLimitDecision::ACTION_RATE_LIMIT ] ) ? (int) $counters[ RateLimitDecision::ACTION_RATE_LIMIT ] : 0,
			'block'      => isset( $counters[ RateLimitDecision::ACTION_BLOCK ] ) ? (int) $counters[ RateLimitDecision::ACTION_BLOCK ] : 0,
		);
	}

	/**
	 * Apply the requested mutation to bot-settings.php. Shared by every
	 * entry point that writes state.
	 *
	 * Note: load→mutate→write is not atomic across concurrent admin
	 * sessions — the atomic-rename in BotSettingsWriter prevents torn
	 * reads but not lost updates. Acceptable for Phase-1 single-site
	 * admin usage; revisit if a cached/batched flow is added later.
	 *
	 * @param string $action              One of SUBMIT_SAVE_PRESET, SUBMIT_ENABLE, SUBMIT_DISABLE.
	 * @param string $preset_from_request The raw preset POST value (may be empty).
	 * @return bool Whether the settings file was written successfully.
	 */
	private function applySubmission( $action, $preset_from_request ) {
		$existing = OptOutFlag::load( $this->wpContentDir );
		$enabled  = $existing->isEnabled();
		$preset   = $existing->hasExplicitPreset() ? $existing->getPreset() : null;
		// The writer rewrites the whole file, so the stats opt-out must be
		// carried through unchanged when only preset / protection toggles here.
		$stats_enabled = $existing->isStatsEnabled();

		if ( self::SUBMIT_DISABLE === $action ) {
			$enabled = false;
		} elseif ( self::SUBMIT_ENABLE === $action ) {
			$enabled = true;
		} elseif ( self::SUBMIT_SAVE_PRESET === $action ) {
			if ( Preset::isValid( $preset_from_request ) && $this->editionAllowsPreset( $preset_from_request ) ) {
				$preset = $preset_from_request;
			}
		} elseif ( self::SUBMIT_STATS_DISABLE === $action ) {
			$stats_enabled = false;
		} elseif ( self::SUBMIT_STATS_ENABLE === $action ) {
			$stats_enabled = true;
		}

		$writer  = new BotSettingsWriter( $this->wpContentDir );
		$written = $writer->write( $enabled, $preset, $stats_enabled );

		// Opting out of stats purges the rollup immediately and stops the daily
		// export cron; opting back in reschedules it. Protection is unaffected —
		// the widget's "blocked in 24h" headline is a separate, always-on
		// aggregate (DailyCounter), not this table.
		if ( $written && self::SUBMIT_STATS_DISABLE === $action ) {
			self::purgeStatsRollup();
			BotLifecycle::unscheduleDailyExport();
		} elseif ( $written && self::SUBMIT_STATS_ENABLE === $action ) {
			BotLifecycle::ensureDailyExportScheduled();
		}

		return $written;
	}

	/**
	 * Wipe the hourly bot-traffic rollup. Called when the site owner opts out
	 * of stats capture. Fail-open: a missing $wpdb or DB error is a no-op.
	 *
	 * @return void
	 */
	private static function purgeStatsRollup() {
		$storage = HourlyStatsStorage::forGlobalWpdb();
		if ( null !== $storage ) {
			$storage->purge();
		}
		$ip_storage = HourlyIpStatsStorage::forGlobalWpdb();
		if ( null !== $ip_storage ) {
			$ip_storage->purge();
		}
	}

	/**
	 * Whether the current license edition may store the given preset.
	 * An ImunifyAV / ImunifyAV+ edition may only ever persist Monitor —
	 * a blocking preset is refused so bot-settings.php never carries a
	 * value that {@see Preset::resolve()} would ignore anyway.
	 *
	 * @param string $preset Requested preset identifier.
	 * @return bool
	 */
	private function editionAllowsPreset( $preset ) {
		return ! $this->isEditionLocked() || Preset::MONITOR === $preset;
	}

	/**
	 * Whether the license edition forces monitor-only bot management
	 * (ImunifyAV / ImunifyAV+). Read from the same plugin_config.php that
	 * drives enforcement, so the widget and the hot path never disagree.
	 *
	 * @return bool
	 */
	private function isEditionLocked() {
		return $this->dataStore->getPluginConfig()->isImunifyAvEdition();
	}

	/**
	 * Re-run the self-reachability probe on demand and persist the result.
	 *
	 * Runs in an authenticated admin request, so cookies and Basic Auth are
	 * forwarded to cut false negatives. A passing probe records an OK result,
	 * which clears the warning the widget reads.
	 *
	 * @return void
	 */
	private function applyRecheck() {
		$result = ( new LoopbackSafetyTest() )->run( $this->homeUrl(), true );
		( new LoopbackStatus() )->record( $result );
	}

	/**
	 * Acknowledge the current warning so the widget stops surfacing it.
	 *
	 * @return void
	 */
	private function applyDismiss() {
		( new LoopbackStatus() )->dismiss();
	}

	/**
	 * Resolve the site home URL for the loopback probe, or '' when WordPress
	 * is not loaded (unit context).
	 *
	 * @return string
	 */
	private function homeUrl() {
		return function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
	}

	/**
	 * AJAX handler wired by Plugin::init() via wp_ajax_{AJAX_ACTION}.
	 * Validates capability + nonce, applies the mutation, then returns
	 * the refreshed row and pane HTML so the JS can swap both in place.
	 *
	 * @return void
	 */
	public function handleAjaxSubmission() {
		if ( ! self::currentUserCanEdit() ) {
			wp_send_json_error(
				array( 'message' => __( 'Forbidden.', 'imunify-security' ) ),
				403
			);
			return; // @phpstan-ignore deadCode.unreachable (wp_die handler may be overridden)
		}
		// check_ajax_referer dies 403 on mismatch; no explicit wp_die needed.
		check_ajax_referer( self::NONCE_ACTION, '_ajax_nonce' );

		$action = isset( $_POST[ self::SUBMIT_FIELD ] )
			? sanitize_text_field( wp_unslash( $_POST[ self::SUBMIT_FIELD ] ) )
			: '';
		$posted = isset( $_POST['preset'] )
			? sanitize_text_field( wp_unslash( $_POST['preset'] ) )
			: '';

		$allowed_actions = array(
			self::SUBMIT_SAVE_PRESET,
			self::SUBMIT_DISABLE,
			self::SUBMIT_ENABLE,
			self::SUBMIT_RECHECK,
			self::SUBMIT_DISMISS,
			self::SUBMIT_STATS_ENABLE,
			self::SUBMIT_STATS_DISABLE,
		);
		if ( ! in_array( $action, $allowed_actions, true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid action.', 'imunify-security' ) ),
				400
			);
			return; // @phpstan-ignore deadCode.unreachable (wp_die handler may be overridden)
		}

		// Save-path guard (defense-in-depth): an AV / AV+ edition can only
		// monitor bot traffic, so refuse a blocking preset before it ever
		// reaches bot-settings.php. Preset::resolve() is authoritative and
		// already ignores the stored value for AV; this just keeps the file
		// from carrying a misleading preset.
		if ( self::SUBMIT_SAVE_PRESET === $action
			&& $this->isEditionLocked()
			&& Preset::isValid( $posted )
			&& Preset::MONITOR !== $posted ) {
			wp_send_json_error(
				array( 'message' => __( 'Bot blocking requires Imunify360. This site can only monitor bot traffic.', 'imunify-security' ) ),
				403
			);
			return; // @phpstan-ignore deadCode.unreachable (wp_die handler may be overridden)
		}

		if ( self::SUBMIT_RECHECK === $action ) {
			$this->applyRecheck();
		} elseif ( self::SUBMIT_DISMISS === $action ) {
			$this->applyDismiss();
		} else {
			$written = $this->applySubmission( $action, $posted );
			if ( ! $written ) {
				$fragments = $this->view();
				wp_send_json_error(
					array(
						'message'  => __( 'Settings could not be saved.', 'imunify-security' ),
						'rowHtml'  => $fragments['row'],
						'paneHtml' => $fragments['pane'],
					)
				);
				return; // @phpstan-ignore deadCode.unreachable (wp_die handler may be overridden)
			}
		}

		$fragments = $this->view();
		wp_send_json_success(
			array(
				'rowHtml'  => $fragments['row'],
				'paneHtml' => $fragments['pane'],
			)
		);
	}
}
