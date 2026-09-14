<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Factory that probes MEMORY engine availability and creates storage pairs.
 *
 * Returns an array with 'counter' (CounterStorageInterface) and 'block'
 * (BlockStorageInterface) keys. If the MEMORY engine is unavailable,
 * both degrade to null implementations (fail-open).
 *
 * Detection is memoised for the request lifetime.
 *
 * @since 4.0.0
 */
class DbStorageFactory {

	/**
	 * Memoised detection result for the default path.
	 *
	 * @var array|null
	 */
	private static $cached = null;

	/**
	 * Detect storage availability and return a pair of implementations.
	 *
	 * @param object $wpdb WordPress database abstraction.
	 * @return array{counter: CounterStorageInterface, block: BlockStorageInterface}
	 */
	public static function detect( $wpdb ) {
		if ( null !== self::$cached ) {
			return self::$cached;
		}

		if ( self::isMemoryEngineAvailable( $wpdb ) ) {
			$result = array(
				'counter' => new MemoryCounterStorage( $wpdb ),
				'block'   => new MemoryBlockStorage( $wpdb ),
			);
		} else {
			StorageEventBuffer::record(
				'MEMORY engine unavailable — bot rate limiting disabled (NullStorage fallback)',
				'bot_null_fallback',
				array( 'bot_storage_null_fallback' )
			);
			$result = self::nullPair();
		}

		self::$cached = $result;
		return $result;
	}

	/**
	 * Return a null-storage pair (fail-open).
	 *
	 * @return array{counter: CounterStorageInterface, block: BlockStorageInterface}
	 */
	public static function nullPair() {
		return array(
			'counter' => new NullCounterStorage(),
			'block'   => new NullBlockStorage(),
		);
	}

	/**
	 * Drop the memoised detection result.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$cached = null;
	}

	/**
	 * Probe whether the MEMORY engine is available.
	 *
	 * @param object $wpdb WordPress database abstraction.
	 * @return bool
	 */
	private static function isMemoryEngineAvailable( $wpdb ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		try {
			$engines = $wpdb->get_results( 'SHOW ENGINES' );
		} catch ( \Exception $e ) {
			return false;
		}

		if ( ! is_array( $engines ) ) {
			return false;
		}

		foreach ( $engines as $row ) {
			if ( isset( $row->Engine ) && 'MEMORY' === strtoupper( $row->Engine ) ) {
				return isset( $row->Support )
					&& in_array( strtoupper( $row->Support ), array( 'YES', 'DEFAULT' ), true );
			}
		}
		return false;
	}
}
