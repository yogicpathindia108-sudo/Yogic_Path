<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender;

/**
 * Request class for handling HTTP request data.
 *
 * Provides a clean abstraction over global variables like $_SERVER, $_GET, $_POST, etc.
 * This makes the code more testable and provides a consistent interface.
 *
 * @since 2.1.0
 */
class Request {

	const MAX_KEY_DEPTH = 5;
	const MAX_KEY_COUNT = 200;

	/**
	 * Request headers PHP exposes in $_SERVER without the HTTP_ prefix.
	 *
	 * @since 4.1.0
	 *
	 * @var string[]
	 */
	const CGI_HEADERS = array( 'CONTENT_TYPE', 'CONTENT_LENGTH' );

	/**
	 * Maximum bytes of php://input read when parsing a JSON body.
	 */
	const MAX_JSON_BODY_BYTES = 1048576;

	/** Cap on the raw body exposed under RAW_BODY_KEY; bounds scan-all regex cost/ReDoS on the hot path (matches getFileContent()'s 8 KB default). */
	const RAW_BODY_INSPECT_BYTES = 8192;

	/** Synthetic ARGS key holding the (capped) raw body of a fail-closed JSON request so scan-all rules can still inspect it; hidden from getArgNames() when injected. */
	const RAW_BODY_KEY = '__waf_raw_body__';

	/**
	 * Request method.
	 *
	 * @var string
	 */
	private $method;

	/**
	 * Server variables.
	 *
	 * @var array
	 */
	private $server;

	/**
	 * GET parameters.
	 *
	 * @var array
	 */
	private $get;

	/**
	 * POST parameters.
	 *
	 * @var array
	 */
	private $post;

	/**
	 * Cookies.
	 *
	 * @var array
	 */
	private $cookies;

	/**
	 * File uploads.
	 *
	 * @var array
	 */
	private $files;

	/**
	 * Whether the RAW_BODY_KEY entry in $this->post is a synthetic sentinel we
	 * injected (only synthetic entries are hidden from getArgNames()).
	 *
	 * @var bool
	 */
	private $raw_body_fail_closed = false;

	/**
	 * Throttled-error payload captured when a JSON body fails closed, for the
	 * owner (Plugin) to emit via Debug::sendThrottledError(); null until then.
	 *
	 * @var array|null
	 */
	private $fail_closed_report = null;

	/**
	 * Constructor.
	 *
	 * @param array $server  Server variables (defaults to $_SERVER).
	 * @param array $get     GET parameters (defaults to $_GET).
	 * @param array $post    POST parameters (defaults to $_POST).
	 * @param array $cookies Cookies (defaults to $_COOKIE).
	 * @param array $files   File uploads (defaults to $_FILES).
	 */
	public function __construct( $server = null, $get = null, $post = null, $cookies = null, $files = null ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended
		$this->server  = null !== $server ? $server : $_SERVER;
		$this->get     = null !== $get ? $get : $_GET;
		$this->post    = null !== $post ? $post : $_POST;
		$this->cookies = null !== $cookies ? $cookies : $_COOKIE;
		$this->files   = null !== $files ? $files : $_FILES;
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended

		// Extract method before parsing JSON body so that a JSON _method key
		// cannot override the real HTTP method detected from $_POST/_SERVER.
		$this->method = $this->extractMethod( $this->server );

		if ( empty( $this->post ) ) {
			$this->maybeParseJsonBody();
		}
	}

