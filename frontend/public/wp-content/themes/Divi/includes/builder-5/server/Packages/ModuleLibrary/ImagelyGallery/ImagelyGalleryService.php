<?php
/**
 * Module Library: Imagely Gallery Service.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\ImagelyGallery;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\GlobalData\GlobalPreset;

/**
 * Service class for Imagely Gallery data access and rendering.
 *
 * Wraps the Imagely (formerly NextGEN Gallery) plugin's DataMapper and
 * Renderer APIs.
 *
 * @since ??
 */
class ImagelyGalleryService {
	/**
	 * Imagely Gallery module block name.
	 *
	 * @var string
	 */
	private const MODULE_BLOCK_NAME = 'divi/imagely-gallery';

	/**
	 * Synthetic shortcode template for NextGEN's public asset enqueue API.
	 *
	 * @var string
	 */
	private const ASSET_SHORTCODE_TEMPLATE = '[imagely id="%d"]';

	private const DISPLAY_TYPE_SLIDESHOW = 'photocrati-nextgen_basic_slideshow';

	/**
	 * Gallery IDs already passed to NextGEN's public enqueue API this request.
	 *
	 * @since ??
	 *
	 * @var array<int, bool>
	 */
	private static array $_enqueued_gallery_assets = [];

	/**
	 * Request-scoped gallery mapper result shared by settings and VB asset passes.
	 *
	 * @var array|null
	 */
	private static ?array $_gallery_entities = null;

	/**
	 * Whether every currently available gallery has been considered for VB assets.
	 *
	 * @var bool
	 */
	private static bool $_all_gallery_assets_enqueued = false;

	/**
	 * Cached availability of the namespaced NextGEN gallery mapper runtime.
	 *
	 * @var bool|null
	 */
	private static ?bool $_runtime_available = null;

	/**
	 * Extract distinct valid gallery IDs from supplied Divi content.
	 *
	 * Dynamic Assets owns request content gathering. This method only interprets
	 * Imagely Gallery blocks, including nested and reusable block references.
	 * Preset render attributes are merged first and explicit block attributes are
	 * applied last, matching the module render pipeline.
	 *
	 * @since ??
	 *
	 * @param string $content Content supplied by Dynamic Assets.
	 *
	 * @return int[] Distinct positive gallery IDs in encounter order.
	 */
	public static function get_gallery_ids_from_content( string $content ): array {
		if (
			'' === $content
			|| ( ! str_contains( $content, self::MODULE_BLOCK_NAME ) && ! str_contains( $content, 'wp:block' ) )
		) {
			return [];
		}

		$gallery_ids = [];

		self::_collect_gallery_ids_from_blocks( parse_blocks( $content ), $gallery_ids );

		return array_values( $gallery_ids );
	}

	/**
	 * Enqueue NextGEN resources for the supplied gallery IDs.
	 *
	 * This is the explicit Divi boundary for
	 * `DisplayManager::enqueue_frontend_resources_for_content()`. NextGEN parses
	 * the synthetic shortcodes and remains responsible for selecting every plugin,
	 * dependency, add-on, handle, and URL.
	 *
	 * @since ??
	 *
	 * @param int[] $gallery_ids Gallery IDs to enqueue.
	 *
	 * @return void
	 */
	public static function enqueue_gallery_assets( array $gallery_ids ): void {
		if ( empty( $gallery_ids ) || ! self::_can_enqueue_assets() ) {
			return;
		}

		$shortcodes = [];

		foreach ( $gallery_ids as $gallery_id ) {
			$gallery_id = (int) $gallery_id;

			if ( $gallery_id < 1 || isset( self::$_enqueued_gallery_assets[ $gallery_id ] ) ) {
				continue;
			}

			$shortcodes[ $gallery_id ] = sprintf( self::ASSET_SHORTCODE_TEMPLATE, $gallery_id );
		}

		if ( empty( $shortcodes ) ) {
			return;
		}

		\Imagely\NGG\Display\DisplayManager::enqueue_frontend_resources_for_content( implode( "\n", $shortcodes ) );

		foreach ( array_keys( $shortcodes ) as $gallery_id ) {
			self::$_enqueued_gallery_assets[ $gallery_id ] = true;
		}
	}

