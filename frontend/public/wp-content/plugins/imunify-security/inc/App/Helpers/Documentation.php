<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Helpers;

/**
 * Links into the public WordPress plugin documentation.
 *
 * @since 4.1.0
 */
class Documentation {
	/**
	 * Documentation site root for the WordPress plugin.
	 *
	 * @var string
	 */
	const BASE_URL = 'https://docs.imunify360.com/wordpress_plugin/';

	/**
	 * Documentation home.
	 *
	 * @return string
	 */
	public static function home() {
		return self::BASE_URL;
	}

	/**
	 * Web Application Firewall (virtual patching) section.
	 *
	 * @return string
	 */
	public static function waf() {
		return self::BASE_URL . '#web-application-firewall-virtual-patching';
	}

	/**
	 * AI Bot Management section.
	 *
	 * @return string
	 */
	public static function aiBotManagement() {
		return self::BASE_URL . '#ai-bot-management';
	}

	/**
	 * Understated, icon-only help link. Shared by the dashboard widget template
	 * and the bot-protection section so markup, escaping and the icon class stay
	 * in one place. Renders no visible text — the tooltip and accessible name
	 * carry the "Need help?" label. Opens in a new tab because it leaves wp-admin
	 * for the external docs site.
	 *
	 * @param string $url Target documentation URL.
	 * @return string HTML anchor.
	 */
	public static function link( $url ) {
		$label = esc_attr__( 'Need help?', 'imunify-security' );
		return '<a href="' . esc_url( $url ) . '" class="imunify-security__doc-link" target="_blank" rel="noopener noreferrer"'
			. ' title="' . $label . '" aria-label="' . $label . '">'
			. '<span class="dashicons dashicons-editor-help" aria-hidden="true"></span></a>';
	}
}
