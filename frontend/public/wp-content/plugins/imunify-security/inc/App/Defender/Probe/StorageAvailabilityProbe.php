<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 *
 * @since 3.0.4
 */

namespace CloudLinux\Imunify\App\Defender\Probe;

/**
 * Probes storage backend availability and read/write performance.
 *
 * Tests APCu, Memcached, and Redis using pure PHP extensions — no WordPress
 * object cache layer, no wp_cache_* functions, no site-specific configuration.
 *
 * @since 3.0.4
 */
class StorageAvailabilityProbe {

	/**
	 * Connection timeout in seconds for network backends.
	 *
	 * @var float
	 */
	const CONNECT_TIMEOUT = 0.1;

	/**
	 * Probabilistic sampling denominator (1 in N requests).
	 *
	 * @var int
	 */
	const SAMPLING_DENOMINATOR = 1000;

	/**
	 * Known probe names that this class can handle.
	 *
	 * @var array
	 */
	private static $knownProbes = array( 'storage' );

	/**
	 * Check if a probe name is supported.
	 *
	 * @param string $name Probe name.
	 *
	 * @return bool
	 */
	public static function isKnownProbe( $name ) {
		return in_array( $name, self::$knownProbes, true );
	}

	/**
	 * Run the storage availability probe.
	 *
	 * @return string Compact probe result string for the incident message.
	 */
	public function run() {
		do_action( 'imunify_security_set_error_handler' );

		$parts = array();

		$parts[] = $this->probeApcu();
		$parts[] = $this->probeMemcached();
		$parts[] = $this->probeRedis();
		$parts[] = 'sapi:' . php_sapi_name();

		do_action( 'imunify_security_restore_error_handler' );

		return implode( ',', $parts );
	}

