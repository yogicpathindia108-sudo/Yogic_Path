<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Views;

use CloudLinux\Imunify\App\AccessManager;
use CloudLinux\Imunify\App\DataStore;
use CloudLinux\Imunify\App\View;
use CloudLinux\Imunify\App\Defender\Model\RuleMode;
use CloudLinux\Imunify\App\Defender\RuleHitTracker;
use CloudLinux\Imunify\App\Defender\RuleProvider;
use CloudLinux\Imunify\App\Views\Display\RuleDisplay;

/**
 * Dashboard widget view.
 */
class Widget extends View {
	/**
	 * Maximum number of malware items to show in widget view.
	 */
	const MAX_WIDGET_ITEMS = 5;

	/**
	 * Maximum number of WAF incidents to show in widget view.
	 */
	const MAX_WAF_INCIDENTS = 10;

	/**
	 * User meta key for storing widget snooze state.
	 *
	 * @var string
	 */
	const WIDGET_SNOOZED_META_KEY = 'imunify_widget_snoozed_until';

	/**
	 * Nonce name for widget snooze action.
	 *
	 * @var string
	 */
	const WIDGET_SNOOZE_NONCE_NAME = 'imunify_widget_snooze_nonce';

	/**
	 * URI path for the malware page in the admin interface.
	 *
	 * @var string
	 */
	const MALWARE_URI_PATH = '/client/malware';

	/**
	 * URI path for the WAF/incidents page in the admin interface.
	 *
	 * @var string
	 */
	const WAF_URI_PATH = '/client/cms-protection/incidents';

	/**
	 * Data store instance.
	 *
	 * @var DataStore
	 */
	public $dataStore;

	/**
	 * Access manager instance.
	 *
	 * @var AccessManager
	 */
	private $accessManager;

	/**
	 * Rule provider instance.
	 *
	 * @var RuleProvider
	 */
	private $ruleProvider;

	/**
	 * Rule hit tracker instance.
	 *
	 * @var RuleHitTracker
	 */
	private $hitTracker;

	/**
	 * Optional Phase-1 bot protection section.
	 *
	 * @var BotProtectionWidgetSection|null
	 */
	private $botProtection;

	/**
	 * Constructor.
	 *
	 * @param AccessManager                   $accessManager Access manager instance.
	 * @param DataStore                       $dataStore     Data store instance.
	 * @param RuleProvider                    $ruleProvider  Rule provider instance.
	 * @param RuleHitTracker                  $hitTracker    Rule hit tracker instance.
	 * @param BotProtectionWidgetSection|null $botProtection Optional bot-protection
	 *                                                      section (DEF-42031). When
	 *                                                      null the section is simply
	 *                                                      not rendered, keeping the
	 *                                                      widget backwards compatible
	 *                                                      with existing tests.
	 */
	public function __construct(
		AccessManager $accessManager,
		DataStore $dataStore,
		RuleProvider $ruleProvider,
		RuleHitTracker $hitTracker,
		BotProtectionWidgetSection $botProtection = null
	) {
		$this->accessManager = $accessManager;
		$this->dataStore     = $dataStore;
		$this->ruleProvider  = $ruleProvider;
		$this->hitTracker    = $hitTracker;
		$this->botProtection = $botProtection;

		add_action( 'wp_dashboard_setup', array( $this, 'add' ) );
		add_action( 'wp_ajax_imunify_snooze_widget', array( $this, 'snoozeWidget' ) );
	}

	/**
	 * Add a new dashboard widget.
	 *
	 * @return void
	 */
	public function add() {
		if ( ! $this->willBeRendered() ) {
			return;
		}

		wp_add_dashboard_widget(
			'imunify_security_widget',
			esc_html__( 'Imunify Security', 'imunify-security' ),
			array(
				$this,
				'view',
			),
			null,
			null,
			'normal',
			'high'
		);
	}

