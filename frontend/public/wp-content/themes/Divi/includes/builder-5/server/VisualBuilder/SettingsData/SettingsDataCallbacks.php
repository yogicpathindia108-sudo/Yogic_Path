<?php
/**
 * Visual Builder Settings.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\SettingsData;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Framework\Customizer\Customizer;
use ET\Builder\Framework\Settings\PageSettings;
use ET\Builder\Framework\Settings\Settings;
use ET\Builder\Framework\Theme\Theme;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\LocaleUtility;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\Shortcode;

use ET\Builder\Framework\Portability\PortabilityPost;
use ET\Builder\Framework\Utility\SiteSettings;
use ET\Builder\Framework\Utility\DependencyChangeDetector;
use ET\Builder\Packages\Conversion\Conversion;
use ET\Builder\Packages\Conversion\LegacyAttributeNames;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Packages\GlobalLayout\GlobalLayout;
use ET\Builder\Packages\ModuleLibrary\GravityForms\GravityFormsService;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentOptions;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElementsUtils;
use ET\Builder\Packages\ModuleLibrary\ImagelyGallery\ImagelyGalleryService;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleLibrary\PostFilterItem\PostFilterProductPriceRange;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use ET\Builder\Services\EmailAccountService\EmailAccountService;
use ET\Builder\Services\InstagramAccountService\InstagramAccountService;
use ET\Builder\Services\PaymentAccountService\PaymentAccountService;
use ET\Builder\Services\SpamProtectionService\SpamProtectionService;
use ET\Builder\ThemeBuilder\Layout;
use ET\Builder\VisualBuilder\AppPreferences\AppPreferences;
use ET\Builder\VisualBuilder\REST\Nonce;
use ET\Builder\VisualBuilder\Saving\SavingUtility;
use ET\Builder\VisualBuilder\Shortcode\ShortcodeUtility;
use ET\Builder\VisualBuilder\Taxonomy;
use ET\Builder\VisualBuilder\TemplatePlaceholder;
use ET\Builder\VisualBuilder\Workspace\Workspace;
use ET\Builder\Packages\Conversion\ShortcodeMigration;
use ET\Builder\VisualBuilder\OffCanvas\OffCanvasHooks;
use ET\Builder\VisualBuilder\Performance\SettingsDataPerfCache;
use ET\Builder\Packages\ModuleUtils\CanvasUtils;
use ET\Builder\VisualBuilder\REST\Announcements\AnnouncementsController;

/**
 * Class that provides Settings Data callbacks.
 *
 * @since ??
 */
class SettingsDataCallbacks {
	/**
	 * Current post ID for REST API context.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	private static $_current_post_id = 0;
	/**
	 * Current Theme Builder layout IDs for REST API context.
	 *
	 * @since ??
	 *
	 * @var array<string,int>
	 */
	private static $_current_theme_builder_layout_ids = [];

	/**
	 * Current main loop type for REST API context.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_current_main_loop_type = 'singular';

	/**
	 * Current main loop settings data for REST API context.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private static $_current_main_loop_settings_data = [];

	/**
	 * Set the current post ID for REST API context.
	 *
	 * @since ??
	 *
	 * @param int $post_id Post ID.
	 */
	public static function set_current_post_id( int $post_id ): void {
		self::$_current_post_id = $post_id;
	}

	/**
	 * Get the current post ID for REST API context.
	 *
	 * @since ??
	 *
	 * @return int Post ID, or 0 if not set.
	 */
	public static function get_current_post_id(): int {
		return self::$_current_post_id;
	}

	/**
	 * Set Theme Builder layout IDs for REST API context.
	 *
	 * @since ??
	 *
	 * @param array<string,int> $layout_ids Layout IDs keyed by layout area.
	 */
	public static function set_current_theme_builder_layout_ids( array $layout_ids ): void {
		self::$_current_theme_builder_layout_ids = $layout_ids;
	}

	/**
	 * Get Theme Builder layout IDs for REST API context.
	 *
	 * @since ??
	 *
	 * @return array<string,int>
	 */
	public static function get_current_theme_builder_layout_ids(): array {
		return self::$_current_theme_builder_layout_ids;
	}

	/**
	 * Set current mainLoopType for REST API context.
	 *
	 * @since ??
	 *
	 * @param string $main_loop_type Main loop type.
	 */
	public static function set_current_main_loop_type( string $main_loop_type ): void {
		self::$_current_main_loop_type = $main_loop_type;
	}

	/**
	 * Get current mainLoopType for REST API context.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public static function get_current_main_loop_type(): string {
		return self::$_current_main_loop_type;
	}

	/**
	 * Set current mainLoopSettingsData for REST API context.
	 *
	 * @since ??
	 *
	 * @param array $data Main loop settings data.
	 */
	public static function set_current_main_loop_settings_data( array $data ): void {
		self::$_current_main_loop_settings_data = $data;
	}

	/**
	 * Get current mainLoopSettingsData for REST API context.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function get_current_main_loop_settings_data(): array {
		return self::$_current_main_loop_settings_data;
	}
	/**
	 * Get `breakpoints` setting data.
	 *
	 * @since ??
	 */
	public static function breakpoints() {
		return Breakpoint::get_settings_values();
	}

	/**
	 * Get `conditionalTags` setting data.
	 *
	 * @since ??
	 */
	public static function conditional_tags() {
		static $return = null;

		if ( null === $return || Conditions::is_test_env() ) {
			$return = et_fb_conditional_tag_params();
		}

		return $return;
	}

	/**
	 * Get `currentPage` setting data.
	 *
	 * @since ??
	 */
	public static function current_page() {
		static $return = null;

		if ( null === $return || Conditions::is_test_env() ) {
			$return = et_fb_current_page_params();

			// Determine the main loop type and override post-derived fields for non-singular pages.
			// On non-singular pages WordPress sets global $post to the first post in the main
			// query loop, so the values produced by et_fb_current_page_params() are misleading.
			$return['mainLoopType'] = self::_get_current_main_loop_type();

			if ( 'singular' !== $return['mainLoopType'] ) {
				$non_singular_data              = self::_get_non_singular_page_data( $return['mainLoopType'] );
				$return['id']                   = false;
				$return['title']                = $non_singular_data['title'];
				$return['thumbnailUrl']         = $non_singular_data['thumbnailUrl'];
				$return['thumbnailId']          = $non_singular_data['thumbnailId'];
				$return['mainLoopSettingsData'] = $non_singular_data['mainLoopSettingsData'];
			}

			// When translations are disabled, override post type label with English version.
			$disable_translations = et_get_option( 'divi_disable_translations', 'off' );
			if ( 'on' === $disable_translations && isset( $return['postTypeLabel'] ) ) {
				global $post;
				$post_type = $post->post_type ?? '';

				if ( ! empty( $post_type ) ) {
					// Format slug (e.g., 'product' -> 'Product', 'custom_post_type' -> 'Custom Post Type').
					$return['postTypeLabel'] = ucwords( str_replace( [ '-', '_' ], ' ', $post_type ) );
				}
			}
		}

		return $return;
	}

	/**
	 * Determine the current main loop type for the Visual Builder.
	 *
	 * Returns 'singular' when a real post object is being edited, otherwise
	 * returns a specific non-singular type string that the frontend uses to
	 * decide which page-settings fields are applicable.
	 *
	 * @since ??
	 *
	 * @return string Main loop type identifier.
	 */
	private static function _get_current_main_loop_type(): string {
		if ( is_singular() ) {
			return 'singular';
		}

		if ( is_category() ) {
			return 'category';
		}

		if ( is_tag() ) {
			return 'tag';
		}

		if ( is_tax() ) {
			return 'taxonomy';
		}

		if ( is_author() ) {
			return 'author';
		}

		if ( is_date() ) {
			return 'date';
		}

		if ( is_post_type_archive() ) {
			return 'post_type_archive';
		}

		if ( is_search() ) {
			return 'search';
		}

		if ( is_home() ) {
			return 'home';
		}

		if ( is_404() ) {
			return '404';
		}

		return 'singular';
	}

	/**
	 * Get page data appropriate for a non-singular main loop type.
	 *
	 * Each non-singular main loop type has different data available from WordPress.
	 * This method returns the correct title, thumbnail URL, and thumbnail ID
	 * for the given loop type instead of the misleading values that come from
	 * the first post in the main query loop.
	 *
	 * The `mainLoopSettingsData` key contains editable field values specific to the
	 * loop type (e.g. term name/slug/description for taxonomy archives, author
	 * display name/bio for author archives). These values are consumed by the
	 * page settings store on the frontend.
	 *
	 * @since ??
	 *
	 * @param string $main_loop_type Non-singular main loop type identifier.
	 *
	 * @return array{title: string, thumbnailUrl: string, thumbnailId: string, mainLoopSettingsData: array} Page data.
	 */
	private static function _get_non_singular_page_data( string $main_loop_type ): array {
		$data = [
			'title'                => '',
			'thumbnailUrl'         => '',
			'thumbnailId'          => '',
			'mainLoopSettingsData' => [],
		];

		switch ( $main_loop_type ) {
			case 'category':
			case 'tag':
			case 'taxonomy':
				$term = get_queried_object();
				if ( $term instanceof \WP_Term ) {
					$data['title'] = $term->name;

					$data['mainLoopSettingsData'] = [
						'termId'          => $term->term_id,
						'taxonomy'        => $term->taxonomy,
						'termName'        => $term->name,
						'termSlug'        => $term->slug,
						'termDescription' => $term->description,
						'canEdit'         => current_user_can( 'edit_term', $term->term_id ),
					];

					$thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
					if ( $thumbnail_id ) {
						$data['thumbnailId']  = $thumbnail_id;
						$data['thumbnailUrl'] = wp_get_attachment_url( (int) $thumbnail_id );
					}
				}
				break;

			case 'author':
				$author = get_queried_object();
				if ( $author instanceof \WP_User ) {
					$data['title'] = $author->display_name;

					$author_description           = get_user_meta( $author->ID, 'description', true );
					$data['mainLoopSettingsData'] = [
						'authorId'          => $author->ID,
						'authorDisplayName' => $author->display_name,
						'authorDescription' => $author_description ? $author_description : '',
						'canEdit'           => current_user_can( 'edit_user', $author->ID ),
					];
				}
				break;

			case 'date':
				$data['title']                = get_the_archive_title();
				$data['mainLoopSettingsData'] = [
					'pageInfo' => $data['title'],
				];
				break;

			case 'post_type_archive':
				$data['title']                = post_type_archive_title( '', false );
				$data['mainLoopSettingsData'] = [
					'pageInfo' => $data['title'],
					'postType' => '',
				];

				$queried_object = get_queried_object();
				if ( $queried_object instanceof \WP_Post_Type && ! empty( $queried_object->name ) ) {
					$data['mainLoopSettingsData']['postType'] = sanitize_key( $queried_object->name );
				} else {
					$post_type = get_query_var( 'post_type' );
					if ( is_array( $post_type ) ) {
						$post_type = $post_type[0] ?? '';
					}
					if ( is_string( $post_type ) && '' !== $post_type ) {
						$data['mainLoopSettingsData']['postType'] = sanitize_key( $post_type );
					}
				}
				break;

			case 'search':
				$data['title']                = get_search_query();
				$data['mainLoopSettingsData'] = [
					'pageInfo' => sprintf(
						/* translators: %s: search query. */
						esc_html__( 'Search results for: %s', 'et_builder_5' ),
						get_search_query()
					),
				];
				break;

			case 'home':
				$page_for_posts = (int) get_option( 'page_for_posts' );
				if ( $page_for_posts > 0 ) {
					$data['title'] = get_the_title( $page_for_posts );
				}

				$data['mainLoopSettingsData'] = [
					'pageInfo' => $data['title'] ? $data['title'] : esc_html__( 'Homepage', 'et_builder_5' ),
				];
				break;

			case '404':
				$data['mainLoopSettingsData'] = [
					'pageInfo' => esc_html__( '404 Error Page', 'et_builder_5' ),
				];
				break;
		}

		$data['title']        = $data['title'] ? esc_html( $data['title'] ) : '';
		$data['thumbnailUrl'] = $data['thumbnailUrl'] ? esc_url( $data['thumbnailUrl'] ) : '';

		return $data;
	}

