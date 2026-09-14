<?php
/**
 * Post Filter Item button icon data-attribute helper.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilterItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\IconLibrary\IconFont\Utils;

/**
 * Builds responsive `data-icon*` attributes from button decoration attrs.
 *
 * @since ??
 */
class PostFilterItemButtonIconDataAttributes {
	/**
	 * Build responsive `data-icon*` attributes from button decoration attrs.
	 *
	 * @since ??
	 *
	 * @param array $button_decoration Button decoration attrs.
	 *
	 * @return array<string, string>
	 */
	public static function get( array $button_decoration = [] ): array {
		if ( empty( $button_decoration ) ) {
			return [];
		}

		$is_icon_enabled = 'on' === ( $button_decoration['desktop']['value']['icon']['enable'] ?? null );

		if ( ! $is_icon_enabled ) {
			return [];
		}

		$icon_data_attrs = [];

		foreach ( $button_decoration as $breakpoint => $breakpoint_value ) {
			if ( ! is_array( $breakpoint_value ) ) {
				continue;
			}

			$icon_settings = $breakpoint_value['value']['icon']['settings'] ?? null;

			if ( empty( $icon_settings ) || ! is_array( $icon_settings ) ) {
				continue;
			}

			$processed_icon = Utils::escape_font_icon( Utils::process_font_icon( $icon_settings ) );

			if ( '' === $processed_icon ) {
				continue;
			}

			$icon_data_attrs[ self::_breakpoint_to_data_attr( (string) $breakpoint ) ] = $processed_icon;
		}

		return $icon_data_attrs;
	}

	/**
	 * Convert a breakpoint name to the matching button icon data-attribute key.
	 *
	 * @since ??
	 *
	 * @param string $breakpoint Breakpoint name.
	 *
	 * @return string
	 */
	private static function _breakpoint_to_data_attr( string $breakpoint ): string {
		if ( 'desktop' === $breakpoint ) {
			return 'data-icon';
		}

		return 'data-icon-' . strtolower( (string) preg_replace( '/([A-Z])/', '-$1', $breakpoint ) );
	}
}
