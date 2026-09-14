<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender\Model;

/**
 * ConditionSource enum-like class.
 *
 * Defines valid condition source types for security rules.
 *
 * @since 2.1.0
 */
class ConditionSource {
	/**
	 * Request URI source.
	 *
	 * @var string
	 */
	const REQUEST_URI = 'REQUEST_URI';

	/**
	 * GET/POST arguments source.
	 *
	 * @var string
	 */
	const ARGS = 'ARGS';

	/**
	 * File upload fields source.
	 *
	 * @var string
	 */
	const FILES = 'FILES';

	/**
	 * Request cookies source.
	 *
	 * @var string
	 */
	const REQUEST_COOKIES = 'REQUEST_COOKIES';

	/**
	 * Request headers source.
	 *
	 * @var string
	 */
	const REQUEST_HEADERS = 'REQUEST_HEADERS';

	/**
	 * GET/POST argument key names source.
	 *
	 * @var string
	 */
	const ARGS_NAMES = 'ARGS_NAMES';

	/**
	 * Get all valid condition sources.
	 *
	 * @return array Array of valid condition sources.
	 */
	public static function getValidSources() {
		return array(
			self::REQUEST_URI,
			self::ARGS,
			self::FILES,
			self::REQUEST_COOKIES,
			self::REQUEST_HEADERS,
			self::ARGS_NAMES,
		);
	}

	/**
	 * Check if a condition source is valid.
	 *
	 * @param string $source Condition source to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function isValid( $source ) {
		return in_array( $source, self::getValidSources(), true );
	}

	/**
	 * Get condition source description.
	 *
	 * @param string $source Condition source.
	 *
	 * @return string Source description.
	 */
	public static function getDescription( $source ) {
		$descriptions = array(
			self::REQUEST_URI     => 'Request URI path',
			self::ARGS            => 'GET/POST parameters',
			self::FILES           => 'File upload fields',
			self::REQUEST_COOKIES => 'Request cookies',
			self::REQUEST_HEADERS => 'HTTP headers',
			self::ARGS_NAMES      => 'GET/POST parameter key names',
		);

		return isset( $descriptions[ $source ] ) ? $descriptions[ $source ] : 'Unknown source';
	}
}
