<?php
/**
 * ModuleLibrary: Wrapper animation helpers for image-like elements.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Common;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Animation\AnimationUtils;

/**
 * Utility helpers for wrapper-targeted animation behavior.
 *
 * @since ??
 */
class ImageWrapperAnimation {

	/**
	 * Get wrapper animation classname from element attrs.
	 *
	 * @since ??
	 *
	 * @param array $element_attr Element attribute array.
	 *
	 * @return string
	 */
	public static function wrapper_animation_classname( array $element_attr ): string {
		return AnimationUtils::classnames( $element_attr['decoration']['animation'] ?? [] );
	}

	/**
	 * Remove decoration animation from element attrs.
	 *
	 * This prevents ModuleElements::render() from applying animation to inner elements
	 * when the animation target should be the wrapper element.
	 *
	 * @since ??
	 *
	 * @param array $element_attr Element attribute array.
	 *
	 * @return array
	 */
	public static function render_attr_without_animation( array $element_attr ): array {
		$render_attr = $element_attr;

		if ( isset( $render_attr['decoration']['animation'] ) ) {
			unset( $render_attr['decoration']['animation'] );
		}

		return $render_attr;
	}

	/**
	 * Normalize image innerContent values so each state has `src`.
	 *
	 * @since ??
	 *
	 * @param array $element_attr Element attribute array.
	 *
	 * @return array
	 */
	public static function normalize_inner_content_src( array $element_attr ): array {
		$inner_content = $element_attr['innerContent'] ?? [];

		foreach ( $inner_content as $breakpoint => $states ) {
			if ( ! is_array( $states ) ) {
				continue;
			}

			foreach ( $states as $state => $state_value ) {
				if ( ! is_array( $state_value ) ) {
					continue;
				}

				$inner_content[ $breakpoint ][ $state ]['src'] = $state_value['src'] ?? $state_value['url'] ?? '';
			}
		}

		$element_attr['innerContent'] = $inner_content;

		return $element_attr;
	}
}