	/**
	 * Enqueue resources for every available gallery in the mutable VB app window.
	 *
	 * Saved content cannot predict a newly inserted module or a gallery selection
	 * changed without reloading the builder. The mapper supplies only available IDs;
	 * NextGEN's public API still owns display-type and resource selection.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_all_gallery_assets(): void {
		if ( self::$_all_gallery_assets_enqueued || ! self::_can_enqueue_assets() ) {
			return;
		}

		$gallery_ids = [];

		foreach ( self::_get_gallery_entities() as $gallery ) {
			$gallery_id = (int) ( $gallery->gid ?? 0 );

			if ( $gallery_id > 0 ) {
				$gallery_ids[ $gallery_id ] = $gallery_id;
			}
		}

		self::enqueue_gallery_assets( array_values( $gallery_ids ) );
		self::$_all_gallery_assets_enqueued = true;
	}

	/**
	 * Recursively collect gallery IDs and follow renderable reusable blocks.
	 *
	 * @since ??
	 *
	 * @param array      $blocks            Parsed WordPress blocks.
	 * @param array      $gallery_ids       Gallery IDs keyed by ID.
	 * @param array<int> $seen_reusable_ids Reusable block IDs in the current branch.
	 *
	 * @return void
	 */
	private static function _collect_gallery_ids_from_blocks(
		array $blocks,
		array &$gallery_ids,
		array $seen_reusable_ids = []
	): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$block_name = $block['blockName'] ?? '';

