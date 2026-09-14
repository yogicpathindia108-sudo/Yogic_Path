<?php
/**
 * RemoteRequestUtility class.
 *
 * Fail-closed public-URL validation and per-hop DNS-pinned downloads.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Utility;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WP_Error;
use WP_Http;
use WP_HTTP_Proxy;

/**
 * RemoteRequestUtility class.
 *
 * Shared outbound HTTP primitive: validate a public hostname, resolve A/AAAA,
 * pin the chosen address with cURL CURLOPT_RESOLVE, and download with a
 * manual redirect loop. Automatic library redirects are never enabled.
 *
 * @since ??
 */
class RemoteRequestUtility {

	/**
	 * Allowlisted destination ports.
	 *
	 * @var int[]
	 */
	public const ALLOWED_PORTS = [ 80, 443 ];

	/**
	 * Maximum number of followed redirects (four responses total).
	 *
	 * @var int
	 */
	public const MAX_REDIRECTS = 3;

	/**
	 * Cumulative HTTP deadline across the redirect chain, in seconds.
	 *
	 * @var int
	 */
	public const HTTP_DEADLINE_SECONDS = 15;

	/**
	 * Hard ceiling for cumulative response-body bytes (10 MB).
	 *
	 * Matches the AI image attachment validator. The effective cap is
	 * `min( MAX_RESPONSE_BYTES, wp_max_upload_size() )`.
	 *
	 * @var int
	 */
	public const MAX_RESPONSE_BYTES = 10485760;

	/**
	 * HTTP statuses that may be followed as redirects.
	 *
	 * @var int[]
	 */
	private const REDIRECT_STATUSES = [ 301, 302, 303, 307, 308 ];

	/**
	 * Normalize a hostname for policy and allowlist checks.
	 *
	 * Lowercases the host and strips a trailing dot so `Localhost.` and
	 * `foo.local.` hit the same local-host rules as `localhost` / `foo.local`.
	 *
	 * @since ??
	 *
	 * @param string $host Hostname from `wp_parse_url()`.
	 *
	 * @return string
	 */
	public static function normalize_host( string $host ): string {
		$host = strtolower( $host );
		$host = rtrim( $host, '.' );

		if ( str_starts_with( $host, '[' ) && str_ends_with( $host, ']' ) ) {
			$host = substr( $host, 1, -1 );
		}

		return $host;
	}

	/**
	 * Build a CURLOPT_RESOLVE entry string.
	 *
	 * Libcurl expects `host:port:ip` for IPv4 and `host:port:[ipv6]` for IPv6.
	 *
	 * @since ??
	 *
	 * @param string $host Hostname.
	 * @param int    $port Port number.
	 * @param string $ip   Resolved destination IP.
	 *
	 * @return string
	 */
	public static function build_curl_resolve_entry( string $host, int $port, string $ip ): string {
		$formatted_ip = self::is_ipv6_address( $ip ) ? '[' . $ip . ']' : $ip;

		return sprintf( '%1$s:%2$d:%3$s', $host, $port, $formatted_ip );
	}

