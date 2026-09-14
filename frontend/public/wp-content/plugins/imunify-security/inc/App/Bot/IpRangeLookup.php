<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Reverse-lookup from IP address to provider name over a set of CIDR lists.
 *
 * Sources may be supplied as either in-memory CIDR arrays or as paths to
 * PHP return-array files produced by bin/update-bot-data.php. Missing or
 * malformed files yield an empty provider per the bot-protection
 * fail-open contract — the lookup never throws.
 *
 * @since 4.0.0
 */
class IpRangeLookup {

	/**
	 * Provider name => raw source (array of CIDRs or path to PHP file).
	 *
	 * Entries are removed once resolved into $ranges.
	 *
	 * @var array
	 */
	private $sources = array();

	/**
	 * Provider name => resolved bucketed CIDR structure.
	 *
	 * Each value is array('by_octet' => array(int => array), 'broad' => array).
	 * Populated lazily from $sources on first access per provider.
	 *
	 * @var array
	 */
	private $ranges = array();

	/**
	 * Register a set of providers and their CIDR lists.
	 *
	 * Sources are stored as-is; file I/O is deferred until the first
	 * find()/matchesProvider()/findAll() call so allowlisted requests
	 * skip the includes entirely.
	 *
	 * @param array $providers Map of provider_name => (array of CIDRs | path to PHP file).
	 */
	public function __construct( $providers = array() ) {
		if ( ! is_array( $providers ) ) {
			return;
		}
		foreach ( $providers as $name => $source ) {
			// In-memory arrays: used by unit tests for ergonomic fixture wiring.
			if ( is_array( $source ) ) {
				$this->ranges[ (string) $name ] = self::bucketCidrs( $source );
			} else {
				$this->sources[ (string) $name ] = $source;
			}
		}
	}

	/**
	 * Return the first provider whose CIDRs contain $ip, or null.
	 *
	 * @param string $ip IP address to look up.
	 * @return string|null
	 */
	public function find( $ip ) {
		$this->resolveAll();
		foreach ( $this->ranges as $name => $cidrs ) {
			if ( CidrMatcher::matchesAnyBucketed( $ip, $cidrs ) ) {
				return $name;
			}
		}
		return null;
	}

	/**
	 * Return every provider whose CIDRs contain $ip.
	 *
	 * @param string $ip IP address to look up.
	 * @return array
	 */
	public function findAll( $ip ) {
		$this->resolveAll();
		$hits = array();
		foreach ( $this->ranges as $name => $cidrs ) {
			if ( CidrMatcher::matchesAnyBucketed( $ip, $cidrs ) ) {
				$hits[] = $name;
			}
		}
		return $hits;
	}

	/**
	 * Check whether $ip falls within the CIDRs registered for $provider.
	 *
	 * Named matchesProvider rather than matches to avoid sibling-class
	 * confusion with CidrMatcher::matches, which takes a CIDR string as its
	 * second argument rather than a provider key.
	 *
	 * @param string $ip       IP address to check.
	 * @param string $provider Registered provider name.
	 * @return bool
	 */
	public function matchesProvider( $ip, $provider ) {
		$this->resolveProvider( $provider );
		if ( ! isset( $this->ranges[ $provider ] ) ) {
			return false;
		}
		return CidrMatcher::matchesAnyBucketed( $ip, $this->ranges[ $provider ] );
	}

	/**
	 * Enumerate registered provider names.
	 *
	 * Order is not guaranteed — array-backed providers may appear before
	 * file-backed ones, and the order may shift after the first lookup
	 * resolves deferred sources.
	 *
	 * @return array Registered provider names (unordered).
	 */
	public function providers() {
		return array_unique( array_merge( array_keys( $this->ranges ), array_keys( $this->sources ) ) );
	}

	/**
	 * Resolve a single deferred provider from $sources into $ranges.
	 *
	 * @param string $provider Provider name.
	 * @return void
	 */
	private function resolveProvider( $provider ) {
		if ( ! isset( $this->sources[ $provider ] ) ) {
			return;
		}
		$this->ranges[ $provider ] = BundledData::loadBucketedFile( $this->sources[ $provider ] );
		unset( $this->sources[ $provider ] );
	}

	/**
	 * Resolve all remaining deferred providers.
	 *
	 * @return void
	 */
	private function resolveAll() {
		if ( empty( $this->sources ) ) {
			return;
		}
		foreach ( $this->sources as $name => $source ) {
			$this->ranges[ $name ] = BundledData::loadBucketedFile( $source );
		}
		$this->sources = array();
	}

	/**
	 * Bucket a flat CIDR list by first octet for use with CidrMatcher::matchesAnyBucketed().
	 *
	 * @param array $cidrs Flat list of CIDR strings.
	 * @return array Array with 'by_octet' and 'broad' keys.
	 */
	private static function bucketCidrs( $cidrs ) {
		$by_octet = array();
		$broad    = array();
		foreach ( $cidrs as $cidr ) {
			if ( ! is_string( $cidr ) ) {
				continue;
			}
			$slash = strpos( $cidr, '/' );
			if ( false === $slash ) {
				continue;
			}
			$prefix = (int) substr( $cidr, $slash + 1 );
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid addresses are silently skipped.
			$bin = @inet_pton( substr( $cidr, 0, $slash ) );
			if ( false === $bin ) {
				continue;
			}
			if ( 16 === strlen( $bin ) && "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff" === substr( $bin, 0, 12 ) ) {
				$bin     = substr( $bin, 12 );
				$prefix -= 96;
			}
			if ( $prefix < 8 ) {
				$broad[] = $cidr;
			} else {
				$by_octet[ ord( $bin[0] ) ][] = $cidr;
			}
		}
		return array(
			'by_octet' => $by_octet,
			'broad'    => $broad,
		);
	}
}
