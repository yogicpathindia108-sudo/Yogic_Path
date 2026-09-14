<?php
/**
 * Conditions: ConditionsHooks.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use Feature\ContentRetriever\ET_Builder_Content_Retriever;

/**
 * Conditions option custom hooks.
 */
class ConditionsHooks {

	/**
	 * Register the conditions option custom hooks for the `ET_Core_Portability` class.
	 *
	 * This method registers the hooks for the `ET_Core_Portability` class.
	 *
	 * The hooks are used to set a cookie based on page visits so Page/Post Visit Display Conditions would function as expected.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp', [ __CLASS__, 'post_visit_set_cookie' ] );
		add_action( 'template_redirect', [ __CLASS__, 'number_of_views_set_cookie' ], 10, 3 );
		// Run after off-canvas save handlers so tracking IDs include newly saved canvas conditions.
		add_action( 'divi_visual_builder_rest_save_post', [ __CLASS__, 'save_tracking_post_ids' ], 20, 1 );
		add_action( 'delete_post', [ __CLASS__, 'delete_tracking_post_ids' ], 10, 1 );
	}


	/**
	 * Sets a cookie based on how many times a module is displayed so "Number of Views" Condition would function as expected.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function number_of_views_set_cookie() {
		$enabled = true;

		/**
		 * Filters "Conditions Option" functionality to determine whether to enable or disable the functionality.
		 *
		 * Useful for disabling/enabling "Conditions Option" feature site-wide.
		 *
		 * @since ??
		 *
		 * @param boolean $enabled True to enable the functionality, False to disable it.
		 */
		$is_display_conditions_enabled = apply_filters( 'et_is_display_conditions_functionality_enabled', $enabled );

		if ( ! $is_display_conditions_enabled || Conditions::is_vb_enabled() ) {
			return;
		}

		/**
		 * This is to ensure that network request such as '/favicon.ico' won't change the cookie
		 * since those requests do trigger these functions to run again without the proper context
		 * resulting updating cookie >=2 times on 1 page load.
		 */
		$is_existing_wp_query = ( is_home() || is_404() || is_archive() || is_search() );
		if ( get_queried_object_id() === 0 && ! $is_existing_wp_query ) {
			return;
		}

		// Setup prerequisite.
		$cookie                     = [];
		$current_post_id            = get_queried_object_id();
		$entire_page_content        = ET_Builder_Content_Retriever::init()->get_entire_page_content( $current_post_id );
		$main_condition_payloads    = self::_extract_display_condition_payloads_from_content( $entire_page_content );
		$off_canvas_payloads        = self::_extract_off_canvas_display_condition_payloads( $current_post_id );
		$display_condition_payloads = array_values(
			array_unique(
				array_merge( $main_condition_payloads, $off_canvas_payloads )
			)
		);

		// Process all discovered display condition payloads (main canvas + off-canvas canvases).
		if ( ! empty( $display_condition_payloads ) ) {
			$cookie = self::number_of_views_process_conditions( $display_condition_payloads );
			if ( false === $cookie ) {
				return;
			}
		}

