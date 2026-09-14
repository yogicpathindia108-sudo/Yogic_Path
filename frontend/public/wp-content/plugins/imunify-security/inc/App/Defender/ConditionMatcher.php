<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender;

/**
 * Pure comparison functions for condition evaluation.
 *
 * Each match method handles both string and array values internally,
 * extracting leaf strings from arrays via Request::extractLeafValues().
 *
 * @since 3.0.0
 */
class ConditionMatcher {

	/**
	 * XSS detection patterns.
	 *
	 * Focused on genuine attack vectors — dangerous tags, event handlers,
	 * protocol injection, and JS execution. Form/layout elements and
	 * encoding-format patterns removed to prevent FPs (normalization
	 * layer handles encoding evasion instead).
	 *
	 * @var string[]
	 */
	const XSS_PATTERNS = array(
		// Dangerous tags: tags whose mere presence in user input is an attack
		// signal (script/object/embed family + obsolete code-execution tags).
		// Removed: svg/math/body/img/video/audio/details/template — these have
		// legitimate uses (data:image/svg+xml from WP-Rocket beacon, image
		// galleries, Vue/page-builder templates). Their XSS variants are
		// caught by the event-handler pattern below or javascript: protocol.
		'/<\s*(?:script|iframe|object|embed|applet|base|noscript|xmp|plaintext|listing)[\s\/>]/i',
		// JavaScript / VBScript / LiveScript protocol — tolerates whitespace within keyword
		// (browsers accept `java\tscript:` in href attributes; livescript: is the legacy Netscape scheme name).
		'/(?:java\s*script|vb\s*script|live\s*script)\s*:/i',
		// Data URI with executable content types.
		'/data\s*:\s*(?:text\/html|application\/(?:x-)?javascript|text\/javascript)/i',
		// JS execution functions.
		'/\b(?:eval|settimeout|setinterval)\s*\(/i',
		// CSS expression (IE legacy, still relevant).
		'/expression\s*\(/i',
		// DOM access for data exfiltration / redirect.
		'/\bdocument\s*\.\s*(?:cookie|write|writeln|location|domain)/i',
		'/\bwindow\s*\.\s*(?:location|open|navigate)/i',
		// DOM sink assignment (CRS 941180, PHPIDS #37, NinjaFirewall).
		'/\b(?:inner|outer)html\s*=/i',
		'/\binsertadjacenthtml\s*\(/i',
		// String.fromCharCode — payload construction without literal tags
		// (CRS 941390, PHPIDS #43, voku/anti-xss).
		'/string\s*\.\s*fromcharcode\s*\(/i',
		// Constructor chain execution.
		'/constructor\s*[\[\(]/i',
		// CSS url() with JS protocol.
		'/url\s*\(\s*(?:javascript|data\s*:\s*text\/html)/i',
		// ESI injection (observed in CH production fleet data).
		'/<\s*esi\s*:/i',
	);

	/**
	 * Event-handler shape gate (cheap pre-filter).
	 *
	 * Matches anything shaped like an HTML event handler attribute
	 * (`on<name>=`) independent of whether `name` is a real handler. Used
	 * as a cost-bound gate before the precise allow-list — with no `on…=`
	 * token in the body we skip the 100+ alternative allow-list entirely,
	 * which dominates per-request cost on ARGS scans.
	 *
	 * Name is bounded [a-z]{2,24} to match all real HTML handlers while
	 * keeping PCRE work linear in input length.
	 *
	 * @var string
	 */
	const XSS_EVENT_HANDLER_SHAPE_GATE = '/\bon[a-z]{2,24}\s*=/i';