	/**
	 * Get `currentUser` setting data.
	 *
	 * @since ??
	 */
	public static function current_user() {
		static $return = null;

		$user         = wp_get_current_user();
		$capabilities = $user->allcaps;

		/**
		 * Handle multisite subsite capabilities issue:
		 * In WordPress multisite, user capabilities are stored per-site in the wp_X_usermeta table.
		 * When a user is added to a subsite, they need to be explicitly granted capabilities for that site.
		 * If the user hasn't been properly added to the subsite or their capabilities haven't been set,
		 * wp_get_current_user()->allcaps will return an empty array on subsites.
		 * As a fallback, we check the main site's capabilities since users typically have their
		 * full set of capabilities defined there.
		 */
		if ( empty( $capabilities ) && is_multisite() && ! is_main_site() ) {
			// Temporarily switch to the main site to get the user's capabilities.
			switch_to_blog( get_main_site_id() );
			$capabilities = wp_get_current_user()->allcaps;
			// Restore the current site context.
			restore_current_blog();
		}

		if ( null === $return || Conditions::is_test_env() ) {
			$return = [
				'role'         => et_pb_get_current_user_role(),
				'capabilities' => $capabilities,
			];
		}

		return $return;
	}

	/**
	 * Get `customizer` setting data.
	 */
	public static function customizer() {
		static $return = null;

		if ( null === $return ) {
			$return = Customizer::get_settings_values();
		}

		return $return;
	}

	/**
	 * Get `dynamicContent` setting data.
	 *
	 * @since ??
	 */
	public static function dynamic_content() {
		static $cache = [];

		// TODO feat(D5, Translation): Handle locale switching for user profile language preference [https://github.com/elegantthemes/Divi/issues/45526].
		// Match other after-app-load callbacks (e.g. off_canvas) by using request-scoped post context.
		$post_id = self::get_current_post_id();

		$user_locale = get_user_locale();

		// Check permission for custom fields.
		// If user doesn't have permission, use 'edit' context to hide custom fields.
		// If user has permission, use 'display' context to show custom fields.
		// This ensures custom field options are not included in DiviSettingsData when permission is off.
		$can_read_custom_fields = et_pb_is_allowed( 'read_dynamic_content_custom_fields' );
		$context                = $can_read_custom_fields ? 'display' : 'edit';

		// Create cache key based on post ID, user locale, and permission status.
		// This ensures different cache entries for users with/without permission.
		$cache_key = $post_id . '_' . $user_locale . '_' . ( $can_read_custom_fields ? 'allowed' : 'restricted' );

		if ( ! isset( $cache[ $cache_key ] ) ) {
			// Handle locale switching for user profile language preference in Visual Builder.
			$locale_switched = LocaleUtility::maybe_switch_locale( 'user' );

			// Get dynamic content options with appropriate context based on permission.
			$cache[ $cache_key ] = [
				'options' => DynamicContentOptions::get_options( $post_id, $context ),
			];

			// Restore original locale if it was switched by us.
			if ( $locale_switched ) {
				LocaleUtility::maybe_restore_locale( $locale_switched );
			}
		}

		return $cache[ $cache_key ];
	}

	/**
	 * Get lightweight `dynamicContent` setting data for initial app load.
	 *
	 * Full dynamic content options are deferred to after-app-load REST fetch.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function dynamic_content_app_load(): array {
		return [
			'options' => [],
		];
	}

	/**
	 * Get `fonts` setting data.
	 *
	 * @since ??
	 */
	public static function fonts() {
		static $return = null;

		if ( null === $return ) {
			$heading_font        = et_get_option( 'heading_font', 'Open Sans' );
			$body_font           = et_get_option( 'body_font', 'Open Sans' );
			$heading_font_weight = et_get_option( 'heading_font_weight', '500' );
			$body_font_weight    = et_get_option( 'body_font_weight', '500' );

			$customizer_fonts = [
				'heading' => [
					'label'      => esc_html__( 'Headings', 'et_builder_5' ),
					'fontId'     => '--et_global_heading_font',
					'fontName'   => $heading_font ? $heading_font : 'Open Sans',
					'fontWeight' => $heading_font_weight ? $heading_font_weight : '500',
				],
				'body'    => [
					'label'      => esc_html__( 'Body', 'et_builder_5' ),
					'fontId'     => '--et_global_body_font',
					'fontName'   => $body_font ? $body_font : 'Open Sans',
					'fontWeight' => $body_font_weight ? $body_font_weight : '500',
				],
			];

			$google_fonts = array_merge(
				[ 'Default' => [] ],
				et_builder_get_websafe_fonts(),
				et_builder_get_google_fonts()
			);

			ksort( $google_fonts );

			$return = [
				'custom'     => et_builder_get_custom_fonts(),
				'customizer' => $customizer_fonts,
				'formats'    => et_pb_get_supported_font_formats(),
				'google'     => $google_fonts,
				'icons'      => et_pb_get_font_icon_symbols(),
				'iconsDown'  => et_pb_get_font_down_icon_symbols(),
				'removed'    => et_builder_old_fonts_mapping(),
			];
		}

		return $return;
	}

	/**
	 * Get `globalPresets` settings data.
	 *
	 * @since ??
	 */
	public static function global_presets() {
		static $return = null;

		if ( null === $return ) {
			// Convert D4 presets to D5 format if they haven't been converted yet.
			// This ensures conversion happens before preset data is sent to client.
			GlobalPreset::maybe_convert_legacy_presets();

			$return = [
				'data'                 => (object) GlobalPreset::get_data(),
				'legacyData'           => (object) GlobalPreset::get_legacy_data(),
				'isLegacyDataImported' => 'yes' === GlobalPreset::is_legacy_presets_imported(),
			];
		}

		return $return;
	}

	/**
	 * Get `google` settings data.
	 *
	 * @since ??
	 */
	public static function google() {
		static $return = null;

		if ( null === $return ) {
			$google_api_settings = et_pb_is_allowed( 'theme_options' ) ? get_option( 'et_google_api_settings' ) : [];
			$google_api_key      = $google_api_settings['api_key'] ?? '';

			$return = [
				// phpcs:ignore ET.Comments.Todo.TodoFound -- Valid D5 Todo task.
				// TODO feat(D5, Refactor) this should be secret.
				'APIKey'           => $google_api_key,
				'mapsScriptNotice' => ! et_pb_enqueue_google_maps_script(),
			];
		}

		return $return;
	}

