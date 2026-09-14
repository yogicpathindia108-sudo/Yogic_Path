<?php
/**
 * Visual Builder's Taxonomy Class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Class for handling taxonomy related data for Visual Builder.
 *
 * @since ??
 */
class Taxonomy {
	/**
	 * Retrieves all WP taxonomies for Visual Builder
	 *
	 * @internal This is port of `et_fb_get_taxonomy_terms()` function.
	 *
	 * @return array
	 */
	public static function get_terms() {
		static $result = null;

		if ( null === $result ) {
			$result = [];

			$taxonomies = get_taxonomies();
			foreach ( $taxonomies as $taxonomy => $name ) {
				// phpcs:ignore WordPress.WP.DeprecatedParameters.Get_termsParam2Found -- Using legacy format for compatibility. hide_empty is needed to get all terms.
				$terms = get_terms( $name, [ 'hide_empty' => false ] ); // phpcs:ignore Generic.CodeAnalysis.ForLoopWithTestFunctionCall.NotAllowed,WordPress.WP.DeprecatedParameters.Get_termsParam2Found -- Need to get the terms for each taxonomy. Using legacy format for compatibility.
				if ( $terms ) {
					$terms_count = count( $terms );

					for ( $i = 0; $i < $terms_count; $i++ ) {
						// `count` gets updated frequently and it causes static cached helpers update.
						// Since we don't use it anywhere, we can exclude the value to avoid the issue.
						unset( $terms[ $i ]->count );
					}
					$result[ $name ] = $terms;
				}
			}
		}

		return $result;
	}
}
