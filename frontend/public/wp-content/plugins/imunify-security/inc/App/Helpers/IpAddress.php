<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Helpers;

/**
 * IP Address helper class for extracting client IP addresses.
 *
 * Provides reliable IP address extraction supporting various proxy headers
 * and load balancer configurations.
 *
 * @since 2.1.0
 */
class IpAddress {

	/**
	 * Get current user IP address with proxy support.
	 *
	 * Checks various headers in order of preference to determine the real client IP.
	 * Supports common proxy headers and load balancer configurations.
	 *
	 * @param \CloudLinux\Imunify\App\Defender\Request $request Request object containing server data.
	 * @return string Current user IP address or '0.0.0.0' if not found.
	 */
	public static function getClientIp( $request ) {
		// Headers to check in order of preference.
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'HTTP_VIA',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			$value = null;

			if ( 'REMOTE_ADDR' === $header ) {
				// Handle REMOTE_ADDR directly from server array.
				$server_data = $request->getAllServer();
				$value       = isset( $server_data['REMOTE_ADDR'] ) ? $server_data['REMOTE_ADDR'] : null;
			} else {
				// Convert header name to proper format (remove HTTP_ prefix for getHeader method).
				$header_name = str_replace( 'HTTP_', '', $header );
				$value       = $request->getHeader( $header_name );
			}

			if ( empty( $value ) ) {
				continue;
			}

			// Validate and return the first valid IP found.
			$value        = trim( $value );
			$validated_ip = self::validate_ip( $value );
			if ( '0.0.0.0' !== $validated_ip ) {
				return $validated_ip;
			}
		}

		// Fallback to '0.0.0.0' if no valid IP found.
		return '0.0.0.0';
	}

	/**
	 * Validate and sanitize IP address.
	 *
	 * @param string $ip The IP address to validate.
	 *
	 * @return string Validated IP address or '0.0.0.0' if invalid.
	 */
	private static function validate_ip( $ip ) {
		if ( empty( $ip ) ) {
			return '0.0.0.0';
		}

		// Validate IP address using filter_var.
		$validated_ip = filter_var( $ip, FILTER_VALIDATE_IP );

		if ( false === $validated_ip ) {
			return '0.0.0.0';
		}

		return $validated_ip;
	}
}