	/**
	 * Get `layout` settings data.
	 *
	 * @since ??
	 */
	public static function layout() {
		static $return = null;

		if ( null === $return ) {
			global $post;

			$post_id        = isset( $post->ID ) ? absint( $post->ID ) : 0;
			$post_type      = isset( $post->post_type ) ? $post->post_type : 'post';
			$layout_type    = '';
			$layout_scope   = '';
			$remote_item_id = '';
			$template_type  = '';

			// phpcs:ignore ET.Comments.Todo.TodoFound -- Valid D5 Todo task.
			// TODO feat(D5, Coverage) more will happen here. See: et_fb_get_dynamic_backend_helpers().
			if ( 'et_pb_layout' === $post_type ) {
				$layout_type   = et_fb_get_layout_type( $post_id );
				$layout_scope  = et_fb_get_layout_term_slug( $post_id, 'scope' );
				$template_type = get_post_meta( $post_id, '_et_pb_template_type', true );

				// Only set the remote_item_id if temp post still exists.
				if ( ! empty( $_GET['cloudItem'] ) && get_post_status( $post_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- This function does not change any state, and is therefore not susceptible to CSRF.
					$remote_item_id = (int) sanitize_text_field( wp_unslash( $_GET['cloudItem'] ) ); // phpcs:ignore WordPress.Security.NonceVerification -- This function does not change any state, and is therefore not susceptible to CSRF.
				}
			}

			$return = [
				'type'         => $layout_type,
				'scope'        => $layout_scope,
				'templateType' => $template_type,
				'remoteItemId' => $remote_item_id,
			];
		}

		return $return;
	}

	/**
	 * Get `markups` setting data.
	 *
	 * @since ??
	 */
	public static function markups() {
		static $return = null;

		if ( null === $return ) {
			$return = [
				'commentsModule' => TemplatePlaceholder::comments(),
			];
		}

		return $return;
	}

	/**
	 * Get lightweight `markups` setting data for initial app load.
	 *
	 * Full markup payload is deferred to after-app-load REST fetch.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function markups_app_load(): array {
		return [
			'commentsModule' => '',
		];
	}

	/**
	 * Get `navMenus` setting data.
	 *
	 * @since ??
	 */
	public static function nav_menus() {
		static $return = null;

		if ( null === $return ) {
			$return = [
				'options' => et_builder_get_nav_menus_options(),
			];
		}

		return $return;
	}

	/**
	 * Get Contact Form 7 forms setting data.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function contact_form_7(): array {
		static $return = null;

		if ( null === $return || Conditions::is_test_env() ) {
			$forms     = [];
			$is_active = false;

			if ( class_exists( '\WPCF7_ContactForm' ) ) {
				$is_active     = true;
				$contact_forms = get_posts(
					[
						'post_type'      => 'wpcf7_contact_form',
						'post_status'    => 'publish',
						// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Settings data must list every available CF7 form.
						'posts_per_page' => 500,
						'orderby'        => 'title',
						'order'          => 'ASC',
					]
				);

				foreach ( $contact_forms as $form ) {
					$forms[ (string) $form->ID ] = [
						'label' => $form->post_title,
					];
				}
			}

			$return = [
				'isActive' => $is_active,
				'forms'    => $forms,
			];
		}

		return $return;
	}

	/**
	 * Gravity Forms setting data for the Visual Builder.
	 *
	 * @since ??
	 *
	 * @return array<string, mixed>
	 */
	public static function gravity_forms(): array {
		static $return = null;

		if ( null === $return || Conditions::is_test_env() ) {
			$is_active = class_exists( '\GFForms' );
			$forms     = $is_active ? GravityFormsService::get_forms_options() : [];

			$return = [
				'isActive' => $is_active,
				'forms'    => [] === $forms ? (object) [] : $forms,
			];
		}

		return $return;
	}

	/**
	 * Get `nonces` setting data.
	 *
	 * @since ??
	 */
	public static function nonces() {
		static $return = null;

		if ( null === $return ) {
			$return = Nonce::get_data();
		}

		return $return;
	}

	/**
	 * Fresh `nonces` payload for after-app-load settings merge (no static cache).
	 *
	 * @since ??
	 *
	 * @return array<string, mixed>
	 */
	public static function nonces_after_app_load() {
		return Nonce::get_data();
	}

	/**
	 * Get `post` setting data.
	 *
	 * @since ??
	 */
	public static function post() {
		static $return = null;

		if ( null === $return || Conditions::is_test_env() ) {
			global $post;

			$post_id      = isset( $post->ID ) ? absint( $post->ID ) : 0;
			$post_content = isset( $post->post_content ) ? $post->post_content : '';
			$post_type    = isset( $post->post_type ) ? $post->post_type : 'post';
			$post_status  = isset( $post->post_status ) ? $post->post_status : false;

			// Apply full PHP conversion for visual builder BEFORE D5-to-D5 migrations run.
			// This ensures D4 content is converted to D5 blocks before D5 migrations try to process it.
			$has_shortcode = '' !== $post_content && str_contains( $post_content, '[' ) && Shortcode::has_builder_shortcode( $post_content );
			if ( $post_content && $has_shortcode ) {
				// Initialize shortcode framework (handles module loading automatically).
				Conversion::initialize_shortcode_framework();

				// Prepare for D4 to D5 conversion by ensuring module definitions are available.
				// This is critical for attribute mappings to work properly.
				do_action( 'divi_visual_builder_before_d4_conversion' );

				// Apply full conversion (includes migration + format conversion).
				$post_content = Conversion::maybeConvertContent( $post_content );
			}

			// Deglobalize nested global modules when editing a global module template in Divi Library.
			// Nested global modules are not allowed, so remove any nested divi/global-layout blocks.
			if ( $post_content && 'et_pb_layout' === $post_type && GlobalLayout::is_global_layout_template( $post_id ) ) {
				$portability_instance = new PortabilityPost( 'et_builder' );
				$post_content         = $portability_instance->maybe_deglobalize_nested_global_modules( $post_content );
			}

			/**
			 * Filters the raw post content that is used for the visual builder.
			 *
			 * @since      ??
			 *
			 * @param string $post_content Raw post content that is used for the visual builder.
			 * @param int    $post_id      Post ID.
			 *
			 * @deprecated 5.0.0 Use the {@see 'divi_visual_builder_settings_data_post_content'} filter instead.
			 */
			$post_content = apply_filters(
				'et_fb_load_raw_post_content',
				$post_content,
				$post_id
			);

			/**
			 * Filters the raw post content that is used for the visual builder.
			 *
			 * @since ??
			 *
			 * @param string $post_content Raw post content that is used for the visual builder.
			 * @param int    $post_id      Post ID.
			 */
			$raw_post_content = apply_filters( 'divi_visual_builder_settings_data_post_content', $post_content, $post_id );

			// Match client-side wrapPlaceholderBlock() to ensure PHP/JS serialization parity.
			if ( ! empty( $raw_post_content ) && ! str_contains( $raw_post_content, '<!-- wp:divi/placeholder -->' ) ) {
				$raw_post_content = ModuleUtils::wrap_placeholder_block( $raw_post_content );
			}

			// If page is not singular and uses theme builder, set $post_status to 'publish'
			// to get the 'Save' button instead of 'Draft' and 'Publish'.
			if ( ! is_singular() && et_fb_is_theme_builder_used_on_page() && et_pb_is_allowed( 'theme_builder' ) ) {
				$post_status = 'publish';
			}

			$request_type = $post_type;

			// Set request_type on 404 pages.
			if ( is_404() ) {
				$request_type = '404';
			}

			// Set request_type on Archive pages.
			if ( is_archive() ) {
				$request_type = 'archive';
			}

			// Set request_type on the homepage (exclude the dedicated posts page index).
			$page_for_posts = (int) get_option( 'page_for_posts' );
			if ( is_home() && ( is_front_page() || 0 >= $page_for_posts ) ) {
				$request_type = 'home';
			}

			$image_dimensions_map = self::_get_image_dimensions_map_for_content( $raw_post_content );
			self::_add_current_page_thumbnail_dimensions_to_map( $image_dimensions_map );

			$return = [
				'content'            => $raw_post_content,
				'imageDimensionsMap' => $image_dimensions_map,
				'id'                 => $post_id,
				'title'              => get_the_title( $post_id ),
				'type'               => $post_type,
				'requestType'        => $request_type,
				'status'             => $post_status,
				'url'                => get_permalink( $post_id ),
				'editUrl'            => get_edit_post_link( $post_id, 'raw' ),
				'iframeSrc'          => ( isset( $_SERVER['HTTP_HOST'] ) && isset( $_SERVER['REQUEST_URI'] ) ) ?
					( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
					. sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
				'showPageCreation'   => get_post_meta( $post_id, '_et_pb_show_page_creation', true ),
			];
		}

		return $return;
	}

	/**
	 * Add current-page thumbnail dimensions to image dimensions map.
	 *
	 * This ensures featured images sourced from currentPage settings
	 * (e.g. Post Title module) can resolve width/height from store.
	 *
	 * @since ??
	 *
	 * @param array<string, array{width: string, height: string}> $dimensions_map Dimensions map accumulator.
	 *
	 * @return void
	 */
	private static function _add_current_page_thumbnail_dimensions_to_map( array &$dimensions_map ): void {
		$current_page = self::current_page();

		$thumbnail_id  = isset( $current_page['thumbnailId'] ) ? strval( $current_page['thumbnailId'] ) : '';
		$thumbnail_url = is_string( $current_page['thumbnailUrl'] ?? null ) ? $current_page['thumbnailUrl'] : '';

		if ( '' === $thumbnail_id && '' === $thumbnail_url ) {
			return;
		}

		$response = ModuleElementsUtils::get_responsive_image_attrs(
			[
				'src' => $thumbnail_url,
				'url' => $thumbnail_url,
				'id'  => $thumbnail_id,
			]
		);

		$resolved_width  = self::_sanitize_image_dimension_value( $response['width'] ?? null );
		$resolved_height = self::_sanitize_image_dimension_value( $response['height'] ?? null );

		if ( '' === $resolved_width || '' === $resolved_height ) {
			return;
		}

		self::_add_image_dimensions_to_map(
			$dimensions_map,
			$thumbnail_id,
			$thumbnail_url,
			$thumbnail_url,
			$resolved_width,
			$resolved_height
		);
	}

	/**
	 * Build image dimensions map from serialized builder content.
	 *
	 * @since ??
	 *
	 * @param string $content Serialized builder block content.
	 *
	 * @return array<string, array{width: string, height: string}>
	 */
	private static function _get_image_dimensions_map_for_content( string $content ): array {
		static $dimensions_map_cache = [];

		if ( '' === $content ) {
			return [];
		}

		$content_hash = md5( $content );
		if ( isset( $dimensions_map_cache[ $content_hash ] ) ) {
			return $dimensions_map_cache[ $content_hash ];
		}

		if ( ! self::_should_collect_image_dimensions_for_content( $content ) ) {
			$dimensions_map_cache[ $content_hash ] = [];
			return [];
		}

		$expanded_content = BlockParserStore::_expand_placeholder_wrapped_blocks( $content );
		$blocks           = parse_blocks( $expanded_content );
		if ( ! is_array( $blocks ) || empty( $blocks ) ) {
			$dimensions_map_cache[ $content_hash ] = [];
			return [];
		}

		$dimensions_map = [];
		$response_cache = [];

		self::_collect_image_dimensions_from_blocks( $blocks, $dimensions_map );
		if ( empty( $dimensions_map ) ) {
			self::_collect_image_dimensions_from_content_fallback( $content, $dimensions_map, $response_cache );
		}

		$dimensions_map_cache[ $content_hash ] = $dimensions_map;

		return $dimensions_map;
	}

	/**
	 * Determine if content should run image dimensions extraction.
	 *
	 * @since ??
	 *
	 * @param string $content Serialized builder block content.
	 *
	 * @return bool
	 */
	private static function _should_collect_image_dimensions_for_content( string $content ): bool {
		if ( '' === $content || ! str_contains( $content, '<!-- wp:divi/' ) || ! str_contains( $content, '"innerContent"' ) ) {
			return false;
		}

		$has_image_markers = str_contains( $content, '"src"' ) || str_contains( $content, '"url"' ) || str_contains( $content, '"id"' );

		return $has_image_markers;
	}

	/**
	 * Collect image dimensions from serialized content as a fallback.
	 *
	 * This fallback is used when block parsing misses deeply wrapped attrs,
	 * while still keeping dimensions server-resolved and cached.
	 *
	 * @since ??
	 *
	 * @param string $content        Serialized builder block content.
	 * @param array  $dimensions_map Dimensions map accumulator.
	 * @param array  $response_cache Responsive attrs cache.
	 *
	 * @return void
	 */
	private static function _collect_image_dimensions_from_content_fallback( string $content, array &$dimensions_map, array &$response_cache ): void {
		$ids                  = [];
		$urls                 = [];
		$image_url_value_keys = [ 'src', 'url' ];
		$has_image_url_value  = false;

		foreach ( $image_url_value_keys as $value_key ) {
			if ( str_contains( $content, '"' . $value_key . '"' ) ) {
				$has_image_url_value = true;
				break;
			}
		}

		if ( ! $has_image_url_value && ! str_contains( $content, '"id"' ) ) {
			return;
		}

		$matched_ids = preg_match_all( '/"id"\s*:\s*"?(\d+)"?/', $content, $id_matches );
		if ( false !== $matched_ids && ! empty( $id_matches[1] ) ) {
			$ids = array_values( array_unique( array_filter( array_map( 'strval', $id_matches[1] ) ) ) );
		}

		if ( $has_image_url_value ) {
			$matched_urls = preg_match_all( '/"(?:src|url)"\s*:\s*"([^"]+)"/', $content, $url_matches );
			if ( false !== $matched_urls && ! empty( $url_matches[1] ) ) {
				$urls = array_values(
					array_unique(
						array_filter(
							array_map(
								static function ( $url ): string {
									return is_string( $url ) ? stripslashes( $url ) : '';
								},
								$url_matches[1]
							)
						)
					)
				);
			}
		}

		foreach ( $ids as $id ) {
			if ( ! wp_attachment_is_image( intval( $id ) ) ) {
				continue;
			}

			$map_key = 'id:' . $id;
			if ( isset( $dimensions_map[ $map_key ] ) ) {
				continue;
			}

			$cache_key = $id . '||';
			if ( ! isset( $response_cache[ $cache_key ] ) ) {
				$response_cache[ $cache_key ] = ModuleElementsUtils::get_responsive_image_attrs(
					[
						'src' => '',
						'url' => '',
						'id'  => $id,
					]
				);
			}

			$resolved_width  = self::_sanitize_image_dimension_value( $response_cache[ $cache_key ]['width'] ?? null );
			$resolved_height = self::_sanitize_image_dimension_value( $response_cache[ $cache_key ]['height'] ?? null );

			if ( '' !== $resolved_width && '' !== $resolved_height ) {
				self::_add_image_dimensions_to_map( $dimensions_map, $id, '', '', $resolved_width, $resolved_height );
			}
		}

		foreach ( $urls as $url ) {
			$map_key = 'src:' . $url;
			if ( isset( $dimensions_map[ $map_key ] ) ) {
				continue;
			}

			$cache_key = '|' . $url . '|';
			if ( ! isset( $response_cache[ $cache_key ] ) ) {
				$response_cache[ $cache_key ] = ModuleElementsUtils::get_responsive_image_attrs(
					[
						'src' => $url,
						'url' => $url,
						'id'  => 0,
					]
				);
			}

			$resolved_width  = self::_sanitize_image_dimension_value( $response_cache[ $cache_key ]['width'] ?? null );
			$resolved_height = self::_sanitize_image_dimension_value( $response_cache[ $cache_key ]['height'] ?? null );

			if ( '' !== $resolved_width && '' !== $resolved_height ) {
				self::_add_image_dimensions_to_map( $dimensions_map, '', $url, $url, $resolved_width, $resolved_height );
			}
		}
	}

	/**
	 * Collect image dimensions recursively from parsed blocks.
	 *
	 * @since ??
	 *
	 * @param array $blocks         Parsed block list.
	 * @param array $dimensions_map Dimensions map accumulator.
	 *
	 * @return void
	 */
	private static function _collect_image_dimensions_from_blocks( array $blocks, array &$dimensions_map ): void {
		foreach ( $blocks as $block ) {
			$attrs = $block['attrs'] ?? null;
			if ( is_array( $attrs ) ) {
				self::_collect_image_dimensions_from_attrs( $attrs, $dimensions_map );
			}

			$inner_blocks = $block['innerBlocks'] ?? null;
			if ( is_array( $inner_blocks ) && ! empty( $inner_blocks ) ) {
				self::_collect_image_dimensions_from_blocks( $inner_blocks, $dimensions_map );
			}
		}
	}

	/**
	 * Collect image dimensions recursively from attrs node.
	 *
	 * @since ??
	 *
	 * @param array $attrs          Attrs node.
	 * @param array $dimensions_map Dimensions map accumulator.
	 *
	 * @return void
	 */
	private static function _collect_image_dimensions_from_attrs( array $attrs, array &$dimensions_map ): void {
		foreach ( $attrs as $attr_value ) {
			if ( ! is_array( $attr_value ) ) {
				continue;
			}

			$inner_content = $attr_value['innerContent'] ?? null;
			if ( is_array( $inner_content ) ) {
				self::_collect_image_dimensions_from_inner_content( $inner_content, $dimensions_map );
			}

			self::_collect_image_dimensions_from_attrs( $attr_value, $dimensions_map );
		}
	}

	/**
	 * Collect image dimensions from an image innerContent payload.
	 *
	 * Uses the same population utility as frontend rendering to keep
	 * width/height resolution behavior aligned with FE.
	 *
	 * @since ??
	 *
	 * @param array $inner_content  Inner content payload (breakpoint/state map).
	 * @param array $dimensions_map Dimensions map accumulator.
	 *
	 * @return void
	 */
	private static function _collect_image_dimensions_from_inner_content( array $inner_content, array &$dimensions_map ): void {
		$populated_inner_content = ModuleElementsUtils::populate_image_element_attrs( $inner_content );

		foreach ( $populated_inner_content as $states ) {
			if ( ! is_array( $states ) ) {
				continue;
			}

			foreach ( $states as $state_value ) {
				if ( ! is_array( $state_value ) ) {
					continue;
				}

				$resolved_width  = self::_sanitize_image_dimension_value( $state_value['width'] ?? null );
				$resolved_height = self::_sanitize_image_dimension_value( $state_value['height'] ?? null );

				if ( '' === $resolved_width || '' === $resolved_height ) {
					continue;
				}

				$id  = isset( $state_value['id'] ) ? strval( $state_value['id'] ) : '';
				$src = is_string( $state_value['src'] ?? null ) ? $state_value['src'] : '';
				$url = is_string( $state_value['url'] ?? null ) ? $state_value['url'] : '';

				self::_add_image_dimensions_to_map( $dimensions_map, $id, $src, $url, $resolved_width, $resolved_height );
			}
		}
	}

	/**
	 * Add image dimensions to map by id/src/url keys.
	 *
	 * @since ??
	 *
	 * @param array  $dimensions_map  Dimensions map accumulator.
	 * @param string $id              Attachment ID value.
	 * @param string $src             Source URL value.
	 * @param string $url             Alternate source URL value.
	 * @param string $resolved_width  Resolved width.
	 * @param string $resolved_height Resolved height.
	 *
	 * @return void
	 */
	private static function _add_image_dimensions_to_map(
		array &$dimensions_map,
		string $id,
		string $src,
		string $url,
		string $resolved_width,
		string $resolved_height
	): void {
		if ( '' === $resolved_width || '' === $resolved_height ) {
			return;
		}

		$normalized_id = strval( absint( $id ) );

		if ( '0' !== $normalized_id ) {
			$dimensions_map[ 'id:' . $normalized_id ] = [
				'width'  => $resolved_width,
				'height' => $resolved_height,
			];
		}

		if ( '' !== $src ) {
			$dimensions_map[ 'src:' . $src ] = [
				'width'  => $resolved_width,
				'height' => $resolved_height,
			];
		}

		if ( '' !== $url ) {
			$dimensions_map[ 'src:' . $url ] = [
				'width'  => $resolved_width,
				'height' => $resolved_height,
			];
		}
	}

	/**
	 * Sanitize image dimension value to a positive integer string.
	 *
	 * @since ??
	 *
	 * @param mixed $value Candidate dimension value.
	 *
	 * @return string
	 */
	private static function _sanitize_image_dimension_value( $value ): string {
		if ( ! is_scalar( $value ) || '' === strval( $value ) ) {
			return '';
		}

		$normalized_value = absint( $value );

		return 0 < $normalized_value ? strval( $normalized_value ) : '';
	}

	/**
	 * Merge multiple image dimensions maps.
	 *
	 * Later maps override earlier maps for matching keys.
	 *
	 * @since ??
	 *
	 * @param array<int, array<string, array{width: string, height: string}>> $maps List of maps.
	 *
	 * @return array<string, array{width: string, height: string}>
	 */
	private static function _merge_image_dimensions_maps( array $maps ): array {
		$merged = [];

		foreach ( $maps as $map ) {
			if ( ! is_array( $map ) || empty( $map ) ) {
				continue;
			}

			$merged = array_merge( $merged, $map );
		}

		return $merged;
	}

	/**
	 * Build a merged image dimensions map from serialized content segments.
	 *
	 * Empty and duplicate segments are skipped to avoid repeated parsing work.
	 *
	 * @since ??
	 *
	 * @param array<int, string> $contents Serialized content segments.
	 *
	 * @return array<string, array{width: string, height: string}>
	 */
	private static function _build_image_dimensions_map_for_contents( array $contents ): array {
		$maps          = [];
		$seen_hashes   = [];
		$empty_hash_md = md5( '' );

		foreach ( $contents as $content ) {
			if ( ! is_string( $content ) ) {
				continue;
			}

			$content_hash = md5( $content );
			if ( $empty_hash_md === $content_hash || isset( $seen_hashes[ $content_hash ] ) ) {
				continue;
			}

			$seen_hashes[ $content_hash ] = true;
			$maps[]                       = self::_get_image_dimensions_map_for_content( $content );
		}

		return self::_merge_image_dimensions_maps( $maps );
	}

	/**
	 * Build a Theme Builder template payload for a layout post.
	 *
	 * @since ??
	 *
	 * @param array  $templates              Theme Builder template payloads keyed by layout type.
	 * @param int    $post_id                Current layout post ID.
	 * @param string $active_tb_layout       Active Theme Builder layout key.
	 * @param bool   $is_tb_layout_post_type Whether the current post is a Theme Builder layout.
	 *
	 * @return array Theme Builder template payloads.
	 */
	private static function _hydrate_editing_tb_layout_template_from_post(
		array $templates,
		int $post_id,
		string $active_tb_layout,
		bool $is_tb_layout_post_type
	): array {
		if ( ! $is_tb_layout_post_type || $post_id <= 0 || '' === $active_tb_layout || ! isset( $templates[ $active_tb_layout ] ) ) {
			return $templates;
		}

		$post_content_data = self::post();

		$templates[ $active_tb_layout ] = [
			'id'       => $post_id,
			'title'    => $post_content_data['title'] ?? get_the_title( $post_id ),
			'content'  => $post_content_data['content'] ?? '',
			'isGlobal' => false,
		];

		return $templates;
	}

	/**
	 * Get Theme Builder template data for a layout area.
	 *
	 * @param array  $theme_builder_layouts Theme Builder layouts data.
	 * @param string $layout_post_type_key  Theme Builder layout post type key constant value.
	 * @param string $expected_post_type    Expected layout post type.
	 * @param array  $global_layout_ids     Global layout IDs grouped by layout post type key.
	 *
	 * @return array{id:int,title:string,content:string,isGlobal:bool}
	 */
	private static function _get_theme_builder_template_data(
		array $theme_builder_layouts,
		string $layout_post_type_key,
		string $expected_post_type,
		array $global_layout_ids = []
	): array {
		$layout_data = $theme_builder_layouts[ $layout_post_type_key ] ?? [];
		$layout_id   = intval( $layout_data['id'] ?? 0 );

		if ( 0 === $layout_id ) {
			return [
				'id'       => 0,
				'title'    => '',
				'content'  => '',
				'isGlobal' => false,
			];
		}

		$layout_post = get_post( $layout_id );

		if ( ! $layout_post || $expected_post_type !== $layout_post->post_type ) {
			return [
				'id'       => 0,
				'title'    => '',
				'content'  => '',
				'isGlobal' => false,
			];
		}

		$layout_content = $layout_post->post_content;

		// Apply conversion if needed (similar to post content processing).
		$has_shortcode = '' !== $layout_content && str_contains( $layout_content, '[' ) && Shortcode::has_builder_shortcode( $layout_content );
		if ( $layout_content && $has_shortcode ) {
			Conversion::initialize_shortcode_framework();
			do_action( 'divi_visual_builder_before_d4_conversion' );
			$layout_content = Conversion::maybeConvertContent( $layout_content );
		}

		// Run the same Visual Builder raw-content migration filter used by `post()`.
		// Without this call, Theme Builder template payloads bypass `et_fb_load_raw_post_content`,
		// so D5 migrations (for example ImageGroupMigration) never execute for header/body/footer layout content.
		// This preserves the same order as `post()`: optional shortcode conversion first, then D5 content migrations.
		$layout_content = apply_filters( 'et_fb_load_raw_post_content', $layout_content, $layout_id );

		// Wrap placeholder block if needed.
		if ( ! empty( $layout_content ) && ! str_contains( $layout_content, '<!-- wp:divi/placeholder -->' ) ) {
			$layout_content = ModuleUtils::wrap_placeholder_block( $layout_content );
		}

		// Check if this template is global.
		// A layout is considered global if:
		// 1) It is marked global on the active template payload, OR
		// 2) Its ID matches the shared global layout ID for this area.
		$is_global = ( isset( $layout_data['global'] ) && true === $layout_data['global'] )
			|| isset( $global_layout_ids[ $layout_post_type_key ][ $layout_id ] );

		return [
			'id'       => $layout_id,
			'title'    => get_the_title( $layout_id ),
			'content'  => $layout_content,
			'isGlobal' => $is_global,
		];
	}

	/**
	 * Whether Theme Builder template hydration is allowed for the current user and post (matches `$can_use_theme_builder` in `theme_builder_templates()`).
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _can_use_theme_builder_for_templates(): bool {
		global $post;

		$post_id               = isset( $post->ID ) ? (int) $post->ID : 0;
		$can_edit_posts        = current_user_can( 'edit_posts' );
		$can_edit_current_post = 0 === $post_id || current_user_can( 'edit_post', $post_id );

		return et_pb_is_allowed( 'theme_builder' ) && $can_edit_posts && $can_edit_current_post;
	}

	/**
	 * Resolve Theme Builder template layouts for the current request.
	 *
	 * In PHPUnit, bypass the static layout store so `et_theme_builder_template_layouts`
	 * filters registered by tests are not skipped after post-ID reuse across the suite.
	 *
	 * @since ??
	 *
	 * @param \ET_Theme_Builder_Request|null $request Optional request context.
	 *
	 * @return array
	 */
	private static function _get_theme_builder_template_layouts( $request = null ): array {
		if ( Conditions::is_test_env() ) {
			return et_theme_builder_get_template_layouts( $request, false, false );
		}

		return et_theme_builder_get_template_layouts( $request );
	}

	/**
	 * Get `themeBuilderTemplates` setting data.
	 *
	 * Provides Theme Builder template content (header, footer, body) for the current post.
	 * This data is used to populate the Visual Builder with template content.
	 *
	 * @since ??
	 */
	public static function theme_builder_templates() {
		static $return = null;

		if ( null === $return || Conditions::is_test_env() ) {
			// Check if Theme Builder templates should be shown based on user preference.
			$show_theme_builder_templates = et_get_option( 'et_fb_pref_show_theme_builder_templates', true, '', true );
			global $post;
			$post_type              = $post->post_type ?? 'post';
			$post_id                = isset( $post->ID ) ? (int) $post->ID : 0;
			$is_tb_layout_post_type = et_theme_builder_is_layout_post_type( $post_type );
			$is_divi_library_layout = 'et_pb_layout' === $post_type;
			$can_use_theme_builder  = self::_can_use_theme_builder_for_templates();
			// In Theme Builder layout editor, template areas must remain available even if the preference is disabled.
			// In Divi Library, template areas must not be shown even if the preference is enabled.
			$should_show_theme_builder_templates = ( $is_tb_layout_post_type || $show_theme_builder_templates ) && $can_use_theme_builder && ! $is_divi_library_layout;
			$active_tb_layout                    = Layout::get_layout_based_on_post_type( $post_type );
			$active_template_areas               = $is_tb_layout_post_type
				? [ $active_tb_layout ]
				: [ 'header', 'body', 'footer' ];
			$template_definitions                = [
				'header' => [
					'layoutPostTypeKey' => ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE,
					'expectedPostType'  => 'et_header_layout',
				],
				'body'   => [
					'layoutPostTypeKey' => ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE,
					'expectedPostType'  => 'et_body_layout',
				],
				'footer' => [
					'layoutPostTypeKey' => ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE,
					'expectedPostType'  => 'et_footer_layout',
				],
			];

			$templates = [
				'header'             => [
					'id'       => 0,
					'title'    => '',
					'content'  => '',
					'isGlobal' => false,
				],
				'footer'             => [
					'id'       => 0,
					'title'    => '',
					'content'  => '',
					'isGlobal' => false,
				],
				'body'               => [
					'id'       => 0,
					'title'    => '',
					'content'  => '',
					'isGlobal' => false,
				],
				'postContent'        => [
					'id'       => 0,
					'title'    => '',
					'content'  => '',
					'isGlobal' => false,
				],
				'imageDimensionsMap' => [],
			];

			// Short-circuit before Theme Builder template fetching when templates are hidden in the UI, or when
			// editing a Divi Library layout ($is_divi_library_layout, post type et_pb_layout).
			if ( ! $should_show_theme_builder_templates ) {
				// Get post content (for postContent layout).
				if ( is_singular() && $post_id > 0 ) {
					$post_content_data        = self::post();
					$templates['postContent'] = [
						'id'       => $post_id,
						'title'    => $post_content_data['title'] ?? '',
						'content'  => $post_content_data['content'] ?? '',
						'isGlobal' => false,
					];
				}

				$templates['imageDimensionsMap'] = self::_build_image_dimensions_map_for_contents(
					[
						$templates['header']['content'] ?? '',
						$templates['body']['content'] ?? '',
						$templates['footer']['content'] ?? '',
						$templates['postContent']['content'] ?? '',
					]
				);

				$return = $templates;
				return $return;
			}

			// Reuse already computed theme builder areas from `theme_builder()` when available.
			$theme_builder_data    = self::theme_builder();
			$theme_builder_layouts = isset( $theme_builder_data['themeBuilderAreas'] ) && is_array( $theme_builder_data['themeBuilderAreas'] )
				? $theme_builder_data['themeBuilderAreas']
				: self::_get_theme_builder_template_layouts();
			$active_layout_ids     = [];

			foreach ( $active_template_areas as $area ) {
				$definition = $template_definitions[ $area ] ?? null;

				if ( null === $definition ) {
					continue;
				}

				$layout_post_type_key = $definition['layoutPostTypeKey'];
				$layout_data          = $theme_builder_layouts[ $layout_post_type_key ] ?? [];
				$layout_id            = intval( $layout_data['id'] ?? 0 );

				if ( $layout_id <= 0 ) {
					continue;
				}

				$has_assigned_layout = $is_tb_layout_post_type
					? true
					: ( ! empty( $layout_data['override'] ) && ! empty( $layout_data['enabled'] ) );

				if ( $has_assigned_layout ) {
					$active_layout_ids[ $layout_post_type_key ] = $layout_id;
				}
			}

			// No assigned TB layouts for this request context: skip expensive global template lookups.
			if ( empty( $active_layout_ids ) ) {
				$post_id = isset( $post->ID ) ? $post->ID : 0;

				if ( $is_tb_layout_post_type && $post_id > 0 ) {
					$templates = self::_hydrate_editing_tb_layout_template_from_post(
						$templates,
						$post_id,
						$active_tb_layout,
						$is_tb_layout_post_type
					);
				} elseif ( ! $is_tb_layout_post_type && is_singular() && $post_id > 0 ) {
					$post_content_data        = self::post();
					$templates['postContent'] = [
						'id'       => $post_id,
						'title'    => $post_content_data['title'] ?? '',
						'content'  => $post_content_data['content'] ?? '',
						'isGlobal' => false,
					];
				}

				$templates['imageDimensionsMap'] = self::_build_image_dimensions_map_for_contents(
					[
						$templates['header']['content'] ?? '',
						$templates['body']['content'] ?? '',
						$templates['footer']['content'] ?? '',
						$templates['postContent']['content'] ?? '',
					]
				);

				$return = $templates;
				return $return;
			}

			$global_layout_ids = [
				ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE => [],
				ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE   => [],
				ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE => [],
			];
			$area_name_by_key  = [
				ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE => 'header',
				ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE   => 'body',
				ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE => 'footer',
			];
			$lookup_pending    = [];

			// First, trust explicit global flags from assigned layout data.
			foreach ( $active_layout_ids as $layout_post_type_key => $layout_id ) {
				$layout_data = $theme_builder_layouts[ $layout_post_type_key ] ?? [];

				if ( ! empty( $layout_data['global'] ) ) {
					$global_layout_ids[ $layout_post_type_key ][ $layout_id ] = true;
					continue;
				}

				$area_name = $area_name_by_key[ $layout_post_type_key ] ?? '';

				if ( '' !== $area_name ) {
					$lookup_pending[ $layout_post_type_key ] = [
						'layoutId' => $layout_id,
						'areaName' => $area_name,
					];
				}
			}

			// For unresolved active layouts, scan template assignments just until all are resolved.
			// This avoids building a full global ID map for every template on every app load.
			if ( ! empty( $lookup_pending ) ) {
				$theme_builder_templates = et_theme_builder_get_theme_builder_templates( true, false );

				foreach ( $theme_builder_templates as $template ) {
					$is_default_template = ! empty( $template['default'] );
					$layouts             = $template['layouts'] ?? [];

					foreach ( $lookup_pending as $layout_post_type_key => $lookup ) {
						$area_name   = $lookup['areaName'];
						$expected_id = $lookup['layoutId'];
						$area_layout = $layouts[ $area_name ] ?? [];
						$area_id     = intval( $area_layout['id'] ?? 0 );

						if ( $area_id <= 0 || $expected_id !== $area_id ) {
							continue;
						}

						$is_area_global = ! empty( $area_layout['global'] );
						if ( $is_default_template || $is_area_global ) {
							$global_layout_ids[ $layout_post_type_key ][ $area_id ] = true;
							unset( $lookup_pending[ $layout_post_type_key ] );
						}
					}

					if ( empty( $lookup_pending ) ) {
						break;
					}
				}
			}

			if ( ! empty( $theme_builder_layouts ) && $should_show_theme_builder_templates ) {
				foreach ( $active_template_areas as $area ) {
					$definition = $template_definitions[ $area ] ?? null;
					if ( null === $definition ) {
						continue;
					}

					$layout_post_type_key = $definition['layoutPostTypeKey'];
					if ( ! isset( $active_layout_ids[ $layout_post_type_key ] ) ) {
						continue;
					}

					$templates[ $area ] = self::_get_theme_builder_template_data(
						$theme_builder_layouts,
						$layout_post_type_key,
						$definition['expectedPostType'],
						$global_layout_ids
					);
				}
			}

			// Get post content (for postContent layout).
			// Require singular context for postContent ownership. On non-singular pages
			// WordPress sets global $post to the first main-query loop post; hydrating
			// postContent from that ID (even when a TB body layout exists) causes SyncToServer
			// to treat the loop post as the editable main post (#49679 / #48529).
			$post_id        = isset( $post->ID ) ? $post->ID : 0;
			$load_post_data = ! $is_tb_layout_post_type && is_singular();

			if ( $load_post_data && $post_id > 0 ) {
				$post_content_data        = self::post();
				$templates['postContent'] = [
					'id'       => $post_id,
					'title'    => $post_content_data['title'] ?? '',
					'content'  => $post_content_data['content'] ?? '',
					'isGlobal' => false,
				];
			}

			$templates = self::_hydrate_editing_tb_layout_template_from_post(
				$templates,
				$post_id,
				$active_tb_layout,
				$is_tb_layout_post_type
			);

			$templates['imageDimensionsMap'] = self::_build_image_dimensions_map_for_contents(
				[
					$templates['header']['content'] ?? '',
					$templates['body']['content'] ?? '',
					$templates['footer']['content'] ?? '',
					$templates['postContent']['content'] ?? '',
				]
			);

			$return = $templates;
		}

		return $return;
	}

	/**
	 * Get `preferences` setting data.
	 *
	 * @since ??
	 *
	 * @return array Array of app preferences.
	 */
	public static function preferences(): array {
		static $return = null;

		if ( null === $return ) {
			$clean_preferences = [];
			$app_preferences   = AppPreferences::mapping();

			foreach ( $app_preferences as $preference_key => $preference ) {
				$option_name  = 'et_fb_pref_' . $preference['key'];
				$option_value = et_get_option( $option_name, $preference['default'], '', true );

				// If options available, verify returned value against valid options. Return default if fails.
				if ( isset( $preference['options'] ) ) {
					$options       = $preference['options'];
					$valid_options = isset( $options[0] ) ? $options : array_keys( $options );
					// phpcs:ignore WordPress.PHP.StrictInArray -- $valid_options array has strings and numbers values.
					if ( ! in_array( (string) $option_value, $valid_options ) ) {
						$option_value = $preference['default'];
					}
				}

				/**
				 * Fix(D5, Theme): Manually set 'd5-enhanced' as app theme for the entire Visual Builder.
				 * We have completely migrated to the new d5-enhanced design. This is to be removed
				 * once all d4 variants of components are removed.
				 */
				if ( 'et_fb_pref_app_theme' === $option_name ) {
					$option_value = 'd5-enhanced';
				}

				$option_value                         = SavingUtility::parse_value_type( $option_value, $preference['type'] );
				$clean_preferences[ $preference_key ] = $option_value;
			}

			/**
			 * Filter to modify Divi Builder app preferences data.
			 *
			 * @since ??
			 *
			 * @param array $clean_preferences Array of preferences.
			 */
			$return = apply_filters( 'divi_visual_builder_preferences_data', $clean_preferences );
		}

		return $return;
	}

	/**
	 * Append payment mutation capability to each provider service payload.
	 *
	 * @since ??
	 *
	 * @param array $services Payment services payload.
	 *
	 * @return array
	 */
	private static function _append_payment_service_mutation_capability( array $services ): array {
		$can_mutate = current_user_can( 'manage_options' );

		foreach ( $services as $provider => $provider_data ) {
			if ( ! is_array( $provider_data ) ) {
				continue;
			}

			$provider_data['canMutate'] = $can_mutate;
			$services[ $provider ]      = $provider_data;
		}

		return $services;
	}

	/**
	 * Get `services` setting data.
	 *
	 * @since ??
	 */
	public static function services() {
		static $return = null;

		if ( null === $return || Conditions::is_test_env() ) {
			$payment_services = [];

			try {
				$payment_services = self::_append_payment_service_mutation_capability( PaymentAccountService::definition() );
			} catch ( \Throwable $error ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'[Divi Builder 5][SettingsDataCallbacks::services] Failed to resolve payment services: %1$s in %2$s:%3$d',
						$error->getMessage(),
						$error->getFile(),
						$error->getLine()
					)
				);
				$payment_services = [];
			}

			$return = [
				'email'          => EmailAccountService::definition(),
				'payment'        => $payment_services,
				'socialMedia'    => [
					'instagram' => [
						'accounts' => InstagramAccountService::definition(),
					],
				],
				'spamProtection' => SpamProtectionService::definition(),
			];
		}

		return $return;
	}

