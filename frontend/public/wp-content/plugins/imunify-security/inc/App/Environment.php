<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App;

/**
 * Resolves the Sentry reporting environment from wp-config.php constants.
 *
 * Reads IMUNIFY_SECURITY_SENTRY_ENV and normalizes the value to one of the
 * canonical environments (prod, dev, test). Common aliases like "production",
 * "development", and "devel" are accepted and mapped automatically.
 * Falls back to "prod" when the constant is missing or has an unrecognized value.
 *
 * @since 3.0.0
 */
class Environment {

	const PROD = 'prod';
	const DEV  = 'dev';
	const TEST = 'test';

	const ALLOWED = array(
		self::PROD,
		self::DEV,
		self::TEST,
	);

	const ALIASES = array(
		'production'  => self::PROD,
		'development' => self::DEV,
		'devel'       => self::DEV,
	);

	/**
	 * Resolve the current environment.
	 *
	 * @return string One of the ALLOWED values (prod, dev, test).
	 */
	public function get() {
		$value = $this->readConstant();
		if ( ! $value ) {
			return self::PROD;
		}

		if ( in_array( $value, self::ALLOWED, true ) ) {
			return $value;
		}

		$aliases = self::ALIASES;
		if ( isset( $aliases[ $value ] ) ) {
			return $aliases[ $value ];
		}

		return self::PROD;
	}

	/**
	 * Read the IMUNIFY_SECURITY_SENTRY_ENV constant value.
	 *
	 * @return string|null The constant value, or null if not defined.
	 */
	public function readConstant() {
		if ( defined( 'IMUNIFY_SECURITY_SENTRY_ENV' ) && is_string( IMUNIFY_SECURITY_SENTRY_ENV ) && '' !== IMUNIFY_SECURITY_SENTRY_ENV ) {
			return IMUNIFY_SECURITY_SENTRY_ENV;
		}

		return null;
	}
}
