<?php
/**
 * Module Library: Timeline Item Module.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\TimelineItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP uses snakeCase in \WP_Block_Parser_Block.

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * Timeline item module class.
 *
 * @since ??
 */
class TimelineItemModule implements DependencyInterface {

	/**
	 * Generate module classnames.
	 *
	 * @since ??
	 *
	 * @param array $args Module classnames args.
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
	 * Render callback for timeline-item module.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Saved block attributes.
	 * @param string         $child_modules_content       Child modules content.
	 * @param WP_Block       $block                       Parsed block object being rendered.
	 * @param ModuleElements $elements                    ModuleElements instance.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ): string {
		$parent                                  = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$marker_icon_attr                        = $attrs['marker']['innerContent']['desktop']['value'] ?? [];
		$marker_icon_value                       = Utils::process_font_icon( $marker_icon_attr );
		$children_ids                            = ChildrenUtils::extract_children_ids( $block );
		$display_date_on_spacer_value            = self::_get_on_off_value( $attrs['date']['advanced']['displayOnSpacer'] ?? [] );
		$display_elements_on_spacer_value        = self::_get_on_off_value( $attrs['spacer']['advanced']['displayElementsOnSpacer'] ?? [] );
		$display_title_on_spacer_value           = self::_get_on_off_value( $attrs['title']['advanced']['displayOnSpacer'] ?? [] );
		$parent_display_date_on_spacer_value     = self::_get_on_off_value( $parent->attrs['children']['date']['advanced']['displayOnSpacer'] ?? [] );
		$parent_display_elements_on_spacer_value = self::_get_on_off_value( $parent->attrs['children']['spacer']['advanced']['displayElementsOnSpacer'] ?? [] );
		$parent_display_title_on_spacer_value    = self::_get_on_off_value( $parent->attrs['children']['title']['advanced']['displayOnSpacer'] ?? [] );
		$resolved_display_date_on_spacer         = self::_resolve_on_off_value( $display_date_on_spacer_value, $parent_display_date_on_spacer_value );
		$resolved_display_elements_on_spacer     = self::_resolve_on_off_value( $display_elements_on_spacer_value, $parent_display_elements_on_spacer_value );
		$resolved_display_title_on_spacer        = self::_resolve_on_off_value( $display_title_on_spacer_value, $parent_display_title_on_spacer_value );
		$should_display_date_on_spacer           = 'on' === $resolved_display_date_on_spacer;
		$should_display_elements_on_spacer       = 'on' === $resolved_display_elements_on_spacer;
		$should_display_title_on_spacer          = 'on' === $resolved_display_title_on_spacer;

		$spacer_children  = '';
		$spacer_children .= $elements->style_components(
			[
				'attrName' => 'spacer',
			]
		);

		if ( $should_display_date_on_spacer ) {
			$spacer_children .= $elements->render(
				[
					'attrName' => 'date',
				]
			);
		}

		if ( $should_display_title_on_spacer ) {
			$spacer_children .= $elements->render(
				[
					'attrName' => 'title',
				]
			);
		}

		if ( $should_display_elements_on_spacer ) {
			$spacer_children .= $child_modules_content;
		}

		$spacer              = $elements->render(
			[
				'attrName'          => 'spacer',
				'attributes'        => [
					'class' => 'et_pb_timeline_spacer',
				],
				'skipAttrChildren'  => true,
				'allowEmptyValue'   => true,
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $spacer_children,
			]
		);
		$connector_children  = '';
		$connector_children .= $elements->style_components(
			[
				'attrName' => 'connector',
			]
		);
		$connector           = $elements->render(
			[
				'attrName'          => 'connector',
				'skipAttrChildren'  => true,
				'allowEmptyValue'   => true,
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $connector_children,
			]
		);
		$marker_children     = '';
		$marker_children    .= $elements->style_components(
			[
				'attrName' => 'marker',
			]
		);
		$marker_children    .= ! empty( $marker_icon_value ) ? '<span class="et-pb-icon">' . $marker_icon_value . '</span>' : '';
		$marker              = $elements->render(
			[
				'attrName'          => 'marker',
				'skipAttrChildren'  => true,
				'allowEmptyValue'   => true,
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $marker_children,
			]
		);
		$ornament            = '<div class="et_pb_timeline_ornament">' . $connector . $marker . '</div>';
		$card_children       = '';

		$card_children .= $elements->style_components(
			[
				'attrName' => 'card',
			]
		);

		if ( ! $should_display_date_on_spacer ) {
			$card_children .= $elements->render(
				[
					'attrName' => 'date',
				]
			);
		}

		if ( ! $should_display_title_on_spacer ) {
			$card_children .= $elements->render(
				[
					'attrName' => 'title',
				]
			);
		}

		$card_children .= $elements->render(
			[
				'attrName' => 'content',
			]
		);

		if ( ! $should_display_elements_on_spacer ) {
			$card_children .= $child_modules_content;
		}

		$card = $elements->render(
			[
				'attrName'          => 'card',
				'skipAttrChildren'  => true,
				'allowEmptyValue'   => true,
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $card_children,
			]
		);

		return Module::render(
			[
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],
				'attrs'                    => $attrs,
				'elements'                 => $elements,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'parentAttrs'              => $parent->attrs ?? [],
				'parentId'                 => $parent->id ?? '',
				'parentName'               => $parent->blockName ?? '',
				'childrenIds'              => $children_ids,
				'id'                       => $block->parsed_block['id'],
				'name'                     => $block->block_type->name,
				'moduleCategory'           => $block->block_type->category,
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'children'                 => [
					$elements->style_components(
						[
							'attrName' => 'module',
						]
					),
					$spacer,
					$ornament,
					$card,
				],
			]
		);
	}

	/**
	 * Resolve OnOff value from responsive attribute.
	 *
	 * @since ??
	 *
	 * @param mixed $attr_value Responsive OnOff attribute.
	 *
	 * @return string
	 */
	private static function _get_on_off_value( $attr_value ): string {
		if ( ! is_array( $attr_value ) ) {
			return '';
		}

		$value = $attr_value['desktop']['value'] ?? $attr_value['value'] ?? '';

		return is_string( $value ) ? wp_strip_all_tags( $value ) : '';
	}