	/**
	 * Get `settings` setting data.
	 *
	 * @since ??
	 */
	public static function settings() {
		static $return = null;

		if ( null === $return ) {
			// GMT Offset.
			$gmt_offset = get_option( 'gmt_offset' );

			// Get Sidebar values.
			$sidebar_values = Theme::get_sidebar_areas();

			$return = [
				'cookiePath'   => SITECOOKIEPATH,
				'page'         => [
					'items'  => PageSettings::get_registered_items(),
					'values' => Settings::get_settings_values(),
				],
				'role'         => et_pb_get_role_settings(),
				'site'         => [
					'gmtOffsetString' => SiteSettings::get_gmt_offset_string( $gmt_offset ),
					'url'             => get_site_url(),
				],
				'theme'        => [
					'widgetAreas' => $sidebar_values['widget_areas'],
					'defaultArea' => $sidebar_values['area'],
				],
				'previewNonce' => wp_create_nonce( 'et_pb_preview_nonce' ),
			];
		}

		return $return;
	}

	/**
	 * Shortcode module definitions for structure modules.
	 *
	 * @since ??
	 */
	public static function structure_module_definitions() {
		// Load the main structure elements if not already loaded.
		if ( ! class_exists( 'ET_Builder_Section' ) ) {
			require_once ET_BUILDER_DIR . '/main-structure-elements.php';
		}

		// Get all modules definitions.
		// We do it this way because `get_structure_modules()` doesn't include `ET_Builder_Column`.
		$all_modules = \ET_Builder_Element::get_parent_and_child_modules( 'et_pb_layout' );

		// Filter out non-structure modules.
		$modules = array_filter(
			$all_modules,
			function ( $module ) {
				return ! empty( $module->is_structure_element );
			}
		);

		// Build the definitions.
		$definitions = [];
		foreach ( $modules as $module ) {
			$definitions[ $module->slug ] = [
				'name'   => $module->name,
				'plural' => $module->plural,
				'slug'   => $module->slug,
				'title'  => $module->name,
			];
		}

		return $definitions;
	}

