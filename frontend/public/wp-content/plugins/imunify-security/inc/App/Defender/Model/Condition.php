<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender\Model;

/**
 * Condition model class.
 *
 * Represents a condition within a security rule.
 *
 * @since 2.1.0
 */
class Condition {
	const REGEX_FIELD_PATTERN          = '#^/(.+)/$#';
	const PARTIAL_REGEX_SPLIT_PATTERN  = '#(/[^/]+/)#';
	const TRAILING_BRACKET_PATTERN     = '/^(.+)\[([^\]]*)\]$/';
	const MAX_FIELD_LENGTH_FOR_BRACKET = 1024;

	/**
	 * Condition name (e.g., REQUEST_URI, ARGS:page, FILES:fileToUpload).
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Condition type (e.g., contains, regex, detectXSS).
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Condition value for comparison.
	 *
	 * @var string|null
	 */
	private $value;

	/**
	 * Whether an absent field should be matched as an empty value.
	 *
	 * @since 4.1.0
	 *
	 * @var bool
	 */
	private $matchAbsent = false;

	/**
	 * Create a condition from an array.
	 *
	 * @param array $data Condition data.
	 *
	 * @return Condition
	 */
	public static function fromArray( $data ) {
		$condition              = new self();
		$condition->name        = isset( $data['name'] ) ? $data['name'] : '';
		$condition->type        = isset( $data['type'] ) ? $data['type'] : '';
		$condition->value       = isset( $data['value'] ) ? $data['value'] : null;
		$condition->matchAbsent = ! empty( $data['match_absent'] );

		return $condition;
	}

	/**
	 * Convert condition to array.
	 *
	 * @return array
	 */
	public function toArray() {
		$data = array(
			'name' => $this->name,
			'type' => $this->type,
		);

		if ( null !== $this->value ) {
			$data['value'] = $this->value;
		}

		if ( $this->matchAbsent ) {
			$data['match_absent'] = true;
		}

		return $data;
	}

	/**
	 * Get condition name.
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Get condition type.
	 *
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Get condition value.
	 *
	 * @return string|null
	 */
	public function getValue() {
		return $this->value;
	}

	/**
	 * Whether an absent field should be matched as an empty value.
	 *
	 * @since 4.1.0
	 *
	 * @return bool
	 */
	public function matchesAbsent() {
		return $this->matchAbsent;
	}

	/**
	 * Check if condition type is valid.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function isValidType() {
		return ConditionType::isValid( $this->type );
	}

	/**
	 * Check if condition has required fields.
	 *
	 * @return bool True if has required fields, false otherwise.
	 */
	public function hasRequiredFields() {
		return ! empty( $this->name ) && ! empty( $this->type );
	}

	/**
	 * Parse condition name to extract source and specific field.
	 *
	 * @return array Array with 'source' and 'field' keys.
	 */
	public function parseName() {
		return self::parseNameString( $this->name );
	}

	/**
	 * Parse a condition name string to extract source, field, and optional field regex.
	 *
	 * Supports:
	 * - Full regex for field names: ARGS:/field_a|field_b/
	 * - Partial regex embedded in field names: REQUEST_HEADERS:headername/\d+/
	 * - PHP bracket notation for nested array access: ARGS:param[key][subkey]
	 * - Regex combined with brackets: ARGS:/field_a|field_b/[key]
	 * - Partial regex combined with brackets: ARGS:param/\d+/[key]
	 * - Regex inside bracket segments: ARGS:param[/\d+/]
	 *
	 * Bracket notation is only parsed when the field portion (after SOURCE:) does not
	 * exceed MAX_FIELD_LENGTH_FOR_BRACKET (1024) characters. Fields exceeding this
	 * limit are treated as literal names.
	 *
	 * Parsing order: brackets are extracted first (right-to-left), then the remaining
	 * base field is checked for full regex, partial regex, or treated as literal.
	 * This allows field_regex and bracket_path to coexist.
	 *
	 * @since 3.0.0
	 *
	 * @param string $name Condition name string.
	 *
	 * @return array {
	 *     @type string      $source       Source prefix (e.g. ARGS, REQUEST_URI).
	 *     @type string|null $field        Root field name (null when field_regex is set).
	 *     @type string|null $field_regex  Regex pattern for field name matching.
	 *     @type array|null  $bracket_path Path segments from bracket notation.
	 *     @type string|null $raw_field    Original unparsed field (set only when bracket_path is present).
	 * }
	 */
	public static function parseNameString( $name ) {
		$parts = explode( ':', $name, 2 );

		if ( count( $parts ) === 1 ) {
			return array(
				'source'       => $parts[0],
				'field'        => null,
				'field_regex'  => null,
				'bracket_path' => null,
				'raw_field'    => null,
			);
		}

		$field       = $parts[1];
		$fieldRegex  = null;
		$bracketPath = null;
		$rawField    = null;

		// Step 1: Extract trailing bracket segments (right-to-left).
		$baseField = $field;
		if ( strlen( $field ) <= self::MAX_FIELD_LENGTH_FOR_BRACKET ) {
			$segments  = array();
			$remaining = $field;
			while ( preg_match( self::TRAILING_BRACKET_PATTERN, $remaining, $bm ) ) {
				array_unshift( $segments, $bm[2] );
				$remaining = $bm[1];
			}
			if ( ! empty( $segments ) ) {
				$baseField   = $remaining;
				$bracketPath = $segments;
				$rawField    = $field;
			}
		}

		// Step 2: Check base field for full regex, then partial regex, then literal.
		if ( preg_match( self::REGEX_FIELD_PATTERN, $baseField, $matches ) ) {
			$fieldRegex = $matches[1];
			$field      = null;
		} else {
			$partialRegex = self::buildFieldRegexFromPartial( $baseField );
			if ( null !== $partialRegex ) {
				$fieldRegex = $partialRegex;
				$field      = null;
			} else {
				$field = $baseField;
			}
		}

		return array(
			'source'       => $parts[0],
			'field'        => $field,
			'field_regex'  => $fieldRegex,
			'bracket_path' => $bracketPath,
			'raw_field'    => $rawField,
		);
	}

	/**
	 * Build a full field regex from a field containing embedded /regex/ segments.
	 *
	 * Splits the field on paired slash-delimited regex segments. Literal parts
	 * are escaped with preg_quote, regex parts are kept verbatim. Returns null
	 * when the field contains no embedded regex (e.g. plain literal or single slash).
	 *
	 * @since 3.0.0
	 *
	 * @param string $field The base field string (brackets already stripped).
	 *
	 * @return string|null The combined regex, or null if no partial regex was found.
	 */
	private static function buildFieldRegexFromPartial( $field ) {
		$splitParts = preg_split( self::PARTIAL_REGEX_SPLIT_PATTERN, $field, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( count( $splitParts ) <= 1 ) {
			return null;
		}

		$regex = '';
		foreach ( $splitParts as $i => $part ) {
			if ( 0 === $i % 2 ) {
				$regex .= preg_quote( $part, '#' );
			} else {
				$regex .= substr( $part, 1, -1 );
			}
		}

		return $regex;
	}
}
