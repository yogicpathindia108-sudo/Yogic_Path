<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender\Model;

use CloudLinux\Imunify\App\Defender\Model\Target;

/**
 * Rule model class.
 *
 * Represents a security rule for runtime fixes.
 */
class Rule {
	/**
	 * Rule identifier (e.g., CVE number).
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Target type (plugin, theme, core).
	 *
	 * @var string
	 */
	private $target;

	/**
	 * Target slug.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Version constraint.
	 *
	 * @var string
	 */
	private $versions;



	/**
	 * Rule mode (pass or block).
	 *
	 * @var string
	 */
	private $mode;

	/**
	 * HTTP method filter (optional).
	 *
	 * @var string|null
	 */
	private $method;

	/**
	 * Action hook name (optional).
	 *
	 * @var string|null
	 */
	private $action;

	/**
	 * AJAX action name (optional).
	 *
	 * @var string|null
	 */
	private $ajaxAction;

	/**
	 * Rule conditions.
	 *
	 * @var array
	 */
	private $conditions;

	/**
	 * Rule configuration.
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Rule tags.
	 *
	 * @var array
	 */
	private $tags;

	/**
	 * Rule description.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * CVE identifier.
	 *
	 * @var string
	 */
	private $cve;

	/**
	 * CVE link URL.
	 *
	 * @var string
	 */
	private $cveLink;

	/**
	 * Probe data collected during condition evaluation.
	 *
	 * @since 3.0.4
	 *
	 * @var string
	 */
	private $probeData = '';

	/**
	 * Rule severity level (CVSS score 0-10).
	 *
	 * @var float
	 */
	private $severity;

	/**
	 * Create a rule from an array.
	 *
	 * @param string $id   Rule identifier.
	 * @param array  $data Rule data.
	 *
	 * @return Rule
	 */
	public static function fromArray( $id, $data ) {
		$rule              = new self();
		$rule->id          = $id;
		$rule->target      = isset( $data['target'] ) ? $data['target'] : '';
		$rule->slug        = isset( $data['slug'] ) ? $data['slug'] : '';
		$rule->versions    = isset( $data['versions'] ) ? $data['versions'] : '';
		$rule->method      = isset( $data['method'] ) ? $data['method'] : null;
		$rule->mode        = isset( $data['mode'] ) ? $data['mode'] : RuleMode::BLOCK;
		$rule->action      = isset( $data['action'] ) ? $data['action'] : null;
		$rule->ajaxAction  = isset( $data['ajax_action'] ) ? $data['ajax_action'] : null;
		$rule->conditions  = isset( $data['conditions'] ) ? $data['conditions'] : array();
		$rule->config      = isset( $data['config'] ) ? $data['config'] : array();
		$rule->tags        = isset( $data['tags'] ) ? $data['tags'] : array();
		$rule->description = isset( $data['description'] ) ? $data['description'] : '';
		$rule->cve         = isset( $data['cve'] ) ? $data['cve'] : '';
		$rule->cveLink     = isset( $data['cve_link'] ) ? $data['cve_link'] : '';
		$rule->severity    = isset( $data['severity'] ) ? Severity::normalize( $data['severity'] ) : Severity::DEFAULT_SEVERITY;

		// Backward compatibility: Convert config.capability to missing_capability condition.
		if ( ! empty( $rule->config ) && isset( $rule->config['capability'] ) ) {
			$capability = $rule->config['capability'];

			// Check if there's already a missing_capability condition.
			$has_missing_capability = false;
			if ( ! empty( $rule->conditions ) && is_array( $rule->conditions ) ) {
				foreach ( $rule->conditions as $condition ) {
					if ( isset( $condition['type'] ) && 'missing_capability' === $condition['type'] ) {
						$has_missing_capability = true;
						break;
					}
				}
			}

			// If no missing_capability condition exists, convert config.capability to a condition.
			if ( ! $has_missing_capability ) {
				$capability_condition = array(
					'type'  => 'missing_capability',
					'value' => $capability,
				);
				$rule->conditions[]   = $capability_condition;
			}

			// Remove the entire config array since it's no longer needed.
			$rule->config = array();
		}

		return $rule;
	}