	/**
	 * Shortcode module definitions setting data.
	 *
	 * @since ??
	 */
	public static function shortcode_module_definitions() {
		static $return = null;

		if ( null === $return ) {
			// Perf-only fast path for CI timing stabilization.
			// This is a strict no-op outside perf mode to avoid core D5 overhead.
			$cached = SettingsDataPerfCache::get_cached_shortcode_module_definitions();
			if ( null !== $cached ) {
				$return = $cached;
				return $return;
			}

			// fire the actions to initialize any Divi Extensions.
			do_action( 'divi_extensions_init' );
			do_action( 'et_builder_ready' );
			do_action( 'divi_visual_builder_before_get_shortcode_module_definitions' );

			$return = \ET_Builder_Element::get_shortcode_module_definitions();

			/**
			 * Filters shortcode module definitions returned after app load.
			 * This affects Visual Builder settings-data payload only and does not alter frontend render callbacks.
			 *
			 * @since ??
			 *
			 * @param array $return Shortcode module definitions.
			 */
			$return = apply_filters( 'divi_visual_builder_settings_data_after_app_load', $return );

			// Perf-only persistent cache write for repeated after-app-load requests.
			SettingsDataPerfCache::cache_shortcode_module_definitions( $return );
		}

		return $return;
	}

	/**
	 * Get `shortcodeTags` setting data.
	 *
	 * @since ??
	 */
	public static function shortcode_tags() {
		static $return = null;

		if ( null === $return ) {
			// Initialize shortcode framework so 3rd-party module classes are
			// available and registered to WP shortcode tags.
			Conversion::initialize_shortcode_framework();

			$return = ShortcodeUtility::get_shortcode_tags();
		}

		return $return;
	}

