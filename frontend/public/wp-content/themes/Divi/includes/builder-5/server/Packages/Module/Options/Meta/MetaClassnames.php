<?php
/**
 * Module: MetaClassnames class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Meta;

use ET\Builder\Packages\Module\Layout\Components\Classnames;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * MetaClassnames class.
 *
 * @since ??
 */
class MetaClassnames {

	/**
	 * TOC list heading inclusion classname when a module forces inclusion.
	 */
	public const TOC_LIST_HEADING_INCLUDED_CLASS = 'et_pb_toc_list_included';

	/**
	 * TOC list heading inclusion classname when a module forces exclusion.
	 */
	public const TOC_LIST_HEADING_EXCLUDED_CLASS = 'et_pb_toc_list_excluded';

	/**
	 * Add TOC list heading classnames from module meta settings.
	 *
	 * @since ??
	 *
	 * @param Classnames $classnames_instance Classnames instance.
	 * @param array      $attrs               Module attributes.
	 *
	 * @return void
	 */
	public static function add_toc_list_heading_classnames( Classnames $classnames_instance, array $attrs ): void {
		$toc_list_heading = self::get_toc_list_heading_value( $attrs );

		if ( 'include' === $toc_list_heading ) {
			$classnames_instance->add( self::TOC_LIST_HEADING_INCLUDED_CLASS );
		} elseif ( 'exclude' === $toc_list_heading ) {
			$classnames_instance->add( self::TOC_LIST_HEADING_EXCLUDED_CLASS );
		}
	}

	/**
	 * Resolve the TOC list heading meta value from module attributes.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attributes.
	 *
	 * @return string One of `default`, `include`, or `exclude`.
	 */
	public static function get_toc_list_heading_value( array $attrs ): string {
		foreach ( $attrs as $element_attrs ) {
			if ( ! is_array( $element_attrs ) ) {
				continue;
			}

			$value = $element_attrs['meta']['meta']['tocListHeading']['desktop']['value'] ?? 'default';

			if ( in_array( $value, [ 'include', 'exclude' ], true ) ) {
				return $value;
			}
		}

		return 'default';
	}
}
