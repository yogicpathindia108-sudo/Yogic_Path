<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender\Model;

/**
 * Target information model.
 *
 * Contains information about the target (plugin, theme, or core) that was matched during rule processing.
 *
 * @since 2.1.0
 */
class TargetInfo {

	/**
	 * Target type (plugin, theme, core).
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Target slug.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Target version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @param string $type    Target type.
	 * @param string $slug    Target slug.
	 * @param string $version Target version.
	 */
	public function __construct( $type, $slug, $version ) {
		$this->type    = $type;
		$this->slug    = $slug;
		$this->version = $version;
	}

	/**
	 * Get target type.
	 *
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Get target slug.
	 *
	 * @return string
	 */
	public function getSlug() {
		return $this->slug;
	}

	/**
	 * Get target version.
	 *
	 * @return string
	 */
	public function getVersion() {
		return $this->version;
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function toArray() {
		return array(
			'type'    => $this->type,
			'slug'    => $this->slug,
			'version' => $this->version,
		);
	}
}
