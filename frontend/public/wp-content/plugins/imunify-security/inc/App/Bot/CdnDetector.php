<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * CDN origin detection.
 *
 * Thin wrapper over an IpRangeLookup scoped to the edge ranges published by
 * the supported CDN providers (Cloudflare, Fastly, CloudFront, Akamai,
 * Sucuri, Imperva, QUIC.cloud). RealIpResolver consults this detector when
 * validating proxy headers — a CDN-specific header (CF-Connecting-IP,
 * X-Sucuri-ClientIP, etc.) is only trusted when the REMOTE_ADDR actually
 * falls inside that CDN's published ranges.
 *
 * @since 4.0.0
 */
class CdnDetector {

	/**
	 * Lookup preloaded with CDN provider ranges.
	 *
	 * @var IpRangeLookup
	 */
	private $lookup;

	/**
	 * Wrap a preloaded IpRangeLookup that covers CDN providers only.
	 *
	 * @param IpRangeLookup $lookup Lookup pre-seeded with CDN providers.
	 */
	public function __construct( $lookup ) {
		$this->lookup = $lookup;
	}

	/**
	 * Return the CDN provider name that owns $ip, or null.
	 *
	 * @param string $ip IP address to check.
	 * @return string|null
	 */
	public function detect( $ip ) {
		return $this->lookup->find( $ip );
	}

	/**
	 * Whether $ip belongs to any registered CDN provider.
	 *
	 * @param string $ip IP address to check.
	 * @return bool
	 */
	public function isKnownCdn( $ip ) {
		return null !== $this->lookup->find( $ip );
	}

	/**
	 * Whether $ip belongs specifically to $cdn.
	 *
	 * @param string $ip  IP address to check.
	 * @param string $cdn Registered CDN provider name.
	 * @return bool
	 */
	public function isFrom( $ip, $cdn ) {
		return $this->lookup->matchesProvider( $ip, $cdn );
	}
}
