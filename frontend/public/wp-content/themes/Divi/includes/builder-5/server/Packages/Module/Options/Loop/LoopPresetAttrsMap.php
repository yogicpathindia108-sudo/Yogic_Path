<?php
/**
 * Module: LoopPresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Loop;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * LoopPresetAttrsMap class.
 *
 * This class provides the static map for the loop preset attributes.
 *
 * @since ??
 */
class LoopPresetAttrsMap {
	/**
	 * Get the map for the loop preset attributes.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The attribute name.
	 *
	 * @return array The map for the loop preset attributes.
	 */
	public static function get_map( string $attr_name ) {
		return [
			"{$attr_name}__enable"                       => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'enable',
			],
			"{$attr_name}__queryType"                    => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'queryType',
			],
			"{$attr_name}__subTypes"                     => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'subTypes',
			],
			"{$attr_name}__includePostWithSpecificTerms" => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'includePostWithSpecificTerms',
			],
			"{$attr_name}__excludePostWithSpecificTerms" => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'excludePostWithSpecificTerms',
			],
			"{$attr_name}__includeSpecificPosts"         => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'includeSpecificPosts',
			],
			"{$attr_name}__excludeSpecificPosts"         => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'excludeSpecificPosts',
			],
			"{$attr_name}__metaQuery"                    => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'metaQuery',
			],
			"{$attr_name}__orderBy"                      => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'orderBy',
			],
			"{$attr_name}__order"                        => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'order',
			],
			"{$attr_name}__postPerPage"                  => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'postPerPage',
			],
			"{$attr_name}__postOffset"                   => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'postOffset',
			],
			"{$attr_name}__excludeCurrentPost"           => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'excludeCurrentPost',
			],
			"{$attr_name}__ignoreStickysPost"            => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'ignoreStickysPost',
			],
		];
	}
}
