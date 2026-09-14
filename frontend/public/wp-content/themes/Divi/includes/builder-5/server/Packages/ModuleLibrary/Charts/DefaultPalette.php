<?php
/**
 * Charts default palette values.
 *
 * @package Divi
 * @since   ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Charts;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Provides shared default palette values for Charts module config builders.
 *
 * @since ??
 */
class DefaultPalette {

	/**
	 * Default palette values.
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	public const VALUES = [ '#5b8def', '#56c596', '#f5a623', '#e66fd2', '#7c6af2', '#38bdf8', '#f97316' ];

	/**
	 * Get default palette values.
	 *
	 * @since ??
	 *
	 * @return string[]
	 */
	public static function values(): array {
		return self::VALUES;
	}
}