	/**
	 * Resolve OnOff value from child and parent values.
	 *
	 * @since ??
	 *
	 * @param string $child_value  Child OnOff value.
	 * @param string $parent_value Parent OnOff value.
	 *
	 * @return string
	 */
	private static function _resolve_on_off_value( string $child_value, string $parent_value ): string {
		return ( 'on' === $child_value || 'off' === $child_value )
			? $child_value
			: $parent_value;
	}

	/**
	 * Timeline-item module script data.
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
				'attrName' => 'spacer',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'connector',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'marker',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'card',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'date',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'title',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'content',
			]
		);
	}

	/**
	 * Timeline-item module styles.
	 *
	 * @since ??
	 *
	 * @param array $args Styles args.
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs            = $args['attrs'] ?? [];
		$elements         = $args['elements'];
		$settings         = $args['settings'] ?? [];
		$base_order_class = $args['baseOrderClass'] ?? '';
		$selector_prefix  = $args['selectorPrefix'] ?? '';

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
								'defaultPrintedStyleAttrs' => $args['defaultPrintedStyleAttrs']['module']['decoration'] ?? [],
								'disabledOn'               => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
								'advancedStyles'           => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $selector_prefix . '.et_pb_timeline ' . $base_order_class . '.et_pb_timeline_item .et_pb_timeline_spacer',
											'attr'     => $attrs['spacer']['decoration']['layout'] ?? [],
											'declarationFunction' => [ self::class, 'spacer_layout_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $selector_prefix . '.et_pb_timeline ' . $base_order_class . '.et_pb_timeline_item .et_pb_timeline_card',
											'attr'     => $attrs['card']['decoration']['layout'] ?? [],
											'defaultPrintedStyleAttr' => $args['defaultPrintedStyleAttrs']['card']['decoration']['layout'] ?? [],
											'declarationFunction' => [ self::class, 'card_layout_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $selector_prefix . '.et_pb_timeline ' . $base_order_class . '.et_pb_timeline_item .et_pb_timeline_ornament',
											'attr'     => $attrs['marker']['advanced'] ?? [],
											'declarationFunction' => [ self::class, 'ornament_marker_position_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/css',
										'props'         => [
											'attr'      => $attrs['css'] ?? [],
											'cssFields' => self::custom_css(),
										],
									],
								],
							],
						]
					),
					$elements->style(
						[
							'attrName' => 'spacer',
						]
					),
					$elements->style(
						[
							'attrName' => 'connector',
						]
					),
					$elements->style(
						[
							'attrName'   => 'marker',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $selector_prefix . '.et_pb_timeline ' . $base_order_class . '.et_pb_timeline_item .et_pb_timeline_marker .et-pb-icon',
											'attr'     => $attrs['marker']['innerContent'] ?? null,
											'declarationFunction' => [ self::class, 'marker_icon_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $selector_prefix . '.et_pb_timeline ' . $base_order_class . '.et_pb_timeline_item .et_pb_timeline_marker .et-pb-icon',
											'attr'     => $attrs['marker']['decoration']['icon'] ?? null,
											'declarationFunction' => [ self::class, 'marker_icon_appearance_style_declaration' ],
										],
									],
								],
							],
						]
					),
					$elements->style(
						[
							'attrName' => 'card',
						]
					),
					$elements->style(
						[
							'attrName' => 'date',
						]
					),
					$elements->style(
						[
							'attrName' => 'title',
						]
					),
					$elements->style(
						[
							'attrName' => 'content',
						]
					),
					CssStyle::style(
						[
							'selector'  => $selector_prefix . '.et_pb_timeline ' . $base_order_class . '.et_pb_timeline_item',
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}

	/**
	 * Card layout style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function card_layout_style_declaration( array $params ): string {
		$attr_value              = $params['attrValue'] ?? [];
		$default_attr_value      = $params['defaultAttrValue'] ?? [];
		$display_raw             = $attr_value['display'] ?? '';
		$display                 = is_string( $display_raw ) ? wp_strip_all_tags( $display_raw ) : '';
		$default_display_raw     = $default_attr_value['display'] ?? '';
		$default_display         = is_string( $default_display_raw ) ? wp_strip_all_tags( $default_display_raw ) : '';
		$flex_direction_raw      = $attr_value['flexDirection'] ?? '';
		$flex_direction_value    = is_string( $flex_direction_raw ) ? wp_strip_all_tags( $flex_direction_raw ) : '';
		$flex_direction          = '' !== $flex_direction_value ? $flex_direction_value : 'column';
		$column_gap_raw          = $attr_value['columnGap'] ?? '';
		$column_gap_value        = is_string( $column_gap_raw ) ? wp_strip_all_tags( $column_gap_raw ) : '';
		$column_gap              = '' !== $column_gap_value ? $column_gap_value : '10px';
		$row_gap_raw             = $attr_value['rowGap'] ?? '';
		$row_gap_value           = is_string( $row_gap_raw ) ? wp_strip_all_tags( $row_gap_raw ) : '';
		$row_gap                 = '' !== $row_gap_value ? $row_gap_value : '10px';
		$declarations            = new StyleDeclarations( [ 'returnType' => 'string' ] );
		$allowed_display_options = [ 'flex', 'grid', 'block' ];

		$should_print_display = in_array( $display, $allowed_display_options, true )
			&& ! ( 'block' === $display && 'block' === $default_display );

		if ( $should_print_display ) {
			$declarations->add( 'display', $display );
		}

		if ( 'flex' === $display ) {
			$declarations->add( 'flex-direction', $flex_direction );
			$declarations->add( 'column-gap', $column_gap );
			$declarations->add( 'row-gap', $row_gap );
		}

		return $declarations->value();
	}

	/**
	 * Spacer layout style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function spacer_layout_style_declaration( array $params ): string {
		$attr_value              = $params['attrValue'] ?? [];
		$display_raw             = $attr_value['display'] ?? '';
		$display                 = is_string( $display_raw ) ? wp_strip_all_tags( $display_raw ) : '';
		$flex_direction_raw      = $attr_value['flexDirection'] ?? '';
		$flex_direction_value    = is_string( $flex_direction_raw ) ? wp_strip_all_tags( $flex_direction_raw ) : '';
		$flex_direction          = '' !== $flex_direction_value ? $flex_direction_value : 'column';
		$column_gap_raw          = $attr_value['columnGap'] ?? '';
		$column_gap_value        = is_string( $column_gap_raw ) ? wp_strip_all_tags( $column_gap_raw ) : '';
		$column_gap              = '' !== $column_gap_value ? $column_gap_value : '10px';
		$row_gap_raw             = $attr_value['rowGap'] ?? '';
		$row_gap_value           = is_string( $row_gap_raw ) ? wp_strip_all_tags( $row_gap_raw ) : '';
		$row_gap                 = '' !== $row_gap_value ? $row_gap_value : '10px';
		$declarations            = new StyleDeclarations( [ 'returnType' => 'string' ] );
		$allowed_display_options = [ 'flex', 'grid', 'block' ];

		if ( in_array( $display, $allowed_display_options, true ) ) {
			$declarations->add( 'display', $display );
		}

		if ( 'flex' === $display ) {
			$declarations->add( 'flex-direction', $flex_direction );
			$declarations->add( 'column-gap', $column_gap );
			$declarations->add( 'row-gap', $row_gap );
		}

		return $declarations->value();
	}

	/**
	 * Marker icon style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function marker_icon_style_declaration( array $params ): string {
		$icon_attr = $params['attrValue'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'font-family' => true,
					'font-weight' => true,
				],
			]
		);

		if ( isset( $icon_attr['type'] ) ) {
			$font_family = 'fa' === $icon_attr['type'] ? 'FontAwesome' : 'ETmodules';
			$style_declarations->add( 'font-family', $font_family );
		}

		if ( ! empty( $icon_attr['weight'] ) ) {
			$style_declarations->add( 'font-weight', $icon_attr['weight'] );
		}

		return $style_declarations->value();
	}

	/**
	 * Marker icon appearance style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function marker_icon_appearance_style_declaration( array $params ): string {
		$icon_attr = $params['attrValue'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
			]
		);

		if ( ! empty( $icon_attr['color'] ) ) {
			$style_declarations->add( 'color', $icon_attr['color'] );
		}

		if ( ! empty( $icon_attr['size'] ) ) {
			$style_declarations->add( 'font-size', $icon_attr['size'] );
		}

		return $style_declarations->value();
	}

	/**
	 * Ornament marker position style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function ornament_marker_position_style_declaration( array $params ): string {
		$declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
			]
		);

		$position = $params['attrValue']['position'] ?? '';

		if ( ! ( 'center' === $position || 'end' === $position || 'start' === $position ) ) {
			return '';
		}

		if ( 'center' === $position ) {
			$declarations->add( 'justify-content', 'center' );
		} elseif ( 'end' === $position ) {
			$declarations->add( 'justify-content', 'flex-end' );
		} else {
			$declarations->add( 'justify-content', 'flex-start' );
		}

		return $declarations->value();
	}

	/**
	 * Get custom css fields.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/timeline-item' )->customCssFields;
	}

	/**
	 * Load timeline-item module.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/timeline-item/';

		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