	/**
	 * Get `styles` setting data.
	 *
	 * @since ??
	 */
	public static function styles() {
		static $return = null;

		if ( null === $return ) {
			$tablet_body_font_size = absint( et_get_option( 'tablet_body_font_size', '14' ) );
			$phone_body_font_size  = absint( et_get_option( 'phone_body_font_size', $tablet_body_font_size ) );

			$return = [
				'acceptableCSSStringValues' => et_builder_get_acceptable_css_string_values( 'all' ),
				'customizer'                => [
					'body'    => [
						'fontHeight'     => floatval( et_get_option( 'body_font_height', '1.7' ) ),
						'fontSize'       => absint( et_get_option( 'body_font_size', '14' ) ),
						'fontSizeTablet' => $tablet_body_font_size,
						'fontSizePhone'  => $phone_body_font_size,
					],
					'heading' => [
						'fontSize' => absint( et_get_option( 'body_header_size', '30' ) ),
					],
					'layout'  => [
						'contentWidth' => absint( et_get_option( 'content_width', '1080' ) ),
					],
				],
			];
		}

		return $return;
	}

	/**
	 * Get `taxonomy` setting data.
	 *
	 * @since ??
	 */
	public static function taxonomy() {
		static $return = null;

		if ( null === $return ) {
			// Divi Taxonomies.
			$layout_taxonomies = Taxonomy::get_terms();

			/**
			 * Filters the taxonomies that are used for the layout category and layout tag.
			 *
			 * @since      ??
			 *
			 * @param array $layout_taxonomies Taxonomies that are used for the layout category and layout tag.
			 *
			 * @deprecated 5.0.0 Use the {@see 'divi_visual_builder_settings_data_layout_taxonomies'} filter instead.
			 */
			$layout_taxonomies = apply_filters(
				'et_fb_taxonomies',
				$layout_taxonomies
			);

			/**
			 * Filters the taxonomies that are used for the layout category and layout tag.
			 *
			 * @since ??
			 *
			 * @param array $layout_taxonomies Taxonomies that are used for the layout category and layout tag.
			 */
			$get_taxonomies = apply_filters( 'divi_visual_builder_settings_data_layout_taxonomies', $layout_taxonomies );

			// Legacy structure for backwards compatibility.
			$return = [
				'layoutCategory'    => array_key_exists( 'layout_category', $get_taxonomies ) ? $get_taxonomies['layout_category'] : [],
				'layoutTag'         => array_key_exists( 'layout_tag', $get_taxonomies ) ? $get_taxonomies['layout_tag'] : [],
				'projectCategories' => array_key_exists( 'project_category', $layout_taxonomies ) ? $layout_taxonomies['project_category'] : (object) [],
				'postCategories'    => array_key_exists( 'category', $layout_taxonomies ) ? $layout_taxonomies['category'] : (object) [],
				'productCategories' => array_key_exists( 'product_cat', $layout_taxonomies ) ? $layout_taxonomies['product_cat'] : (object) [],
			];

			// Add all other taxonomies dynamically to support external modules.
			foreach ( $get_taxonomies as $taxonomy_name => $terms ) {
				if ( ! in_array( $taxonomy_name, [ 'layout_category', 'layout_tag', 'project_category', 'category', 'product_cat' ], true ) ) {
					// Convert taxonomy name to camelCase format for consistency.
					$camel_case_name            = lcfirst( str_replace( ' ', '', ucwords( str_replace( [ '-', '_' ], ' ', $taxonomy_name ) ) ) );
					$return[ $camel_case_name ] = $terms;
				}
			}

			// New structure: organize taxonomies by post type for dynamic support.
			$return['byPostType'] = [];

			// Taxonomies to exclude (same list as CategoriesRESTController).
			$excluded_taxonomies = [
				'post_tag',
				'project_tag',
				'product_tag',
				'post_format',
				'nav_menu',
				'link_category',
				'post_status',
				'product_type',
				'product_brand',
				'product_visibility',
				'product_shipping_class',
			];

			// Get all public post types.
			$post_types = get_post_types( [ 'public' => true ], 'objects' );

			foreach ( $post_types as $post_type_slug => $post_type_object ) {
				// Get taxonomies associated with this post type.
				$post_type_taxonomies = get_object_taxonomies( $post_type_slug, 'objects' );

				$categories = [];

				foreach ( $post_type_taxonomies as $taxonomy_slug => $taxonomy_object ) {
					// Skip excluded taxonomies.
					if ( in_array( $taxonomy_slug, $excluded_taxonomies, true ) ) {
						continue;
					}

					// Include both hierarchical (categories) and non-hierarchical (tags) taxonomies.
					// This enables support for ACF custom taxonomies and other non-hierarchical taxonomies.
					if ( isset( $get_taxonomies[ $taxonomy_slug ] ) ) {
						$categories[] = [
							'slug'  => $taxonomy_slug,
							'name'  => $taxonomy_object->label,
							'terms' => $get_taxonomies[ $taxonomy_slug ],
						];
					}
				}

				// Only add post types that have category taxonomies.
				if ( ! empty( $categories ) ) {
					$return['byPostType'][ $post_type_slug ] = [
						'categories' => $categories,
					];
				}
			}
		}

		return $return;
	}

