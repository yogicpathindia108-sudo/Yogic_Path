<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Views\Display;

use CloudLinux\Imunify\App\Defender\Model\Rule;
use CloudLinux\Imunify\App\Defender\Model\Target;
use CloudLinux\Imunify\App\Helpers\DateTimeFormatter;

/**
 * Display data for a rule in the widget template.
 *
 * This class provides a simple data structure for displaying rule information
 * in templates without exposing the full Rule model logic.
 */
class RuleDisplay {
	/**
	 * Component/slug name.
	 *
	 * @var string
	 */
	public $component;

	/**
	 * Version constraint.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Incidents count in last 7 days.
	 *
	 * @var int
	 */
	public $incidentsCount;

	/**
	 * Status of the rule.
	 *
	 * @var string
	 */
	public $status;

	/**
	 * CVE identifier.
	 *
	 * @var string
	 */
	public $cve;

	/**
	 * CVE link URL.
	 *
	 * @var string
	 */
	public $cveLink;

	/**
	 * Severity score (CVSS 0-10).
	 *
	 * @var float
	 */
	public $severity;

	/**
	 * Unix timestamp of the last incident.
	 *
	 * @var int|null
	 */
	public $lastIncidentTimestamp;

	/**
	 * Formatted last incident date (relative time or date).
	 *
	 * @var string
	 */
	public $lastIncidentDateFormatted;

	/**
	 * Create display object from Rule model.
	 *
	 * @param Rule     $rule          Rule model instance.
	 * @param int      $hitCount      Total hit count for the last 7 days.
	 * @param int|null $lastTimestamp Unix timestamp of the most recent hit.
	 * @return RuleDisplay
	 */
	public static function fromRule( Rule $rule, $hitCount = 0, $lastTimestamp = null ) {
		$display = new self();

		// For core rules, display "WordPress" instead of empty slug.
		if ( Target::CORE === $rule->getTarget() ) {
			$display->component = 'WordPress';
		} else {
			$display->component = $rule->getSlug();
		}

		$display->version                   = $rule->getVersions();
		$display->incidentsCount            = $hitCount;
		$display->status                    = 'active'; // TBD: To be implemented.
		$display->cve                       = $rule->getCve();
		$display->cveLink                   = $rule->getCveLink();
		$display->severity                  = $rule->getSeverity();
		$display->lastIncidentTimestamp     = $lastTimestamp;
		$display->lastIncidentDateFormatted = null !== $lastTimestamp
			? DateTimeFormatter::formatRelativeTime( $lastTimestamp )
			: '';
		return $display;
	}
}
