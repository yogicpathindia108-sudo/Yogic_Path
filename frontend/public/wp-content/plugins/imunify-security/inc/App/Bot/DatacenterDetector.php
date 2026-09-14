<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Datacenter IP detection.
 *
 * Thin wrapper around an IpRangeLookup whose providers are the major cloud
 * hosting networks (AWS, GCP, Azure, OVH, Hetzner, DigitalOcean, ...).
 * Used by the classifier as a secondary signal for the "Unknown Automated"
 * category: a non-browser request from a datacenter IP is suspicious even
 * when no bot UA is present.
 *
 * @since 4.0.0
 */
class DatacenterDetector {

	/**
	 * Preloaded lookup scoped to datacenter providers.
	 *
	 * @var IpRangeLookup
	 */
	private $lookup;

	/**
	 * Wrap a preloaded IpRangeLookup that covers datacenter providers only.
	 *
	 * @param IpRangeLookup $lookup Lookup pre-seeded with datacenter providers.
	 */
	public function __construct( $lookup ) {
		$this->lookup = $lookup;
	}

	/**
	 * Whether $ip belongs to any registered datacenter provider.
	 *
	 * @param string $ip IP address to check.
	 * @return bool
	 */
	public function isDatacenter( $ip ) {
		return null !== $this->lookup->find( $ip );
	}

	/**
	 * Return the datacenter provider name containing $ip, or null.
	 *
	 * @param string $ip IP address to check.
	 * @return string|null
	 */
	public function provider( $ip ) {
		return $this->lookup->find( $ip );
	}
}
