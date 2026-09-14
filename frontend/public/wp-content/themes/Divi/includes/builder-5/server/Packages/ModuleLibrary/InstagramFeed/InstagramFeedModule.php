<?php
/**
 * Module Library: Instagram Feed Module.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\InstagramFeed;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Services\InstagramAccountService\InstagramAccountService;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * Instagram feed module class.
 *
 * @since ??
 */
class InstagramFeedModule implements DependencyInterface {

	/**
	 * Gets first available responsive object from formatted breakpoint attribute.
	 *
	 * Walks builder-enabled breakpoints and their enabled states in order so custom
	 * breakpoints and hover/value layers are respected.
	 *
	 * @since ??
	 *
	 * @param array $attr Formatted breakpoint attribute.
	 *
	 * @return array
	 */
	private static function get_breakpoint_value( array $attr ): array {
		if ( empty( $attr ) ) {
			return [];
		}

		$breakpoints_states = MultiViewUtils::get_breakpoints_states();

		foreach ( Breakpoint::get_enabled_breakpoint_names() as $breakpoint ) {
			if ( empty( $attr[ $breakpoint ] ) || ! is_array( $attr[ $breakpoint ] ) ) {
				continue;
			}

			$states = $breakpoints_states[ $breakpoint ] ?? [ Breakpoint::$base_state ];

			foreach ( $states as $state ) {
				$value = $attr[ $breakpoint ][ $state ] ?? null;

				if ( is_array( $value ) ) {
					return $value;
				}
			}
		}

		return [];
	}

