<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Classifier-agnostic allowlist predicate.
 *
 * Each implementation inspects a loosely-typed $context array (keys:
 * 'uri', 'headers', 'ip', 'ua') and returns true when the request
 * belongs to a well-known infrastructure flow that must bypass rate
 * limiting. The interface is narrow on purpose — RequestAllowlist
 * composes matchers in priority order and returns on the first true,
 * so each matcher can be reasoned about in isolation.
 *
 * @since 4.0.0
 */
interface AllowlistMatcher {

	/**
	 * Decide whether the given request context is allowlisted.
	 *
	 * @param array $context Request context (uri/headers/ip/ua — see RequestAllowlist).
	 * @return bool
	 */
	public function matches( $context );
}
