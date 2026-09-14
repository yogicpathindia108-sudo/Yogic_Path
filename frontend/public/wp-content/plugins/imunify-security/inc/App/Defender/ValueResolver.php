<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender;

use CloudLinux\Imunify\App\Defender\Model\Condition;
use CloudLinux\Imunify\App\Defender\Model\ConditionSource;

/**
 * Resolves candidate values from a Request based on a parsed condition name.
 *
 * Handles the three resolution modes (field regex, scan-all, single field),
 * bracket-path navigation, URI decoding, and source dispatching.
 *
 * @since 3.0.0
 */
class ValueResolver {

	/**
	 * FILES sub-selectors that resolve to a value.
	 *
	 * Mirrors the cases handled by getFilesSubValue(); any other sub-selector
	 * resolves to null for every request.
	 *
	 * @since 4.1.0
	 *
	 * @var string[]
	 */
	const FILES_SUB_SELECTORS = array( 'name', 'filename', 'type', 'content' );

	/**
	 * Resolve candidate values for a condition from the request.
	 *
	 * Returns a mixed[] of values (strings or arrays) that should each be
	 * tested by the caller's matcher. The caller is responsible for type
	 * checking and leaf extraction on array values.
	 *
	 * @param Condition $condition The condition to resolve values for.
	 * @param Request   $request   Request object.
	 *
	 * @return array Candidate values to test.
	 */
	public function resolveValues( Condition $condition, Request $request ) {
		if ( ! $condition->hasRequiredFields() ) {
			return array();
		}

		$parsed = $condition->parseName();
		$source = $parsed['source'];
		$values = array();

		if ( null !== $parsed['field_regex'] ) {
			$regexValues = $this->getFieldValuesByRegex( $request, $source, $parsed['field_regex'] );

			if ( null !== $parsed['bracket_path'] ) {
				$values = $this->navigateBracketPathIntoValues( array_values( $regexValues ), $parsed['bracket_path'] );
			} else {
				$values = array_values( $regexValues );
			}
		} elseif ( null === $parsed['field'] && $this->isCollectionSource( $source ) ) {
			$values = array_values( $this->getAllSourceValues( $request, $source ) );
		} elseif ( ConditionSource::REQUEST_URI === $source ) {
			$values = array( $this->getDecodedUri( $request ) );
		} elseif ( null !== $parsed['bracket_path'] && self::bracketPathHasRegex( $parsed['bracket_path'] ) ) {
			$values = $this->resolveFieldWithRegexBrackets( $request, $parsed );
		} else {
			$value = $this->getFieldValue( $request, $parsed );

			if ( null !== $value ) {
				$values = array( $value );
			}
		}

		// ARGS_NAMES is a field-less source — always returns all key names regardless of parsed field.
		if ( ConditionSource::ARGS_NAMES === $source ) {
			return $request->getArgNames();
		}

		if ( ConditionSource::ARGS === $source && ! empty( $values ) ) {
			return self::decodeArgValues( $values );
		}

		return $values;
	}

	/**
	 * Get field value from request based on parsed condition name.
	 *
	 * Supports bracket-notation for nested PHP arrays (e.g., ARGS:param[key]).
	 * Resolution order: nested array traversal first, literal key fallback.
	 *
	 * @param Request $request Request object.
	 * @param array   $parsed  Parsed condition name from Condition::parseNameString().
	 *
	 * @return string|array<string, mixed>|null Field value or null if not found.
	 */
	public function getFieldValue( $request, $parsed ) {
		$source      = $parsed['source'];
		$field       = $parsed['field'];
		$bracketPath = isset( $parsed['bracket_path'] ) ? $parsed['bracket_path'] : null;
		$rawField    = isset( $parsed['raw_field'] ) ? $parsed['raw_field'] : null;

		switch ( $source ) {
			case ConditionSource::ARGS:
				if ( null === $field ) {
					return null;
				}
				if ( null !== $bracketPath ) {
					$value = $request->resolveNestedGet( $field, $bracketPath );
					if ( null === $value ) {
						$value = $request->resolveNestedPost( $field, $bracketPath );
					}
					if ( null !== $value ) {
						return $value;
					}
					$value = $request->get( $rawField );
					if ( null === $value ) {
						$value = $request->post( $rawField );
					}
					return $value;
				}
				$fieldValue = $request->get( $field );
				if ( null === $fieldValue ) {
					$fieldValue = $request->post( $field );
				}
				return $fieldValue;
			case ConditionSource::REQUEST_URI:
				return $this->getDecodedUri( $request );
			case ConditionSource::FILES:
				if ( null === $field ) {
					return null;
				}
				$filesParsed = self::parseFilesField( $field );
				if ( null !== $filesParsed['sub'] ) {
					return self::getFilesSubValue( $request, $filesParsed['field'], $filesParsed['sub'] );
				}
				return $request->getFile( $field );
			case ConditionSource::REQUEST_COOKIES:
				if ( null === $field ) {
					return null;
				}
				return $request->cookie( $field );
			case ConditionSource::REQUEST_HEADERS:
				if ( null === $field ) {
					return null;
				}
				return $request->getHeader( $field );
			default:
				return null;
		}
	}