	/**
	 * Convert rule to array.
	 *
	 * @return array
	 */
	public function toArray() {
		$data = array(
			'cve'         => $this->cve,
			'description' => $this->description,
			'cve_link'    => $this->cveLink,
			'severity'    => $this->severity,
			'tags'        => $this->tags,
			'mode'        => $this->mode,
			'target'      => $this->target,
			'slug'        => $this->slug,
			'versions'    => $this->versions,
		);

		if ( $this->method ) {
			$data['method'] = $this->method;
		}

		if ( $this->action ) {
			$data['action'] = $this->action;
		}

		if ( $this->ajaxAction ) {
			$data['ajax_action'] = $this->ajaxAction;
		}

		$data['conditions'] = $this->conditions;

		// Only include config if it's not empty.
		if ( ! empty( $this->config ) ) {
			$data['config'] = $this->config;
		}

		return $data;
	}

	/**
	 * Get rule identifier.
	 *
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Get target type.
	 *
	 * @return string
	 */
	public function getTarget() {
		return $this->target;
	}

	/**
	 * Get target name.
	 *
	 * @return string
	 */
	public function getSlug() {
		return $this->slug;
	}

	/**
	 * Get version constraint.
	 *
	 * @return string
	 */
	public function getVersions() {
		return $this->versions;
	}

	/**
	 * Get HTTP method.
	 *
	 * @return string|null
	 */
	public function getMethod() {
		return $this->method;
	}

	/**
	 * Get rule mode.
	 *
	 * @return string
	 */
	public function getMode() {
		return $this->mode;
	}

	/**
	 * Get rule configuration.
	 *
	 * @return array
	 */
	public function getConfig() {
		return $this->config;
	}

	/**
	 * Get action hook name.
	 *
	 * @return string|null
	 */
	public function getAction() {
		return $this->action;
	}

	/**
	 * Get AJAX action name.
	 *
	 * @return string|null
	 */
	public function getAjaxAction() {
		return $this->ajaxAction;
	}

	/**
	 * Get rule tags.
	 *
	 * @return array
	 */
	public function getTags() {
		return $this->tags;
	}

	/**
	 * Get rule description.
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * Get CVE identifier.
	 *
	 * @return string
	 */
	public function getCve() {
		return $this->cve;
	}

	/**
	 * Get CVE link URL.
	 *
	 * @return string
	 */
	public function getCveLink() {
		return $this->cveLink;
	}

	/**
	 * Get rule severity level (CVSS score).
	 *
	 * @return float
	 */
	public function getSeverity() {
		return $this->severity;
	}

	/**
	 * Internal rules use the TEST- prefix (heartbeat, probes) and are
	 * hidden from the WP admin UI / hit stats.
	 *
	 * @since 3.0.4
	 *
	 * @return bool
	 */
	public function isInternal() {
		return 0 === strpos( $this->id, 'TEST-' );
	}

	/**
	 * Get probe data attached to this rule.
	 *
	 * @since 3.0.4
	 *
	 * @return string
	 */
	public function getProbeData() {
		return $this->probeData;
	}

	/**
	 * Set probe data on this rule.
	 *
	 * @since 3.0.4
	 *
	 * @param string $probeData Probe result string.
	 *
	 * @return void
	 */
	public function setProbeData( $probeData ) {
		$this->probeData = $probeData;
	}

	/**
	 * Check if rule mode is valid.
	 *
	 * @return bool True if the rule mode is valid, false otherwise.
	 */
	public function isValidMode() {
		return RuleMode::isValid( $this->mode );
	}

	/**
	 * Check if rule severity is valid.
	 *
	 * @return bool True if the rule severity is valid, false otherwise.
	 */
	public function isValidSeverity() {
		return Severity::isValid( $this->severity );
	}

	/**
	 * Check if rule target is valid.
	 *
	 * @return bool True if the rule target is valid, false otherwise.
	 */
	public function isValidTarget() {
		return Target::isValid( $this->target );
	}

	/**
	 * Get conditions from rule.
	 *
	 * @return Condition[]
	 */
	public function getConditions() {
		if ( ! is_array( $this->conditions ) ) {
			return array();
		}

		$conditions = array();
		foreach ( $this->conditions as $condition_data ) {
			$conditions[] = Condition::fromArray( $condition_data );
		}

		return $conditions;
	}
}
