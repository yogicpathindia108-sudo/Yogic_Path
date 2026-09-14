<?php
/**
 * Module Library: Radio field/icon attr helper.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary;

use ET\Builder\Packages\ModuleUtils\ModuleUtils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Shared helper for preparing radio field attrs and icon attrs.
 *
 * @since ??
 */
class RadioFieldAndIconAttrs {

	/**
	 * Split radio attrs into field attrs and icon attrs.
	 *
	 * @since ??
	 *
	 * @param array $radio_attr Radio attribute object.
	 *
	 * @return array{
	 *     fieldAttr: array,
	 *     iconAttr: array
	 * }
	 */
	public static function get( array $radio_attr ): array {
		$radio_icon_attr = $radio_attr['decoration']['icon'] ?? [];

		if ( is_array( $radio_icon_attr ) ) {
			foreach ( $radio_icon_attr as $breakpoint => $state_values ) {
				if ( ! is_array( $state_values ) ) {
					continue;
				}

				foreach ( ModuleUtils::states() as $state ) {
					if ( is_array( $radio_icon_attr[ $breakpoint ][ $state ] ?? null ) ) {
						$radio_icon_attr[ $breakpoint ][ $state ]['indicatorShape'] = 'radio-default';
					}
				}
			}
		}

		if ( is_array( $radio_attr['decoration'] ?? null ) ) {
			unset( $radio_attr['decoration']['icon'] );
		}

		return [
			'fieldAttr' => $radio_attr,
			'iconAttr'  => $radio_icon_attr,
		];
	}
}
