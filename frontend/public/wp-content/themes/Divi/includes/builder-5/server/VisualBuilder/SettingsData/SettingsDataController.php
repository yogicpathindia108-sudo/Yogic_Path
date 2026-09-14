<?php
/**
 * Settings Data: REST Controller class.
 *
 * @package Divi
 *
 * @since ??
 */

namespace ET\Builder\VisualBuilder\SettingsData;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\VisualBuilder\SettingsData\SettingsData;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Setting Data REST Controller class.
 *
 * @since ??
 */
class SettingsDataController extends RESTController {
	/**
	 * Resolve assigned Theme Builder layout IDs for current request context.
	 *
	 * @since ??
	 *
	 * @param int $post_id Current post ID.
	 *
	 * @return array{header:int,body:int,footer:int}
	 */
	private static function _resolve_assigned_theme_builder_layout_ids( int $post_id ): array {
		$resolved = [
			'header' => 0,
			'body'   => 0,
			'footer' => 0,
		];

		$post_type = get_post_type( $post_id );

		// Divi Library layouts must not receive site Theme Builder header/footer/body; post content only.
		if ( 'et_pb_layout' === $post_type ) {
			return $resolved;
		}

		$is_tb_layout_post_type = is_string( $post_type ) && et_theme_builder_is_layout_post_type( $post_type );

		// When template areas are hidden in UI, skip resolving template IDs unless editing a TB layout directly.
		$show_theme_builder_templates = et_get_option( 'et_fb_pref_show_theme_builder_templates', true, '', true );
		if ( ! $show_theme_builder_templates && ! $is_tb_layout_post_type ) {
			return $resolved;
		}

		if ( $is_tb_layout_post_type ) {
			if ( 'et_header_layout' === $post_type ) {
				$resolved['header'] = $post_id;
			}

			if ( 'et_body_layout' === $post_type ) {
				$resolved['body'] = $post_id;
			}

			if ( 'et_footer_layout' === $post_type ) {
				$resolved['footer'] = $post_id;
			}

			return $resolved;
		}

		$theme_builder_layouts = et_theme_builder_get_template_layouts();

		if ( empty( $theme_builder_layouts ) && 0 < $post_id ) {
			$tb_request = \ET_Theme_Builder_Request::from_post( $post_id );
			if ( $tb_request ) {
				$theme_builder_layouts = et_theme_builder_get_template_layouts( $tb_request, true, false );
			}
		}

		$layout_keys = [
			'header' => ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE,
			'body'   => ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE,
			'footer' => ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE,
		];

		foreach ( $layout_keys as $layout => $layout_key ) {
			$layout_data = $theme_builder_layouts[ $layout_key ] ?? [];
			if ( empty( $layout_data['enabled'] ) || empty( $layout_data['override'] ) ) {
				continue;
			}

			$layout_post_id = absint( $layout_data['id'] ?? 0 );
			if ( 0 === $layout_post_id ) {
				continue;
			}

			if ( ! current_user_can( 'edit_post', $layout_post_id ) ) {
				continue;
			}

			$resolved[ $layout ] = $layout_post_id;
		}

		return $resolved;
	}

