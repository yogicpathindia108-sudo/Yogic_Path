<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender\Model;

/**
 * Rule collection model class.
 *
 * Manages a collection of security rules.
 *
 * @since 3.0.0cbf
 */
class RuleCollection {
	/**
	 * Collection of rules.
	 *
	 * @var Rule[]
	 */
	private $rules = array();

	/**
	 * Create a rule collection from an array.
	 *
	 * @param array $data Rules data array.
	 *
	 * @return RuleCollection
	 */
	public static function fromArray( $data ) {
		$collection = new self();
		if ( empty( $data ) ) {
			return $collection;
		}

		foreach ( $data as $id => $rule_data ) {
			$collection->addRule( Rule::fromArray( $id, $rule_data ) );
		}
		return $collection;
	}

	/**
	 * Create an empty rule collection.
	 *
	 * @return RuleCollection
	 */
	public static function withNoRules() {
		return new self();
	}

	/**
	 * Add a rule to the collection.
	 *
	 * @param Rule $rule Rule to add.
	 */
	public function addRule( $rule ) {
		$this->rules[ $rule->getId() ] = $rule;
	}

	/**
	 * Get all rules.
	 *
	 * @return Rule[]
	 */
	public function getRules() {
		return $this->rules;
	}

	/**
	 * Convert collection to array.
	 *
	 * @return array
	 */
	public function toArray() {
		$data = array();
		foreach ( $this->rules as $id => $rule ) {
			$data[ $id ] = $rule->toArray();
		}
		return $data;
	}

	/**
	 * Get count of rules.
	 *
	 * @return int
	 */
	public function count() {
		return count( $this->rules );
	}

	/**
	 * Check if collection is empty.
	 *
	 * @return bool
	 */
	public function isEmpty() {
		return empty( $this->rules );
	}
}
