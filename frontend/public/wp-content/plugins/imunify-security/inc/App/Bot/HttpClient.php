<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Minimal HTTP GET abstraction used by MirrorDownloader.
 *
 * Production code wires this to wp_remote_get via WpHttpClient; tests
 * inject a fake that returns pre-set responses. Implementations never throw
 * and never return null: a request that failed at the transport level comes
 * back as `HttpResponse::transportError()`, so judging a response — status,
 * declared size against real size, whether a retry is worth it — is the
 * caller's decision rather than a detail buried in the client.
 *
 * @since 4.0.0
 */
interface HttpClient {

	/**
	 * Perform a GET request.
	 *
	 * @param string               $url     Absolute URL to fetch.
	 * @param array<string,string> $headers Extra request headers.
	 * @param int|null             $timeout Timeout in seconds for this request; the
	 *                                      implementation's own timeout when null,
	 *                                      and never longer than it.
	 * @return HttpResponse Always a response object, never null.
	 */
	public function get( $url, $headers = array(), $timeout = null );
}
