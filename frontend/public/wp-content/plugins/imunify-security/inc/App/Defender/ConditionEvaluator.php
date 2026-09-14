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
use CloudLinux\Imunify\App\Defender\Model\ConditionType;
use CloudLinux\Imunify\App\Defender\Probe\StorageAvailabilityProbe;

/**
 * Condition evaluator class.
 *
 * Coordinates value resolution and matching for security rule conditions.
 *
 * @since 2.1.0
 */
class ConditionEvaluator {

	/**
	 * Transient key prefix for probe firing throttles.
	 */
	const PROBE_TRANSIENT_PREFIX = 'imunify_probe_';

	/**
	 * Value resolver instance.
	 *
	 * @var ValueResolver
	 */
	private $valueResolver;

	/**
	 * Condition matcher instance.
	 *
	 * @var ConditionMatcher
	 */
	private $matcher;

	/**
	 * The last failed condition during evaluation.
	 *
	 * @var Condition
	 */
	private $failedCondition = null;

	/**
	 * Probe data collected during condition evaluation.
	 *
	 * @since 3.0.4
	 *
	 * @var string|null
	 */
	private $probeData = null;

	/**
	 * Sampling denominator for probe conditions (1 in N).
	 *
	 * @since 3.0.4
	 *
	 * @var int
	 */
	private $samplingDenominator;

	/**
	 * Constructor.
	 *
	 * @param ValueResolver|null    $valueResolver        Optional value resolver (created internally if null).
	 * @param ConditionMatcher|null $matcher               Optional condition matcher (created internally if null).
	 * @param int|null              $samplingDenominator   Optional sampling denominator override for probe conditions.
	 */
	public function __construct( $valueResolver = null, $matcher = null, $samplingDenominator = null ) {
		$this->valueResolver       = $valueResolver ? $valueResolver : new ValueResolver();
		$this->matcher             = $matcher ? $matcher : new ConditionMatcher();
		$this->samplingDenominator = null !== $samplingDenominator
			? $samplingDenominator
			: StorageAvailabilityProbe::SAMPLING_DENOMINATOR;
	}

	/**
	 * Evaluate a list of conditions.
	 *
	 * @param Condition[] $conditions Array of Condition objects.
	 * @param Request     $request    Request object.
	 *
	 * @return bool True if all conditions are met, false otherwise.
	 */
	public function evaluateConditions( $conditions, $request ) {
		if ( empty( $conditions ) ) {
			return true;
		}

		foreach ( $conditions as $condition ) {
			if ( ! $this->evaluateCondition( $condition, $request ) ) {
				$this->failedCondition = $condition;
				return false;
			}
		}

		return true;
	}

	/**
	 * Evaluate a single condition.
	 *
	 * @param Condition $condition The condition to evaluate.
	 * @param Request   $request   Request object.
	 *
	 * @return bool True if condition is met, false otherwise.
	 */
	private function evaluateCondition( $condition, $request ) {
		if ( ! $condition->isValidType() ) {
			return false;
		}

		switch ( $condition->getType() ) {
			case ConditionType::EXISTS:
				return $this->evaluateFieldExists( $condition, $request );
			case ConditionType::NOT_EXISTS:
				return $this->evaluateFieldNotExists( $condition, $request );
			case ConditionType::MISSING_CAPABILITY:
				return $this->evaluateMissingCapability( $condition, $request );
			case ConditionType::NOT_CURRENT_USER:
				return $this->evaluateNotCurrentUser( $condition, $request );
			case ConditionType::PROBABILISTIC:
				return $this->evaluateProbabilistic( $condition );
			case ConditionType::PROBE:
				return $this->evaluateProbe( $condition );
			default:
				return $this->evaluateWithMatcher( $condition, $request );
		}
	}