	/**
	 * Parse JSON request body into $this->post when $_POST is empty.
	 *
	 * PHP only populates $_POST for application/x-www-form-urlencoded and
	 * multipart/form-data. For application/json requests (used by WordPress
	 * REST API), $_POST is empty and the body is only available via
	 * php://input. This method bridges that gap so ARGS:field conditions
	 * work on JSON request bodies.
	 *
	 * Guards:
	 * - Only runs when $_POST is empty (avoids double-parsing form submissions).
	 * - Only for application/json and +json suffix content types.
	 * - Caps raw input at 1 MB.
	 * - Fails closed when the body cannot be decoded into usable parameters.
	 *
	 * @since 3.0.2
	 */
	private function maybeParseJsonBody() {
		$content_type = isset( $this->server['CONTENT_TYPE'] )
			? $this->server['CONTENT_TYPE']
			: ( isset( $this->server['HTTP_CONTENT_TYPE'] ) ? $this->server['HTTP_CONTENT_TYPE'] : '' );

		$parts     = explode( ';', $content_type, 2 );
		$mime_type = strtolower( trim( $parts[0] ) );
		if ( ! $this->isJsonContentType( $mime_type ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading php://input stream, not a filesystem file.
		$raw = $this->readInput( self::MAX_JSON_BODY_BYTES );
		// Use a strict check rather than empty(): a legitimately empty or
		// unreadable body must return, but the single-character body "0" is a
		// valid bare JSON number that must proceed to the fail-closed branch
		// below (empty("0") === true would otherwise let it slip past).
		if ( false === $raw || '' === $raw ) {
			return;
		}

		// Note: on PHP 5.6, json_decode() may return null without setting
		// json_last_error() when the depth limit is exceeded. The is_array()
		// guard below handles this correctly — null fails the check.
		$decoded    = json_decode( $raw, true, 64 );
		$json_error = json_last_error();
		if ( is_array( $decoded ) ) {
			if ( ! empty( $decoded ) ) {
				$this->post = $decoded;
			}
			// An empty JSON object {} or array [] decodes to an empty array:
			// there are no key/value parameters to inspect, so $this->post
			// stays empty. This is the single fail-open case we accept -- the
			// body carries no content for ARGS rules to act on.
			return;
		}

		// Fail closed: Content-Type claims JSON but the body did not decode into
		// usable parameters (truncated/oversized, malformed, too deep, the `null`
		// literal, or a bare scalar). Leaving $this->post empty would fail open,
		// so expose a capped slice of the raw body under the synthetic sentinel
		// key for scan-all rules; the flag marks it synthetic so getArgNames()
		// can hide it (see RAW_BODY_KEY / RAW_BODY_INSPECT_BYTES).
		$body_bytes                 = strlen( $raw );
		$this->post                 = array( self::RAW_BODY_KEY => substr( $raw, 0, self::RAW_BODY_INSPECT_BYTES ) );
		$this->raw_body_fail_closed = true;

		$this->reportFailClosed( $mime_type, $body_bytes, $json_error );
	}

	/**
	 * Capture the throttled-error payload for a fail-closed JSON body.
	 *
	 * Records (does not emit) the diagnostic metadata so the owner that holds a
	 * Debug handle can throttle and send it after construction -- see
	 * isRawBodyFailClosed() and failClosed*(). The raw body is never reported:
	 * it is attacker-controlled and may carry payloads or PII.
	 *
	 * @since 4.0.2
	 *
	 * @param string $mime_type  Lowercased JSON content type that was detected.
	 * @param int    $body_bytes Length of the raw body that was read.
	 * @param int    $json_error json_last_error() value from the failed decode.
	 *
	 * @return void
	 */
	private function reportFailClosed( $mime_type, $body_bytes, $json_error ) {
		$truncated = $body_bytes >= self::MAX_JSON_BODY_BYTES;

		if ( $truncated ) {
			$reason = 'oversize';
		} elseif ( JSON_ERROR_DEPTH === $json_error ) {
			$reason = 'depth';
		} elseif ( JSON_ERROR_NONE === $json_error ) {
			// Valid JSON that is not an object/array (null literal or bare scalar).
			// On PHP 5.6 a depth overflow also lands here, reported as non_array.
			$reason = 'non_array';
		} else {
			$reason = 'malformed';
		}

		// Error code embeds the reason so each reason throttles/groups on its own.
		$this->fail_closed_report = array(
			'code'    => 'waf_json_body_fail_closed_' . $reason,
			'message' => 'WAF: JSON request body failed closed (reason: ' . $reason . '); raw body exposed to ARGS rules',
			'context' => array(
				'reason'       => $reason,
				'content_type' => $mime_type,
				'body_bytes'   => $body_bytes,
				'truncated'    => $truncated,
				'json_error'   => $json_error,
			),
		);
	}

	/**
	 * Determine whether a MIME type denotes a JSON request body.
	 *
	 * Matches the exact application/json type plus any structured-syntax
	 * suffix per RFC 6839 (e.g. application/ld+json, application/vnd.api+json),
	 * mirroring how framework body parsers detect JSON.
	 *
	 * @since 4.0.2
	 *
	 * @param string $mime_type Lowercased MIME type with parameters stripped.
	 *
	 * @return bool True if the type denotes JSON.
	 */
	private function isJsonContentType( $mime_type ) {
		if ( 'application/json' === $mime_type ) {
			return true;
		}

		return '+json' === substr( $mime_type, -5 );
	}

	/**
	 * Read raw request body from php://input.
	 *
	 * Extracted as a method so unit tests can override the input source
	 * without requiring a real php://input stream.
	 *
	 * @since 3.0.2
	 *
	 * @param int $max_length Maximum bytes to read.
	 *
	 * @return string|false Raw body content, or false on failure.
	 */
	protected function readInput( $max_length ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading php://input stream.
		return file_get_contents( 'php://input', false, null, 0, $max_length );
	}

	/**
	 * Get the request method.
	 *
	 * @return string The HTTP method (GET, POST, PUT, DELETE, etc.).
	 */
	public function getMethod() {
		return $this->method;
	}

	/**
	 * Check if the request method matches the given method.
	 *
	 * @param string $method The method to check against.
	 *
	 * @return bool True if the request method matches, false otherwise.
	 */
	public function isMethod( $method ) {
		return strtolower( $this->method ) === strtolower( $method );
	}

	/**
	 * Get a GET parameter.
	 *
	 * @param string $key     The parameter key.
	 * @param mixed  $default Default value if the parameter doesn't exist.
	 *
	 * @return mixed The parameter value or default.
	 */
	public function get( $key, $default = null ) {
		return isset( $this->get[ $key ] ) ? $this->get[ $key ] : $default;
	}

	/**
	 * Check if a GET parameter exists.
	 *
	 * @param string $key The parameter key.
	 *
	 * @return bool True if the parameter exists, false otherwise.
	 */
	public function hasGet( $key ) {
		return isset( $this->get[ $key ] );
	}

	/**
	 * Get a POST parameter.
	 *
	 * @param string $key     The parameter key.
	 * @param mixed  $default Default value if the parameter doesn't exist.
	 *
	 * @return mixed The parameter value or default.
	 */
	public function post( $key, $default = null ) {
		return isset( $this->post[ $key ] ) ? $this->post[ $key ] : $default;
	}

	/**
	 * Check if a POST parameter exists.
	 *
	 * @param string $key The parameter key.
	 *
	 * @return bool True if the parameter exists, false otherwise.
	 */
	public function hasPost( $key ) {
		return isset( $this->post[ $key ] );
	}

	/**
	 * Get a cookie.
	 *
	 * @param string $key     The cookie key.
	 * @param mixed  $default Default value if the cookie doesn't exist.
	 *
	 * @return mixed The cookie value or default.
	 */
	public function cookie( $key, $default = null ) {
		return isset( $this->cookies[ $key ] ) ? $this->cookies[ $key ] : $default;
	}

	/**
	 * Check if a cookie exists.
	 *
	 * @param string $key The cookie key.
	 *
	 * @return bool True if the cookie exists, false otherwise.
	 */
	public function hasCookie( $key ) {
		return isset( $this->cookies[ $key ] );
	}

	/**
	 * Get all GET parameters.
	 *
	 * @return array All GET parameters.
	 */
	public function getAllGet() {
		return $this->get;
	}

	/**
	 * Get all POST parameters.
	 *
	 * Note: for a fail-closed JSON body this includes the synthetic RAW_BODY_KEY
	 * sentinel. Callers enumerating real parameter names (e.g. incident logging)
	 * should consult isRawBodyFailClosed() and skip RAW_BODY_KEY so the synthetic
	 * key is not recorded as a real POST parameter.
	 *
	 * @return array All POST parameters.
	 */
	public function getAllPost() {
		return $this->post;
	}

	/**
	 * Whether the request body failed closed and RAW_BODY_KEY is synthetic.
	 *
	 * True only when maybeParseJsonBody() injected the sentinel because the JSON
	 * body could not be decoded; false when RAW_BODY_KEY (if present) is a real
	 * decoded parameter. Lets name-enumerating callers hide the synthetic key the
	 * same way getArgNames() does.
	 *
	 * @since 4.0.2
	 *
	 * @return bool
	 */
	public function isRawBodyFailClosed() {
		return $this->raw_body_fail_closed;
	}

	/**
	 * Throttled-error message for a fail-closed JSON body (empty if none).
	 *
	 * @since 4.0.2
	 *
	 * @return string
	 */
	public function failClosedMessage() {
		return null === $this->fail_closed_report ? '' : $this->fail_closed_report['message'];
	}

	/**
	 * Throttled-error code for a fail-closed JSON body (empty if none).
	 *
	 * @since 4.0.2
	 *
	 * @return string
	 */
	public function failClosedCode() {
		return null === $this->fail_closed_report ? '' : $this->fail_closed_report['code'];
	}

	/**
	 * Throttled-error context (reason/size metadata) for a fail-closed JSON body.
	 *
	 * @since 4.0.2
	 *
	 * @return array
	 */
	public function failClosedContext() {
		return null === $this->fail_closed_report ? array() : $this->fail_closed_report['context'];
	}

	/**
	 * Get all GET and POST parameters merged.
	 *
	 * POST values take precedence over GET when keys overlap.
	 *
	 * @since 3.0.0
	 *
	 * @return array All GET and POST parameters.
	 */
	public function getAllArgs() {
		return array_merge( $this->get, $this->post );
	}

	/**
	 * Check if any GET or POST parameters exist.
	 *
	 * @since 3.0.2
	 *
	 * @return bool True if at least one parameter exists.
	 */
	public function hasAnyArgs() {
		return ! empty( $this->get ) || ! empty( $this->post );
	}

	/**
	 * Get all cookies.
	 *
	 * @return array All cookies.
	 */
	public function getAllCookies() {
		return $this->cookies;
	}

	/**
	 * Get all file uploads.
	 *
	 * @return array All file uploads.
	 */
	public function getAllFiles() {
		return $this->files;
	}

	/**
	 * Get all server variables.
	 *
	 * @return array All server variables.
	 */
	public function getAllServer() {
		return $this->server;
	}

	/**
	 * Get the request URI.
	 *
	 * @return string The request URI.
	 */
	public function getUri() {
		return isset( $this->server['REQUEST_URI'] ) ? $this->server['REQUEST_URI'] : '';
	}

	/**
	 * Check if a file upload exists.
	 *
	 * @param string $key The file key.
	 *
	 * @return bool True if the file exists, false otherwise.
	 */
	public function hasFile( $key ) {
		return isset( $this->files[ $key ] ) && ! empty( $this->files[ $key ]['name'] );
	}

	/**
	 * Get a file upload.
	 *
	 * @param string $key The file key.
	 *
	 * @return array|null The file data or null if not found.
	 */
	public function getFile( $key ) {
		return isset( $this->files[ $key ] ) ? $this->files[ $key ] : null;
	}

	/**
	 * Get the original filename of an uploaded file.
	 *
	 * @since 3.0.2
	 *
	 * @param string $key The file field name.
	 *
	 * @return string|null Original filename, or null if not found.
	 */
	public function getFileName( $key ) {
		$file = $this->getFile( $key );
		if ( ! isset( $file['name'] ) ) {
			return null;
		}
		// Strip null bytes that could cause regex matching to silently truncate.
		return str_replace( "\0", '', $file['name'] );
	}

	/**
	 * Get the client-provided MIME type of an uploaded file.
	 *
	 * WARNING: This value comes directly from $_FILES['type'] and is trivially
	 * spoofable by the client. WAF rules should use :name as the primary gate
	 * and treat :type only as a supplementary signal.
	 *
	 * @since 3.0.2
	 *
	 * @param string $key The file field name.
	 *
	 * @return string|null MIME type, or null if not found.
	 */
	public function getFileType( $key ) {
		$file = $this->getFile( $key );
		if ( ! isset( $file['type'] ) ) {
			return null;
		}
		// Strip null bytes that could cause regex matching to silently truncate.
		return str_replace( "\0", '', $file['type'] );
	}

	/**
	 * Read the first N bytes of an uploaded file's content.
	 *
	 * Validates that the file was genuinely uploaded via HTTP POST
	 * before reading, to prevent path-traversal attacks.
	 *
	 * @since 3.0.2
	 *
	 * @param string $key   The file field name.
	 * @param int    $limit Max bytes to read (default 8192).
	 *
	 * @return string|null File content, or null if not found/unreadable.
	 */
	public function getFileContent( $key, $limit = 8192 ) {
		$file = $this->getFile( $key );
		if ( ! isset( $file['tmp_name'] ) ) {
			return null;
		}

		if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return null;
		}

		$tmpPath = $file['tmp_name'];
		if ( ! $this->isUploadedFile( $tmpPath ) ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen -- reading uploaded temp file, not a WP filesystem operation.
		$fh = fopen( $tmpPath, 'rb' );
		if ( false === $fh ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fread -- reading uploaded temp file.
		$content = fread( $fh, $limit );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose -- reading uploaded temp file.
		fclose( $fh );

		if ( false === $content ) {
			return null;
		}

		return $content;
	}

	/**
	 * Check whether a file path points to a genuinely uploaded file.
	 *
	 * Wraps is_uploaded_file() so that unit tests can override this
	 * method to allow reading non-uploaded temp files.
	 *
	 * @since 3.0.2
	 *
	 * @param string $path Temporary file path.
	 *
	 * @return bool True if the file was uploaded via HTTP POST.
	 */
	protected function isUploadedFile( $path ) {
		return is_uploaded_file( $path );
	}

	/**
	 * Resolve a nested GET parameter value using bracket-path segments.
	 *
	 * Navigates $this->get[$rootKey][$path[0]][$path[1]]... to the leaf value.
	 *
	 * @since 3.0.0
	 *
	 * @param string $rootKey Root parameter key.
	 * @param array  $path    Array of bracket-path segments.
	 *
	 * @return mixed|null The leaf value, or null if the path doesn't exist.
	 */
	public function resolveNestedGet( $rootKey, array $path ) {
		return self::resolveNestedPath( $this->get, $rootKey, $path );
	}

	/**
	 * Resolve a nested POST parameter value using bracket-path segments.
	 *
	 * Navigates $this->post[$rootKey][$path[0]][$path[1]]... to the leaf value.
	 *
	 * @since 3.0.0
	 *
	 * @param string $rootKey Root parameter key.
	 * @param array  $path    Array of bracket-path segments.
	 *
	 * @return mixed|null The leaf value, or null if the path doesn't exist.
	 */
	public function resolveNestedPost( $rootKey, array $path ) {
		return self::resolveNestedPath( $this->post, $rootKey, $path );
	}

	/**
	 * Navigate a nested array by root key and path segments.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $data    The source array ($_GET or $_POST).
	 * @param string $rootKey Root key in $data.
	 * @param array  $path    Ordered path segments to traverse.
	 *
	 * @return mixed|null The leaf value, or null if any segment is missing.
	 */
	private static function resolveNestedPath( array $data, $rootKey, array $path ) {
		if ( ! isset( $data[ $rootKey ] ) ) {
			return null;
		}

		$current = $data[ $rootKey ];

		foreach ( $path as $segment ) {
			if ( ! is_array( $current ) || ! isset( $current[ $segment ] ) ) {
				return null;
			}
			$current = $current[ $segment ];
		}

		return $current;
	}

	/**
	 * Recursively extract all leaf string values from a (possibly nested) array.
	 *
	 * Used for scan-all ARGS evaluation when parameter values may be PHP arrays
	 * produced by bracket-notation form fields (e.g., param[key]=val).
	 *
	 * @since 3.0.0
	 *
	 * @param array $data     The array to walk.
	 * @param int   $maxDepth Maximum recursion depth (default 5).
	 * @param int   $maxCount Maximum number of leaf values to return (default 100).
	 *
	 * @return string[] Flat array of leaf string values.
	 */
	public static function extractLeafValues( array $data, $maxDepth = 5, $maxCount = 100 ) {
		$leaves = array();
		self::walkLeaves( $data, $maxDepth, $maxCount, $leaves, 0 );
		return $leaves;
	}

	/**
	 * Recursive helper for extractLeafValues().
	 *
	 * @param array $data     Current array level.
	 * @param int   $maxDepth Maximum recursion depth.
	 * @param int   $maxCount Maximum leaf count.
	 * @param array $leaves   Collected leaves (passed by reference).
	 * @param int   $depth    Current depth.
	 */
	private static function walkLeaves( array $data, $maxDepth, $maxCount, array &$leaves, $depth ) {
		if ( $depth >= $maxDepth ) {
			return;
		}

		foreach ( $data as $value ) {
			if ( count( $leaves ) >= $maxCount ) {
				return;
			}

			if ( is_array( $value ) ) {
				self::walkLeaves( $value, $maxDepth, $maxCount, $leaves, $depth + 1 );
			} elseif ( is_string( $value ) ) {
				$leaves[] = $value;
			}
		}
	}

	/**
	 * Get all GET/POST parameter values whose names match a regex pattern.
	 *
	 * Used with ModSecurity-style field name regex (e.g., ARGS:/field_a|field_b/).
	 * POST parameters take precedence over GET when keys overlap.
	 *
	 * @since 3.0.0
	 *
	 * @param string $pattern Regex pattern to match against parameter names (without delimiters/anchors).
	 *
	 * @return array<string, mixed> Associative array of matching parameter name => value pairs.
	 */
	public function getMatchingArgs( $pattern ) {
		$regex = '#^(?:' . str_replace( '#', '\\#', $pattern ) . ')$#';

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- intentional; invalid regex must be silently skipped.
		if ( false === @preg_match( $regex, '' ) ) {
			return array();
		}

		$results = array();

		foreach ( $this->get as $key => $value ) {
			if ( is_string( $key ) && preg_match( $regex, $key ) ) {
				$results[ $key ] = $value;
			}
		}

		foreach ( $this->post as $key => $value ) {
			if ( is_string( $key ) && preg_match( $regex, $key ) ) {
				$results[ $key ] = $value;
			}
		}

		return $results;
	}

	/**
	 * Get all cookie values whose names match a regex pattern.
	 *
	 * @since 3.0.0
	 *
	 * @param string $pattern Regex pattern to match against cookie names (without delimiters/anchors).
	 *
	 * @return array<string, mixed> Associative array of matching cookie name => value pairs.
	 */
	public function getMatchingCookies( $pattern ) {
		$regex = '#^(?:' . str_replace( '#', '\\#', $pattern ) . ')$#';

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- intentional; invalid regex must be silently skipped.
		if ( false === @preg_match( $regex, '' ) ) {
			return array();
		}

		$results = array();

		foreach ( $this->cookies as $key => $value ) {
			if ( is_string( $key ) && preg_match( $regex, $key ) ) {
				$results[ $key ] = $value;
			}
		}

		return $results;
	}

	/**
	 * Get a request header.
	 *
	 * @param string $key The header key.
	 *
	 * @return string|null The header value or null if not found.
	 */
	public function getHeader( $key ) {
		$normalized = strtoupper( str_replace( '-', '_', $key ) );

		if ( isset( $this->server[ 'HTTP_' . $normalized ] ) ) {
			return $this->server[ 'HTTP_' . $normalized ];
		}

		if ( in_array( $normalized, self::CGI_HEADERS, true ) && isset( $this->server[ $normalized ] ) ) {
			return $this->server[ $normalized ];
		}

		return null;
	}

	/**
	 * Convert a $_SERVER header key to its Title-Case header name.
	 *
	 * @since 4.1.0
	 *
	 * @param string $key Server key with any HTTP_ prefix already removed.
	 *
	 * @return string Header name (e.g. USER_AGENT => User-Agent).
	 */
	private static function headerNameFromKey( $key ) {
		return str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', $key ) ) ) );
	}

	/**
	 * Check if a request header exists.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key The header key (e.g. 'User-Agent', 'X-Custom').
	 *
	 * @return bool True if the header exists, false otherwise.
	 */
	public function hasHeader( $key ) {
		return null !== $this->getHeader( $key );
	}

	/**
	 * Get all header values whose names match a regex pattern.
	 *
	 * PHP normalises header names to HTTP_UPPER_CASE in $_SERVER, so this
	 * method converts them back to Title-Case (e.g. HTTP_USER_AGENT → User-Agent)
	 * and applies a case-insensitive regex match because HTTP header names are
	 * case-insensitive per RFC 7230.
	 *
	 * @since 3.0.0
	 *
	 * @param string $pattern Regex pattern to match against header names (without delimiters/anchors).
	 *
	 * @return array<string, string> Associative array of matching header name => value pairs.
	 */
	public function getMatchingHeaders( $pattern ) {
		$regex = '#^(?:' . str_replace( '#', '\\#', $pattern ) . ')$#i';

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- intentional; invalid regex must be silently skipped.
		if ( false === @preg_match( $regex, '' ) ) {
			return array();
		}

		$results = array();

		foreach ( $this->getAllHeaders() as $headerName => $value ) {
			if ( preg_match( $regex, $headerName ) ) {
				$results[ $headerName ] = $value;
			}
		}

		return $results;
	}

	/**
	 * Get all HTTP request headers as an associative array.
	 *
	 * Extracts headers from $_SERVER entries with HTTP_ prefix and
	 * returns them with normalised Title-Case names.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string, string> Header name => value pairs.
	 */
	public function getAllHeaders() {
		$headers = array();

		foreach ( $this->server as $key => $value ) {
			if ( 0 !== strpos( $key, 'HTTP_' ) || ! is_string( $value ) ) {
				continue;
			}
			$headers[ self::headerNameFromKey( substr( $key, 5 ) ) ] = $value;
		}

		// An HTTP_-prefixed spelling wins, matching getHeader().
		foreach ( self::CGI_HEADERS as $key ) {
			$headerName = self::headerNameFromKey( $key );
			if ( ! isset( $headers[ $headerName ] ) && isset( $this->server[ $key ] ) && is_string( $this->server[ $key ] ) ) {
				$headers[ $headerName ] = $this->server[ $key ];
			}
		}

		return $headers;
	}

	/**
	 * Get all parameter key names from GET and POST as a flat list.
	 *
	 * Recursively flattens nested arrays into bracket-path keys matching
	 * PHP's $_POST structure (e.g., param[key][subkey]).
	 *
	 * @since 3.0.2
	 *
	 * @return string[] Unique array of all parameter key names.
	 */
	public function getArgNames() {
		$keys = array();
		$this->collectKeys( $this->get, '', $keys );
		$this->collectKeys( $this->post, '', $keys );
		return array_values( array_unique( $this->withoutInjectedRawBodyKey( $keys ) ) );
	}

	/**
	 * Get the POST parameter names, excluding the synthetic fail-closed sentinel.
	 *
	 * For callers that enumerate or log POST parameter names (e.g. incident
	 * records): the injected RAW_BODY_KEY is not a client-supplied parameter and
	 * must not be recorded as one. Mirrors getArgNames()'s handling.
	 *
	 * @since 4.0.2
	 *
	 * @return string[] POST parameter names with the injected sentinel removed.
	 */
	public function getPostNames() {
		return $this->withoutInjectedRawBodyKey( array_keys( $this->post ) );
	}

	/**
	 * Remove the synthetic fail-closed sentinel (RAW_BODY_KEY) from a name list.
	 *
	 * Stripped only when we injected it, so a real GET parameter that happens to
	 * share the name survives (the synthetic key only ever lives in $this->post).
	 *
	 * @since 4.0.2
	 *
	 * @param string[] $names Parameter names.
	 *
	 * @return string[] Names with the injected sentinel removed.
	 */
	private function withoutInjectedRawBodyKey( array $names ) {
		if ( ! $this->raw_body_fail_closed || isset( $this->get[ self::RAW_BODY_KEY ] ) ) {
			return $names;
		}
		return array_values(
			array_filter(
				$names,
				function ( $k ) {
					return self::RAW_BODY_KEY !== $k;
				}
			)
		);
	}

	/**
	 * Recursively collect all array keys as bracket-path strings.
	 *
	 * @since 3.0.2
	 *
	 * @param array  $arr    Array to walk.
	 * @param string $prefix Current bracket-path prefix.
	 * @param array  $keys   Collected keys (passed by reference).
	 * @param int    $depth  Current recursion depth.
	 */
	private function collectKeys( $arr, $prefix, &$keys, $depth = 0 ) {
		if ( ! is_array( $arr ) || $depth >= self::MAX_KEY_DEPTH || count( $keys ) >= self::MAX_KEY_COUNT ) {
			return;
		}
		foreach ( $arr as $k => $v ) {
			if ( count( $keys ) >= self::MAX_KEY_COUNT ) {
				return;
			}
			$full   = '' === $prefix ? (string) $k : $prefix . '[' . $k . ']';
			$keys[] = $full;
			if ( is_array( $v ) ) {
				$this->collectKeys( $v, $full, $keys, $depth + 1 );
			}
		}
	}

	/**
	 * Extract the request method from server variables.
	 *
	 * @param array $server Server variables.
	 *
	 * @return string The HTTP method.
	 */
	private function extractMethod( $server ) {
		// Check for X-HTTP-METHOD-OVERRIDE header (used by some frameworks).
		if ( isset( $server['HTTP_X_HTTP_METHOD_OVERRIDE'] ) ) {
			return $server['HTTP_X_HTTP_METHOD_OVERRIDE'];
		}

		// Check for _method parameter (used by some frameworks).
		if ( isset( $this->post['_method'] ) ) {
			return $this->post['_method'];
		}

		// Check for the standard REQUEST_METHOD.
		if ( isset( $server['REQUEST_METHOD'] ) ) {
			return $server['REQUEST_METHOD'];
		}

		// Default to GET if no method is found.
		return 'GET';
	}
}
