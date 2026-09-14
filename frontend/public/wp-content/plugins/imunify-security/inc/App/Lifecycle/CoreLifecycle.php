<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Lifecycle;

use CloudLinux\Imunify\App\DataStore;
use CloudLinux\Imunify\App\Debug;
use CloudLinux\Imunify\App\Helpers\TransientCleaner;
use CloudLinux\Imunify\App\Views\Widget;

/**
 * Removes generic plugin data not owned by a specific feature: the package
 * version option, per-user dashboard-widget snooze state, and the generic
 * error-throttle transients.
 */
class CoreLifecycle {

	/**
	 * Remove generic plugin data on uninstall.
	 *
	 * @return void
	 */
	public static function uninstall() {
		delete_option( DataStore::PACKAGE_VERSIONS_OPTION );
		delete_metadata( 'user', 0, Widget::WIDGET_SNOOZED_META_KEY, '', true );
		TransientCleaner::deleteByPrefix( Debug::ERROR_THROTTLE_PREFIX );
	}
}