	/**
	 * Resolve values and test them against the appropriate matcher.
	 *
	 * @param Condition $condition The condition to evaluate.
	 * @param Request   $request   Request object.
	 *
	 * @return bool True if any resolved value satisfies the matcher.
	 */
	private function evaluateWithMatcher( $condition, $request ) {
		if ( ! $condition->hasRequiredFields() ) {
			return false;
		}

		$type = $condition->getType();

		if ( in_array( $type, array( ConditionType::EQUALS, ConditionType::CONTAINS, ConditionType::REGEX ), true )
			&& null === $condition->getValue()
		) {
			return false;
		}

		$values  = $this->valueResolver->resolveValues( $condition, $request );
		$matcher = $this->getMatcherCallback( $condition );

		if ( null === $matcher ) {
			return false;
		}

		if ( empty( $values ) && $condition->matchesAbsent() && self::absenceMatchesEmptyValue( $condition ) ) {
			$values = array( '' );
		}

		foreach ( $values as $value ) {
			if ( call_user_func( $matcher, $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a matcher callback for the given condition type.
	 *
	 * @param Condition $condition The condition.
	 *
	 * @return callable|null A callable accepting a single value, or null for unknown types.
	 */
	private function getMatcherCallback( $condition ) {
		$matcher = $this->matcher;

		switch ( $condition->getType() ) {
			case ConditionType::EQUALS:
				$expected = $condition->getValue();
				return function ( $value ) use ( $matcher, $expected ) {
					return $matcher->matchEquals( $value, $expected );
				};
			case ConditionType::CONTAINS:
				$needle = $condition->getValue();
				return function ( $value ) use ( $matcher, $needle ) {
					return $matcher->matchContains( $value, $needle );
				};
			case ConditionType::REGEX:
				$pattern = $condition->getValue();
				return function ( $value ) use ( $matcher, $pattern ) {
					return $matcher->matchRegex( $value, $pattern );
				};
			case ConditionType::DETECT_XSS:
				return array( $matcher, 'matchXSS' );
			case ConditionType::DETECT_SQLI:
				return array( $matcher, 'matchSQLi' );
			default:
				return null;
		}
	}

	/**
	 * Check whether a condition's absent field may be matched as an empty value.
	 *
	 * Limited to a single named header or cookie: those are the only fields
	 * whose absence is equivalent to an empty value for the WordPress backend
	 * that reads them. A regex or bracket-path name has no single field that
	 * could be absent, and the other sources carry their own absence semantics.
	 *
	 * @since 4.1.0
	 *
	 * @param Condition $condition The condition object.
	 *
	 * @return bool True if an absent field resolves to an empty value.
	 */
	private static function absenceMatchesEmptyValue( Condition $condition ) {
		$parsed = $condition->parseName();

		$sources = array( ConditionSource::REQUEST_HEADERS, ConditionSource::REQUEST_COOKIES );
		if ( ! in_array( $parsed['source'], $sources, true ) ) {
			return false;
		}

		return null !== $parsed['field']
			&& null === $parsed['field_regex']
			&& null === $parsed['bracket_path'];
	}

	/**
	 * Evaluate exists condition.
	 *
	 * @param Condition $condition The condition object.
	 * @param Request   $request   Request object.
	 *
	 * @return bool True if field exists, false otherwise.
	 */
	private function evaluateFieldExists( $condition, $request ) {
		if ( ! $condition->hasRequiredFields() ) {
			return false;
		}

		$parsed = $condition->parseName();
		$source = $parsed['source'];
		$field  = $parsed['field'];

		if ( null !== $parsed['field_regex'] ) {
			$values = $this->valueResolver->resolveValues( $condition, $request );
			return ! empty( $values );
		}

		if ( null !== $parsed['bracket_path'] && $this->bracketPathHasRegex( $parsed['bracket_path'] ) ) {
			$values = $this->valueResolver->resolveValues( $condition, $request );
			return ! empty( $values );
		}

		switch ( $source ) {
			case ConditionSource::ARGS:
				if ( null === $field ) {
					return ! empty( $request->getAllArgs() );
				}
				if ( null !== $parsed['bracket_path'] ) {
					$value = $request->resolveNestedGet( $field, $parsed['bracket_path'] );
					if ( null === $value ) {
						$value = $request->resolveNestedPost( $field, $parsed['bracket_path'] );
					}
					if ( null !== $value ) {
						return true;
					}
					return $request->hasGet( $parsed['raw_field'] ) || $request->hasPost( $parsed['raw_field'] );
				}
				return $request->hasGet( $field ) || $request->hasPost( $field );
			case ConditionSource::FILES:
				if ( null === $field ) {
					return false;
				}
				$filesParsed = ValueResolver::parseFilesField( $field );
				if ( null === $filesParsed['sub'] ) {
					return $request->hasFile( $filesParsed['field'] );
				}
				$subValue = ValueResolver::getFilesSubValue( $request, $filesParsed['field'], $filesParsed['sub'] );
				return null !== $subValue && '' !== $subValue;
			case ConditionSource::REQUEST_COOKIES:
				if ( null === $field ) {
					return ! empty( $request->getAllCookies() );
				}
				return $request->hasCookie( $field );
			case ConditionSource::REQUEST_HEADERS:
				if ( null === $field ) {
					return ! empty( $request->getAllHeaders() );
				}
				return $request->hasHeader( $field );
			case ConditionSource::REQUEST_URI:
				return ! empty( $request->getUri() );
			case ConditionSource::ARGS_NAMES:
				// Mirror getArgNames() (not hasAnyArgs()) so &ARGS_NAMES existence
				// agrees with the values: the hidden RAW_BODY_KEY sentinel must not
				// count as a name.
				return ! empty( $request->getArgNames() );
			default:
				return false;
		}
	}

	/**
	 * Evaluate not_exists condition.
	 *
	 * Only negates an existence check that the request can actually decide;
	 * every other shape returns false. Negating an existence check that is
	 * false for every request would turn the rule into a match-everything
	 * rule.
	 *
	 * FILES is decided by presence instead, because evaluateFieldExists()
	 * reads the sub-value: negating it would report a sent upload with an
	 * empty name, type or body as absent. exists() keeps its value-based
	 * behaviour, so for a present-but-empty sub-value both checks are false.
	 *
	 * @since 4.1.0
	 *
	 * @param Condition $condition The condition object.
	 * @param Request   $request   Request object.
	 *
	 * @return bool True if the field is absent from the request.
	 */
	private function evaluateFieldNotExists( Condition $condition, Request $request ) {
		if ( ! $condition->hasRequiredFields() ) {
			return false;
		}

		$parsed = $condition->parseName();
		if ( ! $this->existenceDependsOnRequest( $parsed ) ) {
			return false;
		}

		if ( ConditionSource::FILES === $parsed['source'] ) {
			return $this->fileFieldIsAbsent( $request, $parsed['field'] );
		}

		return ! $this->evaluateFieldExists( $condition, $request );
	}

	/**
	 * Check whether a request carries no upload for a FILES field.
	 *
	 * A UPLOAD_ERR_NO_FILE entry counts as absent: the browser sent the part
	 * for a file input the user left empty, which is what a rule author means
	 * by "no file was uploaded". Every other error, including a rejected or
	 * truncated upload, is a present upload. A multi-file field carries one
	 * code per part, nested for a nested input name, and is absent only when
	 * every part is UPLOAD_ERR_NO_FILE, so an empty multiple-file input reads
	 * the same as an empty single one.
	 *
	 * @since 4.1.0
	 *
	 * @param Request $request Request object.
	 * @param string  $field   FILES field, sub-selector included.
	 *
	 * @return bool True if the request carries no upload for the field.
	 */
	private function fileFieldIsAbsent( Request $request, $field ) {
		$filesParsed = ValueResolver::parseFilesField( $field );
		$file        = $request->getFile( $filesParsed['field'] );

		if ( null === $file ) {
			return true;
		}

		if ( ! isset( $file['error'] ) ) {
			// No error code to read: the entry itself is the presence signal.
			return false;
		}

		if ( is_array( $file['error'] ) ) {
			return $this->everyPartIsEmpty( $file['error'] );
		}

		return UPLOAD_ERR_NO_FILE === (int) $file['error'];
	}

	/**
	 * Check whether every part of a multi-file upload status reports no file.
	 *
	 * Recurses because a nested input name such as portfolio[images][] puts the
	 * per-part codes one level deeper than a flat doc[] does.
	 *
	 * @since 4.1.0
	 *
	 * @param array $errors Upload status of each part, possibly nested.
	 *
	 * @return bool True if no part carries an upload.
	 */
	private function everyPartIsEmpty( array $errors ) {
		foreach ( $errors as $error ) {
			$partIsEmpty = is_array( $error )
				? $this->everyPartIsEmpty( $error )
				: UPLOAD_ERR_NO_FILE === (int) $error;

			if ( ! $partIsEmpty ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check whether an existence check reads the given name from the request.
	 *
	 * Restricted to a named field of a field-addressable source. REQUEST_URI and
	 * ARGS_NAMES ignore the field entirely, and a bare source name only asks
	 * whether the whole source is empty — which is either never true
	 * (REQUEST_URI, REQUEST_HEADERS) or true of ordinary traffic (ARGS,
	 * ARGS_NAMES, REQUEST_COOKIES), so neither is a usable absence signal.
	 * Beyond that this mirrors the dispatch in evaluateFieldExists(): regex
	 * field names and regex bracket paths resolve through ValueResolver, which
	 * only scans the collection sources, and FILES resolves only a field with
	 * no sub-selector or a known one. Keep in step with evaluateFieldExists()
	 * when a source gains or loses resolution support.
	 *
	 * @since 4.1.0
	 *
	 * @param array $parsed Parsed condition name.
	 *
	 * @return bool True if existence is decided by the request.
	 */
	private function existenceDependsOnRequest( array $parsed ) {
		$source            = $parsed['source'];
		$collectionSources = array(
			ConditionSource::ARGS,
			ConditionSource::REQUEST_COOKIES,
			ConditionSource::REQUEST_HEADERS,
		);

		if ( ! in_array( $source, array_merge( $collectionSources, array( ConditionSource::FILES ) ), true ) ) {
			return false;
		}

		$resolvesByRegex = null !== $parsed['field_regex']
			|| ( null !== $parsed['bracket_path'] && $this->bracketPathHasRegex( $parsed['bracket_path'] ) );

		if ( $resolvesByRegex ) {
			return in_array( $source, $collectionSources, true );
		}

		if ( null === $parsed['field'] ) {
			return false;
		}

		if ( ConditionSource::FILES !== $source ) {
			return true;
		}

		$filesParsed = ValueResolver::parseFilesField( $parsed['field'] );

		return null === $filesParsed['sub']
			|| in_array( $filesParsed['sub'], ValueResolver::FILES_SUB_SELECTORS, true );
	}

	/**
	 * Evaluate missing_capability condition.
	 *
	 * @param Condition $condition Condition to evaluate.
	 * @param Request   $request   Request object.
	 *
	 * @return bool True if capability is missing (condition matches), false otherwise.
	 */
	private function evaluateMissingCapability( Condition $condition, Request $request ) {
		$capability = $condition->getValue();
		if ( empty( $capability ) ) {
			return false;
		}

		$name = $condition->getName();
		if ( ! empty( $name ) ) {
			$user_ids = $this->getCandidateUserIds( $name, $request );
			if ( empty( $user_ids ) ) {
				// Field absent: request acts on the current user, rule out of scope.
				return false;
			}
			// Fire if ANY candidate the backend might resolve lacks the capability
			// (closes the source/parser gaps; see getCandidateUserIds).
			foreach ( $user_ids as $user_id ) {
				if ( ! user_can( $user_id, $capability ) ) {
					return true;
				}
			}
			return false;
		}

		return ! current_user_can( $capability );
	}

	/**
	 * Evaluate not_current_user condition.
	 *
	 * Checks if the user ID in the request does not match the currently
	 * logged-in user. Returns false when the parameter is absent (no IDOR).
	 *
	 * @since 3.0.2
	 *
	 * @param Condition $condition Condition to evaluate.
	 * @param Request   $request   Request object.
	 *
	 * @return bool True if request user ID differs from current user (condition matches).
	 */
	private function evaluateNotCurrentUser( Condition $condition, Request $request ) {
		$user_ids = $this->getCandidateUserIds( $condition->getName(), $request );
		if ( empty( $user_ids ) ) {
			// No identifier in any source: acts on the current user, not an IDOR.
			return false;
		}

		$current_user_id = get_current_user_id();
		foreach ( $user_ids as $user_id ) {
			if ( $current_user_id !== $user_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve every user ID the WordPress backend could read for an
	 * identity-keyed condition name (e.g. ARGS:user_id).
	 *
	 * Values go through absint() to match get_user_by('id', ...), and a plain
	 * ARGS field is read from both GET and POST (WordPress reads $_REQUEST),
	 * closing the parser/HPP gaps an attacker could use to point firewall and
	 * backend at different users. Duplicates are collapsed.
	 *
	 * @param string  $name    Condition name with source and field.
	 * @param Request $request Request object.
	 *
	 * @return int[] Backend-equivalent user IDs (empty when the field is absent).
	 */
	private function getCandidateUserIds( $name, Request $request ) {
		$parsed = Condition::parseNameString( $name );
		if ( null === $parsed['field'] ) {
			return array();
		}

		$ids = array();
		foreach ( $this->collectRawFieldValues( $request, $parsed ) as $value ) {
			$ids[] = absint( $value );
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Collect every present raw value for an identity-keyed field across the
	 * sources the WordPress backend could read.
	 *
	 * Plain ARGS is read from BOTH the query string and the body (see
	 * getCandidateUserIds). Other sources, and bracketed/nested names, use the
	 * single-value resolver. Absent values are omitted.
	 *
	 * @param Request $request Request object.
	 * @param array   $parsed  Parsed condition name.
	 *
	 * @return array Present raw values (string/array), in GET-then-POST order.
	 */
	private function collectRawFieldValues( Request $request, $parsed ) {
		// bracket_path is always set: null for a literal field, an array otherwise.
		$is_plain_args = ConditionSource::ARGS === $parsed['source']
			&& ! isset( $parsed['bracket_path'] );

		if ( ! $is_plain_args ) {
			$value = $this->valueResolver->getFieldValue( $request, $parsed );
			return null === $value ? array() : array( $value );
		}

		$values = array();
		$get    = $request->get( $parsed['field'] );
		if ( null !== $get ) {
			$values[] = $get;
		}
		$post = $request->post( $parsed['field'] );
		if ( null !== $post ) {
			$values[] = $post;
		}

		return $values;
	}

	/**
	 * Check whether a bracket path contains any /regex/ segments.
	 *
	 * @since 3.0.0
	 *
	 * @param array $bracketPath Array of bracket-path segments.
	 *
	 * @return bool True if at least one segment is a /regex/ pattern.
	 */
	private function bracketPathHasRegex( array $bracketPath ) {
		foreach ( $bracketPath as $segment ) {
			if ( preg_match( '#^/(.+)/$#', $segment ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Evaluate probabilistic condition.
	 *
	 * Triggers with a configurable probability per request.
	 * The trigger rate is stored in the condition's value field as a fraction
	 * (e.g., 0.0001 = 1 in 10,000 requests).
	 *
	 * @since 3.1.0
	 *
	 * @param Condition $condition Condition to evaluate.
	 *
	 * @return bool True if the random check passes, false otherwise.
	 */
	private function evaluateProbabilistic( Condition $condition ) {
		$rate = $condition->getValue();
		if ( null === $rate || ! is_numeric( $rate ) ) {
			return false;
		}

		$rate = (float) $rate;
		if ( $rate <= 0.0 ) {
			return false;
		}
		if ( $rate >= 1.0 ) {
			return true;
		}

		$denominator = (int) round( 1.0 / $rate );
		if ( $denominator < 1 ) {
			return true;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- sampling, not security; avoids syscall overhead at scale
		return mt_rand( 1, $denominator ) === 1;
	}

	/**
	 * Evaluate probe condition.
	 *
	 * Two-layer gate: probabilistic filter eliminates most requests with zero I/O,
	 * then a transient guard ensures we only collect data once per interval.
	 * The interval (in seconds) is read from the condition's value field
	 * (e.g. 86400 = 24 h, the default in the shipped ruleset).
	 *
	 * @since 3.0.4
	 *
	 * @param Condition $condition Condition to evaluate.
	 *
	 * @return bool True if probe should fire, false otherwise.
	 */
	private function evaluateProbe( Condition $condition ) {
		if ( ! $condition->hasRequiredFields() ) {
			return false;
		}

		$name     = $condition->getName();
		$interval = $condition->getValue();

		if ( ! is_numeric( $interval ) || (int) $interval <= 0 ) {
			return false;
		}

		if ( ! StorageAvailabilityProbe::isKnownProbe( $name ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- sampling, not security
		if ( mt_rand( 1, $this->samplingDenominator ) !== 1 ) {
			return false;
		}

		$transientKey = self::PROBE_TRANSIENT_PREFIX . $name . '_sent';
		if ( get_transient( $transientKey ) ) {
			return false;
		}

		$probe           = new StorageAvailabilityProbe();
		$this->probeData = $probe->run();

		set_transient( $transientKey, 1, (int) $interval );

		return true;
	}

	/**
	 * Get probe data collected during condition evaluation.
	 *
	 * @since 3.0.4
	 *
	 * @return string|null Probe data string, or null if no probe fired.
	 */
	public function getProbeData() {
		return $this->probeData;
	}

	/**
	 * Get the last failed condition during evaluation.
	 *
	 * @return Condition|null The last failed Condition object or null if none failed.
	 */
	public function getFailedCondition() {
		return $this->failedCondition;
	}
}
