<?php
/**
 * Dynamic Assets Store.
 *
 * Holds all state objects for the Dynamic Assets system in memory.
 * Provides typed getters for accessing state objects.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets\DynamicAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\FrontEnd\Assets\DynamicAssets\State\CacheState;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\DetectionState;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\EnqueueState;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\FeatureState;

/**
 * Dynamic Assets Store class.
 *
 * Holds all state objects for the Dynamic Assets system in memory.
 * This class encapsulates the four state objects and provides typed accessors.
 * One store instance is created per DynamicAssets instance and shared across
 * all collaborators, avoiding global/static state and preserving testability.
 *
 * @since ??
 */
class DynamicAssetsStore {

	/**
	 * Cache state container.
	 *
	 * @var CacheState
	 */
	private CacheState $cache;

	/**
	 * Detection state container.
	 *
	 * @var DetectionState
	 */
	private DetectionState $detection;

	/**
	 * Enqueue state container.
	 *
	 * @var EnqueueState
	 */
	private EnqueueState $enqueue;

	/**
	 * Feature state container.
	 *
	 * @var FeatureState
	 */
	private FeatureState $feature;

	/**
	 * Constructor.
	 *
	 * @since ??
	 *
	 * @param CacheState     $cache     Cache state container.
	 * @param DetectionState $detection Detection state container.
	 * @param EnqueueState   $enqueue   Enqueue state container.
	 * @param FeatureState   $feature   Feature state container.
	 */
	public function __construct(
		CacheState $cache,
		DetectionState $detection,
		EnqueueState $enqueue,
		FeatureState $feature
	) {
		$this->cache     = $cache;
		$this->detection = $detection;
		$this->enqueue   = $enqueue;
		$this->feature   = $feature;
	}

	/**
	 * Get cache state.
	 *
	 * @since ??
	 *
	 * @return CacheState
	 */
	public function cache(): CacheState {
		return $this->cache;
	}

	/**
	 * Get detection state.
	 *
	 * @since ??
	 *
	 * @return DetectionState
	 */
	public function detection(): DetectionState {
		return $this->detection;
	}

	/**
	 * Get enqueue state.
	 *
	 * @since ??
	 *
	 * @return EnqueueState
	 */
	public function enqueue(): EnqueueState {
		return $this->enqueue;
	}

	/**
	 * Get feature state.
	 *
	 * @since ??
	 *
	 * @return FeatureState
	 */
	public function feature(): FeatureState {
		return $this->feature;
	}
}