	/**
	 * Get lightweight `taxonomy` setting data for initial app load.
	 *
	 * Full taxonomy payload is deferred to after-app-load REST fetch.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function taxonomy_app_load(): array {
		return [
			'layoutCategory'    => [],
			'layoutTag'         => [],
			'projectCategories' => (object) [],
			'postCategories'    => (object) [],
			'productCategories' => (object) [],
			'byPostType'        => [],
		];
	}

	/**
	 * Get `postTypes` setting data.
	 *
	 * Returns post type slugs and their display labels for use in the Visual Builder.
	 *
	 * @since ??
	 */
	public static function post_types() {
		static $return = null;

		if ( null === $return ) {
			$return = et_get_registered_post_type_options( false, false );
		}

		return $return;
	}

	/**
	 * Get `themeBuilder` setting data.
	 *
	 * @since ??
	 */
	public static function theme_builder() {
		static $return = null;

		if ( null === $return || Conditions::is_test_env() ) {
			global $post;
			$post_type = $post->post_type ?? 'post';

			// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
			// TODO feat(D5, Theme Builder) Maybe remove these parameters. Check whether these are used or not.
			// At the moment these are straight copy from Divi 4 counterpart.
			// Validate the Theme Builder body layout and its post content module, if any.
			$theme_builder_layouts    = self::_get_theme_builder_template_layouts();
			$has_tb_layouts           = ! empty( $theme_builder_layouts );
			$is_tb_layout             = et_theme_builder_is_layout_post_type( $post_type );
			$tb_body_layout           = ArrayUtility::get_value( $theme_builder_layouts, ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE, [] );
			$tb_body_has_post_content = $tb_body_layout && et_theme_builder_layout_has_post_content( $tb_body_layout );
			$has_valid_body_layout    = ! $has_tb_layouts || $is_tb_layout || $tb_body_has_post_content;

			$return = [
				'layout'                         => Layout::get_layout_based_on_post_type( $post_type ),

				// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
				// TODO feat(D5, Theme Builder) Maybe remove these parameters. Check whether these are used or not.
				// At the moment these are straight copy from Divi 4 counterpart.
				'isLayout'                       => et_theme_builder_is_layout_post_type( $post_type ),
				'layoutPostTypes'                => et_theme_builder_get_layout_post_types(),
				'bodyLayoutPostType'             => ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE,
				'postContentModules'             => et_theme_builder_get_post_content_modules(),
				'hasValidBodyLayout'             => $has_valid_body_layout,
				'themeBuilderAreas'              => $theme_builder_layouts,
				'canUseThemeBuilderForTemplates' => self::_can_use_theme_builder_for_templates(),
			];
		}

		return $return;
	}

	/**
	 * Get `tinymce` setting data.
	 *
	 * @since ??
	 */
	public static function tinymce() {
		static $return = null;

		if ( null === $return ) {
			$tinymce_default_plugins = [
				'autolink',
				'autoresize',
				'charmap',
				'emoticons',
				'fullscreen',
				'image',
				'link',
				'lists',
				'paste',
				'preview',
				'print',
				'table',
				'textcolor',
				'wpview',
			];

			/**
			 * Filters the TinyMCE plugins that are used for the visual builder.
			 *
			 * @since      ??
			 *
			 * @param array $tinymce_defaults_plugins TinyMCE plugins that are used for the visual builder.
			 *
			 * @deprecated 5.0.0 Use the {@see 'divi_visual_builder_tinymce_plugins'} filter instead.
			 */
			$tinymce_default_plugins = apply_filters(
				'et_fb_tinymce_plugins',
				$tinymce_default_plugins
			);

			/**
			 * Filters the TinyMCE plugins that are used for the visual builder.
			 *
			 * @since ??
			 *
			 * @param array $tinymce_default_plugins TinyMCE plugins that are used for the visual builder.
			 */
			$tinymce_plugins = apply_filters( 'divi_visual_builder_tinymce_plugins', $tinymce_default_plugins );

			$return = [
				'skinUrl'  => ET_BUILDER_5_URI . '/visual-builder-assets/tinymce-skin',
				'cssFiles' => esc_url( includes_url( 'js/tinymce' ) . '/skins/wordpress/wp-content.css' ),
				'plugins'  => $tinymce_plugins,
			];
		}

		return $return;
	}

	/**
	 * Get `urls` setting data.
	 *
	 * @since ??
	 */
	public static function urls() {
		static $return = null;

		if ( null === $return ) {
			$return = [
				'admin'                  => admin_url(),
				'adminOptionsGeneralUrl' => esc_url( admin_url( 'options-general.php' ) ),
				'ajax'                   => is_ssl() ? admin_url( 'admin-ajax.php' ) : admin_url( 'admin-ajax.php', 'http' ),
				'builderImages'          => esc_url( ET_BUILDER_URI . '/images' ),
				'builder5Images'         => esc_url( ET_BUILDER_5_URI . '/images' ),
				'themeOptions'           => esc_url( et_pb_get_options_page_link() ),
				'homeUrl'                => esc_url( home_url( '/' ) ),
				'restRootUrl'            => esc_url( get_rest_url() ),
			];
		}

		return $return;
	}

	/**
	 * Retrieve WooCommerce settings and configuration data.
	 *
	 * This method provides WooCommerce-specific settings including default values,
	 * module options, and UI messages for the visual builder. It includes proper
	 * caching and early returns for performance optimization.
	 *
	 * @since ??
	 *
	 * @return array Associative array containing WooCommerce settings and default values.
	 *               Returns empty array if not in REST API/VB context.
	 */
	public static function woocommerce(): array {
		// Skip processing if not in Visual Builder, REST API, or Theme Builder context.
		if ( ! (
			Conditions::is_rest_api_request() ||
			Conditions::is_vb_app_window() ||
			Conditions::is_tb_enabled()
		) ) {
			return [];
		}

		static $return = null;

		// Cache the result for performance, but refresh in test environments.
		if ( null === $return || Conditions::is_test_env() ) {
			$return = [
				'defaults'                          => [
					'columnsPosts' => WooCommerceUtils::get_default_columns_posts(),
					'homeUrl'      => esc_url_raw( get_home_url() ),
					'pageType'     => WooCommerceUtils::get_default_page_type(),
					'product'      => WooCommerceUtils::get_default_product(),
					'productTabs'  => WooCommerceUtils::get_default_product_tabs(),
				],
				'inactiveModuleNotice'              => esc_html__(
					'WooCommerce must be active for this module to appear',
					'et_builder_5'
				),
				'isWooCommerceActive'               => Conditions::is_woocommerce_enabled(),
				'productPriceRange'                 => PostFilterProductPriceRange::get_range(),
				'productTabsOptions'                => Conditions::is_tb_enabled() && Conditions::is_woocommerce_enabled()
					? WooCommerceUtils::set_default_product_tabs_options()
					: WooCommerceUtils::get_product_tabs_options(),
				'woocommerceModuleMarkup'           => WooCommerceUtils::get_current_page_woocommerce_components_markup(),
				'isCheckoutContext'                 => WooCommerceUtils::is_checkout_context(),
				'hasBillingOnlyShippingDestination' => WooCommerceUtils::has_billing_only_shipping_destination(),
			];
		}

		return $return;
	}

	/**
	 * Get workspaces data.
	 *
	 * @since ??
	 */
	public static function workspaces() {
		static $return = null;

		if ( null === $return ) {
			$workspace_items        = Workspace::get_items();
			$global_preferences     = self::preferences();
			$preferences_workspaces = Workspace::get_preferences_workspaces();
			$global_workspace       = is_array( $preferences_workspaces['global']['workspace'] ?? null ) ? $preferences_workspaces['global']['workspace'] : [];
			$custom_preferences     = is_array( $preferences_workspaces['custom'] ?? null ) ? $preferences_workspaces['custom'] : [];
			$active_workspace_id    = is_string( $preferences_workspaces['activeWorkspaceId'] ?? null ) ? $preferences_workspaces['activeWorkspaceId'] : 'global';
			$default_workspace_id   = is_string( $preferences_workspaces['defaultWorkspaceId'] ?? null ) ? $preferences_workspaces['defaultWorkspaceId'] : 'global';

			if (
				'global' !== $active_workspace_id &&
				! isset( $custom_preferences[ $active_workspace_id ] ) &&
				! str_starts_with( $active_workspace_id, 'premade-' )
			) {
				$active_workspace_id = 'global';
			}

			if (
				'global' !== $default_workspace_id &&
				! isset( $custom_preferences[ $default_workspace_id ] ) &&
				! str_starts_with( $default_workspace_id, 'premade-' )
			) {
				$default_workspace_id = 'global';
			}

			// On initial builder load, active workspace must follow the default workspace.
			$active_workspace_id = $default_workspace_id;

			$active_workspace_values = 'global' === $active_workspace_id
				? $global_preferences
				: ( is_array( $custom_preferences[ $active_workspace_id ]['settings'] ?? null ) ? $custom_preferences[ $active_workspace_id ]['settings'] : $global_preferences );

			$workspace_items['preferences'] = [
				'activeWorkspaceId'  => $active_workspace_id,
				'defaultWorkspaceId' => $default_workspace_id,
				'global'             => [
					'id'        => 'global',
					'name'      => esc_html__( 'Global', 'et_builder_5' ),
					'settings'  => $global_preferences,
					'workspace' => is_array( $global_workspace ) ? $global_workspace : [],
				],
				'custom'             => $custom_preferences,
				'activeSettings'     => $active_workspace_values,
			];

			$return = $workspace_items;
		}

		return $return;
	}