			if ( self::MODULE_BLOCK_NAME === $block_name ) {
				$raw_attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [];
				$attrs     = array_replace_recursive(
					GlobalPreset::get_merged_preset_render_attrs(
						[
							'moduleName'  => self::MODULE_BLOCK_NAME,
							'moduleAttrs' => $raw_attrs,
						]
					),
					$raw_attrs
				);

				$gallery_id = (int) ( $attrs['imagelyGallery']['innerContent']['desktop']['value']['galleryId'] ?? 0 );

				if ( $gallery_id > 0 ) {
					$gallery_ids[ $gallery_id ] = $gallery_id;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::_collect_gallery_ids_from_blocks( $block['innerBlocks'], $gallery_ids, $seen_reusable_ids );
			}

			if ( 'core/block' !== $block_name ) {
				continue;
			}

			$reusable_id = absint( $block['attrs']['ref'] ?? 0 );

			if ( $reusable_id < 1 || in_array( $reusable_id, $seen_reusable_ids, true ) ) {
				continue;
			}

			$reusable_post = get_post( $reusable_id );

			if (
				! ( $reusable_post instanceof \WP_Post )
				|| 'wp_block' !== $reusable_post->post_type
				|| 'publish' !== $reusable_post->post_status
				|| '' !== (string) $reusable_post->post_password
			) {
				continue;
			}

			self::_collect_gallery_ids_from_blocks(
				parse_blocks( (string) $reusable_post->post_content ),
				$gallery_ids,
				array_merge( $seen_reusable_ids, [ $reusable_id ] )
			);
		}
	}

	/**
	 * Whether NextGEN's public frontend enqueue boundary is available.
	 *
	 * Service callers outside the frontend provider pipeline, including the mutable
	 * Visual Builder preload, retain this defensive runtime check.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _can_enqueue_assets(): bool {
		return class_exists( '\Imagely\NGG\Display\DisplayManager' )
			&& is_callable( [ '\Imagely\NGG\Display\DisplayManager', 'enqueue_frontend_resources_for_content' ] );
	}

	/**
	 * Prepare NextGEN gallery markup for Divi wrappers (owned display transforms only).
	 *
	 * This is NOT a security sanitizer. Gallery HTML is generated by NextGEN per
	 * request (Divi stores only a gallery ID), so there is no save boundary at which
	 * to run `wp_kses`. Sibling third-party modules (Contact Form 7)
	 * trust plugin HTML on the front end; we match that trust model and only apply
	 * the display/layout transforms Divi must own because Imagely will not fix them.
	 *
	 * Pipeline: strip script/style → force_balance_tags → EXIF sibling wrap.
	 *
	 * @since ??
	 *
	 * @param string $html Raw gallery markup from NextGEN (or the raw-markup filter).
	 *
	 * @return string Markup ready to wrap in `.et-imagely-content`.
	 */
	public static function prepare_gallery_markup_for_divi( string $html ): string {
		if ( '' === $html ) {
			return '';
		}

		$stripped = self::_strip_script_style_blocks( $html );
		if ( ! is_string( $stripped ) ) {
			return $html;
		}

		$balanced = force_balance_tags( $stripped );

		return self::_wrap_exif_meta_data( $balanced );
	}

	/**
	 * Strip `<script>`/`<style>` elements, including their inner text.
	 *
	 * Rule 7.7 — owned transform:
	 * 1. Failing input: inline `<script>`/`<style>` (Pro/custom templates; also any
	 *    body that embeds HTML-looking text that would confuse `force_balance_tags`).
	 * 2. Frequency: rare on free NextGEN display types we ship; kept as a cheap
	 *    prerequisite for balancing and to keep script bodies out of visible copy.
	 * 3. Label: display (layout integrity), not security.
	 * 4. Upstream: N/A for strip — balancer prerequisite. Follows
	 *    `ET\Builder\VisualBuilder\Saving\SavingUtility::strip_script_style_blocks()`,
	 *    plus a third pass for unterminated open tags.
	 *
	 * @since ??
	 *
	 * @param string $html Raw markup.
	 *
	 * @return string|null Markup with script/style elements removed, or null when
	 *                     PCRE cannot complete the transform.
	 */
	private static function _strip_script_style_blocks( string $html ): ?string {
		if ( false === stripos( $html, '<script' ) && false === stripos( $html, '<style' ) ) {
			return $html;
		}

		/*
		 * Matches a `<script>`/`<style>` open tag, its body, and its matching
		 * close tag (case-insensitive, `.` spans newlines). Covered by
		 * ImagelyGalleryServiceTest::test_prepare_gallery_markup_strips_script_style_*.
		 * regex101: https://regex101.com/r/uTtYt7/1/unit-tests (PHP flavor).
		 */
		$without_blocks = preg_replace( '/<(script|style)\b[^>]*>.*?<\/\1\s*>/is', '', $html );
		if ( ! is_string( $without_blocks ) ) {
			return null;
		}

		/*
		 * Also remove malformed/self-closing `<script/>`/`<style/>` tags.
		 * regex101: https://regex101.com/r/oHFgUJ/1/unit-tests (PHP flavor).
		 */
		$without_self_closing = preg_replace( '/<(script|style)\b[^>]*\/>/is', '', $without_blocks );
		if ( ! is_string( $without_self_closing ) ) {
			return null;
		}

		/*
		 * Finally, remove an unterminated `<script>`/`<style>` (an open tag with no
		 * matching close): strip from the open tag to the end of the string so the
		 * body cannot leak as visible page text after the open tag is gone.
		 * regex101: https://regex101.com/r/CRSYc2/1/unit-tests (PHP flavor).
		 */
		$without_unterminated = preg_replace( '/<(script|style)\b[^>]*>.*/is', '', $without_self_closing );

		return is_string( $without_unterminated ) ? $without_unterminated : null;
	}


	/**
	 * Wrap the legacy EXIF "Meta data" heading and its table in a single container.
	 *
	 * Rule 7.7 — owned transform:
	 * 1. Failing input: NextGEN legacy `imagebrowser-exif.php` emits heading +
	 *    `<table class="exif-data">` as adjacent siblings (`:42-43`) with no common
	 *    wrapper that is not also the nav.
	 * 2. Frequency: every gallery using the legacy EXIF ImageBrowser template.
	 * 3. Label: display (Divi `exifMetaData` design controls need one box).
	 * 4. Upstream: Imagely contacted; no fix expected — Divi owns the wrapper.
	 *
	 * CSS cannot draw one border/background/shadow across two siblings. The wrapper
	 * is additive: plugin rules use descendant combinators only; ImageBrowser JS and
	 * AJAX paging still resolve `.ngg-imagebrowser`.
	 *
	 * Runs after strip+balance. Matches the heading structurally (`<h3>` immediately
	 * before `table.exif-data`), never by translated text.
	 *
	 * @since ??
	 *
	 * @param string $html Prepared gallery markup.
	 *
	 * @return string Markup with the EXIF heading + table wrapped, or unchanged when
	 *                the render carries no EXIF table.
	 */
	private static function _wrap_exif_meta_data( string $html ): string {
		if ( false === stripos( $html, 'exif-data' ) ) {
			return $html;
		}

		/*
		 * The heading body is TEMPERED (`(?:(?!<\/?h3\b).)*`) rather than a plain
		 * `.*?`. With a plain lazy match the engine can backtrack ACROSS an earlier
		 * `</h3>` — the image Title heading at `:29` — and swallow the image itself
		 * into group 1, wrapping the photo along with the metadata. Refusing to cross
		 * any `h3` boundary confines the match to the heading that actually precedes
		 * the table.
		 *
		 * Both `preg_replace` calls below are covered by adversarial tests:
		 * `ImagelyGalleryServiceTest::test_render_gallery_wraps_exif_heading_and_table_in_one_container`
		 * asserts the wrapper's exact contents AND that it contains neither `<img`,
		 * `class="pic"` nor the Title heading — i.e. it fails on precisely the
		 * backtracking the tempering prevents;
		 * `ImagelyGalleryServiceTest::test_render_gallery_wraps_exif_table_when_heading_is_absent`
		 * covers the fallback; and
		 * `ImagelyGalleryServiceTest::test_render_gallery_balances_real_imagebrowser_fixture`
		 * pins the negative case, that a view with no `.exif-data` gets no wrapper.
		 * The class token is bounded by WHITESPACE-OR-QUOTE, never by `\b`. `\b` is a
		 * word boundary and `-` is a non-word character, so `\bexif-data\b` also matches
		 * `class="my-exif-data"` and `class="exif-data-extra"` — unrelated tables would
		 * be wrapped and would inherit the user's EXIF box decoration.
		 * regex101 (heading+table): https://regex101.com/r/gQWpTy/1/unit-tests (PHP flavor).
		 */
		$table = '<table\b[^>]*class="(?:[^"]*\s)?exif-data(?:\s[^"]*)?"[^>]*>.*?<\/table>';

		/*
		 * NO `$limit`: every EXIF block is wrapped, not just the leftmost. NextGEN
		 * emits author-controlled, entity-decoded HTML EARLIER in the same container
		 * — the image alttext (`imagebrowser-exif.php:29`) and the description
		 * (`:40`), neither of which is stripped of structural tags by
		 * `I18N::ngg_decode_sanitized_html_content()`. With a limit of 1, a
		 * description containing `<h3>x</h3><table class="exif-data">` captures the
		 * wrapper and the REAL metadata block silently loses its styling hook.
		 * Wrapping all matches means the genuine block is always wrapped.
		 */
		$wrapped = preg_replace(
			'/(<h3\b[^>]*>(?:(?!<\/?h3\b).)*<\/h3>\s*)(' . $table . ')/is',
			'<div class="et-imagely-exif">$1$2</div>',
			$html,
			-1,
			$count
		);

		if ( null !== $wrapped && $count > 0 ) {
			return $wrapped;
		}

		/*
		 * Fallback for a customized template that drops the heading: wrap the table
		 * alone, so the element still resolves to exactly one node instead of going
		 * dead. Never leave the surface unwrapped — the `exifMetaData` selector is
		 * `.et-imagely-exif` and nothing else matches it.
		 *
		 * Covered by
		 * `ImagelyGalleryServiceTest::test_render_gallery_wraps_exif_table_when_heading_is_absent`,
		 * which asserts the wrapper holds exactly the table and reaches back for
		 * neither the image nor the Title heading.
		 * regex101 (table-only): https://regex101.com/r/v5OaLo/1/unit-tests (PHP flavor).
		 */
		$table_only = preg_replace(
			'/(' . $table . ')/is',
			'<div class="et-imagely-exif">$1</div>',
			$html
		);

		return null === $table_only ? $html : $table_only;
	}

	/**
	 * Whether the Imagely gallery mapper runtime is available.
	 *
	 * Use this single request-scoped gate instead of repeating
	 * `class_exists( '\\Imagely\\NGG\\DataMappers\\Gallery' )` at each call site.
	 *
	 * @since ??
	 *
	 * @return bool True when the gallery data mapper runtime can be used.
	 */
	public static function is_runtime_available(): bool {
		if ( null !== self::$_runtime_available ) {
			return self::$_runtime_available;
		}

		self::$_runtime_available = class_exists( '\\Imagely\\NGG\\DataMappers\\Gallery' );

		return self::$_runtime_available;
	}

	/**
	 * Retrieve all Imagely galleries.
	 *
	 * @since ??
	 *
	 * @return array Array of gallery items with `id` and `title` keys.
	 */
	public static function get_galleries(): array {
		/*
		 * Guard against environments where the plugin bootstrap class exists but
		 * the namespaced runtime does not (legacy NextGEN versions, test aliases).
		 */
		if ( ! self::is_runtime_available() ) {
			return [];
		}

		$entities = self::_get_gallery_entities();
		$result   = [];

		if ( ! is_array( $entities ) ) {
			return $result;
		}

		foreach ( $entities as $gallery ) {
			$result[] = [
				'id'    => (int) $gallery->gid,
				'title' => (string) $gallery->title,
			];
		}

		return $result;
	}

	/**
	 * Read gallery entities for the current operation without deriving settings.
	 *
	 * The mutable Visual Builder asset window only needs IDs. Keeping that path
	 * separate avoids loading four display-type configurations merely to enqueue
	 * plugin-owned assets.
	 *
	 * @return array Gallery mapper entities.
	 */
	private static function _get_gallery_entities(): array {
		if ( ! self::is_runtime_available() ) {
			return [];
		}

		if ( null !== self::$_gallery_entities ) {
			return self::$_gallery_entities;
		}

		$entities                = \Imagely\NGG\DataMappers\Gallery::get_instance()->find_all();
		self::$_gallery_entities = is_array( $entities ) ? $entities : [];

		return self::$_gallery_entities;
	}

	/**
	 * Read global display type settings and controller defaults once per request.
	 *
	 * @return array
	 */
	private static function _get_display_type_configuration(): array {
		if ( ! class_exists( '\\Imagely\\NGG\\DataMappers\\DisplayType' ) ) {
			return [];
		}

		$mapper = \Imagely\NGG\DataMappers\DisplayType::get_instance();
		$result = [];

		foreach ( [ self::DISPLAY_TYPE_SLIDESHOW ] as $display_type ) {
			$entity = $mapper->find_by_name( $display_type );

			if ( ! $entity ) {
				continue;
			}

			$defaults = [];

			if ( self::_has_controller_factory() && \Imagely\NGG\DisplayType\ControllerFactory::has_controller( $display_type ) ) {
				$controller = \Imagely\NGG\DisplayType\ControllerFactory::get_controller( $display_type );

				if ( method_exists( $controller, 'get_default_settings' ) ) {
					$defaults = $controller->get_default_settings();
				}
			}

			$result[ $display_type ] = [
				'settings' => $entity->settings ?? null,
				'defaults' => $defaults,
			];
		}

		return $result;
	}

	/**
	 * Canonicalize public NextGEN aliases when its controller registry is loaded.
	 *
	 * @param string $display_type Display type ID or alias.
	 *
	 * @return string
	 */
	private static function _canonicalize_display_type( string $display_type ): string {
		$display_type = trim( $display_type );

		if (
			'' !== $display_type
			&& self::_has_controller_factory()
			&& \Imagely\NGG\DisplayType\ControllerFactory::has_controller( $display_type )
		) {
			return (string) \Imagely\NGG\DisplayType\ControllerFactory::get_display_type_id( $display_type );
		}

		return $display_type;
	}

	/**
	 * Resolve the request-constant NextGEN controller-factory capability once.
	 *
	 * @return bool
	 */
	private static function _has_controller_factory(): bool {
		static $has_controller_factory = null;

		if ( null === $has_controller_factory ) {
			$has_controller_factory = class_exists( '\\Imagely\\NGG\\DisplayType\\ControllerFactory' );
		}

		return $has_controller_factory;
	}

	/**
	 * Resolve global settings plus explicit per-gallery overrides exactly as NextGEN does.
	 *
	 * @param array $stored        Stored per-gallery settings.
	 * @param array $configuration Global settings and controller defaults.
	 *
	 * @return array|null
	 */
	private static function _resolve_effective_settings( array $stored, array $configuration ): ?array {
		$global   = self::_settings_to_array( $configuration['settings'] ?? null );
		$defaults = self::_settings_to_array( $configuration['defaults'] ?? [] );

		if ( null === $global || null === $defaults ) {
			return null;
		}

		foreach ( $stored as $key => $value ) {
			if ( ! array_key_exists( $key, $defaults ) || (string) $defaults[ $key ] !== (string) $value ) {
				$global[ $key ] = $value;
			}
		}

		return $global;
	}

	/**
	 * Convert plugin setting containers without accepting scalar malformed data.
	 *
	 * @param mixed $settings Settings container.
	 *
	 * @return array|null
	 */
	private static function _settings_to_array( $settings ): ?array {
		if ( is_array( $settings ) ) {
			return $settings;
		}

		return is_object( $settings ) ? get_object_vars( $settings ) : null;
	}

	/**
	 * Resolve a slideshow gallery's Slick options for the Visual Builder preview.
	 *
	 * On the published page NextGEN boots the carousel from the DisplayedGallery's
	 * effective settings (`static/Slideshow/ngg_basic_slideshow.js`). The builder
	 * preview initializes Slick itself, so it must mirror NextGEN's display-type
	 * alias canonicalization and global/default/per-gallery merge before mapping
	 * the options with the same casts as the plugin's boot script.
	 *
	 * Returns `null` for non-slideshow galleries (or when the runtime/gallery is
	 * unavailable), so the client keeps its default options for those cases.
	 *
	 * @since ??
	 *
	 * @param int $gallery_id The Imagely gallery ID.
	 *
	 * @return array|null Slick options map, or null when not an available slideshow gallery.
	 */
	public static function get_slideshow_slick_options( int $gallery_id ): ?array {
		if ( $gallery_id <= 0 || ! self::is_runtime_available() ) {
			return null;
		}

		$mapper  = \Imagely\NGG\DataMappers\Gallery::get_instance();
		$gallery = $mapper->find( $gallery_id );

		if ( ! $gallery ) {
			return null;
		}

		return self::_get_slideshow_slick_options_from_gallery(
			$gallery,
			self::_get_display_type_configuration()
		);
	}

	/**
	 * Resolve Slick options from a gallery entity and display-type configuration.
	 *
	 * Kept separate from mapper access so alias and effective-setting parity can
	 * be tested deterministically without mutating NextGEN's gallery database.
	 *
	 * @param object $gallery       NextGEN gallery entity.
	 * @param array  $configuration Display-type global settings and defaults.
	 *
	 * @return array|null Slick options map, or null when the input is unsupported.
	 */
	private static function _get_slideshow_slick_options_from_gallery( object $gallery, array $configuration ): ?array {

		$stored_display_type = (string) ( $gallery->display_type ?? '' );
		$display_type        = self::_canonicalize_display_type( $stored_display_type );

		if ( self::DISPLAY_TYPE_SLIDESHOW !== $display_type ) {
			return null;
		}

		$all_stored_settings = self::_settings_to_array( $gallery->display_type_settings ?? [] );

		if ( null === $all_stored_settings || ! isset( $configuration[ self::DISPLAY_TYPE_SLIDESHOW ] ) ) {
			return null;
		}

		$stored_settings = self::_settings_to_array( $all_stored_settings[ $stored_display_type ] ?? [] );

		if ( null === $stored_settings ) {
			return null;
		}

		$settings = self::_resolve_effective_settings(
			$stored_settings,
			$configuration[ self::DISPLAY_TYPE_SLIDESHOW ]
		);

		if ( null === $settings ) {
			return null;
		}

		$required_settings = [
			'autoplay',
			'arrows',
			'interval',
			'pauseonhover',
			'transition_speed',
			'transition_style',
		];

		foreach ( $required_settings as $setting_name ) {
			if ( ! array_key_exists( $setting_name, $settings ) || ! is_scalar( $settings[ $setting_name ] ) ) {
				return null;
			}
		}

		foreach ( [ 'autoplay', 'arrows', 'pauseonhover' ] as $flag_setting ) {
			if ( ! is_bool( $settings[ $flag_setting ] ) && ! is_numeric( $settings[ $flag_setting ] ) ) {
				return null;
			}
		}

		if ( ! is_numeric( $settings['interval'] ) || ! is_numeric( $settings['transition_speed'] ) ) {
			return null;
		}

		/*
		 * Mirror NextGEN's own slideshow boot (`static/Slideshow/ngg_basic_slideshow.js`):
		 * boolean/numeric flags are truthiness-cast, `transition_style` maps to `fade`, and
		 * interval/speed are numeric. Required values come from NextGEN's effective
		 * global settings; missing/malformed configuration returns null above instead
		 * of inventing a preview-only fallback.
		 */
		return [
			'autoplay'      => (bool) (int) $settings['autoplay'],
			'arrows'        => (bool) (int) $settings['arrows'],
			'draggable'     => false,
			'dots'          => false,
			'fade'          => 'fade' === (string) $settings['transition_style'],
			'autoplaySpeed' => (int) $settings['interval'],
			'speed'         => (int) $settings['transition_speed'],
			'pauseOnHover'  => (bool) (int) $settings['pauseonhover'],
		];
	}

	/**
	 * Render an Imagely gallery by ID and return its HTML.
	 *
	 * Uses the `[imagely]` shortcode so the gallery is rendered through
	 * Imagely's own pipeline with its stored display settings. The output
	 * is wrapped in a `.et-imagely-content` container to provide a stable
	 * CSS anchor for Divi's design controls.
	 *
	 * @since ??
	 *
	 * @param int  $gallery_id The Imagely gallery ID.
	 * @param bool $preview Whether the render is for the Visual Builder preview endpoint.
	 *
	 * @return string Rendered gallery HTML wrapped in a content div, or empty string on failure.
	 */
	public static function render_gallery( int $gallery_id, bool $preview = false ): string {
		if ( $gallery_id <= 0 ) {
			return '';
		}

		/**
		 * Filters the raw Imagely gallery markup before Divi prepares and wraps it
		 * in the `.et-imagely-content` container.
		 *
		 * Return a non-null string to supply the markup directly and bypass the
		 * NextGEN runtime entirely. Tests use this to inject captured fixtures so the
		 * prepare pipeline can be exercised without the plugin, and it lets
		 * integrations short-circuit the shortcode render. Returns `null` by default,
		 * so production always renders through Imagely.
		 *
		 * @since ??
		 *
		 * @param string|null $markup     Raw gallery markup, or null to render via NextGEN.
		 * @param int         $gallery_id The Imagely gallery ID.
		 * @param bool        $preview    Whether this is a Visual Builder preview render.
		 */
		$output = apply_filters( 'divi_module_library_imagely_gallery_raw_markup', null, $gallery_id, $preview );

		if ( null === $output ) {
			if ( ! self::is_runtime_available() ) {
				return '';
			}

			// Verify the gallery exists before rendering.
			$mapper  = \Imagely\NGG\DataMappers\Gallery::get_instance();
			$gallery = $mapper->find( $gallery_id );

			if ( ! $gallery ) {
				return '';
			}

			// Build a simple shortcode — display settings come from the gallery's own config.
			$shortcode_attrs = sprintf( 'id="%d"', absint( $gallery_id ) );
			$shortcode       = sprintf( '[imagely %s]', $shortcode_attrs );

			$restore_route_state = null;

			if ( $preview ) {
				$restore_route_state = self::_normalize_preview_route_state();
			}

			try {
				/*
				 * We cannot use do_shortcode() here because Imagely's Shortcode Manager
				 * replaces shortcode output with placeholders when running inside the
				 * `the_content` filter (which is where D5's block rendering pipeline runs).
				 * The placeholders are substituted back at PHP_INT_MAX priority, but our
				 * module output is already rendered by then and never goes through that
				 * late substitution pass. Instead, we call render_shortcode() directly on
				 * the Shortcodes manager to bypass the placeholder system entirely.
				 */
				if ( class_exists( '\\Imagely\\NGG\\Display\\Shortcodes' ) ) {
					$manager = \Imagely\NGG\Display\Shortcodes::get_instance();
					$params  = shortcode_parse_atts( $shortcode_attrs );

					/*
					 * NextGEN's `Shortcodes::render_shortcode()` returns the literal
					 * string 'Invalid shortcode' when the requested alias is not
					 * registered (see the plugin's src/Display/Shortcodes.php); the
					 * `imagely` alias is registered in src/Display/DisplayManager.php.
					 * Pre-check the manager's public shortcode registry so an
					 * unregistered alias never renders the sentinel — this is more
					 * robust across plugin versions than string-matching the sentinel,
					 * which the final guard below still catches as a defensive backstop.
					 */
					$registered_shortcodes = method_exists( $manager, 'get_shortcodes' ) ? (array) $manager->get_shortcodes() : [];
					$output                = isset( $registered_shortcodes['imagely'] )
						? $manager->render_shortcode( 'imagely', $params, '' )
						: '';
				} else {
					// Fallback if Shortcodes class is unavailable.
					$output = do_shortcode( $shortcode );
				}
			} finally {
				/*
				 * Restore route state even if the render throws, so a fatal inside
				 * NextGEN can't leak the mutated `$_SERVER['QUERY_STRING']` / router
				 * request URI into the rest of the request.
				 */
				if ( is_callable( $restore_route_state ) ) {
					$restore_route_state();
				}
			}

			/*
			 * If the handler was not registered, do_shortcode() returns the raw
			 * shortcode string unchanged.
			 */
			if ( $output === $shortcode ) {
				return '';
			}
		}

		/*
		 * Bail on an empty render or the NextGEN 'Invalid shortcode' sentinel —
		 * whichever path (runtime render or the injection filter) produced
		 * `$output`. This is the defensive backstop for the alias pre-check above
		 * so the literal sentinel can never be prepared and printed as visible
		 * page text.
		 */
		if ( empty( $output ) || 'Invalid shortcode' === $output ) {
			return '';
		}

		/*
		 * Owned display transforms only (see `prepare_gallery_markup_for_divi()`).
		 * No FE `wp_kses` — Divi is not a security boundary around NextGEN HTML
		 * (Contact Form 7 precedent). Strip runs before balance so script
		 * bodies cannot inject orphan closes; balance repairs ImageBrowser's
		 * unbalanced `</div>`s (Rule 7.7: every ImageBrowser default-view;
		 * display; Imagely silent upstream).
		 */
		$prepared = self::prepare_gallery_markup_for_divi( (string) $output );

		if ( '' === $prepared ) {
			return '';
		}

		/*
		 * Intentionally unescaped (Contact Form 7 shape): NextGEN owns the HTML;
		 * Divi only applied display transforms above. `et_core_esc_wp` would falsely
		 * claim `wp_kses` provenance we no longer perform.
		 */
		return '<div class="et-imagely-content">' . et_core_intentionally_unescaped( $prepared, 'html' ) . '</div>';
	}

	/**
	 * Normalize Imagely route state before rendering a VB preview.
	 *
	 * NextGEN's thumbnail controller switches to ImageBrowser when route
	 * parameters such as `pid`, `image`, or `show` are present. The Visual
	 * Builder preview endpoint should render the gallery's base display, so
	 * clear those route parameters during preview rendering and restore them
	 * afterwards.
	 *
	 * @since ??
	 *
	 * @return callable|null Route state restore callback, or null when routing is unavailable.
	 */
	private static function _normalize_preview_route_state(): ?callable {
		if ( ! class_exists( '\\Imagely\\NGG\\Util\\Router' ) ) {
			return null;
		}

		$router = \Imagely\NGG\Util\Router::get_instance();
		$app    = $router->get_routed_app();

		if ( ! $app || ! method_exists( $app, 'get_app_request_uri' ) || ! method_exists( $app, 'set_app_request_uri' ) || ! method_exists( $app, 'remove_parameter' ) ) {
			return null;
		}

		$request_uri = $app->get_app_request_uri();

		/*
		 * Capture the RAW `$_SERVER['QUERY_STRING']` before mutation so it can be
		 * restored byte-for-byte, including restoring the key's absence when it
		 * was never set. `remove_parameter()` writes a modified value straight to
		 * the superglobal via `Router::set_querystring()`, and the matching
		 * `Router::get_querystring()` returns a SANITIZED copy (see the plugin's
		 * src/Util/Router.php) — round-tripping through the router would silently
		 * rewrite the superglobal, so we snapshot and restore it directly.
		 */
		$had_query_string      = isset( $_SERVER['QUERY_STRING'] );
		$original_query_string = $had_query_string
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized -- Snapshot of the raw value restored verbatim below; sanitizing or unslashing here would corrupt the value on restore.
			? $_SERVER['QUERY_STRING']
			: null;

		foreach ( [ 'pid', 'image', 'show', 'nggpage' ] as $parameter ) {
			$app->remove_parameter( $parameter );
		}

		return static function () use ( $app, $request_uri, $had_query_string, $original_query_string ): void {
			$app->set_app_request_uri( $request_uri );

			if ( $had_query_string ) {
				$_SERVER['QUERY_STRING'] = $original_query_string;
			} else {
				unset( $_SERVER['QUERY_STRING'] );
			}
		};
	}
}
