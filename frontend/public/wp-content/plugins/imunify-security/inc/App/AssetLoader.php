<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App;

use CloudLinux\Imunify\App\Views\BotProtectionWidgetSection;
use CloudLinux\Imunify\App\Views\BotTrafficPage;
use CloudLinux\Imunify\App\Views\Widget;

/**
 * Handles loading of JavaScript and CSS assets for the widget.
 */
class AssetLoader {

	/**
	 * Asset handle for the widget styles and scripts.
	 */
	const WIDGET_HANDLE = 'imunify-security-widget';

	/**
	 * Asset handle for the Bot Traffic page styles and scripts.
	 */
	const BOT_TRAFFIC_HANDLE = 'imunify-security-bot-traffic';

	/**
	 * The widget instance.
	 *
	 * @var Widget
	 */
	private $widget;

	/**
	 * The Bot Traffic page, or null when WP_CONTENT_DIR was unavailable at
	 * setup. Used to enqueue that page's bundle only on its own admin hook.
	 *
	 * @var BotTrafficPage|null
	 */
	private $botTrafficPage;

	/**
	 * Constructor.
	 *
	 * @param Widget              $widget           The widget instance.
	 * @param BotTrafficPage|null $bot_traffic_page The Bot Traffic page, if available.
	 */
	public function __construct( Widget $widget, $bot_traffic_page = null ) {
		$this->widget         = $widget;
		$this->botTrafficPage = $bot_traffic_page;

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );
	}

	/**
	 * Enqueues assets if the widget will be rendered.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueueAssets( $hook ) {

		// Widget assets.
		if ( 'index.php' === $hook && $this->widget->willBeRendered() ) {

			$plugin_url = plugin_dir_url( IMUNIFY_SECURITY_FILE_PATH );
			wp_enqueue_style(
				self::WIDGET_HANDLE,
				"{$plugin_url}assets/css/admin.min.css",
				array(),
				IMUNIFY_SECURITY_VERSION
			);

			wp_enqueue_script(
				self::WIDGET_HANDLE,
				"{$plugin_url}assets/js/admin.min.js",
				array( 'jquery' ),
				IMUNIFY_SECURITY_VERSION,
				true
			);

			wp_localize_script(
				self::WIDGET_HANDLE,
				'imunifyWidget',
				array(
					'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
					'snoozeNonce'          => wp_create_nonce( Widget::WIDGET_SNOOZE_NONCE_NAME ),
					'wafMonitoringTooltip' => $this->widget->getWafMonitoringTooltipData(),
					'botProtection'        => array(
						'action'       => \CloudLinux\Imunify\App\Views\BotProtectionWidgetSection::AJAX_ACTION,
						'nonce'        => wp_create_nonce( \CloudLinux\Imunify\App\Views\BotProtectionWidgetSection::NONCE_ACTION ),
						'errorMessage' => __( 'Could not update bot protection settings. Please try again.', 'imunify-security' ),
					),
				)
			);
		}

		// Bot Traffic page assets — only on that page's own admin hook.
		if ( null !== $this->botTrafficPage && $hook === $this->botTrafficPage->hookSuffix() ) {
			$this->enqueueBotTrafficAssets();
		}
	}

	/**
	 * Enqueue the self-contained Bot Traffic bundle (Vanilla JS + its own CSS)
	 * and localize the nonce for the stats opt-out toggle.
	 *
	 * @return void
	 */
	private function enqueueBotTrafficAssets() {
		$plugin_url = plugin_dir_url( IMUNIFY_SECURITY_FILE_PATH );
		wp_enqueue_style(
			self::BOT_TRAFFIC_HANDLE,
			"{$plugin_url}assets/css/bot-traffic.min.css",
			array(),
			IMUNIFY_SECURITY_VERSION
		);
		wp_enqueue_script(
			self::BOT_TRAFFIC_HANDLE,
			"{$plugin_url}assets/js/bot-traffic.min.js",
			array(),
			IMUNIFY_SECURITY_VERSION,
			true
		);
		wp_localize_script(
			self::BOT_TRAFFIC_HANDLE,
			'imunifyBotTraffic',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'action'       => BotProtectionWidgetSection::AJAX_ACTION,
				'nonce'        => wp_create_nonce( BotProtectionWidgetSection::NONCE_ACTION ),
				'errorMessage' => __( 'Could not update statistics settings. Please try again.', 'imunify-security' ),
			)
		);
	}
}