	/**
	 * Normalize mainLoopSettingsData to array format.
	 *
	 * @since ??
	 *
	 * @param mixed $data Raw request param.
	 *
	 * @return array
	 */
	private static function _normalize_main_loop_settings_data( $data ): array {
		if ( is_array( $data ) ) {
			return $data;
		}

		if ( ! is_string( $data ) || '' === $data ) {
			return [];
		}

		$decoded = json_decode( $data, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$decoded = json_decode( stripslashes( $data ), true );
		}

		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Retrieve the settings data after app load for Visual Builder
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response returns the REST response object containing the rendered HTML
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		// Extract post ID from request params and make it available to callbacks.
		// This is needed for callbacks that require post context (e.g., offCanvas).
		// Frontend sends both 'et_post_id' and 'currentPage[id]' with the same value.
		$post_id = 0;

		if ( $request->has_param( 'et_post_id' ) ) {
			$post_id = absint( $request->get_param( 'et_post_id' ) );
		} elseif ( $request->has_param( 'currentPage[id]' ) ) {
			$post_id = absint( $request->get_param( 'currentPage[id]' ) );
		}

		// Store post ID in a static variable accessible to callbacks.
		if ( $post_id > 0 ) {
			SettingsDataCallbacks::set_current_post_id( $post_id );
		}

		SettingsDataCallbacks::set_current_theme_builder_layout_ids(
			self::_resolve_assigned_theme_builder_layout_ids( $post_id )
		);

		// Pass main-loop context into callbacks (non-singular pages).
		$main_loop_type = $request->has_param( 'mainLoopType' ) ? sanitize_text_field( $request->get_param( 'mainLoopType' ) ) : 'singular';
		SettingsDataCallbacks::set_current_main_loop_type( $main_loop_type );

		$main_loop_settings_data = $request->has_param( 'mainLoopSettingsData' ) ? $request->get_param( 'mainLoopSettingsData' ) : [];
		SettingsDataCallbacks::set_current_main_loop_settings_data( self::_normalize_main_loop_settings_data( $main_loop_settings_data ) );

		// Due to lazy load mechanism which was introduced to boost performance by only registering modules that are
		// actually used on the current page, shortcode module data is not registered by default on REST request.
		// Thus without the following tweaks, no shortcode modules data is returned when
		// `ET_Builder_Element::get_shortcode_module_definitions()` is called. For this method to returned shortcode
		// module data on REST request, three things need to be done:
		//
		// 1. `ET_Builder_Module_Shortcode_Manager::_should_register_shortcodes()` should return true
		// 2. `et_builder_should_load_all_module_data()` should return true as well.
		// Otherwise only WC shortcode module's definition being returned.
		// 3. The most important part: `$action_hook` at `ET_Builder_Module_Shortcode_Manager::register_shortcode()` has
		// to be `wp_loaded`. This is tricky one because `is_admin()` doesn't return true on REST request page and the
		// constant `REST_REQUEST` hasn't been defined yet since It is too early for it
		//
		// This means nothing that can be done at `ET_Builder_Module_Shortcode_Manager::_should_register_shortcodes()`
		// side to make it returns `true` because it is too early to call whether it is `REST` request page or not.
		// To overcome this, force `et_builder_should_load_all_module_data` to return `true` below then execute
		// `ET_Builder_Module_Shortcode_Manager->register_all_shortcodes()` method to register all shortcode modules here.

		// Force load all module data. Without forching this, only WooCommerce modules will be registered.
		add_filter( 'et_builder_should_load_all_module_data', '__return_true' );

		// Create instance of shortcode manager class then register all shortcode modules.
		do_action( 'divi_visual_builder_settings_data_before_register_all_shortcodes' );
		$manager = new \ET_Builder_Module_Shortcode_Manager();
		$manager->register_all_shortcodes();
		do_action( 'divi_visual_builder_settings_data_after_register_all_shortcodes' );

		return self::response_success(
			SettingsData::get_settings_data( [ 'usage' => 'after_app_load' ] )
		);
	}

	/**
	 * Arguments for the index actions.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the index action.
	 */
	public static function index_args(): array {
		return [];
	}

	/**
	 * Provides the permission status for the index action.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return bool returns `true` if the current user has the permission to use the rest endpoint, otherwise `false`
	 */
	public static function index_permission( WP_REST_Request $request ): bool {
		if ( ! UserRole::can_current_user_use_visual_builder() ) {
			return false;
		}

		$post_id = $request->has_param( 'et_post_id' )
			? absint( $request->get_param( 'et_post_id' ) )
			: absint( $request->get_param( 'currentPage[id]' ) );

		if ( 0 < $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		}

		$main_loop_type = sanitize_text_field( (string) $request->get_param( 'mainLoopType' ) );

		return '' !== $main_loop_type
			&& 'singular' !== $main_loop_type
			&& current_user_can( 'edit_theme_options' )
			&& et_pb_is_allowed( 'theme_builder' );
	}

	/**
	 * Fresh nonces only (for clients recovering after `rest_cookie_invalid_nonce` without loading full after-app-load).
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $_request REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function nonces( WP_REST_Request $_request ): WP_REST_Response {
		return self::response_success(
			[
				'nonces' => SettingsDataCallbacks::nonces_after_app_load(),
			],
			[
				'cache-control' => 'no-store, no-cache, must-revalidate',
				'pragma'        => 'no-cache',
			]
		);
	}

	/**
	 * Arguments for the `/settings-data/nonces` action.
	 *
	 * @since ??
	 *
	 * @return array<string, mixed>
	 */
	public static function nonces_args(): array {
		return [];
	}

	/**
	 * Permission for `/settings-data/nonces`.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function nonces_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}
}
