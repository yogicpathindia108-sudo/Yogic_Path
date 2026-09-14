<?php
/**
 * Safe wrapper for {@see comments_template()} when Discussion settings use invalid comments_per_page with pagination.
 *
 * Prevents PHP 8+ DivisionByZeroError in WordPress core comment-template.php when page_comments is on
 * and comments_per_page is not positive (see https://core.trac.wordpress.org/ticket/61468).
 *
 * TODO fix(D4, Comments): Remove this shim and revert call sites to comments_template after WordPress core resolves Trac #61468. [https://github.com/elegantthemes/Divi/issues/28338]
 *
 * @package Divi
 * @since ??
 */

if ( ! function_exists( 'et_comments_template_safe' ) ) :
	/**
	 * Calls comments_template() with a short-lived option filter when coercion is required.
	 *
	 * TODO fix(D4, Comments): Delete this function and file after WordPress core resolves Trac #61468. [https://github.com/elegantthemes/Divi/issues/28338]
	 *
	 * @param string $file              Template file.
	 * @param bool   $separate_comments Separate comments flag.
	 * @return void
	 */
	function et_comments_template_safe( $file = '', $separate_comments = false ) {
		if ( ! get_option( 'page_comments' ) ) {
			comments_template( $file, $separate_comments );
			return;
		}

		$raw_per_page = (int) get_option( 'comments_per_page' );

		if ( 0 < $raw_per_page ) {
			comments_template( $file, $separate_comments );
			return;
		}

		$filter_callback = static function ( $value ) {
			$per_page = (int) $value;

			if ( 0 >= $per_page ) {
				return 1;
			}

			return $value;
		};

		add_filter( 'option_comments_per_page', $filter_callback, PHP_INT_MAX, 1 );

		try {
			comments_template( $file, $separate_comments );
		} finally {
			remove_filter( 'option_comments_per_page', $filter_callback, PHP_INT_MAX );
		}
	}
endif;