	/**
	 * Parse FILES sub-selector from field name.
	 *
	 * FILES:async-upload:name  => field=async-upload, sub=name
	 * FILES:file:type          => field=file, sub=type
	 * FILES:file:content       => field=file, sub=content
	 * FILES:file               => field=file, sub=null (legacy exists-only)
	 *
	 * @since 3.0.2
	 *
	 * @param string $field The field string (part after first colon in condition name).
	 *
	 * @return array Array with 'field' and 'sub' keys.
	 */
	public static function parseFilesField( $field ) {
		$segments = explode( ':', $field, 2 );
		if ( 2 === count( $segments ) ) {
			return array(
				'field' => $segments[0],
				'sub'   => $segments[1],
			);
		}
		return array(
			'field' => $field,
			'sub'   => null,
		);
	}

	/**
	 * Get a FILES sub-value (name, filename, type, or content).
	 *
	 * @since 3.0.2
	 *
	 * @param Request $request Request object.
	 * @param string  $field   The file field key (e.g. 'async-upload').
	 * @param string  $sub     The sub-selector (name, filename, type, content).
	 *
	 * @return string|null The sub-value or null if not available.
	 */
	public static function getFilesSubValue( $request, $field, $sub ) {
		switch ( $sub ) {
			case 'name':
			case 'filename':
				return $request->getFileName( $field );
			case 'type':
				return $request->getFileType( $field );
			case 'content':
				return $request->getFileContent( $field );
			default:
				return null;
		}
	}

	/**
	 * Get all field values from request whose names match a regex pattern.
	 *
	 * @param Request $request    Request object.
	 * @param string  $source     Field source (e.g., ARGS, REQUEST_COOKIES).
	 * @param string  $fieldRegex Regex pattern for field names (without delimiters).
	 *
	 * @return array<string, mixed> Associative array of matching field name => value pairs.
	 */
	private function getFieldValuesByRegex( $request, $source, $fieldRegex ) {
		switch ( $source ) {
			case ConditionSource::ARGS:
				return $request->getMatchingArgs( $fieldRegex );
			case ConditionSource::REQUEST_COOKIES:
				return $request->getMatchingCookies( $fieldRegex );
			case ConditionSource::REQUEST_HEADERS:
				return $request->getMatchingHeaders( $fieldRegex );
			default:
				return array();
		}
	}

	/**
	 * Check whether a source represents a collection of named values.
	 *
	 * Collection sources (ARGS, REQUEST_COOKIES, REQUEST_HEADERS) contain
	 * multiple named fields and support scan-all semantics when no specific
	 * field is given.
	 *
	 * @param string $source Condition source constant.
	 *
	 * @return bool True if the source is a collection.
	 */
	private function isCollectionSource( $source ) {
		return in_array(
			$source,
			array( ConditionSource::ARGS, ConditionSource::REQUEST_COOKIES, ConditionSource::REQUEST_HEADERS ),
			true
		);
	}

	/**
	 * Get all values for a source (scan-all mode).
	 *
	 * @param Request $request Request object.
	 * @param string  $source  Condition source constant.
	 *
	 * @return array Associative array of field name => value pairs.
	 */
	private function getAllSourceValues( $request, $source ) {
		switch ( $source ) {
			case ConditionSource::ARGS:
				return $request->getAllArgs();
			case ConditionSource::REQUEST_COOKIES:
				return $request->getAllCookies();
			case ConditionSource::REQUEST_HEADERS:
				return $request->getAllHeaders();
			default:
				return array();
		}
	}