	/**
	 * Whether cURL and CURLOPT_RESOLVE are available for pinning.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_curl_available(): bool {
		$available = function_exists( 'curl_init' ) && function_exists( 'curl_setopt' ) && defined( 'CURLOPT_RESOLVE' );

		/**
		 * Filter whether the remote-request primitive may use cURL DNS pinning.
		 *
		 * @since ??
		 *
		 * @param bool $available Whether cURL pinning is available.
		 */
		return (bool) apply_filters( 'et_builder_5_remote_request_curl_available', $available );
	}

	/**
	 * Whether a value is a valid IPv4 or IPv6 address.
	 *
	 * @since ??
	 *
	 * @param string $value Value to check.
	 *
	 * @return bool
	 */
	public static function is_ip_address( string $value ): bool {
		return false !== filter_var( $value, FILTER_VALIDATE_IP );
	}

	/**
	 * Whether a value is a valid IPv6 address.
	 *
	 * @since ??
	 *
	 * @param string $value Value to check.
	 *
	 * @return bool
	 */
	public static function is_ipv6_address( string $value ): bool {
		return false !== filter_var( $value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 );
	}

	/**
	 * Whether an IP string is private or reserved.
	 *
	 * IPv4-mapped IPv6 (`::ffff:a.b.c.d`) and NAT64 (`64:ff9b::/96`) embeddings
	 * are classified using the embedded IPv4 address.
	 *
	 * @since ??
	 *
	 * @param string $value Host value or IP string.
	 *
	 * @return bool
	 */
	public static function is_private_or_reserved_ip( string $value ): bool {
		if ( false === filter_var( $value, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		$embedded_ipv4 = self::_extract_embedded_ipv4( $value );

		if ( '' !== $embedded_ipv4 ) {
			return self::is_private_or_reserved_ip( $embedded_ipv4 );
		}

		$flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

		if ( false === filter_var( $value, FILTER_VALIDATE_IP, $flags ) ) {
			return true;
		}

		return self::_is_cgnat_or_link_local_ipv4( $value );
	}

	/**
	 * Whether an IPv4 address is CGNAT or link-local.
	 *
	 * PHP 7.4 `FILTER_FLAG_NO_RES_RANGE` does not treat `100.64.0.0/10` as
	 * reserved. WordPress 7.0+ does, and IMDS sits in `169.254.0.0/16`.
	 *
	 * @since ??
	 *
	 * @param string $ip IP string.
	 *
	 * @return bool
	 */
	private static function _is_cgnat_or_link_local_ipv4( string $ip ): bool {
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}

		$long = ip2long( $ip );

		if ( false === $long ) {
			return false;
		}

		$ranges = [
			[ ip2long( '100.64.0.0' ), ip2long( '100.127.255.255' ) ],
			[ ip2long( '169.254.0.0' ), ip2long( '169.254.255.255' ) ],
		];

		foreach ( $ranges as $range ) {
			if ( false === $range[0] || false === $range[1] ) {
				continue;
			}

			if ( $long >= $range[0] && $long <= $range[1] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve host DNS records with tolerant A/AAAA querying.
	 *
	 * @since ??
	 *
	 * @param string $host Hostname.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function resolve_host_dns_records( string $host ): array {
		$host = self::normalize_host( $host );

		/**
		 * Filter DNS records for a remote-request host.
		 *
		 * Returning an array short-circuits system DNS. Used by tests and by
		 * the legacy `et_builder_5_outside_vb_dns_records` seam.
		 *
		 * @since ??
		 *
		 * @param array<int, array<string, mixed>>|null $records DNS records or null to continue.
		 * @param string                                $host    Normalized hostname.
		 */
		$filtered_records = apply_filters( 'et_builder_5_remote_request_dns_records', null, $host );

		if ( is_array( $filtered_records ) ) {
			return $filtered_records;
		}

		$filtered_records = apply_filters( 'et_builder_5_outside_vb_dns_records', null, $host );

		if ( is_array( $filtered_records ) ) {
			return $filtered_records;
		}

		$records = [];

		$a_records = dns_get_record( $host, DNS_A );
		if ( is_array( $a_records ) ) {
			$records = array_merge( $records, $a_records );
		}

		$aaaa_records = dns_get_record( $host, DNS_AAAA );
		if ( is_array( $aaaa_records ) ) {
			$records = array_merge( $records, $aaaa_records );
		}

		return $records;
	}

	/**
	 * Validate a public HTTP(S) URL and return the pin target for this hop.
	 *
	 * When `$allowed_hosts` is non-empty, the normalized hostname must match
	 * exactly. An empty list skips the allowlist gate (`read-web-page`) unless
	 * `$require_allowlist` is true, in which case an empty list fails closed.
	 *
	 * @since ??
	 *
	 * @param mixed    $value             URL to validate.
	 * @param string[] $allowed_hosts     Optional exact-match hostname allowlist.
	 * @param bool     $require_allowlist Optional. Reject when `$allowed_hosts` is empty
	 *                                    instead of skipping the allowlist gate. Default false.
	 *
	 * @return array<string, mixed>|WP_Error {
	 *     @type string $url       Sanitized URL.
	 *     @type string $host      Normalized hostname.
	 *     @type int    $port      Destination port.
	 *     @type string $scheme    `http` or `https`.
	 *     @type string $pinned_ip Selected public A/AAAA address.
	 * }
	 */
	public static function validate_public_url( $value, array $allowed_hosts = [], bool $require_allowlist = false ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return self::_invalid_param_error( esc_html__( 'The URL must be a non-empty string.', 'et_builder_5' ) );
		}

		$raw_parts = wp_parse_url( $value );

		if ( is_array( $raw_parts ) && ( isset( $raw_parts['user'] ) || isset( $raw_parts['pass'] ) ) ) {
			return self::_invalid_param_error( esc_html__( 'The URL is invalid or unsafe for outbound HTTP requests.', 'et_builder_5' ) );
		}

		$url   = esc_url_raw( $value );
		$parts = wp_parse_url( $url );

		if ( '' === $url || ! is_array( $parts ) ) {
			return self::_invalid_param_error( esc_html__( 'The URL is invalid.', 'et_builder_5' ) );
		}

		if ( false === wp_http_validate_url( $url ) ) {
			return self::_invalid_param_error( esc_html__( 'The URL is invalid or unsafe for outbound HTTP requests.', 'et_builder_5' ) );
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host   = isset( $parts['host'] ) ? self::normalize_host( (string) $parts['host'] ) : '';
		$port   = isset( $parts['port'] ) ? (int) $parts['port'] : ( 'https' === $scheme ? 443 : 80 );

		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return self::_invalid_param_error( esc_html__( 'The URL is invalid or unsafe for outbound HTTP requests.', 'et_builder_5' ) );
		}

		if ( ! in_array( $port, self::ALLOWED_PORTS, true ) ) {
			return self::_invalid_param_error( esc_html__( 'Only standard web ports are allowed.', 'et_builder_5' ) );
		}

		if ( 'localhost' === $host || str_ends_with( $host, '.localhost' ) || str_ends_with( $host, '.local' ) ) {
			return self::_invalid_param_error( esc_html__( 'Local hostnames are not allowed.', 'et_builder_5' ) );
		}

		if ( self::is_ip_address( $host ) ) {
			return self::_invalid_param_error( esc_html__( 'Only publicly resolvable domain-name hosts are allowed.', 'et_builder_5' ) );
		}

		if ( [] === $allowed_hosts ) {
			if ( $require_allowlist ) {
				return self::_invalid_param_error( esc_html__( 'The image host is not allowed.', 'et_builder_5' ) );
			}
		} elseif ( ! self::_host_is_allowlisted( $host, $allowed_hosts ) ) {
			return self::_invalid_param_error( esc_html__( 'The image host is not allowed.', 'et_builder_5' ) );
		}

		$dns_records       = self::resolve_host_dns_records( $host );
		$has_usable_dns_ip = false;
		$pinned_ip         = '';

		if ( empty( $dns_records ) ) {
			return self::_invalid_param_error( esc_html__( 'The URL is invalid or unsafe for outbound HTTP requests.', 'et_builder_5' ) );
		}

		foreach ( $dns_records as $record ) {
			$record_ips = self::_ips_from_dns_record( $record );

			foreach ( $record_ips as $record_ip ) {
				$has_usable_dns_ip = true;

				if ( self::is_private_or_reserved_ip( $record_ip ) ) {
					return self::_invalid_param_error( esc_html__( 'The URL is invalid or unsafe for outbound HTTP requests.', 'et_builder_5' ) );
				}

				if ( '' === $pinned_ip ) {
					$pinned_ip = $record_ip;
				}
			}
		}

		if ( ! $has_usable_dns_ip || '' === $pinned_ip ) {
			return self::_invalid_param_error( esc_html__( 'The URL is invalid or unsafe for outbound HTTP requests.', 'et_builder_5' ) );
		}

		// Rebuild the URL to ensure the host perfectly matches the normalized version.
		// This prevents DNS pinning bypasses where libcurl extracts a host with a trailing dot
		// but the CURLOPT_RESOLVE pin was registered without it.
		$url = $scheme . '://' . $host;
		if ( ( 'http' === $scheme && 80 !== $port ) || ( 'https' === $scheme && 443 !== $port ) ) {
			$url .= ':' . $port;
		}
		$url .= isset( $parts['path'] ) ? $parts['path'] : '';
		if ( isset( $parts['query'] ) ) {
			$url .= '?' . $parts['query'];
		}
		if ( isset( $parts['fragment'] ) ) {
			$url .= '#' . $parts['fragment'];
		}

		return [
			'url'       => $url,
			'host'      => $host,
			'port'      => $port,
			'scheme'    => $scheme,
			'pinned_ip' => $pinned_ip,
		];
	}

	/**
	 * Download a remote file with per-hop validation, DNS pinning, and budgets.
	 *
	 * @since ??
	 *
	 * @param string               $url  URL to download.
	 * @param array<string, mixed> $args {
	 *     Optional. Download arguments.
	 *
	 *     @type string[] $allowed_hosts     Exact-match hostname allowlist. Empty skips the gate
	 *                                       unless `require_allowlist` is true.
	 *     @type bool     $require_allowlist Reject when `allowed_hosts` is empty instead of
	 *                                       skipping the allowlist gate. Default false.
	 *     @type int      $timeout           Cumulative HTTP deadline in seconds. Default 15.
	 *     @type int      $max_bytes         Cumulative response-byte cap. Default min(10 MB, upload max).
	 * }
	 *
	 * @return array<string, mixed>|WP_Error {
	 *     @type string $file          Temporary file path owned by the caller.
	 *     @type string $final_url     Final hop URL after redirects.
	 *     @type int    $response_code HTTP 200.
	 * }
	 *
	 * @throws \Exception When an HTTP callback throws; temp files are still deleted.
	 */
	public static function download_file( string $url, array $args = [] ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$args = wp_parse_args(
			$args,
			[
				'allowed_hosts'     => [],
				'require_allowlist' => false,
				'timeout'           => self::HTTP_DEADLINE_SECONDS,
				'max_bytes'         => null,
			]
		);

		$allowed_hosts     = is_array( $args['allowed_hosts'] ) ? $args['allowed_hosts'] : [];
		$require_allowlist = (bool) $args['require_allowlist'];
		$deadline          = microtime( true ) + max( 1, (int) $args['timeout'] );
		$remaining_bytes   = self::_effective_byte_cap( $args['max_bytes'] );
		$current_url       = $url;
		$followed          = 0;
		$seen_urls         = [];
		$owned_files       = [];

		try {
			while ( true ) {
				if ( microtime( true ) >= $deadline ) {
					return self::_request_failed_error( esc_html__( 'The remote request timed out.', 'et_builder_5' ) );
				}

				$remaining_time = $deadline - microtime( true );

				if ( $remaining_time <= 0 || $remaining_bytes <= 0 ) {
					if ( $remaining_bytes <= 0 ) {
						return self::_too_large_error();
					}

					return self::_request_failed_error( esc_html__( 'The remote request timed out.', 'et_builder_5' ) );
				}

				$validated = self::validate_public_url( $current_url, $allowed_hosts, $require_allowlist );

				if ( is_wp_error( $validated ) ) {
					return $validated;
				}

				if ( in_array( $validated['url'], $seen_urls, true ) ) {
					return self::_request_failed_error( esc_html__( 'The remote request redirected in a loop.', 'et_builder_5' ) );
				}

				$seen_urls[] = $validated['url'];
				$current_url = $validated['url'];

				$tmp_name = self::_create_hop_temp_file( $current_url );

				if ( is_wp_error( $tmp_name ) ) {
					return $tmp_name;
				}

				$owned_files[] = $tmp_name;

				$request_args = [
					'timeout'             => $remaining_time >= 1 ? (int) ceil( $remaining_time ) : $remaining_time,
					'redirection'         => 0,
					'stream'              => true,
					'filename'            => $tmp_name,
					'limit_response_size' => $remaining_bytes + 1,
					'cookies'             => [],
					'headers'             => [],
				];

				$response = self::_pinned_remote_get(
					$current_url,
					$request_args,
					$validated['pinned_ip'],
					$validated['host'],
					$validated['port']
				);

				if ( is_wp_error( $response ) ) {
					self::_delete_owned_files( $owned_files );
					return $response;
				}

				$file_size      = self::_file_size_or_zero( $tmp_name );
				$content_length = self::_content_length_from_response( $response );

				if ( $content_length > $remaining_bytes || $file_size > $remaining_bytes ) {
					self::_delete_owned_files( $owned_files );
					return self::_too_large_error();
				}

				$remaining_bytes -= $file_size;
				$status           = (int) wp_remote_retrieve_response_code( $response );

				if ( 200 === $status ) {
					self::_delete_owned_files(
						array_values(
							array_filter(
								$owned_files,
								static function ( $path ) use ( $tmp_name ) {
									return $path !== $tmp_name;
								}
							)
						)
					);

					return [
						'file'          => $tmp_name,
						'final_url'     => $current_url,
						'response_code' => 200,
					];
				}

				if ( in_array( $status, self::REDIRECT_STATUSES, true ) ) {
					if ( $followed >= self::MAX_REDIRECTS ) {
						self::_delete_owned_files( $owned_files );
						return self::_request_failed_error( esc_html__( 'The remote request exceeded the redirect limit.', 'et_builder_5' ) );
					}

					$next_url = self::_redirect_target_from_response( $response, $current_url );

					if ( is_wp_error( $next_url ) ) {
						self::_delete_owned_files( $owned_files );
						return $next_url;
					}

					self::_delete_owned_files( [ $tmp_name ] );
					$owned_files = array_values(
						array_filter(
							$owned_files,
							static function ( $path ) use ( $tmp_name ) {
								return $path !== $tmp_name;
							}
						)
					);

					$current_url = $next_url;
					++$followed;
					continue;
				}

				self::_delete_owned_files( $owned_files );

				return self::_error_for_http_status( $status, (string) wp_remote_retrieve_response_message( $response ) );
			}
		} catch ( \Exception $exception ) {
			self::_delete_owned_files( $owned_files );
			throw $exception;
		}
	}

	/**
	 * Fetch a remote URL into memory with per-hop validation and DNS pinning.
	 *
	 * Unlike `download_file()`, this keeps the body in memory and does not
	 * stream to disk. Automatic library redirects are never enabled. An optional
	 * `before_hop` callback runs after a hop is validated and before the pinned
	 * GET so callers can enforce quotas without hiding hops.
	 *
	 * @since ??
	 *
	 * @param string               $url  URL to fetch.
	 * @param array<string, mixed> $args {
	 *     Optional. Fetch arguments.
	 *
	 *     @type string[]      $allowed_hosts Exact-match hostname allowlist. Empty skips the gate.
	 *     @type int           $timeout       Cumulative HTTP deadline in seconds. Default 15.
	 *     @type int           $max_bytes     Response-byte cap. Default 10 MB.
	 *     @type int           $max_redirects Maximum followed redirects. Default 3.
	 *     @type array         $headers       Outbound request headers.
	 *     @type callable|null $before_hop    Optional `( string $url, array $validated )` admission hook.
	 * }
	 *
	 * @return array<string, mixed>|WP_Error {
	 *     @type string $body          Response body.
	 *     @type string $final_url     Final hop URL after redirects.
	 *     @type int    $response_code Terminal HTTP status (2xx).
	 *     @type string $content_type  Raw Content-Type header, if any.
	 * }
	 */
	public static function get( string $url, array $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'allowed_hosts' => [],
				'timeout'       => self::HTTP_DEADLINE_SECONDS,
				'max_bytes'     => self::MAX_RESPONSE_BYTES,
				'max_redirects' => self::MAX_REDIRECTS,
				'headers'       => [],
				'before_hop'    => null,
			]
		);

		$allowed_hosts = is_array( $args['allowed_hosts'] ) ? $args['allowed_hosts'] : [];
		$deadline      = microtime( true ) + max( 1, (int) $args['timeout'] );
		$max_bytes     = is_numeric( $args['max_bytes'] ) && (int) $args['max_bytes'] > 0 ? (int) $args['max_bytes'] : self::MAX_RESPONSE_BYTES;
		$max_redirects = max( 0, (int) $args['max_redirects'] );
		$current_url   = $url;
		$followed      = 0;
		$seen_urls     = [];
		$headers       = is_array( $args['headers'] ) ? $args['headers'] : [];
		$before_hop    = is_callable( $args['before_hop'] ) ? $args['before_hop'] : null;

		while ( true ) {
			if ( microtime( true ) >= $deadline ) {
				return self::_request_failed_error( esc_html__( 'The remote request timed out.', 'et_builder_5' ) );
			}

			$remaining_time = $deadline - microtime( true );

			if ( $remaining_time <= 0 ) {
				return self::_request_failed_error( esc_html__( 'The remote request timed out.', 'et_builder_5' ) );
			}

			$validated = self::validate_public_url( $current_url, $allowed_hosts );

			if ( is_wp_error( $validated ) ) {
				return $validated;
			}

			if ( in_array( $validated['url'], $seen_urls, true ) ) {
				return self::_request_failed_error( esc_html__( 'The remote request redirected in a loop.', 'et_builder_5' ) );
			}

			$seen_urls[] = $validated['url'];
			$current_url = $validated['url'];

			if ( null !== $before_hop ) {
				$admitted = $before_hop( $current_url, $validated );

				if ( is_wp_error( $admitted ) ) {
					return $admitted;
				}
			}

			$request_args = [
				'timeout'             => $remaining_time >= 1 ? (int) ceil( $remaining_time ) : $remaining_time,
				'redirection'         => 0,
				'limit_response_size' => $max_bytes,
				'cookies'             => [],
				'headers'             => $headers,
			];

			$response = self::_pinned_remote_get(
				$current_url,
				$request_args,
				$validated['pinned_ip'],
				$validated['host'],
				$validated['port']
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$body           = (string) wp_remote_retrieve_body( $response );
			$body_size      = strlen( $body );
			$content_length = self::_content_length_from_response( $response );

			if ( $content_length > $max_bytes || $body_size > $max_bytes ) {
				return self::_too_large_error();
			}

			$status = (int) wp_remote_retrieve_response_code( $response );

			if ( $status >= 200 && $status < 300 ) {
				$content_type = wp_remote_retrieve_header( $response, 'content-type' );

				if ( is_array( $content_type ) ) {
					$content_type = reset( $content_type );
				}

				return [
					'body'          => $body,
					'final_url'     => $current_url,
					'response_code' => $status,
					'content_type'  => is_string( $content_type ) ? $content_type : '',
				];
			}

			if ( in_array( $status, self::REDIRECT_STATUSES, true ) ) {
				if ( $followed >= $max_redirects ) {
					return self::_request_failed_error( esc_html__( 'The remote request exceeded the redirect limit.', 'et_builder_5' ) );
				}

				$next_url = self::_redirect_target_from_response( $response, $current_url );

				if ( is_wp_error( $next_url ) ) {
					return $next_url;
				}

				$current_url = $next_url;
				++$followed;
				continue;
			}

			return self::_request_failed_error( esc_html__( 'The remote request failed.', 'et_builder_5' ) );
		}
	}

	/**
	 * Issue a DNS-pinned GET for a single hop.
	 *
	 * @since ??
	 *
	 * @param string               $url        Hop URL.
	 * @param array<string, mixed> $args       WP HTTP args. Must include `redirection => 0`.
	 * @param string               $pinned_ip  Address chosen during validation.
	 * @param string               $host       Normalized hostname.
	 * @param int                  $port       Destination port.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function _pinned_remote_get( string $url, array $args, string $pinned_ip, string $host, int $port ) {
		if ( ! self::is_curl_available() ) {
			return self::_request_failed_error( esc_html__( 'Secure remote requests require cURL.', 'et_builder_5' ) );
		}

		if ( self::_is_sent_through_proxy( $url ) ) {
			return self::_request_failed_error( esc_html__( 'Remote requests cannot be sent through an HTTP proxy.', 'et_builder_5' ) );
		}

		$args['redirection'] = 0;
		$args['cookies']     = [];

		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = [];
		}

		unset( $args['headers']['Authorization'], $args['headers']['authorization'] );

		$pin_applied = false;
		$curl_filter = static function ( $handle, $request_args, $request_url ) use ( $url, $host, $port, $pinned_ip, &$pin_applied ) {
			unset( $request_args );

			if ( ! is_string( $request_url ) || $request_url !== $url ) {
				return;
			}

			$is_curl_handle = is_resource( $handle ) || ( is_object( $handle ) && 'CurlHandle' === get_class( $handle ) );

			if ( ! $is_curl_handle || ! function_exists( 'curl_setopt' ) || ! defined( 'CURLOPT_RESOLVE' ) ) {
				return;
			}

			$resolve_entry = self::build_curl_resolve_entry( $host, $port, $pinned_ip );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- CURLOPT_RESOLVE pins this hop to the address already validated.
			curl_setopt( $handle, CURLOPT_RESOLVE, [ $resolve_entry ] );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Prevent cURL from following library-level redirects.
			curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, false );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Ensure cURL redirect limit is 0.
			curl_setopt( $handle, CURLOPT_MAXREDIRS, 0 );

			$pin_applied = true;
		};

		$request_args_filter = static function ( $parsed_args, $request_url ) use ( $url ) {
			if ( ! is_array( $parsed_args ) ) {
				return $parsed_args;
			}

			if ( ! is_string( $request_url ) || $request_url === $url ) {
				$parsed_args['redirection'] = 0;
				$parsed_args['cookies']     = [];

				if ( isset( $parsed_args['headers'] ) && is_array( $parsed_args['headers'] ) ) {
					unset( $parsed_args['headers']['Authorization'], $parsed_args['headers']['authorization'] );
				}
			}

			return $parsed_args;
		};

		add_filter( 'http_api_curl', $curl_filter, 10, 3 );
		add_filter( 'http_request_args', $request_args_filter, PHP_INT_MAX, 2 );

		try {
			$response = wp_safe_remote_get( $url, $args );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			if ( isset( $response['http_response'] ) && true !== $pin_applied ) {
				return self::_request_failed_error( esc_html__( 'The remote request could not be pinned.', 'et_builder_5' ) );
			}

			return $response;
		} finally {
			remove_filter( 'http_api_curl', $curl_filter, 10 );
			remove_filter( 'http_request_args', $request_args_filter, PHP_INT_MAX );
		}
	}

	/**
	 * Whether WordPress would send this URL through an HTTP proxy.
	 *
	 * @since ??
	 *
	 * @param string $url Hop URL.
	 *
	 * @return bool
	 */
	private static function _is_sent_through_proxy( string $url ): bool {
		$send_through_proxy = false;

		if ( class_exists( WP_HTTP_Proxy::class ) ) {
			$proxy              = new WP_HTTP_Proxy();
			$send_through_proxy = $proxy->is_enabled() && $proxy->send_through_proxy( $url );
		}

		/**
		 * Filter whether this hop would be sent through a WordPress HTTP proxy.
		 *
		 * @since ??
		 *
		 * @param bool   $send_through_proxy Whether the hop is proxied.
		 * @param string $url                Hop URL.
		 */
		return (bool) apply_filters( 'et_builder_5_remote_request_send_through_proxy', $send_through_proxy, $url );
	}

	/**
	 * Resolve a redirect Location against the current hop URL.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $response    WP HTTP response.
	 * @param string               $current_url Current hop URL.
	 *
	 * @return string|WP_Error
	 */
	private static function _redirect_target_from_response( array $response, string $current_url ) {
		$location = wp_remote_retrieve_header( $response, 'location' );

		if ( is_array( $location ) ) {
			if ( 1 !== count( $location ) ) {
				return self::_request_failed_error( esc_html__( 'The remote request returned an invalid redirect.', 'et_builder_5' ) );
			}

			$location = reset( $location );
		}

		if ( ! is_string( $location ) || '' === trim( $location ) ) {
			return self::_request_failed_error( esc_html__( 'The remote request returned an invalid redirect.', 'et_builder_5' ) );
		}

		$absolute = WP_Http::make_absolute_url( trim( $location ), $current_url );
		$absolute = self::_strip_fragment( is_string( $absolute ) ? $absolute : '' );

		if ( '' === $absolute ) {
			return self::_request_failed_error( esc_html__( 'The remote request returned an invalid redirect.', 'et_builder_5' ) );
		}

		return $absolute;
	}

	/**
	 * Strip a URL fragment.
	 *
	 * @since ??
	 *
	 * @param string $url URL that may contain a fragment.
	 *
	 * @return string
	 */
	private static function _strip_fragment( string $url ): string {
		$hash_position = strpos( $url, '#' );

		if ( false === $hash_position ) {
			return $url;
		}

		return substr( $url, 0, $hash_position );
	}

	/**
	 * Effective cumulative byte cap.
	 *
	 * @since ??
	 *
	 * @param mixed $max_bytes Optional override.
	 *
	 * @return int
	 */
	private static function _effective_byte_cap( $max_bytes ): int {
		$cap = self::MAX_RESPONSE_BYTES;

		if ( is_numeric( $max_bytes ) && (int) $max_bytes > 0 ) {
			$cap = (int) $max_bytes;
		}

		$upload_max = (int) wp_max_upload_size();

		if ( $upload_max > 0 ) {
			$cap = min( $cap, $upload_max );
		}

		return $cap;
	}

	/**
	 * Create a per-hop temporary file.
	 *
	 * @since ??
	 *
	 * @param string $url Hop URL used as a filename hint.
	 *
	 * @return string|WP_Error
	 */
	private static function _create_hop_temp_file( string $url ) {
		$url_path     = wp_parse_url( $url, PHP_URL_PATH );
		$url_filename = is_string( $url_path ) && '' !== $url_path ? wp_basename( $url_path ) : 'download';
		$tmp_name     = wp_tempnam( $url_filename );

		if ( ! $tmp_name ) {
			return new WP_Error(
				'http_no_file',
				esc_html__( 'Could not create temporary file.', 'et_builder_5' )
			);
		}

		return $tmp_name;
	}

	/**
	 * Collect IP strings from a dns_get_record() row.
	 *
	 * @since ??
	 *
	 * @param mixed $record DNS record.
	 *
	 * @return string[]
	 */
	private static function _ips_from_dns_record( $record ): array {
		if ( ! is_array( $record ) ) {
			return [];
		}

		$record_ips = [];

		if ( isset( $record['ip'] ) && is_string( $record['ip'] ) && '' !== $record['ip'] ) {
			$record_ips[] = $record['ip'];
		}

		if ( isset( $record['ipv6'] ) && is_string( $record['ipv6'] ) && '' !== $record['ipv6'] ) {
			$record_ips[] = $record['ipv6'];
		}

		return $record_ips;
	}

	/**
	 * Whether a normalized host is in an exact-match allowlist.
	 *
	 * @since ??
	 *
	 * @param string   $host          Normalized hostname.
	 * @param string[] $allowed_hosts Allowlist.
	 *
	 * @return bool
	 */
	private static function _host_is_allowlisted( string $host, array $allowed_hosts ): bool {
		foreach ( $allowed_hosts as $allowed_host ) {
			if ( ! is_string( $allowed_host ) ) {
				continue;
			}

			if ( self::normalize_host( $allowed_host ) === $host ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract an IPv4 address embedded in IPv4-mapped IPv6 or NAT64.
	 *
	 * @since ??
	 *
	 * @param string $ip IP string.
	 *
	 * @return string Embedded IPv4 or empty string.
	 */
	private static function _extract_embedded_ipv4( string $ip ): string {
		$ip = strtolower( $ip );

		if ( 1 === preg_match( '/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/', $ip, $matches ) ) {
			return $matches[1];
		}

		if ( 1 === preg_match( '/^::ffff:([0-9a-f]{1,4}):([0-9a-f]{1,4})$/', $ip, $matches ) ) {
			return self::_hex_pair_to_ipv4( $matches[1], $matches[2] );
		}

		if ( 1 === preg_match( '/^64:ff9b::(\d{1,3}(?:\.\d{1,3}){3})$/', $ip, $matches ) ) {
			return $matches[1];
		}

		if ( 1 === preg_match( '/^64:ff9b::([0-9a-f]{1,4}):([0-9a-f]{1,4})$/', $ip, $matches ) ) {
			return self::_hex_pair_to_ipv4( $matches[1], $matches[2] );
		}

		return '';
	}

	/**
	 * Convert two IPv6 hextets into dotted IPv4.
	 *
	 * @since ??
	 *
	 * @param string $high High hextet.
	 * @param string $low  Low hextet.
	 *
	 * @return string
	 */
	private static function _hex_pair_to_ipv4( string $high, string $low ): string {
		$high_value = hexdec( $high );
		$low_value  = hexdec( $low );

		return sprintf(
			'%d.%d.%d.%d',
			( $high_value >> 8 ) & 0xff,
			$high_value & 0xff,
			( $low_value >> 8 ) & 0xff,
			$low_value & 0xff
		);
	}

	/**
	 * Content-Length header as an integer, or 0 when absent/invalid.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $response WP HTTP response.
	 *
	 * @return int
	 */
	private static function _content_length_from_response( array $response ): int {
		$header = wp_remote_retrieve_header( $response, 'content-length' );

		if ( is_array( $header ) ) {
			$header = reset( $header );
		}

		if ( ! is_numeric( $header ) ) {
			return 0;
		}

		return (int) $header;
	}

	/**
	 * Filesize for a path, or 0 when unreadable.
	 *
	 * @since ??
	 *
	 * @param string $path File path.
	 *
	 * @return int
	 */
	private static function _file_size_or_zero( string $path ): int {
		if ( ! file_exists( $path ) ) {
			return 0;
		}

		$size = filesize( $path );

		return false === $size ? 0 : (int) $size;
	}

	/**
	 * Delete temporary files that this utility still owns.
	 *
	 * @since ??
	 *
	 * @param string[] $paths File paths.
	 *
	 * @return void
	 */
	private static function _delete_owned_files( array $paths ): void {
		foreach ( $paths as $path ) {
			if ( ! is_string( $path ) || '' === $path || ! file_exists( $path ) ) {
				continue;
			}

			wp_delete_file( $path );
		}
	}

	/**
	 * REST-compatible invalid URL error.
	 *
	 * @since ??
	 *
	 * @param string $message Error message.
	 *
	 * @return WP_Error
	 */
	private static function _invalid_param_error( string $message ): WP_Error {
		return new WP_Error(
			'rest_invalid_param',
			$message,
			[ 'status' => 400 ]
		);
	}

	/**
	 * Generic non-retryable download failure.
	 *
	 * @since ??
	 *
	 * @param string $message Error message.
	 *
	 * @return WP_Error
	 */
	private static function _request_failed_error( string $message ): WP_Error {
		return new WP_Error(
			'http_request_failed',
			$message,
			[ 'status' => 500 ]
		);
	}

	/**
	 * Response-body overflow error.
	 *
	 * @since ??
	 *
	 * @return WP_Error
	 */
	private static function _too_large_error(): WP_Error {
		return new WP_Error(
			'remote_file_too_large',
			esc_html__( 'The remote file exceeds the allowed size.', 'et_builder_5' ),
			[ 'status' => 413 ]
		);
	}

	/**
	 * Map an origin HTTP status to a sideload-compatible error.
	 *
	 * @since ??
	 *
	 * @param int    $status  HTTP status.
	 * @param string $message Origin status message.
	 *
	 * @return WP_Error
	 */
	private static function _error_for_http_status( int $status, string $message ): WP_Error {
		if ( 403 === $status ) {
			return new WP_Error(
				'http_403',
				'' !== $message ? $message : esc_html__( 'Forbidden', 'et_builder_5' ),
				[ 'status' => 403 ]
			);
		}

		if ( 404 === $status ) {
			return new WP_Error(
				'http_404',
				'' !== $message ? $message : esc_html__( 'Not Found', 'et_builder_5' ),
				[ 'status' => 404 ]
			);
		}

		return new WP_Error(
			'http_request_failed',
			esc_html__( 'The remote file could not be downloaded.', 'et_builder_5' ),
			[ 'status' => $status > 0 ? $status : 500 ]
		);
	}
}
