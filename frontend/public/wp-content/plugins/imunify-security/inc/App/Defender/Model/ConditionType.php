<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender\Model;

/**
 * Condition type enum-like class.
 *
 * This class provides an enum-like functionality for condition types
 * that is compatible with PHP 5.6.
 */
class ConditionType {
	/**
	 * Field exists condition type.
	 *
	 * @var string
	 */
	const EXISTS = 'exists';

	/**
	 * Field does not exist condition type.
	 *
	 * @var string
	 *
	 * @since 4.1.0
	 */
	const NOT_EXISTS = 'not_exists';

	/**
	 * Field equals condition type.
	 *
	 * @var string
	 */
	const EQUALS = 'equals';

	/**
	 * Field contains condition type.
	 *
	 * @var string
	 */
	const CONTAINS = 'contains';

	/**
	 * Field regex condition type.
	 *
	 * @var string
	 */
	const REGEX = 'regex';

	/**
	 * XSS detection condition type.
	 *
	 * @var string
	 */
	const DETECT_XSS = 'detectXSS';

	/**
	 * SQL injection detection condition type.
	 *
	 * @var string
	 */
	const DETECT_SQLI = 'detectSQLi';

	/**
	 * Missing capability condition type.
	 *
	 * @var string
	 */
	const MISSING_CAPABILITY = 'missing_capability';

	/**
	 * Probabilistic trigger condition type.
	 *
	 * @var string
	 */
	const PROBABILISTIC = 'probabilistic';

	/**
	 * Not current user condition type.
	 *
	 * @var string
	 *
	 * @since 3.0.2
	 */
	const NOT_CURRENT_USER = 'not_current_user';

	/**
	 * Probe condition type.
	 *
	 * @var string
	 *
	 * @since 3.0.4
	 */
	const PROBE = 'probe';

	/**
	 * Get all available condition types.
	 *
	 * @return string[]
	 */
	public static function getValidTypes() {
		return array(
			self::EXISTS,
			self::NOT_EXISTS,
			self::EQUALS,
			self::CONTAINS,
			self::REGEX,
			self::DETECT_XSS,
			self::DETECT_SQLI,
			self::MISSING_CAPABILITY,
			self::PROBABILISTIC,
			self::NOT_CURRENT_USER,
			self::PROBE,
		);
	}

	/**
	 * Check if a condition type is valid.
	 *
	 * @param string $type Condition type to check.
	 *
	 * @return bool True if the condition type is valid, false otherwise.
	 */
	public static function isValid( $type ) {
		return in_array( $type, self::getValidTypes(), true );
	}

	/**
	 * Get the display name for a condition type.
	 *
	 * @param string $type Condition type.
	 *
	 * @return string Display name for the condition type.
	 */
	public static function getDisplayName( $type ) {
		switch ( $type ) {
			case self::EXISTS:
				return esc_html__( 'Field Exists', 'imunify-security' );
			case self::NOT_EXISTS:
				return esc_html__( 'Field Does Not Exist', 'imunify-security' );
			case self::EQUALS:
				return esc_html__( 'Field Equals', 'imunify-security' );
			case self::CONTAINS:
				return esc_html__( 'Field Contains', 'imunify-security' );
			case self::REGEX:
				return esc_html__( 'Field Regex', 'imunify-security' );
			case self::DETECT_XSS:
				return esc_html__( 'XSS Detection', 'imunify-security' );
			case self::DETECT_SQLI:
				return esc_html__( 'SQL Injection Detection', 'imunify-security' );
			case self::MISSING_CAPABILITY:
				return esc_html__( 'Missing Capability', 'imunify-security' );
			case self::PROBABILISTIC:
				return esc_html__( 'Probabilistic Trigger', 'imunify-security' );
			case self::NOT_CURRENT_USER:
				return esc_html__( 'Not Current User', 'imunify-security' );
			case self::PROBE:
				return esc_html__( 'Probe', 'imunify-security' );
			default:
				return '';
		}
	}

	/**
	 * Get the description for a condition type.
	 *
	 * @param string $type Condition type.
	 *
	 * @return string Description for the condition type.
	 */
	public static function getDescription( $type ) {
		switch ( $type ) {
			case self::EXISTS:
				return esc_html__( 'Checks if a field exists in GET or POST data.', 'imunify-security' );
			case self::NOT_EXISTS:
				return esc_html__( 'Checks that a field is absent from the request.', 'imunify-security' );
			case self::EQUALS:
				return esc_html__( 'Checks if a field equals a specific value after sanitization.', 'imunify-security' );
			case self::CONTAINS:
				return esc_html__( 'Checks if a field contains a specific value.', 'imunify-security' );
			case self::REGEX:
				return esc_html__( 'Checks if a field matches a regular expression pattern.', 'imunify-security' );
			case self::DETECT_XSS:
				return esc_html__( 'Detects XSS (Cross-Site Scripting) attacks in field data.', 'imunify-security' );
			case self::DETECT_SQLI:
				return esc_html__( 'Detects SQL injection attacks in field data.', 'imunify-security' );
			case self::MISSING_CAPABILITY:
				return esc_html__( 'Check if user is missing a specific WordPress capability', 'imunify-security' );
			case self::PROBABILISTIC:
				return esc_html__( 'Triggers with a configurable probability per request.', 'imunify-security' );
			case self::NOT_CURRENT_USER:
				return esc_html__( 'Checks if a request parameter user ID does not match the currently logged-in user.', 'imunify-security' );
			case self::PROBE:
				return esc_html__( 'Collects diagnostic data at a configurable interval (value = seconds, default 86400 = 24 h).', 'imunify-security' );
			default:
				return '';
		}
	}

	/**
	 * Get required fields for a condition type.
	 *
	 * @param string $type Condition type.
	 *
	 * @return string[] Required fields for the condition type.
	 */
	public static function getRequiredFields( $type ) {
		switch ( $type ) {
			case self::DETECT_XSS:
			case self::DETECT_SQLI:
			case self::EXISTS:
			case self::NOT_EXISTS:
				return array( 'name' );
			case self::CONTAINS:
			case self::REGEX:
			case self::EQUALS:
				return array( 'name', 'value' );
			case self::MISSING_CAPABILITY:
				return array( 'value' );
			case self::PROBABILISTIC:
				return array( 'value' );
			case self::NOT_CURRENT_USER:
				return array( 'name' );
			case self::PROBE:
				return array( 'name', 'value' );
			default:
				return array();
		}
	}
}