	/**
	 * Check whether a bracket path contains any regex segments.
	 *
	 * @since 3.0.0
	 *
	 * @param array $bracketPath Array of bracket-path segments.
	 *
	 * @return bool True if at least one segment is a /regex/ pattern.
	 */
	private static function bracketPathHasRegex( array $bracketPath ) {
		foreach ( $bracketPath as $segment ) {
			if ( preg_match( '#^/(.+)/$#', $segment ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Resolve a literal field value then navigate regex-aware bracket path.
	 *
	 * Used when the field name is literal but bracket segments contain /regex/.
	 *
	 * @since 3.0.0
	 *
	 * @param Request $request Request object.
	 * @param array   $parsed  Parsed condition name from Condition::parseNameString().
	 *
	 * @return array Resolved leaf values.
	 */
	private function resolveFieldWithRegexBrackets( Request $request, array $parsed ) {
		$source = $parsed['source'];
		$field  = $parsed['field'];

		if ( null === $field ) {
			return array();
		}

		$rootValue = null;
		switch ( $source ) {
			case ConditionSource::ARGS:
				$rootValue = $request->get( $field );
				if ( null === $rootValue ) {
					$rootValue = $request->post( $field );
				}
				break;
			case ConditionSource::REQUEST_COOKIES:
				$rootValue = $request->cookie( $field );
				break;
			case ConditionSource::REQUEST_HEADERS:
				$rootValue = $request->getHeader( $field );
				break;
			default:
				return array();
		}

		if ( null === $rootValue ) {
			return array();
		}

		return self::navigateBracketPath( $rootValue, $parsed['bracket_path'] );
	}

	/**
	 * Navigate a bracket path into each value from a regex-matched set.
	 *
	 * Each regex-matched value is expected to be an array. The bracket path
	 * segments are traversed into each value. Segments wrapped in /regex/
	 * are treated as regex patterns that match multiple keys at that level.
	 *
	 * @since 3.0.0
	 *
	 * @param array $values      Flat array of matched values.
	 * @param array $bracketPath Array of bracket-path segments.
	 *
	 * @return array Resolved leaf values after bracket navigation.
	 */
	private function navigateBracketPathIntoValues( array $values, array $bracketPath ) {
		$results = array();

		foreach ( $values as $value ) {
			$navigated = self::navigateBracketPath( $value, $bracketPath );
			foreach ( $navigated as $leaf ) {
				$results[] = $leaf;
			}
		}

		return $results;
	}

	/**
	 * Navigate a single value through bracket-path segments.
	 *
	 * Literal segments perform a direct array key lookup. Segments matching
	 * the /regex/ convention iterate over keys at that level.
	 *
	 * @since 3.0.0
	 *
	 * @param mixed $value       The value to navigate into.
	 * @param array $bracketPath Array of bracket-path segments.
	 *
	 * @return array Resolved leaf values (may contain strings or arrays).
	 */
	private static function navigateBracketPath( $value, array $bracketPath ) {
		$current = array( $value );

		foreach ( $bracketPath as $segment ) {
			$next = array();

			if ( preg_match( '#^/(.+)/$#', $segment, $m ) ) {
				$regex = '#^(?:' . str_replace( '#', '\\#', $m[1] ) . ')$#';
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid regex silently skipped.
				if ( false === @preg_match( $regex, '' ) ) {
					return array();
				}

				foreach ( $current as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					foreach ( $item as $key => $val ) {
						if ( preg_match( $regex, (string) $key ) ) {
							$next[] = $val;
						}
					}
				}
			} else {
				foreach ( $current as $item ) {
					if ( is_array( $item ) && isset( $item[ $segment ] ) ) {
						$next[] = $item[ $segment ];
					}
				}
			}

			if ( empty( $next ) ) {
				return array();
			}

			$current = $next;
		}

		return $current;
	}

	/**
	 * Apply an extra URL-decode pass to resolved ARGS values.
	 *
	 * PHP's $_GET/$_POST parsing performs a single urldecode. This method
	 * applies one additional urldecode to match the double-decode already
	 * used for REQUEST_URI, closing the double-encoding evasion gap.
	 *
	 * @since 3.0.2
	 *
	 * @param array $values Resolved ARGS values (strings or nested arrays).
	 *
	 * @return array Values with one additional URL-decode applied.
	 */
	private static function decodeArgValues( array $values ) {
		return array_map( array( __CLASS__, 'decodeArgValue' ), $values );
	}

	/**
	 * URL-decode a single ARGS value, recursing into arrays.
	 *
	 * Non-string scalars (int, float, bool) are cast to their string form so
	 * the downstream match operators (matchEquals / matchContains / matchRegex
	 * / detectXSS / detectSQLi) see a consistent string type. Without this
	 * cast, JSON-bodied REST requests such as `{"user_id": 1, "role": 5}`
	 * would produce PHP-int leaves that Request::extractLeafValues()
	 * silently drops (the walker collects only is_string() values), so a rule
	 * like `detectSQLi on ARGS:role` would evaluate to false regardless of
	 * payload content. null is coerced to empty string for the same reason.
	 *
	 * @since 3.0.2
	 *
	 * @param mixed $value The value to decode.
	 *
	 * @return mixed Decoded value (string, array, or unchanged object).
	 */
	private static function decodeArgValue( $value ) {
		if ( is_string( $value ) ) {
			return urldecode( $value );
		}

		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'decodeArgValue' ), $value );
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		if ( null === $value ) {
			return '';
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		return $value;
	}

	/**
	 * Get the decoded request URI for condition evaluation.
	 *
	 * Applies urldecode() twice to defend against both single-encoded
	 * and double-encoded URI bypass attempts (e.g., %2F and %252F).
	 *
	 * @param Request $request Request object.
	 *
	 * @return string The decoded URI.
	 */
	private function getDecodedUri( $request ) {
		return urldecode( urldecode( $request->getUri() ) );
	}
}
