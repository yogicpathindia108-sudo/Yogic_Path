<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender\Handler;

use CloudLinux\Imunify\App\Defender\ConditionEvaluator;
use CloudLinux\Imunify\App\Defender\IncidentRecorder;
use CloudLinux\Imunify\App\Defender\Model\Rule;
use CloudLinux\Imunify\App\Defender\Model\RuleMode;
use CloudLinux\Imunify\App\Defender\Model\TargetInfo;
use CloudLinux\Imunify\App\Defender\Request;
use CloudLinux\Imunify\App\Defender\RuleHitTracker;

/**
 * Handler class for rule handlers in the Defender module.
 * Provides common functionality for blocking requests and handling configuration.
 *
 * @since 2.1.0
 */
class Handler implements HandlerInterface {

	/**
	 * Rule object for this handler.
	 *
	 * @var Rule
	 */
	protected $rule;

	/**
	 * Request object.
	 *
	 * @var Request
	 */
	protected $request;

	/**
	 * Incident recorder.
	 *
	 * @var IncidentRecorder
	 */
	protected $incidentRecorder;

	/**
	 * Rule hit tracker.
	 *
	 * @var RuleHitTracker
	 */
	protected $hitTracker;

	/**
	 * Target information.
	 *
	 * @var TargetInfo
	 */
	protected $targetInfo;

	/**
	 * Ruleset version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Optional condition evaluator override.
	 *
	 * @since 3.0.4
	 *
	 * @var ConditionEvaluator|null
	 */
	private $conditionEvaluator = null;

	/**
	 * Constructor.
	 *
	 * @param Rule             $rule             Rule object.
	 * @param Request          $request          Request object.
	 * @param IncidentRecorder $incidentRecorder Incident recorder instance.
	 * @param RuleHitTracker   $hitTracker       Rule hit tracker instance.
	 * @param TargetInfo       $targetInfo       Target information.
	 * @param string           $version          Ruleset version.
	 */
	public function __construct( $rule, $request, $incidentRecorder, $hitTracker, $targetInfo, $version = '' ) {
		$this->rule             = $rule;
		$this->request          = $request;
		$this->incidentRecorder = $incidentRecorder;
		$this->hitTracker       = $hitTracker;
		$this->targetInfo       = $targetInfo;
		$this->version          = $version;
	}

	/**
	 * {@inheritDoc}
	 */
	public function apply() {
		$hooks = $this->getHooks();
		foreach ( $hooks as $hook ) {
			add_action( $hook, array( $this, 'maybeBlock' ), 0 );
		}
	}

	/**
	 * Get the hooks to which this handler should be applied.
	 *
	 * @return array
	 */
	protected function getHooks() {
		// Check for AJAX action configuration.
		if ( $this->rule->getAjaxAction() ) {
			$ajaxAction = $this->rule->getAjaxAction();
			return array(
				'wp_ajax_' . $ajaxAction,
				'wp_ajax_nopriv_' . $ajaxAction,
			);
		}

		// Check for regular action configuration.
		if ( $this->rule->getAction() ) {
			return array( $this->rule->getAction() );
		}

		return array();
	}

	/**
	 * Set a custom condition evaluator (used in tests).
	 *
	 * @since 3.0.4
	 *
	 * @param ConditionEvaluator $evaluator Evaluator instance.
	 *
	 * @return void
	 */
	public function setConditionEvaluator( ConditionEvaluator $evaluator ) {
		$this->conditionEvaluator = $evaluator;
	}

	/**
	 * {@inheritDoc}
	 */
	public function maybeBlock() {
		$conditions = $this->rule->getConditions();

		if ( ! empty( $conditions ) ) {
			$evaluator = $this->conditionEvaluator
				? $this->conditionEvaluator
				: new ConditionEvaluator();

			if ( ! $evaluator->evaluateConditions( $conditions, $this->request ) ) {
				return;
			}

			$probeData = $evaluator->getProbeData();
			if ( null !== $probeData ) {
				$this->rule->setProbeData( (string) $probeData );
			}
		}

		$this->processIncident();
	}

	/**
	 * Process a security incident by evaluating the rule mode and potentially blocking.
	 *
	 * Records the incident and blocks if mode is 'block'.
	 *
	 * @since 3.0.4 Data-collection hit tracking skip; probe data read from Rule.
	 *
	 * @return void
	 */
	protected function processIncident() {
		do_action( 'imunify_security_set_error_handler' );
		$this->incidentRecorder->recordIncident( $this->rule, $this->rule->getMode(), $this->targetInfo, $this->request, $this->version );
		do_action( 'imunify_security_restore_error_handler' );

		if ( ! $this->rule->isInternal() ) {
			$this->hitTracker->recordHit( $this->rule );
		}

		if ( $this->rule->getMode() === RuleMode::PASS ) {
			return;
		}

		$this->blockRequest();
	}

	/**
	 * Block the request by sending a 403 response and terminating execution.
	 *
	 * @return void
	 */
	protected function blockRequest() {
		nocache_headers();
		status_header( 403 );
		die;
	}
}