	/**
	 * Output the contents of the dashboard widget.
	 */
	public function view() {
		$pluginUrl = plugin_dir_url( IMUNIFY_SECURITY_FILE_PATH );
		if ( ! $this->dataStore->isDataAvailable() ) {
			$this->render(
				'widget-not-installed',
				array(
					'installLink' => 'https://imunify360.com/getting-started-installation/',
					'pluginUrl'   => $pluginUrl,
				)
			);
		} else {
			$scanData = $this->dataStore->getScanData();
			if ( null === $scanData ) {
				// Data is not available, do not render the widget.
				return;
			}

			$malwareItems   = $scanData->getMalware();
			$malwareCount   = count( $malwareItems );
			$canUserUpgrade = $this->accessManager->canUserUpgrade( $this->dataStore );
			$showMoreButton = $malwareCount > self::MAX_WIDGET_ITEMS;

			$templateData = array(
				'scanData'          => $scanData,
				'pluginUrl'         => $pluginUrl,
				'features'          => $this->dataStore->getFeatures(),
				'malwareItems'      => array_slice( $malwareItems, 0, self::MAX_WIDGET_ITEMS ),
				'totalItemsCount'   => $malwareCount,
				'showMoreButton'    => $showMoreButton,
				'showMoreUrl'       => $showMoreButton ? $this->getAdminPageUrl() : '',
				'showUpgradeButton' => $canUserUpgrade,
				'upgradeUrl'        => $canUserUpgrade ? $this->getUpgradeUrl() : '',
				'statusTitle'       => $this->getProtectionStatusTitle(),
				'statusIcon'        => $this->getProtectionStatusIcon(),
				'malwareUrl'        => $this->getMalwareUrl(),
				'wafUrl'            => $this->getWafUrl(),
			);

			// Phase-1 bot protection section (DEF-42031). The section is
			// optional so tests that construct Widget with the legacy 4-arg
			// signature still pass empty strings through. BotProtectionWidgetSection
			// returns the row and pane as separate fragments — the row lives
			// inside the main pane's nav-links, the pane is a sibling slide-in.
			if ( null === $this->botProtection ) {
				$templateData['botProtectionRowHtml']  = '';
				$templateData['botProtectionPaneHtml'] = '';
			} else {
				$fragments                             = $this->botProtection->view();
				$templateData['botProtectionRowHtml']  = $fragments['row'];
				$templateData['botProtectionPaneHtml'] = $fragments['pane'];
			}

			$rules                          = $this->ruleProvider->loadRules();
			$templateData['showWafSection'] = false;
			$templateData['wafEnabled']     = false;
			$templateData['wafMonitoring']  = false;

			// Check if WAF rules exist.
			if ( null !== $rules && ! $rules->isEmpty() ) {
				$isImunify360 = AccessManager::isProductType( $this->dataStore, 'imunify360' );

				// Set the appropriate WAF status flag.
				if ( $isImunify360 ) {
					// Imunify360: WAF is enabled with full protection (blocking).
					$templateData['wafEnabled'] = true;
				} else {
					// ImunifyAV: WAF is in monitoring-only mode (logging only).
					$templateData['wafMonitoring'] = true;
				}

				// Process incidents for both product types.
				$ruleDisplays = array();

				foreach ( $rules->getRules() as $rule ) {
					if ( $rule->isInternal() ) {
						continue;
					}

					// For Imunify360 only: exclude pass-mode rules from the incidents list.
					if ( $isImunify360 && $rule->getMode() === RuleMode::PASS ) {
						continue;
					}
					$hitCount      = $this->hitTracker->getTotalHitsForRule( $rule );
					$lastTimestamp = $this->hitTracker->getLastTimestampForRule( $rule );

					// Only include rules that have actual incidents (hit count > 0).
					if ( $hitCount > 0 ) {
						$ruleDisplays[] = RuleDisplay::fromRule( $rule, $hitCount, $lastTimestamp );
					}
				}

				// Show incident details section only if there are actual incidents.
				if ( ! empty( $ruleDisplays ) ) {
					$templateData['showWafSection'] = true;

					// Sort by last incident timestamp (most recent first).
					usort(
						$ruleDisplays,
						function ( $a, $b ) {
							// Handle null timestamps - push them to the end.
							if ( null === $a->lastIncidentTimestamp && null === $b->lastIncidentTimestamp ) {
								return 0;
							}
							if ( null === $a->lastIncidentTimestamp ) {
								return 1;
							}
							if ( null === $b->lastIncidentTimestamp ) {
								return -1;
							}
							// Sort descending (most recent first).
							return $b->lastIncidentTimestamp - $a->lastIncidentTimestamp;
						}
					);

					// Limit to max incidents.
					$templateData['rules']   = array_slice( $ruleDisplays, 0, self::MAX_WAF_INCIDENTS );
					$templateData['ruleset'] = $this->ruleProvider->getRulesetVersion();
				}
			}

			$this->render( 'widget', $templateData );
		}
	}

