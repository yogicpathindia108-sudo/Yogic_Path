<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender\Handler;

/**
 * Interface for rule handlers in the Defender module.
 *
 * This interface defines the methods that any rule handler must implement.
 *
 * @since 2.1.0
 */
interface HandlerInterface {
	/**
	 * Apply the rule handler.
	 */
	public function apply();

	/**
	 * Maybe block the request.
	 */
	public function maybeBlock();
}