	/**
	 * Event-handler precise allow-list.
	 *
	 * Evaluated only when XSS_EVENT_HANDLER_SHAPE_GATE fires. Covers the
	 * AB-validated production set, CRS 941160 HTML5 handlers (onmessage,
	 * onstorage, onpageshow), and the imunify-connect port's form /
	 * clipboard / media handlers (onabort, oninvalid, onreset, oncopy,
	 * oncut, onpaste, onloadstart, onsearch, oncancel, onclose,
	 * onauxclick). This pattern is the sole catch-all for `<svg>`/`<img>`/
	 * `<video>`/`<audio>`/`<body>` XSS now that tag-narrowing removed
	 * those tags from the dangerous-tag list.
	 *
	 * @var string
	 */
	const XSS_EVENT_HANDLER_ALLOWLIST = '/\bon(?:error|load|loadstart|loadend|loadeddata|loadedmetadata|click|auxclick|dblclick|mouseover|mouseout|mouseenter|mouseleave|mousemove|mousedown|mouseup|focus|focusin|focusout|blur|submit|reset|invalid|change|input|search|keydown|keyup|keypress|animationend|animationstart|animationiteration|transitionend|transitionstart|transitionrun|transitioncancel|toggle|begin|start|canplay|canplaythrough|ended|playing|pause|play|seeking|seeked|stalled|suspend|waiting|emptied|durationchange|ratechange|timeupdate|volumechange|progress|cuechange|beforeunload|hashchange|popstate|resize|scroll|scrollend|unload|dragstart|dragend|drag|dragenter|dragleave|dragover|drop|contextmenu|wheel|pointerdown|pointerup|pointermove|pointercancel|pointerenter|pointerleave|pointerover|pointerout|gotpointercapture|lostpointercapture|touchstart|touchend|touchmove|touchcancel|copy|cut|paste|cancel|close|message|storage|pageshow|pagehide|afterprint|beforeprint|afterscriptexecute|beforescriptexecute|formdata|securitypolicyviolation|abort|selectstart|select|show|sort|activate)\s*=/i';

