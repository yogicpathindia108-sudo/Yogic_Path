<?php
/**
 * Module Library: Table of Contents module.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\TableOfContents;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\ScriptData;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use WP_Block;

/**
 * TableOfContentsModule class.
 *
 * @since ??
 */
class TableOfContentsModule implements DependencyInterface {
	/**
	 * Register script data for frontend table of contents behavior.
	 *
	 * @since ??
	 *
	 * @param array $args Script data args.
	 *
	 * @return void
	 */
	public static function module_script_data( array $args ): void {
		$id             = $args['id'] ?? '';
		$name           = $args['name'] ?? '';
		$selector = $args['selector'] ?? '';
		$attrs          = $args['attrs'] ?? [];
		$elements       = $args['elements'];
		$store_instance = $args['storeInstance'] ?? null;

		foreach ( [ 'module', 'title', 'list', 'list1', 'list2', 'list3', 'list4', 'list5', 'list6', 'marker', 'emptyState' ] as $attr_name ) {
			$elements->script_data(
				[
					'attrName' => $attr_name,
				]
			);
		}

		self::set_front_end_data(
			[
				'selector' => $selector,
				'attrs'    => $attrs,
			]
		);

		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'hoverSelector' => $selector,
				'setContent'    => [
					[
						'selector'      => "{$selector} .et_pb_table_of_contents__title",
						'data'          => $attrs['title']['innerContent'] ?? [],
						'valueResolver' => static function ( $value ) {
							return esc_html( $value ?? '' );
						},
					],
					[
						'selector'      => "{$selector} .et_pb_table_of_contents__empty",
						'data'          => $attrs['emptyState']['innerContent'] ?? [],
						'valueResolver' => static function ( $value ) {
							return esc_html( $value ?? '' );
						},
					],
				],
			]
		);
	}

	/**
	 * Set frontend script data payload.
	 *
	 * @since ??
	 *
	 * @param array $args Payload args.
	 *
	 * @return void
	 */
	public static function set_front_end_data( array $args ): void {
		// Script data is not needed in VB.
		if ( Conditions::is_vb_enabled() ) {
			return;
		}

		$selector = $args['selector'] ?? '';
		$attrs    = $args['attrs'] ?? [];

		if ( '' === $selector || empty( $attrs ) ) {
			return;
		}

		ScriptData::add_data_item(
			[
				'data_name'    => 'table_of_contents',
				'data_item_id' => null,
				'data_item'    => [
					'selector' => $selector,
					'data'     => [
						'interaction' => $attrs['list']['advanced']['interaction'] ?? [],
						'source'      => $attrs['list']['innerContent'] ?? [],
						'layout'      => $attrs['list']['advanced']['layout'] ?? [],
					],
				],
			]
		);
	}


	/**
	 * Get module classnames.
	 *
	 * @since ??
	 *
	 * @param array $args Classname arguments.
	 *
	 * @return void
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => array_merge(
						$attrs['module']['decoration'] ?? [],
						[
							'link' => $attrs['module']['advanced']['link'] ?? [],
						]
					),
				]
			)
		);
	}

	/**
	 * Output module styles.
	 *
	 * @since ??
	 *
	 * @param array $args Style args.
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];
		$settings = $args['settings'] ?? [];

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
					$elements->style(
						[
							'attrName' => 'title',
						]
					),
					$elements->style(
						[
							'attrName' => 'list',
						]
					),
					$elements->style(
						[
							'attrName' => 'list1',
						]
					),
					$elements->style(
						[
							'attrName' => 'list2',
						]
					),
					$elements->style(
						[
							'attrName' => 'list3',
						]
					),
					$elements->style(
						[
							'attrName' => 'list4',
						]
					),
					$elements->style(
						[
							'attrName' => 'list5',
						]
					),
					$elements->style(
						[
							'attrName' => 'list6',
						]
					),
					$elements->style(
						[
							'attrName' => 'marker',
						]
					),
					$elements->style(
						[
							'attrName' => 'emptyState',
						]
					),
					CssStyle::style(
						[
							'selector' => $args['orderClass'],
							'attr'     => $attrs['css'] ?? [],
						]
					),
				],
			]
		);
	}

	/**
	 * Render module output.
	 *
	 * @since ??
	 *
	 * @param array          $attrs Block attrs.
	 * @param string         $child_modules_content Child content.
	 * @param WP_Block       $block Block.
	 * @param ModuleElements $elements Elements instance.
	 *
	 * @return string
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements ): string {
		$title = $elements->render(
			[
				'attrName' => 'title',
			]
		);

		$list_layout_raw = $attrs['list']['advanced']['layout']['desktop']['value']['markerStyle'] ?? 'ordered';
		$list_layout     = in_array( $list_layout_raw, [ 'ordered', 'unordered', 'none' ], true ) ? $list_layout_raw : 'ordered';
		$list_tag        = 'ordered' === $list_layout ? 'ol' : 'ul';
		$list_class      = 'et_pb_table_of_contents__list et_pb_table_of_contents__list--root et_pb_table_of_contents__list--' . $list_layout;

		$empty_state = $elements->render(
			[
				'attrName' => 'emptyState',
			]
		);

		$toc_nav_aria_label         = esc_attr__( 'Table of contents', 'et_builder_5' );
		$toc_nav_placeholder_format = esc_attr(
			/* translators: %d: heading level number (1-6). */
			__( 'Heading %d', 'et_builder_5' )
		);

		$children_ids = ChildrenUtils::extract_children_ids( $block );
		$parent       = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		return Module::render(
			[
				'orderIndex'         => $block->parsed_block['orderIndex'],
				'storeInstance'      => $block->parsed_block['storeInstance'],
				'attrs'              => $attrs,
				'elements'           => $elements,
				'id'                 => $block->parsed_block['id'],
				'name'               => $block->block_type->name,
				'classnamesFunction' => [ self::class, 'module_classnames' ],
				'moduleCategory'     => $block->block_type->category,
				'stylesComponent'    => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'parentAttrs'        => $parent->attrs ?? [],
				'parentId'           => $parent->id ?? '',
				'parentName'         => $parent->blockName ?? '',
				'childrenIds'        => $children_ids,
				'children'           => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $title . '<nav class="et_pb_table_of_contents__nav" aria-label="' . $toc_nav_aria_label . '" data-et-toc-placeholder-heading-format="' . $toc_nav_placeholder_format . '">' .
				$empty_state .
				'<' . $list_tag . ' class="' . esc_attr( $list_class ) . '"></' . $list_tag . '>' .
				'</nav>' . $child_modules_content,
			]
		);
	}

	/**
	 * Register module.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/table-of-contents/';

		add_filter( 'divi_conversion_presets_attrs_map', [ TableOfContentsPresetAttrsMap::class, 'get_map' ], 10, 2 );

		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
