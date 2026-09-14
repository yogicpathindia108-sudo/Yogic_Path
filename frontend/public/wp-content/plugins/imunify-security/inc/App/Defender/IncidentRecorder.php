<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
 */

namespace CloudLinux\Imunify\App\Defender;

use CloudLinux\Imunify\App\Bot\AtomicFileWriter;
use CloudLinux\Imunify\App\Defender\Model\Rule;
use CloudLinux\Imunify\App\Defender\Model\TargetInfo;
use CloudLinux\Imunify\App\Helpers\IpAddress;

/**
 * Incident recorder for security events.
 *
 * Records security incidents to daily files for processing by the Imunify agent.
 *
 * @since 2.1.0
 */
class IncidentRecorder {

	/**
	 * Maximum size for raw POST data in bytes (1 kB).
	 *
	 * @var int
	 */
	const RAW_DATA_MAX_SIZE = 1024;

	/**
	 * Maximum JSON-encoded size for array data fields in bytes (10 kB).
	 *
	 * @var int
	 */
	const ARRAY_DATA_MAX_SIZE = 10240;

	/**
	 * Rate limiter instance.
	 *
	 * @var RateLimiter
	 */
	private $rateLimiter;

	/**
	 * Constructor.
	 *
	 * @param RateLimiter $rateLimiter Rate limiter instance.
	 */
	public function __construct( RateLimiter $rateLimiter ) {
		$this->rateLimiter = $rateLimiter;
	}

	/**
	 * Record a security incident.
	 *
	 * @param Rule       $rule       The rule that was triggered.
	 * @param string     $mode       The rule mode (pass or block).
	 * @param TargetInfo $targetInfo Target information.
	 * @param Request    $request    Request object.
	 * @param string     $version    Ruleset version.
	 *
	 * @return void
	 */
	public function recordIncident( Rule $rule, $mode, TargetInfo $targetInfo, Request $request, $version = '' ) {
		// Extract client IP address for rate limiting.
		$ip_address = IpAddress::getClientIp( $request );

		// Check rate limit before recording incident.
		if ( ! $this->rateLimiter->checkRateLimit( $rule->getId(), $ip_address ) ) {
			// Rate limit exceeded, silently skip recording.
			return;
		}

		$incidentData = $this->buildIncidentData( $rule, $mode, $targetInfo, $request, $version );
		$this->writeIncidentToFile( $incidentData );
	}