	/**
	 * Get module custom CSS fields.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/instagram-feed' )->customCssFields;
	}

	/**
	 * Generate class names for the module.
	 *
	 * @since ??
	 *
	 * @param array $args Render args.
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
			)
		);
	}

	/**
	 * Register module script data.
	 *
	 * @since ??
	 *
	 * @param array $args Render args.
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
				'attrName' => 'feed',
			]
		);
		$elements->script_data(
			[
				'attrName' => 'item',
			]
		);
		$elements->script_data(
			[
				'attrName' => 'media',
			]
		);
		$elements->script_data(
			[
				'attrName' => 'followButton',
			]
		);
	}

	/**
	 * Add module styles.
	 *
	 * @since ??
	 *
	 * @param array $args Render args.
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs       = $args['attrs'] ?? [];
		$elements    = $args['elements'];
		$settings    = $args['settings'] ?? [];
		$order_class = $args['orderClass'] ?? '';

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
					$elements->style( [ 'attrName' => 'feed' ] ),
					$elements->style( [ 'attrName' => 'item' ] ),
					$elements->style( [ 'attrName' => 'media' ] ),
					$elements->style( [ 'attrName' => 'followButton' ] ),
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
	 * Render callback for instagram feed module.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                Module attrs.
	 * @param string         $child_modules_content Child module content.
	 * @param WP_Block       $block                Parsed block.
	 * @param ModuleElements $elements             Module elements.
	 *
	 * @return string
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements ): string {
		$feed_inner_attr = self::get_breakpoint_value( $attrs['feed']['innerContent'] ?? [] );
		$feed_inner_attr = is_array( $feed_inner_attr ) ? $feed_inner_attr : [];
		$feed_items      = [];
		$account_id      = isset( $feed_inner_attr['accountId'] ) ? (string) $feed_inner_attr['accountId'] : '';

		$feed                        = self::get_breakpoint_value( $attrs['feed']['advanced']['config'] ?? [] );
		$follow_button_inner_content = $attrs['followButton']['innerContent'] ?? [];
		$follow_button_show          = $attrs['followButton']['advanced']['show']['desktop']['value'] ?? 'on';
		$post_count                  = isset( $feed_inner_attr['postCount'] ) ? intval( $feed_inner_attr['postCount'] ) : 6;
		$post_count          = max( 1, min( InstagramAccountService::MAX_LIMIT, $post_count ) );
		$lightbox            = $feed['lightbox'] ?? 'on';
		$is_lightbox_enabled = 'off' !== $lightbox;
		$show_follow_button  = 'off' !== $follow_button_show;
		$follow_button_url   = '';

		if ( '' !== $account_id ) {
			$remote_items = InstagramAccountService::fetch_media( $account_id, $post_count, false );
			$accounts     = InstagramAccountService::fetch_account( $account_id );
			$account_data = is_array( $accounts ) ? $accounts[ $account_id ] ?? [] : [];

			$username = sanitize_text_field( (string) ( $account_data['username'] ?? '' ) );
			if ( '' === $username ) {
				$username = sanitize_text_field( (string) ( $account_data['account_name'] ?? '' ) );
			}

			$username = ltrim( $username, '@' );

			if ( '' !== $username ) {
				$follow_button_url = esc_url( sprintf( 'https://instagram.com/%s', rawurlencode( $username ) ) );
			}

			if ( is_wp_error( $remote_items ) ) {
				$feed_items = [];
			} else {
				$feed_items = $remote_items;
			}
		}
		$header      = '';
		$empty_state = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [ 'class' => 'et_pb_instagram_feed__empty-state' ],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => [
					HTMLUtility::render(
						[
							'tag'      => 'p',
							'children' => esc_html__( 'No media found', 'et_builder_5' ),
						]
					),
				],
			]
		);

		$items_render = '';
		foreach ( $feed_items as $item ) {
			$media_url  = isset( $item['mediaUrl'] ) ? esc_url( $item['mediaUrl'] ) : '';
			$permalink  = isset( $item['permalink'] ) ? esc_url( $item['permalink'] ) : '#';
			$media_type = $item['mediaType'] ?? 'image';
			$is_video   = 'video' === $media_type;
			$caption    = isset( $item['caption'] ) ? sanitize_text_field( (string) $item['caption'] ) : '';
			$img_alt    = '' !== $caption ? $caption : __( 'Instagram post image', 'et_builder_5' );

			if ( '' === $media_url ) {
				continue;
			}

			$media_content = HTMLUtility::render(
				[
					'tag'        => 'img',
					'attributes' => [
						'src' => $media_url,
						'alt' => $img_alt,
					],
				]
			);

			$is_item_lightbox_enabled = $is_lightbox_enabled && ! $is_video;

			$media_attributes = [
				'class' => HTMLUtility::classnames(
					[
						'et_pb_instagram_feed__media' => true,
						'et_pb_lightbox_image'        => $is_item_lightbox_enabled,
					]
				),
				'href'  => $is_item_lightbox_enabled ? $media_url : $permalink,
			];

			if ( ! $is_item_lightbox_enabled ) {
				$media_attributes['target'] = '_blank';
				$media_attributes['rel']    = 'noreferrer';
			}

			$media = HTMLUtility::render(
				[
					'tag'               => 'a',
					'attributes'        => $media_attributes,
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $media_content,
				]
			);

			$items_render .= HTMLUtility::render(
				[
					'tag'               => 'div',
					'attributes'        => [ 'class' => 'et_pb_instagram_feed__item' ],
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $media,
				]
			);
		}

		$feed_container = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [ 'class' => 'et_pb_instagram_feed__items' ],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => '' !== $items_render ? $items_render : $empty_state,
			]
		);

		if ( $show_follow_button && '' !== $follow_button_url ) {
			$follow_button_aria_label       = esc_attr__( 'Follow Instagram account', 'et_builder_5' );
			$follow_button_inner_content    = array_replace_recursive(
				$follow_button_inner_content,
				[
					'desktop' => [
						'value' => [
							'linkUrl'    => $follow_button_url,
							'linkTarget' => 'on',
							'rel'        => [ 'noreferrer' ],
						],
					],
				]
			);
			$follow_button_rendered_element = $elements->render(
				[
					'attrName'     => 'followButton',
					'attributes'   => [
						'class'      => 'et_pb_instagram_feed__follow_button',
						'aria-label' => $follow_button_aria_label,
					],
					'elementProps' => [
						'allowEmptyUrl' => true,
						'hasWrapper'    => false,
						'innerContent'  => $follow_button_inner_content,
					],
				]
			);

			$header = HTMLUtility::render(
				[
					'tag'               => 'div',
					'attributes'        => [ 'class' => 'et_pb_instagram_feed__header' ],
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $follow_button_rendered_element,
				]
			);
		}

		$parent       = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$children_ids = $block->parsed_block['innerBlocks'] ? array_map(
			function ( $inner_block ) {
				return $inner_block['id'];
			},
			$block->parsed_block['innerBlocks']
		) : [];

		return Module::render(
			[
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],
				'attrs'               => $attrs,
				'elements'            => $elements,
				'id'                  => $block->parsed_block['id'],
				'name'                => $block->block_type->name,
				'moduleCategory'      => $block->block_type->category,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'parentAttrs'         => $parent->attrs ?? [],
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->blockName ?? '',
				'childrenIds'         => $children_ids,
				'children'            => [
					$elements->style_components( [ 'attrName' => 'module' ] ),
					$header,
					$feed_container,
					$child_modules_content,
				],
			]
		);
	}

	/**
	 * Load module registration.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctionParameters.dirname_levelsFound -- We have PHP 7 support now, This can be deleted once PHPCS config is updated.
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/instagram-feed/';

		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
