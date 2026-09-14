<?php
/**
 * Module Library: Imagely Gallery Module.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\ImagelyGallery;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP uses snakeCase in \WP_Block_Parser_Block.

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * Imagely Gallery module class.
 *
 * Conditionally registers the Divi Imagely Gallery module when the Imagely
 * (formerly NextGEN Gallery) plugin is active. Handles module registration
 * and frontend rendering.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class ImagelyGalleryModule implements DependencyInterface {
	/**
	 * Module block name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const MODULE_BLOCK_NAME = 'divi/imagely-gallery';

	/**
	 * Generate classnames for the module wrapper element.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type object $classnamesInstance Module classnames instance.
	 *     @type array  $attrs              Block attributes data for rendering.
	 * }
	 *
	 * @return void
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => $attrs['module']['decoration'] ?? [],
				]
			),
			true
		);

		/*
		 * NextGEN thumbnail grids float their items, so the module wrapper needs a
		 * clearfix to avoid collapsing. Mirrors `clearfix` on the VB ModuleContainer
		 * in `edit.tsx`. See ContactFormModule::module_classnames() for precedent.
		 */
		$classnames_instance->add( 'clearfix', true );
	}

	/**
	 * Retrieve custom CSS fields from the block type registry.
	 *
	 * @since ??
	 *
	 * @return array Custom CSS fields for this module.
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( self::MODULE_BLOCK_NAME )->customCssFields;
	}

	/**
	 * Module preset attrs map normalization.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $attrs_map   Preset attrs map.
	 * @param string               $module_name Module name.
	 *
	 * @return array<string, mixed>
	 */
	public static function preset_attrs_map( array $attrs_map, string $module_name ): array {
		if ( self::MODULE_BLOCK_NAME !== $module_name ) {
			return $attrs_map;
		}

		$nested_button_key_prefix  = 'navigationArrows.decoration.button.decoration.';
		$nested_button_attr_prefix = 'navigationArrows.decoration.button.decoration.';
		$nested_groups_to_keep     = [
			'background__',
			'border__',
			'boxShadow__',
			'sizing__',
			'spacing__',
		];

		foreach ( array_keys( $attrs_map ) as $key ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			$is_nested_button_entry = str_starts_with( $key, $nested_button_key_prefix );
			$is_phantom_inner_entry = str_starts_with( $key, 'navigationArrows.decoration.button.innerContent__' );

			if ( $is_phantom_inner_entry ) {
				unset( $attrs_map[ $key ] );
				continue;
			}

			if ( ! $is_nested_button_entry ) {
				continue;
			}

			$nested_suffix   = substr( $key, strlen( $nested_button_key_prefix ) );
			$should_flatten  = false;
			$normalized_key  = "navigationArrows.decoration.{$nested_suffix}";
			$normalized_item = $attrs_map[ $key ] ?? null;

			foreach ( $nested_groups_to_keep as $nested_group_prefix ) {
				if ( str_starts_with( $nested_suffix, $nested_group_prefix ) ) {
					$should_flatten = true;
					break;
				}
			}

			if ( $should_flatten && is_array( $normalized_item ) ) {
				if (
					isset( $normalized_item['attrName'] )
					&& is_string( $normalized_item['attrName'] )
					&& str_starts_with( $normalized_item['attrName'], $nested_button_attr_prefix )
				) {
					$normalized_item['attrName'] = 'navigationArrows.decoration.' . substr( $normalized_item['attrName'], strlen( $nested_button_attr_prefix ) );
				}

				/*
				 * Preserve an existing flat key when present so conversion remains
				 * idempotent and never replaces a valid stored path with data from a
				 * nested duplicate entry.
				 */
				if ( ! array_key_exists( $normalized_key, $attrs_map ) ) {
					$attrs_map[ $normalized_key ] = $normalized_item;
				}
			}

			/*
			 * Remove the nested path unconditionally so unsupported/hidden sub-groups
			 * are not emitted in the final map.
			 */
			unset( $attrs_map[ $key ] );
		}

		/*
		 * Keep PHP parity with the VB parser for keys that are not emitted by the
		 * current client-side metadata parser for this module.
		 */
		unset( $attrs_map['navigationArrows.decoration.sizing__alignSelf'] );
		unset( $attrs_map['navigationArrows.decoration.sizing__alignment'] );
		unset( $attrs_map['navigationArrows.decoration.sizing__gridAlignSelf'] );
		unset( $attrs_map['navigationArrows.decoration.sizing__gridJustifySelf'] );

		/*
		 * Keep PHP parity with the VB parser for a slideshow-link key that is not
		 * emitted by current client-side metadata parsing for this module.
		 */
		unset( $attrs_map['slideshowLink.decoration.font.font__textAlign'] );

		return $attrs_map;
	}

	/**
	 * Output CSS for the Imagely Gallery module sub-elements.
	 *
	 * Generates CSS for Imagely-rendered sub-elements through the
	 * `.et-imagely-content` wrapper.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Style arguments.
	 *
	 *     @type string         $id            Module ID.
	 *     @type string         $name          Module name.
	 *     @type array          $attrs         Module attributes.
	 *     @type string         $orderIndex    Order index.
	 *     @type string         $storeInstance Store instance.
	 *     @type ModuleElements $elements      Module elements helper.
	 *     @type array          $settings      Module settings.
	 * }
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];
		$settings = $args['settings'] ?? [];

		/*
		 * Navigation Arrows glyph selectors. The two glyph mechanisms are kept in
		 * SEPARATE selectors, not one list, because only one of them faces an upstream
		 * `!important` and `!important` is emitted per advancedStyles entry, not per
		 * selector — merging them would force the escalation onto both. Mirrors
		 * `slideshowArrowGlyphSelector` / `imageBrowserArrowGlyphSelector` in the VB
		 * `module-styles.tsx` so both renderers emit byte-identical CSS.
		 *
		 * Slideshow (Slick) glyphs — `::before` pseudo-elements. NextGEN ships
		 * `.ngg-slideshow .slick-next:before, .ngg-slideshow .slick-prev:before {
		 * color:#ccc !important; font-size:32px !important }` plus a `:hover:before`
		 * twin with `color:#aaa !important; font-size:32px !important`
		 * (`ngg_basic_slideshow.css:45-49` and `:51-55`). Importance outranks specificity
		 * unconditionally, so ONLY `!important` can answer those — both Size and Color
		 * must escalate here.
		 */
		$slideshow_arrow_glyph_selector = $args['orderClass'] . ' .et-imagely-content .ngg-slideshow .slick-prev:before, ' . $args['orderClass'] . ' .et-imagely-content .ngg-slideshow .slick-next:before';

		/*
		 * ImageBrowser arrow anchors. NO `!important` — and no escalation of any kind is
		 * warranted: `.ngg-browser-prev` / `.ngg-browser-next` carry ZERO CSS rules
		 * anywhere in the plugin (they exist only as markup in
		 * `templates/ImageBrowser/default-view.php:98,:106`,
		 * `templates/ImageBrowser/nextgen_basic_imagebrowser.php:78,:86` and the three
		 * `src/Legacy/view/imagebrowser*.php` views, and as a JS click hook in
		 * `static/GalleryDisplay/common.js:19`). Two plugin rules DO reach these anchors,
		 * both matching by element rather than class, and NEITHER declares `color` or
		 * `font-size` — the two properties emitted here — so there is nothing to
		 * out-rank. `.ngg-imagebrowser.default-view .ngg-imagebrowser-nav a{,:hover,:focus}`
		 * (`ImageBrowser/style.css:132-142`) declares only `box-shadow`,
		 * `text-decoration`, `padding`, `border`, `display`, `width` and `height`; and
		 * `.ngg-imagebrowser-nav .back a:hover, .ngg-imagebrowser-nav .next a:hover`
		 * (`src/Legacy/static/hovereffect.css:138-141`) declares only
		 * `text-decoration: none !important`, in a legacy stylesheet that no plugin code
		 * path enqueues. Shipping `!important` here would only block the user's own
		 * non-important Custom CSS from ever restyling the ImageBrowser chevrons.
		 *
		 * The anchor is targeted rather than an inner icon because NextGEN's ImageBrowser
		 * templates vary: the glyph is a FontAwesome 5 `<i class="fas fa-chevron-*">`
		 * GRANDCHILD in `default-view.php:101,:109`, and plain `◄`/`►` text entities in
		 * the basic (`nextgen_basic_imagebrowser.php:78-91`) and legacy views.
		 * `font-size`/`color` on the anchor apply to the text glyphs directly and reach
		 * the FontAwesome icon by INHERITANCE, so all templates are covered. Nothing
		 * upstream contests the inherited value on that icon: the only plugin rule
		 * declaring `font-size`/`color` on it,
		 * `.ngg-imagebrowser.default-view .ngg-imagebrowser-nav .fa { font-size: 16px;
		 * color: #fff; … }` (`ImageBrowser/style.css:117-122`), is dead against the `fas`
		 * markup (`.fa` matches neither `fas` nor `fa-chevron-*`), and the
		 * `.fa-chevron-left` / `.fa-chevron-right` rules that DO match (`:124-130`) set
		 * only `margin`.
		 */
		$image_browser_arrow_glyph_selector = $args['orderClass'] . ' .et-imagely-content .ngg-imagebrowser-nav .ngg-browser-prev, ' . $args['orderClass'] . ' .et-imagely-content .ngg-imagebrowser-nav .ngg-browser-next';

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'disabledOn' => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
							],
						]
					),

					// Title text (h3).
					$elements->style(
						[
							'attrName' => 'title',
						]
					),

					// Gallery image. Its constant sizing importance policy lives in metadata.
					$elements->style(
						[
							'attrName' => 'image',
						]
					),

					// Counter text.
					$elements->style(
						[
							'attrName' => 'counter',
						]
					),

					// Description/caption text.
					$elements->style(
						[
							'attrName' => 'description',
						]
					),

					// Tag Cloud links.
					$elements->style(
						[
							'attrName' => 'tagCloud',
						]
					),

					// Pagination.
					$elements->style(
						[
							'attrName' => 'pagination',
						]
					),

					// View Link ("View Thumbnails" / "View Slideshow" view-switch link).
					$elements->style(
						[
							'attrName' => 'slideshowLink',
						]
					),

					/*
					 * EXIF Meta Data — the legacy imagebrowser-exif heading + `.exif-data`
					 * table, wrapped as ONE node in `.et-imagely-exif` by
					 * `ImagelyGalleryService::_wrap_exif_meta_data()` so box decorations
					 * paint once instead of once per node. The font selector still names
					 * the `h3` heading and the `th`/`td` cells, each of which carries a direct
					 * rule that an inherited value cannot beat.
					 * The element's only explicit group is `font`; its box groups come
					 * from the framework composables and reuse the element selector —
					 * which is why that selector is a single wrapper node. No
					 * `!important` is owed: NextGEN ships no font declarations here.
					 */
					$elements->style(
						[
							'attrName' => 'exifMetaData',
						]
					),

					/*
					 * Navigation Arrows glyphs. Arrow Size + Color target the GLYPHS
					 * (not the button box, auto-emitted by the native `button` element on
					 * the element selector) — the anchor/`::before` owns the chevron;
					 * `.back`/`.next` is the button SHELL and stays the element selector's
					 * job. Each of the two glyph mechanisms gets its OWN pair of
					 * advancedStyles entries so the `!important` escalation is scoped to the
					 * half that actually needs it: the Slick `::before` pair answers
					 * NextGEN's own `!important` declarations, while the ImageBrowser
					 * anchors — which no plugin rule targets at all — stay plain so user
					 * Custom CSS can still reach them. See the two selector notes above for
					 * the per-mechanism evidence. Mirrors the VB `module-styles.tsx`
					 * emission so the published page matches the builder.
					 */
					$elements->style(
						[
							'attrName'   => 'navigationArrows',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector'  => $slideshow_arrow_glyph_selector,
											'attr'      => $attrs['navigationArrows']['advanced']['size'] ?? null,
											'property'  => 'font-size',
											'important' => true,
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector'  => $slideshow_arrow_glyph_selector,
											'attr'      => $attrs['navigationArrows']['advanced']['color'] ?? null,
											'property'  => 'color',
											'important' => true,
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector'  => $image_browser_arrow_glyph_selector,
											'attr'      => $attrs['navigationArrows']['advanced']['size'] ?? null,
											'property'  => 'font-size',
											'important' => false,
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector'  => $image_browser_arrow_glyph_selector,
											'attr'      => $attrs['navigationArrows']['advanced']['color'] ?? null,
											'property'  => 'color',
											'important' => false,
										],
									],
								],
							],
						]
					),

					// Custom CSS fields.
					CssStyle::style(
						[
							'selector'  => $args['orderClass'] . '.et_pb_imagely_gallery',
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}

	/**
	 * Imagely Gallery module script data.
	 *
	 * @since ??
	 *
	 * @param array $args Script data args.
	 *
	 * @return void
	 */
	public static function module_script_data( array $args ): void {
		$elements = $args['elements'];

		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'imagelyGallery',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'title',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'image',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'counter',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'description',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'tagCloud',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'pagination',
			]
		);

		// View Link ("View Thumbnails" / "View Slideshow" view-switch link) — font-only element.
		$elements->script_data(
			[
				'attrName' => 'slideshowLink',
			]
		);

		// EXIF Meta Data (legacy imagebrowser-exif template) — font-only element.
		$elements->script_data(
			[
				'attrName' => 'exifMetaData',
			]
		);

		/*
		 * Native `elementType: 'button'` element; registered on both renderers
		 * for parity.
		 */
		$elements->script_data(
			[
				'attrName' => 'navigationArrows',
			]
		);
	}

	/**
	 * Render the Imagely Gallery module on the frontend.
	 *
	 * Retrieves the `galleryId` attribute, renders the Imagely gallery HTML
	 * via `ImagelyGalleryService`, and wraps it in the Divi module container.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                 Block attributes.
	 * @param string         $child_modules_content Rendered inner blocks (nested modules).
	 * @param WP_Block       $block                 The WP_Block instance.
	 * @param ModuleElements $elements              Module elements helper.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string Rendered module HTML.
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs = [] ): string {
		$children_ids    = ChildrenUtils::extract_children_ids( $block );
		$gallery_id      = (int) ( $attrs['imagelyGallery']['innerContent']['desktop']['value']['galleryId'] ?? 0 );
		$gallery_html    = ImagelyGalleryService::render_gallery( $gallery_id );
		$gallery_content = '';

		if ( '' !== $gallery_html ) {
			$gallery_content = HTMLUtility::render(
				[
					'tag'               => 'div',
					'tagEscaped'        => true,
					'attributes'        => [
						'class' => 'et_pb_module_inner',
					],
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $gallery_html,
				]
			);
		}

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		return Module::render(
			[
				// FE only.
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'                    => $attrs,
				'elements'                 => $elements,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'id'                       => $block->parsed_block['id'],
				'name'                     => $block->block_type->name,
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'moduleCategory'           => $block->block_type->category,
				'parentAttrs'              => $parent->attrs ?? [],
				'parentId'                 => $parent->id ?? '',
				'parentName'               => $parent->blockName ?? '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP uses snakeCase in \WP_Block_Parser_Block
				'childrenIds'              => $children_ids,
				'children'                 => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $gallery_content
				. $child_modules_content,
			]
		);
	}



	/**
	 * Load the Imagely Gallery module.
	 *
	 * Registers module-scoped preset attrs map normalization unconditionally so
	 * conversion can normalize stored data even when NextGEN is inactive. Full
	 * module registration still bails when the plugin/runtime is unavailable, or
	 * when the request targets Imagely's own `ngg-preview` endpoint (to prevent
	 * memory exhaustion caused by running the full Divi block-rendering
	 * pipeline alongside Imagely's gallery rendering in a single PHP process).
	 *
	 * Settings-data injection lives in `SettingsDataCallbacks::imagely_gallery()`
	 * under the top-level `imagelyGallery` settings key.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		add_filter( 'divi_conversion_presets_attrs_map', [ self::class, 'preset_attrs_map' ], 10, 2 );

		if ( ! class_exists( 'C_NextGEN_Bootstrap' ) || ! ImagelyGalleryService::is_runtime_available() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context detection; nonce is verified by Imagely's preview handler.
		if ( isset( $_GET['ngg-preview'] ) ) {
			return;
		}

		// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctionParameters.dirname_levelsFound -- PHP 7 support confirmed.
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/imagely-gallery/';

		/*
		 * Ensure that all filters and actions applied during module registration are registered before calling `ModuleRegistration::register_module()`.
		 * However, for consistency, register all module-specific filters and actions prior to invoking `ModuleRegistration::register_module()`.
		 */
		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