	/**
	 * Write incident data to the hourly file.
	 *
	 * @param array $incidentData The incident data to write.
	 *
	 * @return void
	 */
	private function writeIncidentToFile( $incidentData ) {
		$filePath = $this->getHourlyFilePath();

		// Ensure directory exists before writing.
		if ( ! $this->ensureDirectoryExists() ) {
			// Failed to ensure directory exists, cannot proceed.
			return;
		}

		// Encode the JSON data as base64 and format with PHP comment prefix.
		$jsonData    = wp_json_encode( $incidentData );
		$encodedData = base64_encode( $jsonData ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$jsonLine    = '#' . $encodedData . "\n";

		// Check if file exists to determine if we need to write the PHP header.
		$fileExists = file_exists( $filePath );

		// If file doesn't exist, write the PHP header first.
		if ( ! $fileExists ) {
			$phpHeader = "<?php __halt_compiler();\n";
			@file_put_contents( $filePath, $phpHeader );
		}

		// Append to the hourly file.
		@file_put_contents(
			$filePath,
			$jsonLine,
			FILE_APPEND
		);
	}

	/**
	 * Build incident data array.
	 *
	 * @param Rule       $rule       The rule that was triggered.
	 * @param string     $mode       The rule mode (pass or block).
	 * @param TargetInfo $targetInfo Target information.
	 * @param Request    $request    Request object.
	 * @param string     $version    Ruleset version.
	 *
	 * @return array
	 */
	private function buildIncidentData( Rule $rule, $mode, TargetInfo $targetInfo, Request $request, $version = '' ) {
		$result = array(
			'ts'             => time(),
			'rule_id'        => $rule->getId(),
			'cve'            => $rule->getCve(),
			'mode'           => $mode,
			'target'         => $targetInfo->getType(),
			'slug'           => $targetInfo->getSlug(),
			'version'        => $targetInfo->getVersion(),
			'user_logged_in' => is_user_logged_in(),
			'attacker_ip'    => IpAddress::getClientIp( $request ),
			'message'        => $this->buildLogMessage( $rule, $mode, $targetInfo, $version ),
			'FILES'          => $this->getFilesData( $request ),
			'GET_NAMES'      => $this->getGetNames( $request ),
			'POST_NAMES'     => $this->getPostNames( $request ),
			'RAW_DATA'       => $this->getRawPostData(),
		);

		// Merge server data fields at top level.
		return array_merge( $result, $this->getServerData( $request ) );
	}

	/**
	 * Build log message for the incident.
	 *
	 * @param Rule       $rule       The rule that was triggered.
	 * @param string     $mode       The rule mode (pass or block).
	 * @param TargetInfo $targetInfo Target information.
	 * @param string     $version    Ruleset version.
	 *
	 * @return string
	 */
	private function buildLogMessage( Rule $rule, $mode, TargetInfo $targetInfo, $version = '' ) {
		$message = sprintf(
			'IM WP plugin: %s %s %s %s %s',
			$rule->getId(),
			$rule->getCve(),
			$targetInfo->getSlug(),
			$targetInfo->getVersion(),
			$mode
		);

		$probeData = $rule->getProbeData();
		if ( ! empty( $probeData ) ) {
			$message .= '||PROBE:' . $probeData;
		}

		if ( ! empty( $version ) ) {
			$message .= '||RSV:' . $version;
		}

		return $message;
	}


	/**
	 * Get server data from Request object.
	 *
	 * @param Request $request Request object.
	 *
	 * @return array
	 */
	private function getServerData( Request $request ) {
		$result = array();
		$server = $request->getAllServer();

		$serverFields = array(
			'REMOTE_ADDR',
			'REQUEST_METHOD',
			'SCRIPT_FILENAME',
			'PHP_SELF',
			'PATH_INFO',
			'REQUEST_URI',
			'QUERY_STRING',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_USER_AGENT',
			'HTTP_REFERER',
		);

		foreach ( $serverFields as $field ) {
			$result[ $field ] = isset( $server[ $field ] ) ? $server[ $field ] : '';
		}

		return $result;
	}

	/**
	 * Get raw POST data from php://input.
	 *
	 * Limits the data to first 1 kB and base64 encodes it.
	 *
	 * @return string Base64-encoded string of first 1 kB of raw POST data.
	 */
	public function getRawPostData() {
		$result = @file_get_contents( 'php://input' );

		if ( ! $result ) {
			return '';
		}

		// Limit to first 1 kB.
		$truncated = substr( $result, 0, self::RAW_DATA_MAX_SIZE );

		// Base64 encode the truncated data.
		return base64_encode( $truncated ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Get POST argument names as array.
	 *
	 * @param Request $request Request object.
	 *
	 * @return array Array of POST argument names, limited to 10 kB JSON-encoded size.
	 */
	private function getPostNames( Request $request ) {
		// getPostNames() already drops the synthetic fail-closed RAW_BODY_KEY
		// sentinel, so it is not logged as a client-supplied POST parameter.
		$names = $request->getPostNames();

		// Limit the array to 10 kB JSON-encoded size.
		return $this->limitArraySize( $names, self::ARRAY_DATA_MAX_SIZE );
	}

	/**
	 * Get GET argument names as array.
	 *
	 * @param Request $request Request object.
	 *
	 * @return array Array of GET argument names, limited to 10 kB JSON-encoded size.
	 */
	private function getGetNames( Request $request ) {
		$names = array_keys( $request->getAllGet() );

		// Limit the array to 10 kB JSON-encoded size.
		return $this->limitArraySize( $names, self::ARRAY_DATA_MAX_SIZE );
	}

	/**
	 * Get files information.
	 *
	 * Handles both single-file and multi-file upload structures from PHP's
	 * $_FILES superglobal. Multi-file uploads (e.g. name="files[]") produce
	 * arrays for 'name', 'type', 'size' — these are expanded into individual
	 * entries keyed as "{fieldName}_0", "{fieldName}_1", etc.
	 *
	 * @param Request $request Request object.
	 *
	 * @return array Detailed file information, limited to 10 kB JSON-encoded size.
	 */
	private function getFilesData( Request $request ) {
		$result = array();
		$files  = $request->getAllFiles();

		foreach ( $files as $fieldName => $fileInfo ) {
			if ( ! isset( $fileInfo['name'] ) ) {
				continue;
			}

			if ( is_array( $fileInfo['name'] ) ) {
				$names = $fileInfo['name'];
				$types = isset( $fileInfo['type'] ) && is_array( $fileInfo['type'] ) ? $fileInfo['type'] : array();
				$sizes = isset( $fileInfo['size'] ) && is_array( $fileInfo['size'] ) ? $fileInfo['size'] : array();

				foreach ( $names as $index => $name ) {
					$key            = $fieldName . '_' . $index;
					$result[ $key ] = array(
						'name' => $name,
						'type' => isset( $types[ $index ] ) ? $types[ $index ] : '',
						'size' => isset( $sizes[ $index ] ) ? $sizes[ $index ] : 0,
					);
				}
			} else {
				$result[ $fieldName ] = array(
					'name' => $fileInfo['name'],
					'type' => isset( $fileInfo['type'] ) ? $fileInfo['type'] : '',
					'size' => isset( $fileInfo['size'] ) ? $fileInfo['size'] : 0,
				);
			}
		}

		// Limit the array to 10 kB JSON-encoded size.
		return $this->limitArraySize( $result, self::ARRAY_DATA_MAX_SIZE );
	}

	/**
	 * Get the hourly file path for incidents.
	 *
	 * @return string
	 */
	private function getHourlyFilePath() {
		$date   = gmdate( 'Y-m-d-H' );
		$result = WP_CONTENT_DIR . '/imunify-security/incidents';
		return $result . '/' . $date . '.php';
	}

	/**
	 * Ensure the incidents directory exists.
	 *
	 * @return bool
	 */
	public function ensureDirectoryExists() {
		$incidentsDir = WP_CONTENT_DIR . '/imunify-security/incidents';
		if ( ! is_dir( $incidentsDir ) ) {
			$success = wp_mkdir_p( $incidentsDir );
			if ( ! $success ) {
				return false;
			}
		}

		// Ensure directory listing protection is in place.
		$this->ensureDirectoryListingProtection( $incidentsDir );

		return true;
	}

	/**
	 * Ensure directory listing protection files exist.
	 *
	 * Creates .htaccess, index.php, and index.html files to prevent directory listing.
	 *
	 * @param string $directory The directory path to protect.
	 *
	 * @return void
	 */
	public function ensureDirectoryListingProtection( $directory ) {
		AtomicFileWriter::ensureDirectoryProtection( $directory );
	}

	/**
	 * Limit array size based on estimated JSON-encoded size.
	 *
	 * @param array $array The array to limit.
	 * @param int   $max_size Maximum size in bytes for JSON-encoded array.
	 *
	 * @return array The truncated array that fits within the size limit.
	 */
	private function limitArraySize( $array, $max_size ) {
		if ( empty( $array ) ) {
			return $array;
		}

		$is_numeric_array = $this->isNumericArray( $array );
		$result           = array();
		$estimated_size   = 2;

		foreach ( $array as $key => $value ) {
			$elementSize = 0;

			if ( $is_numeric_array ) {
				$elementSize = strlen( $value ) + 2;
			} else {
				$keySize      = strlen( (string) $key ) + 4;
				$fileInfoSize = 2;
				$first_inner  = true;
				foreach ( $value as $k => $v ) {
					$keyLen        = strlen( (string) $k );
					$valLen        = is_array( $v )
						? strlen( (string) wp_json_encode( $v ) )
						: strlen( (string) $v );
					$fileInfoSize += $keyLen + $valLen + 5;
					if ( ! $first_inner ) {
						$fileInfoSize++;
					}
					$first_inner = false;
				}
				$elementSize = $keySize + $fileInfoSize;
			}

			if ( ! empty( $result ) ) {
				$elementSize++;
			}

			if ( $estimated_size + $elementSize > $max_size ) {
				break;
			}

			$result[ $key ]  = $value;
			$estimated_size += $elementSize;
		}

		return $result;
	}

	/**
	 * Check if an array is a numeric array (sequential integer keys starting from 0).
	 *
	 * @param array $array The array to check.
	 *
	 * @return bool True if numeric array, false if associative.
	 */
	private function isNumericArray( $array ) {
		if ( empty( $array ) ) {
			return true;
		}

		$keys         = array_keys( $array );
		$expected_key = 0;

		foreach ( $keys as $key ) {
			if ( ! is_int( $key ) || $key !== $expected_key ) {
				return false;
			}
			++$expected_key;
		}

		return true;
	}
}
