<?php
/**
 * GlobalPresetItemUtils class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\GlobalData;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\TextTransform;


/**
 * GlobalPresetItemUtils class.
 *
 * @since ??
 */
class GlobalPresetItemUtils {


	/**
	 * Generate preset class name.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $presetType       The Preset type. Can be 'module' or 'group'.
	 *     @type string $presetModuleName The Preset Module Name.
	 *     @type string $presetGroupName  The Preset Group Name.
	 *     @type string $presetGroupId    The Preset Group host ID.
	 *     @type string $presetId         The Preset ID.
	 *     @type bool   $isNested         Whether this is a nested group preset. Default is false.
	 * }
	 *
	 * @return string The preset class name.
	 */
	public static function generate_preset_class_name( array $args ): string {
		static $class_name_cache                = [];
		$list_of_excluded_groups_for_class_name = [ 'divi/id-classes', 'divi/animation' ];
		$cache_key                              = wp_json_encode( $args );

		if ( isset( $class_name_cache[ $cache_key ] ) ) {
			return $class_name_cache[ $cache_key ];
		}

		$preset_type        = $args['presetType'] ?? 'module';
		$preset_module_name = $args['presetModuleName'] ?? '';
		$preset_group_name  = $args['presetGroupName'] ?? '';
		$preset_group_id    = $args['presetGroupId'] ?? '';
		$preset_id          = $args['presetId'] ?? 'default';
		$is_nested          = $args['isNested'] ?? false;

		if ( in_array( $preset_group_name, $list_of_excluded_groups_for_class_name, true ) ) {
			$class_name_cache[ $cache_key ] = '';
			return $class_name_cache[ $cache_key ];
		}

		if ( $preset_module_name && $preset_group_name ) {
			$module_name_kebab = TextTransform::kebab_case( $preset_module_name );
			$group_name_kebab  = TextTransform::kebab_case( $preset_group_name );
			$group_id_segment  = '';

			if ( ! empty( $preset_group_id ) && ! str_starts_with( $preset_group_id, 'module.' ) ) {
				$group_id_segment = '--' . self::_get_group_host_token( $preset_group_id );
			}

			// For nested group presets, insert --nested-- before the preset ID.
			$format = $is_nested
				? "preset--%s--%s--%s{$group_id_segment}--nested--%s"
				: "preset--%s--%s--%s{$group_id_segment}--%s";

			$class_name_cache[ $cache_key ] = sprintf( $format, $preset_type, $module_name_kebab, $group_name_kebab, $preset_id );
			return $class_name_cache[ $cache_key ];
		}

		if ( $preset_module_name ) {
			$class_name_cache[ $cache_key ] = sprintf( 'preset--%s--%s--%s', $preset_type, TextTransform::kebab_case( $preset_module_name ), $preset_id );
			return $class_name_cache[ $cache_key ];
		}

		if ( $preset_group_name ) {
			$class_name_cache[ $cache_key ] = sprintf( 'preset--%s--%s--%s', $preset_type, TextTransform::kebab_case( $preset_group_name ), $preset_id );
			return $class_name_cache[ $cache_key ];
		}

		$class_name_cache[ $cache_key ] = sprintf( 'preset--%s--%s', $preset_type, $preset_id );

		return $class_name_cache[ $cache_key ];
	}

	/**
	 * Build a compact deterministic token for non-module group hosts.
	 *
	 * @since ??
	 *
	 * @param string $group_id Group host ID.
	 *
	 * @return string
	 */
	private static function _get_group_host_token( string $group_id ): string {
		static $token_cache = [];

		if ( '' === $group_id ) {
			return '';
		}

		if ( isset( $token_cache[ $group_id ] ) ) {
			return $token_cache[ $group_id ];
		}

		$hash   = 0;
		$length = strlen( $group_id );

		for ( $i = 0; $i < $length; $i++ ) {
			$hash = ( ( ( $hash << 5 ) - $hash ) + ord( $group_id[ $i ] ) ) & 0xFFFFFFFF;
		}

		$base36 = strtolower( base_convert( (string) $hash, 10, 36 ) );

		$token_cache[ $group_id ] = 'h' . substr( $base36, 0, 6 );

		return $token_cache[ $group_id ];
	}
}