	/**
	 * Gets the URL for the admin page.
	 *
	 * @return string
	 *
	 * @since 2.0.0
	 */
	public function getAdminPageUrl() {
		return AdminPage::pageUrl();
	}
	/**
	 * Gets the upgrade URL for the button.
	 *
	 * @return string
	 *
	 * @since 2.0.0
	 */
	public function getUpgradeUrl() {
		return AdminPage::upgradeUrl();
	}

	/**
	 * Gets the malware page URL.
	 *
	 * @return string
	 */
	public function getMalwareUrl() {
		return $this->getAdminPageUrl() . '#/' . $this->getProductUriPrefix() . self::MALWARE_URI_PATH;
	}

	/**
	 * Gets the WAF/incidents page URL.
	 *
	 * @return string
	 */
	public function getWafUrl() {
		return $this->getAdminPageUrl() . '#/' . $this->getProductUriPrefix() . self::WAF_URI_PATH;
	}

	/**
	 * Checks if the widget will be rendered.
	 *
	 * @return bool
	 */
	public function willBeRendered() {
		if ( ! $this->accessManager->isUserAdmin() ) {
			return false;
		}

		return ! $this->isSnoozed();
	}

	/**
	 * Gets the WAF monitoring tooltip data for JavaScript.
	 *
	 * @return array Tooltip configuration with message and optional upgrade URL.
	 */
	public function getWafMonitoringTooltipData() {
		$canUpgrade = $this->accessManager->canUserUpgrade( $this->dataStore );

		return array(
			'message'    => __( 'Attacks are logged but not blocked. Upgrade to Imunify360 to enable full WAF protection.', 'imunify-security' ),
			'canUpgrade' => $canUpgrade,
			'upgradeUrl' => $canUpgrade ? $this->getUpgradeUrl() : '',
			'linkText'   => __( 'Upgrade now', 'imunify-security' ),
		);
	}

	/**
	 * Checks if the widget is currently snoozed.
	 *
	 * @return bool
	 */
	private function isSnoozed() {
		$user_id       = get_current_user_id();
		$snoozed_until = get_user_meta( $user_id, self::WIDGET_SNOOZED_META_KEY, true );

		return $snoozed_until && time() < $snoozed_until;
	}

	/**
	 * Snoozes the widget for the specified number of weeks.
	 *
	 * @return void
	 */
	public function snoozeWidget() {
		check_ajax_referer( self::WIDGET_SNOOZE_NONCE_NAME, 'nonce' );

		$weeks = filter_input( INPUT_POST, 'weeks', FILTER_VALIDATE_INT );
		if ( ! $weeks || $weeks < 1 || $weeks > 4 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid snooze duration', 'imunify-security' ) ) );
		} else {
			$user_id      = get_current_user_id();
			$snooze_until = strtotime( "+{$weeks} weeks UTC" );
			update_user_meta( $user_id, self::WIDGET_SNOOZED_META_KEY, $snooze_until );
			wp_send_json_success();
		}
	}

	/**
	 * Returns the URI prefix based on product type: '360' for Imunify360, 'AV' for ImunifyAV.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	private function getProductUriPrefix() {
		return AccessManager::isProductType( $this->dataStore, 'imunify360' ) ? '360' : 'AV';
	}

	/**
	 * Checks if the product is in the ImunifyAV family (ImunifyAV or ImunifyAV+).
	 *
	 * @return bool
	 */
	private function isImunifyAV() {
		return AccessManager::isImunifyAvFamily( $this->dataStore );
	}

	/**
	 * Gets the protection status title based on product type.
	 *
	 * @return string
	 */
	private function getProtectionStatusTitle() {
		return $this->isImunifyAV()
			? esc_html__( 'Not protected', 'imunify-security' )
			: esc_html__( 'Protected', 'imunify-security' );
	}

	/**
	 * Gets the protection status icon based on product type.
	 *
	 * @return string
	 */
	private function getProtectionStatusIcon() {
		return $this->isImunifyAV()
			? 'shield-warning.svg'
			: 'shield-check.svg';
	}
}