		/**
		 * Encode cookie content and set cookie only if quired object id can be retrieved.
		 * `setrawcookie` is used to ignore automatic `urlencode` with `setcookie` since it corrupts base64 data.
		 */
		if ( ! empty( $cookie ) ) {
			$cookie = base64_encode( wp_json_encode( $cookie ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode  -- base64_encode data is an array.
			setrawcookie( 'divi_module_views', $cookie, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
		}
	}

	/**
	 * Checks "NumberOFViews" conditions against respective $_COOKIE content and updates/reset the
	 * condition when necessary.
	 *
	 * @since ??
	 *
	 * @param  array $display_conditions Array of conditions.
	 *
	 * @return array
	 */
	public static function number_of_views_process_conditions( $display_conditions ) {
		$is_cookie_set    = isset( $_COOKIE['divi_module_views'] );
		$current_datetime = current_datetime();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Cookie is not stored or displayed therefore XSS safe, The returned data is an array and necessary validation checks are performed.
		$decoded_cookie = $is_cookie_set ? json_decode( base64_decode( wp_unslash( $_COOKIE['divi_module_views'] ) ), true ) : [];
		$cookie         = is_array( $decoded_cookie ) ? $decoded_cookie : [];

		foreach ( $display_conditions as $display_condition ) {
			$display_condition = json_decode( $display_condition, true );

			if ( is_array( $display_condition ) ) {
				$condition_id       = $display_condition['id'];
				$condition_name     = $display_condition['conditionName'];
				$condition_settings = $display_condition['conditionSettings'];

				if ( 'numberOfViews' !== $condition_name ) {
					continue;
				}

				$is_reset_on               = 'on' === $condition_settings['resetAfterDuration'] ? true : false;
				$reset_time                = $condition_settings['displayAgainAfter'] . ' ' . $condition_settings['displayAgainAfterUnit'];
				$is_condition_id_in_cookie = array_search( $condition_id, array_column( $cookie, 'id' ), true ) !== false ? true : false;

				if ( $is_reset_on && $is_cookie_set && isset( $cookie[ $condition_id ] ) ) {
					$first_visit_timestamp = $cookie[ $condition_id ]['first_visit_timestamp'];
					$first_visit_datetime  = $current_datetime->setTimestamp( $first_visit_timestamp );
					$reset_datetime        = $first_visit_datetime->modify( $reset_time );
					if ( $current_datetime > $reset_datetime ) {
						$cookie[ $condition_id ]['visit_count']           = 1;
						$cookie[ $condition_id ]['first_visit_timestamp'] = $current_datetime->getTimestamp();
						continue;
					}
				}

				if ( $is_cookie_set && $is_condition_id_in_cookie ) {
					$cookie[ $condition_id ]['visit_count'] += 1;
				} else {
					$cookie[ $condition_id ] = [
						'id'                    => $condition_id,
						'visit_count'           => 1,
						'first_visit_timestamp' => $current_datetime->getTimestamp(),
					];
				}
			}
		}

		return $cookie;
	}

	/**
	 * Extract display condition payload snippets from serialized content.
	 *
	 * @since ??
	 *
	 * @param string $content Serialized block content.
	 *
	 * @return array
	 */
	private static function _extract_display_condition_payloads_from_content( string $content ): array {
		$payloads = [];

		if ( preg_match_all( '/"conditions"\:{"desktop"\:{"value"\:\[(.*?)\]}}}/mi', $content, $matches ) ) {
			$payloads = $matches[1] ?? [];
		}

		return array_values( array_filter( $payloads ) );
	}

	/**
	 * Extract display condition payload snippets from off-canvas metadata content.
	 *
	 * @since ??
	 *
	 * @param int $post_id Current queried post ID.
	 *
	 * @return array
	 */
	private static function _extract_off_canvas_display_condition_payloads( int $post_id ): array {
		if ( 0 >= $post_id ) {
			return [];
		}

		$canvas_data         = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id );
		$all_canvas_metadata = $canvas_data['all_canvas_metadata'] ?? [];
		$payloads            = [];

		foreach ( $all_canvas_metadata as $canvas_metadata ) {
			$canvas_content = $canvas_metadata['content'] ?? '';
			if ( ! is_string( $canvas_content ) || '' === $canvas_content ) {
				continue;
			}

			$payloads = array_merge(
				$payloads,
				self::_extract_display_condition_payloads_from_content( $canvas_content )
			);
		}

		return array_values( array_filter( $payloads ) );
	}

	/**
	 * Resolve the post ID used for page/post visit cookie tracking.
	 *
	 * In Theme Builder layout rendering the post stack is spoofed to the layout post.
	 * Tracking must use the viewed page/post from the main query.
	 *
	 * @since ??
	 *
	 * @return int Post ID to track, or 0 when tracking should not run.
	 */
	public static function get_post_visit_tracking_post_id(): int {
		$is_shop_page = class_exists( 'WooCommerce' ) && is_shop();

		if ( ! is_singular() && ! $is_shop_page ) {
			return 0;
		}

		$current_post_id = $is_shop_page ? (int) wc_get_page_id( 'shop' ) : (int) get_queried_object_id();

		if ( class_exists( '\ET_Theme_Builder_Layout' )
			&& \ET_Theme_Builder_Layout::is_theme_builder_layout()
			&& class_exists( '\ET_Post_Stack' ) ) {
			$main_post_id = (int) \ET_Post_Stack::get_main_post_id();

			if ( $main_post_id > 0 ) {
				$current_post_id = $main_post_id;
			}
		}

		return $current_post_id;
	}

	/**
	 * Sets a cookie based on page visits so Page/Post Visit Display Conditions would function as expected.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function post_visit_set_cookie() {
		$enabled = true;

		/**
		 * Filters "Display Conditions" functionality to determine whether to enable or disable the functionality or not.
		 *
		 * Useful for disabling/enabling "Display Condition" feature site-wide.
		 *
		 * @since ??
		 *
		 * @param boolean True to enable the functionality, False to disable it.
		 */
		$is_display_conditions_enabled = apply_filters( 'et_is_display_conditions_functionality_enabled', $enabled );

		if ( ! $is_display_conditions_enabled ) {
			return;
		}

		$current_post_id = self::get_post_visit_tracking_post_id();

		if ( 0 >= $current_post_id ) {
			return;
		}

		$new_cookie              = [];
		$has_visited_page_before = false;
		$wp_option               = get_option( 'et_display_conditions_tracking_post_ids', null );
		$is_wp_option_exist      = is_array( $wp_option ) && ! empty( $wp_option );
		$flatten_wp_option       = is_array( $wp_option ) ? array_unique( array_reduce( $wp_option, 'array_merge', [] ) ) : [];
		$is_post_id_in_wp_option = in_array( $current_post_id, $flatten_wp_option, true );

		if ( ! $is_wp_option_exist || ! $is_post_id_in_wp_option ) {
			return;
		}

		if ( isset( $_COOKIE['divi_post_visit'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Cookie is not stored or displayed therefore XSS safe, base64_decode returned data is an array and necessary validation checks are performed.
			$new_cookie = json_decode( base64_decode( wp_unslash( $_COOKIE['divi_post_visit'] ) ), true );
		}

		if ( $new_cookie && is_array( $new_cookie ) ) {
			$has_visited_page_before = array_search( $current_post_id, array_column( $new_cookie, 'id' ), true );
		}

		if ( false === $has_visited_page_before ) {
			$new_cookie[] = [
				'id' => $current_post_id,
			];
			$new_cookie   = base64_encode( wp_json_encode( $new_cookie ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode  -- base64_encode data is an array.

			if ( ! headers_sent() ) {
				setrawcookie( 'divi_post_visit', $new_cookie, time() + 3600 * 24 * 365, COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
			}
		}
	}

	/**
	 * Saves Post IDs selected in PageVisit/PostVisit Display Conditions into WP Options.
	 *
	 * This data will be used to only track the Posts which are selected by the user
	 * It is to keep the PageVisit/PostVisit related Cookie minimal and under 4KB limitation.
	 *
	 * @since ??
	 *
	 * @param int $post_id Post ID which is being saved.
	 *
	 * @return void
	 */
	public static function save_tracking_post_ids( $post_id ) {
		$enabled = true;

		/**
		 * Filters "Display Conditions" functionality to determine whether to enable or disable the functionality or not.
		 *
		 * Useful for disabling/enabling "Display Condition" feature site-wide.
		 *
		 * @since ??
		 *
		 * @param boolean True to enable the functionality, False to disable it.
		 */
		$is_display_conditions_enabled = apply_filters( 'et_is_display_conditions_functionality_enabled', $enabled );

		if ( ! $is_display_conditions_enabled ) {
			return;
		}

		$post = get_post( $post_id );

		// Validation and Security Checks.
		if ( ! $post || ! $post instanceof \WP_Post ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_autosave( $post ) || wp_is_post_revision( $post ) ) {
			return;
		}

		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type instanceof \WP_Post_Type || ! current_user_can( $post_type->cap->edit_post, $post_id ) ) {
			return;
		}

		// Setup Prerequisites.
		$tracking_post_ids = [];
		$content           = get_the_content( null, false, $post );
		$condition_values  = self::_extract_display_condition_payloads_from_content( $content );

		$off_canvas_condition_values = self::_extract_off_canvas_display_condition_payloads( (int) $post_id );
		$condition_values            = array_values(
			array_unique(
				array_merge( $condition_values, $off_canvas_condition_values )
			)
		);

		// Loop through $condition_values to get the Post IDs.
		foreach ( $condition_values as $condition_value ) {
			$condition_settings = json_decode( $condition_value, true );
			$condition_name     = $condition_settings['conditionName'] ?? '';

			if ( 'postVisit' !== $condition_name && 'pageVisit' !== $condition_name ) {
				continue;
			}

			$pages_raw         = $condition_settings['conditionSettings']['pages'] ?? [];
			$pages_ids         = array_map(
				function ( $item ) {
					return isset( $item['value'] ) ? (int) $item['value'] : '';
				},
				$pages_raw
			);
			$pages_ids         = array_filter( array_map( 'intval', $pages_ids ) );
			$tracking_post_ids = array_merge( $pages_ids, $tracking_post_ids );
		}

		$tracking_post_ids = array_unique( $tracking_post_ids );
		$result            = $tracking_post_ids ? [ (int) $post_id => $tracking_post_ids ] : null;
		$wp_option         = get_option( 'et_display_conditions_tracking_post_ids', null );

		// If option exist, Either update it OR remove from it.
		if ( is_array( $wp_option ) ) {
			if ( $result ) {
				$result = array_replace( $wp_option, $result );
			} else {
				$result = array_filter(
					$wp_option,
					function ( $key ) use ( $post_id ) {
						return $key !== $post_id;
					},
					ARRAY_FILTER_USE_KEY
				);
			}
		}

		if ( $wp_option === $result ) {
			return;
		}

		update_option( 'et_display_conditions_tracking_post_ids', $result );
	}

	/**
	 * Deletes Post IDs selected in PageVisit/PostVisit Display Conditions from WP Options.
	 *
	 * This data will be used to only track the Posts which are selected by the user
	 * It is to keep the PageVisit/PostVisit related Cookie minimal and under 4KB limitation.
	 *
	 * @since ??
	 *
	 * @param  int $post_id Post ID which is being deleted.
	 *
	 * @return void
	 */
	public static function delete_tracking_post_ids( $post_id ) {
		$enabled = true;

		/**
		 * Filters "Display Conditions" functionality to determine whether to enable or disable the functionality or not.
		 *
		 * Useful for disabling/enabling "Display Condition" feature site-wide.
		 *
		 * @since ??
		 *
		 * @param boolean True to enable the functionality, False to disable it.
		 */
		$is_display_conditions_enabled = apply_filters( 'et_is_display_conditions_functionality_enabled', $enabled );

		if ( ! $is_display_conditions_enabled ) {
			return;
		}

		$post               = get_post( $post_id );
		$wp_option          = get_option( 'et_display_conditions_tracking_post_ids', null );
		$is_wp_option_exist = is_array( $wp_option ) && ! empty( $wp_option );

		if ( ! $is_wp_option_exist ) {
			return;
		}

		if ( ! $post || ! $post instanceof \WP_Post ) {
			return;
		}

		// Get real Post ID if Revision ID is passed, Using `Empty Trash` button will set $post_id to revision id.
		$revision_parent_id = wp_is_post_revision( $post_id );
		if ( $revision_parent_id ) {
			$post_id = $revision_parent_id;
		}

		$post_type = get_post_type_object( $post->post_type );
		if ( ! current_user_can( $post_type->cap->delete_post, $post_id ) ) {
			return;
		}

		$result = array_filter(
			$wp_option,
			function ( $key ) use ( $post_id ) {
				return (int) $key !== (int) $post_id;
			},
			ARRAY_FILTER_USE_KEY
		);

		if ( $wp_option === $result ) {
			return;
		}

		update_option( 'et_display_conditions_tracking_post_ids', $result );
	}
}