	/**
	 * Get the builder version.
	 *
	 * @since ??
	 */
	public static function get_the_builder_version() {
		if ( ! defined( 'ET_BUILDER_VERSION' ) ) {
			return '0';
		}
		return ET_BUILDER_VERSION;
	}

	/**
	 * Get legacy attribute names from migration classes
	 *
	 * @since ??
	 *
	 * @return array Array of legacy attribute names
	 */
	public static function legacy_attribute_names() {
		return LegacyAttributeNames::get_legacy_attribute_names();
	}

	/**
	 * Get Imagely (NextGEN) Gallery settings data.
	 *
	 * @since ??
	 *
	 * @return array Imagely Gallery settings.
	 */
	public static function imagely_gallery(): array {
		static $return = null;

		if ( null === $return || Conditions::is_test_env() ) {
			$is_active = class_exists( 'C_NextGEN_Bootstrap' ) && ImagelyGalleryService::is_runtime_available();
			$galleries = [];

			if ( $is_active ) {
				foreach ( ImagelyGalleryService::get_galleries() as $gallery ) {
					$galleries[ (string) $gallery['id'] ] = [
						'label' => sanitize_text_field( wp_strip_all_tags( (string) $gallery['title'] ) ),
					];
				}
			}

			if ( [] === $galleries ) {
				$galleries = (object) [];
			}

			$return = [
				'isActive'  => $is_active,
				'galleries' => $galleries,
			];
		}

		return $return;
	}

	/**
	 * Get dependency change detection data for attrs maps cache invalidation.
	 *
	 * @since ??
	 *
	 * @return array Dependency change detection information.
	 */
	public static function dependency_change_detection() {
		static $return = null;

		if ( null === $return ) {
			$return = DependencyChangeDetector::get_change_data();
		}

		return $return;
	}

	/**
	 * Get `offCanvas` setting data.
	 *
	 * @since ??
	 *
	 * @return array Off-canvas data including canvases, activeCanvasId, and mainCanvasName.
	 */
	public static function off_canvas() {
		// Get post ID from SettingsDataController (set before callbacks are invoked).
		$post_id                = self::get_current_post_id();
		$post                   = get_post( $post_id );
		$post_type              = $post instanceof \WP_Post ? $post->post_type : '';
		$is_tb_layout_post_type = ! empty( $post_type ) && et_theme_builder_is_layout_post_type( $post_type );
		$active_tb_layout       = $is_tb_layout_post_type
			? Layout::get_layout_based_on_post_type( $post_type )
			: '';

		$main_loop_type          = self::get_current_main_loop_type();
		$main_loop_settings_data = self::get_current_main_loop_settings_data();

		// Keep the post_id guard for singular pages (and when mainLoopType is undefined / defaults to singular).
		// Non-singular pages can have no stable post ID (currentPage.id is false), but can still own canvases
		// via a main-loop context key.
		if ( 0 === $post_id && ( ! is_string( $main_loop_type ) || '' === $main_loop_type || 'singular' === $main_loop_type ) ) {
			return [
				'canvases'       => [],
				'activeCanvasId' => '',
				'mainCanvasName' => '',
			];
		}

		// Start with a consistent return shape.
		$off_canvas_data = [
			'canvases'       => [],
			'activeCanvasId' => '',
			'mainCanvasName' => '',
		];

		// Load post-backed canvases when a post ID exists.
		if ( 0 !== $post_id ) {
			$off_canvas_data = OffCanvasHooks::get_off_canvas_data_for_post( $post_id );
		}

		// Include context-backed canvases for non-singular main loop types.
		// This ensures archive pages list canvases stored under `_divi_canvas_parent_context`.
		$context_key = CanvasUtils::get_main_loop_parent_context_key( $main_loop_type, $main_loop_settings_data );

		if ( is_string( $context_key ) && '' !== $context_key ) {
			$context_off_canvas_data = OffCanvasHooks::get_off_canvas_data_for_context( $context_key );

			$context_canvases = $context_off_canvas_data['canvases'] ?? [];
			if ( is_array( $context_canvases ) && ! empty( $context_canvases ) ) {
				$off_canvas_data['canvases'] = array_merge( $off_canvas_data['canvases'], $context_canvases );
			}
		}

		// Resolve assigned template layout IDs directly from Theme Builder layout assignments.
		// Do not rely on `theme_builder_templates()` here because that payload can be filtered
		// by UI preferences (e.g. show/hide template rendering in builder), while off-canvas
		// ownership mapping must always include assigned template canvases.
		$theme_builder_layouts = [];
		$current_tb_layout_ids = self::get_current_theme_builder_layout_ids();

		// Prefer layout IDs resolved from the initial app-load context.
		// This preserves archive/term template ownership in REST callbacks.
		if ( ! empty( array_filter( $current_tb_layout_ids ) ) ) {
			$theme_builder_layouts = [
				ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE => [ 'id' => absint( $current_tb_layout_ids['header'] ?? 0 ) ],
				ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE   => [ 'id' => absint( $current_tb_layout_ids['body'] ?? 0 ) ],
				ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE => [ 'id' => absint( $current_tb_layout_ids['footer'] ?? 0 ) ],
			];
		}

		// If app-load IDs are unavailable, resolve from current query context.
		if ( empty( $theme_builder_layouts ) ) {
			$theme_builder_layouts = self::_get_theme_builder_template_layouts();
		}

		// Last fallback: resolve by post context.
		if ( empty( $theme_builder_layouts ) ) {
			$tb_request = \ET_Theme_Builder_Request::from_post( $post_id );
			if ( $tb_request ) {
				$theme_builder_layouts = self::_get_theme_builder_template_layouts( $tb_request );
			}
		}

		$get_assigned_layout_id = static function ( array $layouts, string $layout_key ): int {
			$layout_data = $layouts[ $layout_key ] ?? [];
			// In some Theme Builder contexts, `override` can be false/omitted even when
			// a layout ID is active (e.g. default/global assignment paths). Rely on the
			// resolved layout ID directly so template-owned canvases are still included.
			return absint( $layout_data['id'] ?? 0 );
		};

		$template_post_ids = [
			'header' => $get_assigned_layout_id( $theme_builder_layouts, ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ),
			'body'   => $get_assigned_layout_id( $theme_builder_layouts, ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ),
			'footer' => $get_assigned_layout_id( $theme_builder_layouts, ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ),
		];

		// In Theme Builder layout editor, only canvases from the active layout area
		// should be included in the payload (e.g. header editor shows header canvases only).
		if ( $is_tb_layout_post_type && in_array( $active_tb_layout, [ 'header', 'body', 'footer' ], true ) ) {
			foreach ( [ 'header', 'body', 'footer' ] as $layout ) {
				if ( $layout !== $active_tb_layout ) {
					$template_post_ids[ $layout ] = 0;
				}
			}
		}

		// Regression note:
		// - c338657b769 introduced slot-prefixed merge IDs (`{$layout}-{$canvas_id}`) for template canvases.
		// - 68d40d92680 retained that merge behavior in the current off_canvas() implementation.
		// We have always used `_divi_dynamic_assets_canvases_used` cache, but cached keys can already be
		// prefixed (e.g. `footer-<uuid>`). Re-prefixing during merge created duplicate hydration entries.
		// Remove template-owned local canvases from base payload before template merge so each template
		// canvas appears once in the builder payload.
		$template_owner_post_ids = array_values(
			array_filter(
				array_map(
					'absint',
					$template_post_ids
				),
				static function ( int $template_owner_post_id ) use ( $post_id ): bool {
					return 0 !== $template_owner_post_id && $template_owner_post_id !== $post_id;
				}
			)
		);

		if ( ! empty( $template_owner_post_ids ) && isset( $off_canvas_data['canvases'] ) && is_array( $off_canvas_data['canvases'] ) ) {
			foreach ( $off_canvas_data['canvases'] as $existing_canvas_id => $existing_canvas ) {
				if ( ! empty( $existing_canvas['isGlobal'] ) ) {
					continue;
				}

				$existing_parent_post_id = isset( $existing_canvas['parentPostId'] ) ? absint( $existing_canvas['parentPostId'] ) : 0;
				if ( in_array( $existing_parent_post_id, $template_owner_post_ids, true ) ) {
					unset( $off_canvas_data['canvases'][ $existing_canvas_id ] );
				}
			}
		}

		$normalize_template_canvas_id = static function ( string $canvas_id, string $layout ): string {
			$canvas_id = sanitize_text_field( $canvas_id );
			$layout    = sanitize_key( $layout );

			if ( '' === $canvas_id || ! in_array( $layout, [ 'header', 'body', 'footer' ], true ) ) {
				return $canvas_id;
			}

			$layout_prefix = "{$layout}-";
			$prefix_length = strlen( $layout_prefix );

			while ( str_starts_with( $canvas_id, $layout_prefix ) ) {
				$canvas_id = substr( $canvas_id, $prefix_length );
			}

			return $canvas_id;
		};

		foreach ( $template_post_ids as $layout => $template_post_id ) {
			if ( 0 === $template_post_id || $template_post_id === $post_id ) {
				continue;
			}

			$template_off_canvas_data = OffCanvasHooks::get_off_canvas_data_for_post( $template_post_id );
			$template_canvases        = $template_off_canvas_data['canvases'] ?? [];

			if ( ! is_array( $template_canvases ) || empty( $template_canvases ) ) {
				continue;
			}

			foreach ( $template_canvases as $canvas_id => $template_canvas ) {
				$is_global = ! empty( $template_canvas['isGlobal'] );

				if ( ! $is_global ) {
					$raw_canvas_id  = isset( $template_canvas['id'] ) && is_string( $template_canvas['id'] )
						? $template_canvas['id']
						: ( is_string( $canvas_id ) ? $canvas_id : '' );
					$base_canvas_id = $normalize_template_canvas_id( $raw_canvas_id, $layout );

					// Local canvases merged from assigned template layouts belong to a slot area.
					// Include the slot so UI badges/grouping can show Header/Body/Footer labels.
					$template_canvas['themeBuilderLayout'] = $layout;

					// Local canvases from TB layouts are owned by the areas themselves, but
					// they need a distinct ID to avoid collisions with main post canvases.
					$canvas_id = "{$layout}-{$base_canvas_id}";

					// For local canvases, ensure the data also reflects the prefixed ID.
					$template_canvas['id'] = $canvas_id;
				}

				$off_canvas_data['canvases'][ $canvas_id ] = $template_canvas;
			}
		}

		return $off_canvas_data;
	}

	/**
	 * Get announcements data.
	 *
	 * This payload is used by the Visual Builder to render product updates instantly from the store.
	 * Announcements are version-cached server-side and refreshed only when builder version changes.
	 *
	 * @since ??
	 *
	 * @return array<string, mixed>
	 */
	public static function announcements(): array {
		static $return = null;

		if ( null === $return || Conditions::is_test_env() ) {
			$return = AnnouncementsController::get_announcements_payload_for_current_user();
		}

		return $return;
	}
}