	/**
	 * SQL injection detection patterns.
	 *
	 * Focused on structural SQL injection signatures — UNION SELECT,
	 * stacked queries, tautologies, time/error-based blind, schema
	 * access, and data exfiltration. Single English keyword patterns
	 * and bare comment/encoding patterns removed to prevent FPs
	 * (normalization layer handles encoding and inline-comment evasion).
	 *
	 * @var string[]
	 */
	const SQLI_PATTERNS = array(
		// UNION-based injection.
		'/\bunion\b\s+(?:all\s+)?select\b/i',
		// Stacked queries: semicolon followed by DML/DDL keyword.
		'/;\s*(?:select|insert|update|delete|drop|alter|create|exec|execute)\b/i',
		// Boolean-based blind: numeric tautology / contradiction, including
		// hex literals (0x31=0x31) and float-notation (1e0=1e0) — libinjection
		// and CRS 942140/942100 collapse these to the same class.
		'/\b(?:or|and)\s+[\'"]?(?:\d+(?:e\d+)?|0x[0-9a-f]+)[\'"]?\s*=\s*[\'"]?(?:\d+(?:e\d+)?|0x[0-9a-f]+)/i',
		// Boolean-based blind: string-literal tautology (' x '=' x ', " a "=" a ")
		// — the non-numeric counterpart. Both quote styles supported so MSSQL
		// double-quoted identifiers are covered.
		'/\b(?:or|and)\s+([\'"])[^\'"\s]{1,32}\1\s*=\s*([\'"])[^\'"\s]{1,32}\2/i',
		// Boolean-based blind: SQL keyword operands (true / false / null). Plain
		// `or true` / `and false` has no numeric operands so the patterns above
		// miss it; these are common libinjection class-1a tautologies.
		'/\b(?:or|and)\s+(?:true|false|null)\b(?:\s+(?:is\s+null|is\s+not\s+null))?/i',
		// Time-based blind: sleep / benchmark functions.
		'/\b(?:sleep|benchmark|pg_sleep|dbms_lock\.sleep)\s*\(/i',
		// Time-based blind: MSSQL WAITFOR DELAY.
		'/\bwaitfor\s+delay\b/i',
		// Error-based: XML functions.
		'/\b(?:extractvalue|updatexml)\s*\(/i',
		// Error-based: floor(rand()) GROUP BY collision.
		'/\bfloor\s*\(\s*rand\b/i',
		// Error-based: MySQL 5.6+ GTID, geometric, ELT.
		'/\b(?:gtid_subset|st_latfromgeohash|st_longfromgeohash|elt)\s*\(/i',
		// Data exfiltration functions.
		'/\b(?:group_concat|load_file|concat_ws)\s*\(/i',
		// CHAR() / CHR() string construction — evades string-literal matching
		// (CRS 942370, PHPIDS #62, libinjection). Accepts numeric, hex-literal,
		// or a nested numeric-producing function as the first arg — raw `char(`
		// alone is too broad and FPs on the English word "char" in prose.
		'/\b(?:char|chr|ascii)\s*\(\s*(?:\d+|0x[0-9a-f]+|cast\b|unhex\b|conv\b|hex\b|concat\b|ord\b|mid\b)/i',
		// MSSQL system procedures (CRS 942250-942260).
		// sp_executesql can be called with `n'sql'` instead of `(`, so
		// the parenthesis is optional.
		'/\b(?:xp_cmdshell|sp_executesql|openrowset)\b/i',
		// SQLite metadata access (CRS 942530); WordPress 6.1+ SQLite plugin.
		'/\b(?:sqlite_master|sqlite_schema)\b/i',
		// JSON function injection (MySQL 5.7+).
		'/\b(?:json_extract|json_value|json_set|json_keys)\s*\(/i',
		// MySQL/MariaDB JSON arrow operator — `col->'$.path'` (JSON_EXTRACT)
		// and `col->>'$.path'` (JSON_UNQUOTE + JSON_EXTRACT). The `$` root is
		// mandatory syntax so anchoring on quote + `$` avoids FP on JS arrow
		// functions or C++ member dereference (neither uses quoted `$`).
		'/->>?\s*[\'"]\s*\$/i',
		// File write.
		'/\binto\s+(?:out|dump)file\b/i',
		// PROCEDURE ANALYSE.
		'/\bprocedure\s+analyse\b/i',
		// Schema / metadata access.
		'/\binformation_schema\b/i',
		// Database-specific system object access.
		'/\b(?:mysql|sys|pg_catalog)\s*\.\s*\w+/i',
		// Subquery injection: SELECT...FROM inside parentheses.
		// Bounded .{0,200} limits backtracking scope; fail-closed wrapper
		// treats PCRE exhaustion as a match (safe default).
		'/\(\s*select\b[\s(].{0,200}\bfrom\b/i',
		// INSERT INTO...VALUES.
		'/\binsert\s+into\b\s+.{1,200}\bvalues\b/i',
		// UPDATE table SET column=value.
		'/\bupdate\b\s+\S+\s+\bset\b\s+\S+\s*=/i',
		// DROP / ALTER DDL.
		'/\b(?:drop|alter)\s+(?:table|database|column|index)\b/i',
		// Classic string injection: quote break followed by SQL keyword.
		'/\'\s*(?:or|and|union|having|order|group)\s/i',
		// Comment termination after quote break (auth bypass).
		'/\'\s*(?:--|#|\/\*)/i',
		// XOR boolean blind (observed in CH production fleet data).
		'/\bxor\s*\(\s*(?:if|case)\b/i',
		// Prepared statement bypass.
		'/\bprepare\s+\w+\s+from\b/i',
		// Hex-encoded strings in SQL context.
		'/\b(?:concat|select|union|from|where)\s*\(?\s*0x[0-9a-f]{8,}/i',
		// Information disclosure functions.
		'/\b(?:user|database|version|schema|current_user|session_user)\s*\(\s*\)/i',
		// Privilege operations (structured — avoids FP on single words).
		'/\b(?:grant|revoke)\s+(?:all|select|insert|update|delete|execute|usage|alter|create|drop)\b/i',
		// Aggregate function followed by FROM (in query context).
		'/\b(?:count|sum|avg)\s*\([^)]*\)\s+(?:from|over)\b/i',
	);