	/**
	 * Probe APCu availability and performance.
	 *
	 * @return string Result in format apcu:available:method:write_us:read_us:version or apcu:0:shm:error.
	 */
	private function probeApcu() {
		if ( ! function_exists( 'apcu_store' ) ) {
			return 'apcu:0:shm:ext_not_loaded';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- unique key for probe, not security
		$key = 'imunify_probe_' . mt_rand( 100000, 999999 );
		$val = 'probe_test';

		try {
			$writeStart = microtime( true );
			$written    = apcu_store( $key, $val );
			$writeEnd   = microtime( true );

			if ( ! $written ) {
				return 'apcu:0:shm:store_failed';
			}

			$readStart = microtime( true );
			$readVal   = apcu_fetch( $key );
			$readEnd   = microtime( true );

			apcu_delete( $key );

			if ( $readVal !== $val ) {
				return 'apcu:0:shm:read_mismatch';
			}

			$writeUs = (int) round( ( $writeEnd - $writeStart ) * 1000000 );
			$readUs  = (int) round( ( $readEnd - $readStart ) * 1000000 );
			$version = phpversion( 'apcu' );

			return sprintf( 'apcu:1:shm:%d:%d:%s', $writeUs, $readUs, $version );
		} catch ( \Exception $e ) {
			return 'apcu:0:shm:' . $this->sanitizeError( $e->getMessage() );
		}
	}

	/**
	 * Probe Memcached availability and performance using fallback chain.
	 *
	 * @return string Result string.
	 */
	private function probeMemcached() {
		if ( ! class_exists( 'Memcached' ) ) {
			return 'memcached:0:none:ext_not_loaded';
		}

		$endpoints = array(
			'tcp'  => array(
				'host' => '127.0.0.1',
				'port' => 11211,
			),
			'sock' => array(
				'host' => '/var/run/memcached/memcached.sock',
				'port' => 0,
			),
			'tmp'  => array(
				'host' => '/tmp/memcached.sock',
				'port' => 0,
			),
		);

		$failures = array();
		foreach ( $endpoints as $method => $endpoint ) {
			list( $result, $error ) = $this->tryMemcached( $method, $endpoint['host'], $endpoint['port'] );
			if ( null !== $result ) {
				return $result;
			}
			$failures[] = $method . '=' . $error;
		}

		return 'memcached:0:none:' . implode( '+', $failures );
	}

	/**
	 * Try a single Memcached connection.
	 *
	 * @param string $method   Connection method name (tcp/sock/tmp).
	 * @param string $host     Host or socket path.
	 * @param int    $port     Port number (0 for sockets).
	 *
	 * @return array Two-element array: [0] = result string on success or null, [1] = failure code or null.
	 */
	private function tryMemcached( $method, $host, $port ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- unique key for probe, not security
		$key = 'imunify_probe_' . mt_rand( 100000, 999999 );
		$val = 'probe_test';

		try {
			$mc = new \Memcached();
			// OPT_CONNECT_TIMEOUT is in ms; OPT_SEND/RECV_TIMEOUT are in µs.
			$mc->setOption( \Memcached::OPT_CONNECT_TIMEOUT, (int) ( self::CONNECT_TIMEOUT * 1000 ) );
			$mc->setOption( \Memcached::OPT_SEND_TIMEOUT, (int) ( self::CONNECT_TIMEOUT * 1000000 ) );
			$mc->setOption( \Memcached::OPT_RECV_TIMEOUT, (int) ( self::CONNECT_TIMEOUT * 1000000 ) );
			$mc->addServer( $host, $port );

			$writeStart = microtime( true );
			$written    = $mc->set( $key, $val, 60 );
			$writeEnd   = microtime( true );

			if ( ! $written ) {
				$mc->quit();
				return array( null, 'set_failed' );
			}

			$readStart = microtime( true );
			$readVal   = $mc->get( $key );
			$readEnd   = microtime( true );

			$mc->delete( $key );
			$mc->quit();

			if ( $readVal !== $val ) {
				return array( null, 'get_mismatch' );
			}

			$writeUs = (int) round( ( $writeEnd - $writeStart ) * 1000000 );
			$readUs  = (int) round( ( $readEnd - $readStart ) * 1000000 );
			$version = phpversion( 'memcached' );

			return array( sprintf( 'memcached:1:%s:%d:%d:%s', $method, $writeUs, $readUs, $version ), null );
		} catch ( \Exception $e ) {
			return array( null, 'conn_failed' );
		}
	}

	/**
	 * Probe Redis availability and performance using fallback chain.
	 *
	 * @return string Result string.
	 */
	private function probeRedis() {
		if ( ! class_exists( 'Redis' ) ) {
			return 'redis:0:none:ext_not_loaded';
		}

		$endpoints = array(
			'tcp'  => array(
				'host' => '127.0.0.1',
				'port' => 6379,
			),
			'sock' => array(
				'host' => '/var/run/redis/redis-server.sock',
				'port' => 0,
			),
			'tmp'  => array(
				'host' => '/tmp/redis.sock',
				'port' => 0,
			),
		);

		$failures = array();
		foreach ( $endpoints as $method => $endpoint ) {
			list( $result, $error ) = $this->tryRedis( $method, $endpoint['host'], $endpoint['port'] );
			if ( null !== $result ) {
				return $result;
			}
			$failures[] = $method . '=' . $error;
		}

		return 'redis:0:none:' . implode( '+', $failures );
	}

	/**
	 * Try a single Redis connection.
	 *
	 * @param string $method Connection method name (tcp/sock/tmp).
	 * @param string $host   Host or socket path.
	 * @param int    $port   Port number (0 for sockets).
	 *
	 * @return array Two-element array: [0] = result string on success or null, [1] = failure code or null.
	 */
	private function tryRedis( $method, $host, $port ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- unique key for probe, not security
		$key = 'imunify_probe_' . mt_rand( 100000, 999999 );
		$val = 'probe_test';

		try {
			$redis = new \Redis();

			if ( $port > 0 ) {
				$connected = $redis->connect( $host, $port, self::CONNECT_TIMEOUT );
			} else {
				$connected = $redis->connect( $host, 0, self::CONNECT_TIMEOUT );
			}

			if ( ! $connected ) {
				return array( null, 'conn_failed' );
			}

			$writeStart = microtime( true );
			$written    = $redis->set( $key, $val, 60 );
			$writeEnd   = microtime( true );

			if ( ! $written ) {
				$redis->close();
				return array( null, 'set_failed' );
			}

			$readStart = microtime( true );
			$readVal   = $redis->get( $key );
			$readEnd   = microtime( true );

			$redis->del( $key );
			$redis->close();

			if ( $readVal !== $val ) {
				return array( null, 'get_mismatch' );
			}

			$writeUs = (int) round( ( $writeEnd - $writeStart ) * 1000000 );
			$readUs  = (int) round( ( $readEnd - $readStart ) * 1000000 );
			$version = phpversion( 'redis' );

			return array( sprintf( 'redis:1:%s:%d:%d:%s', $method, $writeUs, $readUs, $version ), null );
		} catch ( \Exception $e ) {
			return array( null, 'conn_failed' );
		}
	}

	/**
	 * Sanitize error message for inclusion in compact probe string.
	 *
	 * @param string $message Error message.
	 *
	 * @return string Sanitized error (spaces replaced, truncated).
	 */
	private function sanitizeError( $message ) {
		$clean = preg_replace( '/[^a-zA-Z0-9_]/', '_', $message );
		return substr( $clean, 0, 40 );
	}
}
