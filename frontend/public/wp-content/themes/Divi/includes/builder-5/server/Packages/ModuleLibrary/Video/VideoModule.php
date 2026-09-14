<?php
/**
 * ModuleLibrary: Video Module class.
 *
 * @package Builder\Packages\ModuleLibrary\VideoModule
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Video;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElementsUtils;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleLibrary\Video\VideoUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use WP_Block;
use ET\Builder\FrontEnd\Module\ScriptData;
use ET\Builder\FrontEnd\Assets\CriticalCSS;

use ET_Builder_Post_Features;

/**
 * `VideoModule` is consisted of functions used for Video Module such as Front-End rendering, REST API Endpoints etc.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class VideoModule implements DependencyInterface {

	/**
	 * Check whether thumbnail value has a usable source.
	 *
	 * @param mixed $value Thumbnail attribute value.
	 *
	 * @return bool
	 */
	private static function has_thumbnail_src( $value ): bool {
		return '' !== ModuleElementsUtils::resolve_image_field_url_string( $value );
	}

	/**
	 * Resolve overlay visibility from a thumbnail value.
	 *
	 * @param mixed $value Thumbnail attribute value.
	 *
	 * @return string
	 */
	private static function resolve_overlay_visibility( $value ): string {
		return self::has_thumbnail_src( $value ) ? 'visible' : 'hidden';
	}

	/**
	 * Module classnames function for Video module.
	 *
	 * This function is equivalent of JS function moduleClassnames located in
	 * visual-builder/packages/module-library/src/components/video/module-classnames.ts.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type object $classnamesInstance Instance of ET\Builder\Packages\Module\Layout\Components\Classnames.
	 *     @type array  $attrs              Block attributes data that being rendered.
	 * }
	 */
	public static function module_classnames( $args ) {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => $attrs['module']['decoration'] ?? [],
				]
			)
		);

		if ( is_customize_preview() || is_et_pb_preview() ) {
			$classnames_instance->add( 'et_pb_in_customizer' );
		}
	}

	/**
	 * Set module script data of Video Module options.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *   Array of arguments.
	 *
	 *   @type string         $id            Module id.
	 *   @type string         $name          Module name.
	 *   @type string         $selector      Module selector.
	 *   @type array          $attrs         Module attributes.
	 *   @type int            $storeInstance The ID of instance where this block stored in BlockParserStore class.
	 *   @type ModuleElements $elements      ModuleElements instance.
	 * }
	 */
	public static function module_script_data( $args ) {
		// Assign variables.
		$id                            = $args['id'] ?? '';
		$name                          = $args['name'] ?? '';
		$selector                      = $args['selector'] ?? '';
		$attrs                         = $args['attrs'] ?? [];
		$elements                      = $args['elements'];
		$store_instance                = $args['storeInstance'] ?? null;
		$thumbnail_attrs               = $attrs['thumbnail']['innerContent'] ?? [];
		$overlay_background_image_data = MultiViewUtils::merge_values(
			[
				'video'     => $attrs['video']['innerContent'] ?? [],
				'thumbnail' => $thumbnail_attrs,
			]
		);

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);

		// Register script data for lazy loading if video is below the fold.
		$has_video = ! empty( $attrs['video']['innerContent']['desktop']['value']['src'] ?? '' ) ||
			! empty( $attrs['video']['innerContent']['desktop']['value']['webm'] ?? '' );
		if ( $has_video && CriticalCSS::should_generate_critical_css() && CriticalCSS::is_current_module_below_fold_for_lazy_load() ) {
			ScriptData::add_data_item(
				[
					'data_name'    => 'video_lazy_load',
					'data_item_id' => $id,
					'data_item'    => [
						'selector' => $selector,
					],
				]
			);
		}

		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'hoverSelector' => $selector,
				'setContent'    => [
					[
						'selector'      => $selector . ' .et_pb_video_box',
						'data'          => $attrs['video']['innerContent'] ?? [],
						'valueResolver' => function ( $value ) {
							// Get Video Urls.
							$video_mp4_url  = $value['src'] ?? '';
							$video_webm_url = $value['webm'] ?? '';

							return VideoModule::get_video_html( $video_mp4_url, $video_webm_url );
						},
						'sanitizer'     => 'et_core_esc_previously',
					],
				],
				'setStyle'      => [
					[
						'selector'      => $selector . ' .et_pb_video_overlay',
						'data'          => [
							'background-image' => $overlay_background_image_data,
						],
						'valueResolver' => function ( $value ) {
							$video               = $value['video'] ?? [];
							$thumbnail           = $value['thumbnail'] ?? [];
							$video_mp4_url       = $video['src'] ?? '';
							$video_thumbnail_url = ModuleElementsUtils::resolve_image_field_url_string( $thumbnail );
							$video_cover_url     = VideoModule::get_video_overlay_cover_image_url( $video_mp4_url, $video_thumbnail_url );

							return 'url(' . ( $video_cover_url ) . ')';
						},
						'sanitizer'     => 'et_core_esc_previously',
					],
				],
				'setVisibility' => [
					[
						'selector'      => $selector . ' .et_pb_video_overlay',
						'data'          => $thumbnail_attrs,
						'valueResolver' => function ( $value ) {
							return VideoModule::resolve_overlay_visibility( $value );
						},
					],
				],
			]
		);
	}

	/**
	 * Get Video HTML Trait.
	 *
	 * @since ??
	 *
	 * @param string|null $video_mp4_url  Video Mp4 URL.
	 * @param string|null $video_webm_url Video Webm url.
	 *
	 * @return string Video HTML.
	 */
	public static function get_video_html( $video_mp4_url, $video_webm_url ): string {
		static $cached = [];

		$cache_key = md5( $video_mp4_url . $video_webm_url );

		if ( isset( $cached[ $cache_key ] ) ) {
			return $cached[ $cache_key ];
		}

		// Get the instance of ET_Builder_Post_Features.
		$post_features = ET_Builder_Post_Features::instance();

		// Generate Video HTML.
		$video_params = [
			'src'      => esc_url( $video_mp4_url ),
			'src_webm' => esc_url( $video_webm_url ),
		];

		$generate_video_html = function () use ( $video_params ) {
			// Generate Video HTML.
			if ( false !== et_pb_check_oembed_provider( $video_params['src'] ) ) {
				$video = et_builder_get_oembed( $video_params['src'] );

				if ( empty( $video ) && false !== VideoHTMLController::validate_youtube_url( $video_params['src'] ) ) {
					$video = VideoHTMLController::get_youtube_fallback_embed_html( $video_params['src'] );
				}
			} elseif ( false !== VideoHTMLController::validate_youtube_url( $video_params['src'] ) ) {
				$video = et_builder_get_oembed( VideoHTMLController::normalize_youtube_url( $video_params['src'] ) );

				if ( empty( $video ) ) {
					$video = VideoHTMLController::get_youtube_fallback_embed_html( $video_params['src'] );
				}
			} else {
				$video = HTMLUtility::render(
					[
						'tag'               => 'video',
						'attributes'        => [
							'controls' => true,
						],
						'childrenSanitizer' => 'et_core_esc_previously',
						'children'          => [
							'' !== $video_params['src'] ? HTMLUtility::render(
								[
									'tag'               => 'source',
									'attributes'        => [
										'type' => 'video/mp4',
										'src'  => $video_params['src'],
									],
									'childrenSanitizer' => 'et_core_esc_previously',
								]
							) : '',
							'' !== $video_params['src_webm'] ? HTMLUtility::render(
								[
									'tag'               => 'source',
									'attributes'        => [
										'type' => 'video/webm',
										'src'  => $video_params['src_webm'],
									],
									'childrenSanitizer' => 'et_core_esc_previously',
								]
							) : '',
						],
					]
				);
			}

			return $video;
		};

		// Get the attachment ID from the cache.
		$video = $post_features->get(
			// Cache key.
			$cache_key,
			// Callback function if the cache key is not found.
			$generate_video_html,
			// Cache group.
			'video_html'
		);

		// ET_Builder_Post_Feature_Base::get() treats a missing key on an already-primed cache as
		// falsey without re-running the callback. Paginated Loop Builder pages introduce new video
		// URLs that were not present when page 1 primed `_et_builder_post_features_cache`, so
		// recompute and persist those entries (#50701).
		if ( false === $video ) {
			$before = microtime( true );
			$video  = $generate_video_html();

			// Only persist string HTML; oEmbed can return false/non-string on failure (#50701).
			if ( is_string( $video ) ) {
				$post_features->cache_set( $cache_key, $video, 'video_html', microtime( true ) - $before );
			}
		}

		if ( ! is_string( $video ) ) {
			$video = '';
		}

		if ( ! empty( $video ) ) {
			// Include MediaElement JS and CSS if any element with <video> is there.
			wp_enqueue_style( 'wp-mediaelement' );
			wp_enqueue_script( 'wp-mediaelement' );
		}

		$cached[ $cache_key ] = $video;

		return $video;
	}

	/**
	 * Get Video Overlay Cover Image Trait.
	 *
	 * @since ??
	 *
	 * @param string|null $video_url           Video URL.
	 * @param string|null $video_thumbnail_url Video cover url.
	 *
	 * @return string Video Cover URL.
	 */
	public static function get_video_overlay_cover_image_url( $video_url, $video_thumbnail_url ): string {
		static $cached = [];

		$cache_key = md5( $video_url . $video_thumbnail_url );

		if ( isset( $cached[ $cache_key ] ) ) {
			return $cached[ $cache_key ];
		}

		$video_cover_url = $video_thumbnail_url;

		// If thumbnail url found, try to get high resolution image.
		if ( ! empty( $video_thumbnail_url ) ) {
			$video_cover_data = VideoThumbnailController::get_video_thumbnail(
				[
					'image_src' => esc_url( $video_thumbnail_url ),
					'src'       => esc_url( $video_url ),
				]
			);

			$cover_from_media = isset( $video_cover_data['cover'] ) && is_string( $video_cover_data['cover'] )
				? $video_cover_data['cover']
				: '';

			// Prefer high-res cover when VideoThumbnailController returns a non-empty string (#49908).
			if ( '' !== $cover_from_media ) {
				$video_cover_url = $cover_from_media;
			}
		}

		if ( ! is_string( $video_cover_url ) ) {
			$video_cover_url = is_string( $video_thumbnail_url ) ? $video_thumbnail_url : '';
		}

		$cached[ $cache_key ] = $video_cover_url;

		return $video_cover_url;
	}

	/**
	 * Video module render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * This function is equivalent of JS function VideoEdit located in
	 * visual-builder/packages/module-library/src/components/video/edit.tsx.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by VB.
	 * @param string         $content                     Block content.
	 * @param WP_Block       $block                       Parsed block object that being rendered.
	 * @param ModuleElements $elements                    ModuleElements instance.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string HTML rendered of Video module.
	 */
	public static function render_callback( $attrs, $content, $block, $elements, $default_printed_style_attrs ) {
		$children_ids         = ChildrenUtils::extract_children_ids( $block );
		$video_mp4_url        = $attrs['video']['innerContent']['desktop']['value']['src'] ?? '';
		$video_webm_url       = $attrs['video']['innerContent']['desktop']['value']['webm'] ?? '';
    $thumb_desktop_value  = $attrs['thumbnail']['innerContent']['desktop']['value'] ?? [];
		$video_thumbnail_url  = ModuleElementsUtils::resolve_image_field_url_string( $thumb_desktop_value );
		$thumbnail_attrs= $attrs['thumbnail']['innerContent'] ?? [];
		$has_video_thumbnail  = ModuleUtils::has_value(
			$thumbnail_attrs,
			[
				'valueResolver' => function ( $value ) {
					return VideoModule::has_thumbnail_src( $value );
				},
			]
		);

		// Generate Video HTML.
		$video_params = [
			'src'      => esc_url( $video_mp4_url ),
			'src_webm' => esc_url( $video_webm_url ),
		];
		$video_html   = self::get_video_html( $video_params['src'], $video_params['src_webm'] );

		// Defer video loading if module is below the fold.
		$video = VideoUtils::maybe_defer_video_loading( $video_html );

		// Video HTML.
		$video_html = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'et_pb_video_box',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => [
					$video,
				],
			]
		);

		// Video Overlay Image Html.
		$video_cover_url = self::get_video_overlay_cover_image_url( $video_mp4_url, $video_thumbnail_url );

		$video_overlay_classes = HTMLUtility::classnames(
			[
				'et_pb_video_overlay'    => true,
				'et_multi_view_hidden'   => '' === $video_thumbnail_url,
			]
		);

		$video_overlay_attrs = [
			'class' => $video_overlay_classes,
		];

		if ( '' !== $video_cover_url ) {
			$video_overlay_attrs['style'] = [
				'background-image' => "url({$video_cover_url})",
			];
		}

		// Generate Video Cover Image Html.
		$video_cover_image_html = $has_video_thumbnail
			? HTMLUtility::render(
				[
					'tag'               => 'div',
					'attributes'        => $video_overlay_attrs,
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => [
						HTMLUtility::render(
							[
								'tag'               => 'div',
								'attributes'        => [
									'class' => 'et_pb_video_overlay_hover',
								],
								'childrenSanitizer' => 'et_core_esc_previously',
								'children'          => [
									// Use elements->render for play icon to support custom attributes.
									$elements->render(
										[
											'attrName'   => 'playIcon',
											'tagName'    => 'a',
											'attributes' => [
												'class' => 'et_pb_video_play',
												'href'  => '#',
											],
											'skipAttrChildren' => true,
											'childrenSanitizer' => 'et_core_esc_previously',
										]
									),
								],
							]
						),
					],
				]
			)
			: '';

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		$res = Module::render(
			[
				// FE only.
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'id'                       => $block->parsed_block['id'],
				'name'                     => $block->block_type->name,
				'moduleCategory'           => $block->block_type->category,
				'attrs'                    => $attrs,
				'elements'                 => $elements,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'parentId'                 => $parent->id ?? '',
				'parentName'               => $parent->blockName ?? '', // @phpcs:ignore -- Snake case is valid in this case.
				'parentAttrs'              => $parent->attrs ?? [],
				'childrenIds'              => $children_ids,
				'children'                 => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $video_html . $video_cover_image_html . $content,
			]
		);

		return $res;
	}

	/**
	 * Custom CSS fields
	 *
	 * This function is equivalent of JS const cssFields located in
	 * visual-builder/packages/module-library/src/components/video/custom-css.ts.
	 *
	 * A minor difference with the JS const cssFields, this function did not have `label` property on each array item.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function custom_css() {
		return \WP_Block_Type_Registry::get_instance()->get_registered( 'divi/video' )->customCssFields;
	}

	/**
	 * Icon size style declaration.
	 *
	 * This function will declare icon size style for Video module.
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array      $attrValue  The value (breakpoint > state > value) of module attribute.
	 *     @type bool|array $important  If set to true, the CSS will be added with !important.
	 *     @type string     $returnType This is the type of value that the function will return. Can be either string or key_value_pair.
	 * }
	 *
	 * @since ??
	 */
	public static function icon_size_style_declaration( $params ) {

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		$icon_attrs = $params['attrValue'];
		$use_size   = $params['attr']['desktop']['value']['useSize'] ?? '';
		$size       = $icon_attrs['size'] ?? '';

		if ( 'on' === $use_size && ! empty( $size ) ) {
			// Handle parsed icon size numeric value.
			$icon_size       = SanitizerUtility::numeric_parse_value( $size );
			$icon_size_value = 0 - ( $icon_size['valueNumber'] ?? 0 );

			$style_declarations->add( 'margin-top', 0 !== $icon_size_value ? round( $icon_size_value / 2 ) . $icon_size['valueUnit'] : 0 );
			$style_declarations->add( 'margin-left', 0 !== $icon_size_value ? round( $icon_size_value / 2 ) . $icon_size['valueUnit'] : 0 );
		}

		return $style_declarations->value();
	}



	/**
	 * Video Module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * visual-builder/packages/module-library/src/components/video/module-styles.tsx.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *      @type string $id                Module ID. In VB, the ID of module is UUIDV4. In FE, the ID is order index.
	 *      @type string $name              Module name.
	 *      @type string $attrs             Module attributes.
	 *      @type string $parentAttrs       Parent attrs.
	 *      @type string $orderClass        Selector class name.
	 *      @type string $parentOrderClass  Parent selector class name.
	 *      @type string $wrapperOrderClass Wrapper selector class name.
	 *      @type string $settings          Custom settings.
	 *      @type string $state             Attributes state.
	 *      @type string $mode              Style mode.
	 *      @type int    $orderIndex        Module order index.
	 *      @type int    $storeInstance     The ID of instance where this block stored in BlockParserStore class.
	 *      @type ModuleElements $elements  ModuleElements instance.
	 * }
	 */
	public static function module_styles( $args ) {
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];
		$settings = $args['settings'] ?? [];

		// Defaulted printed style attributes.
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					// Module.
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'defaultPrintedStyleAttrs' => $default_printed_style_attrs['module']['decoration'] ?? [],
								'disabledOn'               => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
								'boxShadow'                => [
									'selectorFunction' => function ( $params ) {
										$box_shadow_attr     = $params['attr'] ?? [];
										$box_shadow_position = $box_shadow_attr['desktop']['value']['position'] ?? '';
										if ( 'inner' === $box_shadow_position ) {
											return implode(
												', ',
												[
													"{$params['selector']}>.box-shadow-overlay",
													"{$params['selector']}.et-box-shadow-no-overlay",
												]
											);
										}
										return $params['selector'];
									},
								],
								'advancedStyles'           => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => implode(
												', ',
												[
													"{$args['orderClass']} iframe",
													"{$args['orderClass']}",
												]
											),
											'attr'     => $attrs['module']['decoration']['border'] ?? [],
											'declarationFunction' => function ( $params ) use ( $attrs ) {
												$overflow_attr = $attrs['module']['decoration']['overflow'] ?? [];
												return Declarations::overflow_for_border_radius_style_declaration( $params, $overflow_attr );
											},
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr' => $attrs['module']['decoration']['border'] ?? [],
											'declarationFunction' => function ( $params ) use ( $attrs ) {
												$overflow_attr = $attrs['module']['decoration']['overflow'] ?? [];
												return Declarations::overflow_for_border_radius_style_declaration( $params, $overflow_attr );
											},
										],
									],
								],
							],
						]
					),
					// Video Play Icon.
					$elements->style(
						[
							'attrName'   => 'playIcon',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr' => $attrs['playIcon']['decoration']['icon'] ?? [],
											'declarationFunction' => [ self::class, 'icon_size_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Video Overlay.
					$elements->style(
						[
							'attrName' => 'overlay',
						]
					),
					// Module - Only for Custom CSS.
					CssStyle::style(
						[
							'selector'  => $args['orderClass'],
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}

	/**
	 * Loads `VideoModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load() {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/video/';

		add_filter( 'divi_conversion_presets_attrs_map', [ VideoPresetAttrsMap::class, 'get_map' ], 10, 2 );

		// Ensure that all filters and actions applied during module registration are registered before calling `ModuleRegistration::register_module()`.
		// However, for consistency, register all module-specific filters and actions prior to invoking `ModuleRegistration::register_module()`.
		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