	/**
	 * Test if a value strictly equals the expected string.
	 *
	 * For array values, extracts leaf strings and checks each.
	 *
	 * @param mixed  $value    The resolved value (string or array).
	 * @param string $expected The expected string.
	 *
	 * @return bool
	 */
	public function matchEquals( $value, $expected ) {
		if ( is_string( $value ) ) {
			return $value === $expected;
		}

		if ( is_array( $value ) ) {
			foreach ( Request::extractLeafValues( $value ) as $leaf ) {
				if ( $leaf === $expected ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Test if a value contains the given substring.
	 *
	 * For array values, extracts leaf strings and checks each.
	 *
	 * @param mixed  $value  The resolved value (string or array).
	 * @param string $needle The substring to search for.
	 *
	 * @return bool
	 */
	public function matchContains( $value, $needle ) {
		if ( is_string( $value ) ) {
			return false !== strpos( $value, $needle );
		}

		if ( is_array( $value ) ) {
			foreach ( Request::extractLeafValues( $value ) as $leaf ) {
				if ( false !== strpos( $leaf, $needle ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Test if a value matches a PCRE pattern (fail-closed).
	 *
	 * For array values, extracts leaf strings and checks each.
	 *
	 * @param mixed  $value   The resolved value (string or array).
	 * @param string $pattern PCRE pattern.
	 *
	 * @return bool
	 */
	public function matchRegex( $value, $pattern ) {
		if ( is_string( $value ) ) {
			return self::pregMatchFailClosed( $pattern, $value );
		}

		if ( is_array( $value ) ) {
			foreach ( Request::extractLeafValues( $value ) as $leaf ) {
				if ( self::pregMatchFailClosed( $pattern, $leaf ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Detect XSS patterns in a value.
	 *
	 * For array values, extracts leaf strings and checks each.
	 *
	 * @param mixed $value The value to check.
	 *
	 * @return bool True if XSS is detected.
	 */
	public function matchXSS( $value ) {
		if ( is_array( $value ) ) {
			foreach ( Request::extractLeafValues( $value ) as $leaf ) {
				if ( $this->detectXSS( $leaf ) ) {
					return true;
				}
			}
			return false;
		}

		return $this->detectXSS( $value );
	}

	/**
	 * Detect SQL injection patterns in a value.
	 *
	 * For array values, extracts leaf strings and checks each.
	 *
	 * @param mixed $value The value to check.
	 *
	 * @return bool True if SQL injection is detected.
	 */
	public function matchSQLi( $value ) {
		if ( is_array( $value ) ) {
			foreach ( Request::extractLeafValues( $value ) as $leaf ) {
				if ( $this->detectSQLi( $leaf ) ) {
					return true;
				}
			}
			return false;
		}

		return $this->detectSQLi( $value );
	}

	/**
	 * Fail-closed preg_match wrapper.
	 *
	 * When preg_match hits the PCRE backtrack or recursion limit it returns false.
	 * Treating false as "no match" would let an attacker craft input that
	 * exhausts the limit and silently bypasses a blocking rule.
	 * This wrapper treats any PCRE error the same as a positive match.
	 *
	 * @param string $pattern PCRE pattern.
	 * @param string $subject String to test.
	 *
	 * @return bool True when the pattern matches OR when preg_match fails.
	 */
	public static function pregMatchFailClosed( $pattern, $subject ) {
		$result = @preg_match( $pattern, $subject ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- PCRE errors are handled via the return value.

		return 1 === $result || false === $result;
	}

	/**
	 * Normalize input for detection: decode encodings and strip evasion bytes.
	 *
	 * Handles URL encoding (double-pass for double-encoding), HTML entities,
	 * hex escapes (\x3c), Unicode escapes (\u003c), and null bytes.
	 * Result is lowercased for case-insensitive matching.
	 *
	 * @param string $value Raw input value.
	 *
	 * @return string Normalized, lowercased value.
	 */
	private function normalizeInput( $value ) {
		// URL-decode iteratively until stable (handles single, double, and
		// triple encoding). Bounded to 4 passes to prevent pathological
		// inputs from consuming unbounded CPU.
		for ( $i = 0; $i < 4; $i++ ) {
			$decoded = rawurldecode( $value );
			if ( $decoded === $value ) {
				break;
			}
			$value = $decoded;
		}

		// Strip null bytes (evasion: <scr\x00ipt>).
		$value = str_replace( "\x00", '', $value );

		// Decode HTML entities + JS hex/unicode escapes. The three stages
		// feed each other: an entity can decode to `\x3c`, a `\`
		// decodes to `\` that forms a new `\x..` escape, and so on. We
		// iterate until the value stabilises (max 3 passes to bound
		// pathological inputs). ENT_HTML5 enables HTML5 named entities
		// (&Tab;, &NewLine;, &colon;, &lpar;, &rpar;, etc.) — without
		// it, attackers evade by wrapping delimiters in those entities.
		for ( $pass = 0; $pass < 3; $pass++ ) {
			$before = $value;

			// Browsers accept numeric entities without trailing `;`
			// (`&#60script` -> `<script`). PHP's html_entity_decode
			// REQUIRES the semicolon, so attackers drop it to evade.
			// Pre-pass: inject `;` whenever a numeric entity is followed
			// by a non-digit/non-semicolon so html_entity_decode can
			// process it correctly. Runs inside the loop so newly-exposed
			// entities produced by the previous pass (e.g. `&amp;#60…` ->
			// `&#60…` after one decode) are also normalised.
			$decoded = preg_replace(
				array(
					'/&#x([0-9a-fA-F]{1,6})(?=[^0-9a-fA-F;]|$)/',
					'/&#([0-9]{1,7})(?=[^0-9;]|$)/',
				),
				array( '&#x$1;', '&#$1;' ),
				$value
			);
			if ( null !== $decoded ) {
				$value = $decoded;
			}

			$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

			$decoded = preg_replace_callback(
				'/\\\\x([0-9a-fA-F]{2})/',
				function ( $m ) {
					return chr( hexdec( $m[1] ) );
				},
				$value
			);
			if ( null !== $decoded ) {
				$value = $decoded;
			}

			$decoded = preg_replace_callback(
				'/\\\\u([0-9a-fA-F]{4})/',
				function ( $m ) {
					return html_entity_decode( '&#x' . $m[1] . ';', ENT_QUOTES, 'UTF-8' );
				},
				$value
			);
			if ( null !== $decoded ) {
				$value = $decoded;
			}

			if ( $value === $before ) {
				break;
			}
		}

		// Second null-byte strip: hex (\x00) and unicode (\u0000) decode
		// stages above can produce new null bytes that weren't present in
		// the original URL-decoded value.
		$value = str_replace( "\x00", '', $value );

		// Protocol keyword re-normalization: browsers strip ASCII tab/newline
		// (U+0009, U+000A, U+000D) from URL schemes before parsing, so
		// `jav\tascript:` (produced e.g. by `jav&Tab;ascript:` decoding via
		// ENT_HTML5, or by literal whitespace inserted at any keyword
		// position) is equivalent to `javascript:`. Collapse whitespace
		// sprinkled anywhere within javascript/vbscript/livescript schemes
		// to the canonical form so the existing protocol pattern matches.
		// Bounded \s{0,3} per inter-char gap limits backtracking; overall
		// match is anchored by the trailing `:`.
		$decoded = preg_replace(
			array(
				'/j\s{0,3}a\s{0,3}v\s{0,3}a\s{0,3}s\s{0,3}c\s{0,3}r\s{0,3}i\s{0,3}p\s{0,3}t\s*:/i',
				'/v\s{0,3}b\s{0,3}s\s{0,3}c\s{0,3}r\s{0,3}i\s{0,3}p\s{0,3}t\s*:/i',
				'/l\s{0,3}i\s{0,3}v\s{0,3}e\s{0,3}s\s{0,3}c\s{0,3}r\s{0,3}i\s{0,3}p\s{0,3}t\s*:/i',
			),
			array( 'javascript:', 'vbscript:', 'livescript:' ),
			$value
		);
		if ( null !== $decoded ) {
			$value = $decoded;
		}

		// Lowercase for case-insensitive matching.
		$value = strtolower( $value );

		return $value;
	}

	/**
	 * Detect XSS patterns in a string value.
	 *
	 * Applies input normalization (URL/entity/hex decoding, null-byte stripping)
	 * before matching patterns, so encoding evasion is handled by the
	 * normalization layer rather than individual patterns.
	 *
	 * @param string $value The value to check for XSS patterns.
	 *
	 * @return bool True if XSS is detected, false otherwise.
	 */
	private function detectXSS( $value ) {
		if ( ! is_string( $value ) || empty( $value ) ) {
			return false;
		}

		$value = $this->normalizeInput( $value );

		foreach ( self::XSS_PATTERNS as $pattern ) {
			if ( self::pregMatchFailClosed( $pattern, $value ) ) {
				return true;
			}
		}

		// Two-phase event-handler check: the cheap bounded shape gate
		// (`on[a-z]{2,24}\s*=`) filters the common case where no handler-
		// shaped token exists in the body, so we only pay the cost of the
		// 100+ alternative precise allow-list when the gate fires.
		// Fail-closed applies to both phases so a PCRE error in either
		// still short-circuits to "detected".
		if ( self::pregMatchFailClosed( self::XSS_EVENT_HANDLER_SHAPE_GATE, $value )
			&& self::pregMatchFailClosed( self::XSS_EVENT_HANDLER_ALLOWLIST, $value ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Detect SQL injection patterns in a string value.
	 *
	 * Applies input normalization plus SQL-specific comment stripping.
	 * Inline comments are normalized in two modes to handle both
	 * between-keyword evasion (UNION/**\/SELECT) and within-keyword
	 * evasion (UN/**\/ION).
	 *
	 * @param string $value The value to check for SQL injection patterns.
	 *
	 * @return bool True if SQL injection is detected, false otherwise.
	 */
	private function detectSQLi( $value ) {
		if ( ! is_string( $value ) || empty( $value ) ) {
			return false;
		}

		$base = $this->normalizeInput( $value );

		// Mode 1: comments → space (handles UNION/**/SELECT → UNION SELECT).
		// Each step falls back to its predecessor (not $base) to preserve
		// intermediate results if a later preg_replace fails.
		$step   = preg_replace( '/\/\*!\d{0,5}(.*?)\*\//s', ' $1 ', $base );
		$spaced = null !== $step ? $step : $base;
		$step   = preg_replace( '/\/\*.*?\*\//s', ' ', $spaced );
		$spaced = null !== $step ? $step : $spaced;
		$step   = preg_replace( '/\s+/', ' ', $spaced );
		$spaced = null !== $step ? trim( $step ) : trim( $spaced );

		foreach ( self::SQLI_PATTERNS as $pattern ) {
			if ( self::pregMatchFailClosed( $pattern, $spaced ) ) {
				return true;
			}
		}

		// Mode 2: comments → empty (handles UN/**/ION → UNION).
		// Skip entirely when no comments present — both modes produce same result.
		if ( false !== strpos( $base, '/*' ) ) {
			$step   = preg_replace( '/\/\*!\d{0,5}(.*?)\*\//s', '$1', $base );
			$joined = null !== $step ? $step : $base;
			$step   = preg_replace( '/\/\*.*?\*\//s', '', $joined );
			$joined = null !== $step ? $step : $joined;
			$step   = preg_replace( '/\s+/', ' ', $joined );
			$joined = null !== $step ? trim( $step ) : trim( $joined );

			if ( $joined !== $spaced ) {
				foreach ( self::SQLI_PATTERNS as $pattern ) {
					if ( self::pregMatchFailClosed( $pattern, $joined ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}
}
