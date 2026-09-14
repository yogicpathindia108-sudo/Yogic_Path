<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender;

use CloudLinux\Imunify\App\Defender\Handler\Handler;
use CloudLinux\Imunify\App\Defender\Model\Rule;

/**
 * Class Defender.
 *
 * @since 2.1.0
 */
class Defender {
	/**
	 * Rule provider instance.
	 *
	 * @var RuleProvider
	 */
	private $ruleProvider;


	/**
	 * Incident recorder instance.
	 *
	 * @var IncidentRecorder
	 */
	private $incidentRecorder;

	/**
	 * Disabled rules manager instance.
	 *
	 * @var DisabledRulesManager|null
	 */
	private $disabledRulesManager;

	/**
	 * Rule hit tracker instance.
	 *
	 * @var RuleHitTracker
	 */
	private $hitTracker;

	/**
	 * Constructor.
	 *
	 * @param RuleProvider              $ruleProvider          Rule provider instance.
	 * @param IncidentRecorder          $incidentRecorder      Incident recorder instance.
	 * @param RuleHitTracker            $hitTracker            Rule hit tracker instance.
	 * @param DisabledRulesManager|null $disabledRulesManager  Disabled rules manager instance (optional).
	 */
	public function __construct( $ruleProvider, $incidentRecorder, $hitTracker, $disabledRulesManager = null ) {
		$this->ruleProvider         = $ruleProvider;
		$this->incidentRecorder     = $incidentRecorder;
		$this->hitTracker           = $hitTracker;
		$this->disabledRulesManager = $disabledRulesManager;
	}

	/**
	 * Processes the rules.
	 *
	 * @param Request $request Request object.
	 */
	public function processRules( $request ) {

		$rules = $this->ruleProvider->loadRules();
		if ( $rules->isEmpty() ) {
			return;
		}

		// Get ruleset version from rule provider.
		$version = $this->ruleProvider->getRulesetVersion();

		foreach ( $rules->getRules() as $rule ) {
			/*
			 * Note: We check for invalid rules and rules without a target here even though it's already done when
			 * before saving rules loaded from a file in RuleProvider::loadRules(). This is to ensure that even if
			 * an invalid rule somehow ends up in the database, it won't cause issues here.
			 */

			// Skip invalid rules.
			if ( ! $this->ruleProvider->isRuleValid( $rule ) ) {
				continue;
			}

			// Skip disabled rules.
			if ( $this->isRuleDisabled( $rule ) ) {
				continue;
			}

			// Skip rules that don't apply for the current request's method.
			if ( ! $this->isMethodAllowed( $rule, $request ) ) {
				continue;
			}

			// Skip rules that don't have a target.
			$targetInfo = $this->ruleProvider->getTargetInfo( $rule );
			if ( ! $targetInfo ) {
				continue;
			}

			// Apply the rule.
			$handler = new Handler( $rule, $request, $this->incidentRecorder, $this->hitTracker, $targetInfo, $version );
			$handler->apply();
		}
	}


	/**
	 * Check if the current request method is allowed by the rule.
	 *
	 * @param Rule    $rule    Rule object.
	 * @param Request $request Request object.
	 *
	 * @return bool True if the method is allowed, false otherwise.
	 */
	private function isMethodAllowed( $rule, $request ) {
		$ruleMethod = $rule->getMethod();

		// If no method filter is set, allow all methods.
		if ( empty( $ruleMethod ) ) {
			return true;
		}

		// Compare methods case-insensitively using the Request object.
		return $request->isMethod( $ruleMethod );
	}

	/**
	 * Check if a rule is disabled.
	 *
	 * @since 3.0.0
	 *
	 * @param Rule $rule Rule object.
	 *
	 * @return bool True if the rule is disabled, false otherwise.
	 */
	private function isRuleDisabled( $rule ) {
		if ( null === $this->disabledRulesManager ) {
			return false;
		}

		return $this->disabledRulesManager->isRuleDisabled( $rule->getId() );
	}
}
