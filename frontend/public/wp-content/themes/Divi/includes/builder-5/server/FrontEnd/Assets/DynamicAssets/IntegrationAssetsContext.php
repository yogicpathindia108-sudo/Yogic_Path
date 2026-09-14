<?php
/**
 * Dynamic Assets integration asset context.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets\DynamicAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use InvalidArgumentException;

/**
 * Immutable-by-design request context supplied to integration asset providers.
 *
 * @since ??
 */
final class IntegrationAssetsContext {

	/** Early Dynamic Assets pass. */
	public const PHASE_EARLY = 'early';

	/** Late Dynamic Assets pass. */
	public const PHASE_LATE = 'late';

	/**
	 * Dynamic Assets-owned content for this pass.
	 *
	 * @var string
	 */
	private string $_content;

	/**
	 * Current Dynamic Assets pass.
	 *
	 * @var string
	 */
	private string $_phase;

	/**
	 * Constructor.
	 *
	 * @since ??
	 *
	 * @param string $content Dynamic Assets-owned content for this pass.
	 * @param string $phase   Current pass. One of the `PHASE_*` constants.
	 */
	public function __construct( string $content, string $phase ) {
		if ( ! in_array( $phase, [ self::PHASE_EARLY, self::PHASE_LATE ], true ) ) {
			throw new InvalidArgumentException( 'Invalid Dynamic Assets integration asset phase.' );
		}

		$this->_content = $content;
		$this->_phase   = $phase;
	}

	/**
	 * Get the Dynamic Assets-owned content for this pass.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public function get_content(): string {
		return $this->_content;
	}

	/**
	 * Get the current Dynamic Assets pass.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public function get_phase(): string {
		return $this->_phase;
	}

	/**
	 * Whether this is the late Dynamic Assets pass.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public function is_late(): bool {
		return self::PHASE_LATE === $this->_phase;
	}
}
