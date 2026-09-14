<?php
/**
 * Module Library: Timeline Module.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Timeline;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP uses snakeCase in \WP_Block_Parser_Block.

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * Timeline module class.
 *
 * @since ??
 */
class TimelineModule implements DependencyInterface {

	/**
	 * Render callback for timeline module.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Saved block attributes.
	 * @param string         $content                     Block content.
	 * @param WP_Block       $block                       Parsed block object being rendered.
	 * @param ModuleElements $elements                    ModuleElements instance.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ): string {
		$children_ids = $block->parsed_block['innerBlocks'] ? array_map(
			function ( $inner_block ) {
				return $inner_block['id'];
			},
			$block->parsed_block['innerBlocks']
		) : [];

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		$track = '<div class="et_pb_timeline_track_wrapper"><div class="et_pb_timeline_track">' . $content . '</div></div>';

		return Module::render(
			[
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],
				'id'                       => $block->parsed_block['id'],
				'name'                     => $block->block_type->name,
				'moduleCategory'           => $block->block_type->category,
				'attrs'                    => $attrs,
				'elements'                 => $elements,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'parentId'                 => $parent->id ?? '',
				'parentName'               => $parent->blockName ?? '',
				'parentAttrs'              => $parent->attrs ?? [],
				'childrenIds'              => $children_ids,
				'children'                 => [
					$elements->style_components(
						[
							'attrName' => 'module',
						]
					),
					$track,
				],
			]
		);
	}

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
	 * Timeline module script data.
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
				'attrName' => 'track',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'item',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'itemEven',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'spacer',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'spacerEven',
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
				'attrName' => 'cardEven',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'date',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'dateEven',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'title',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'titleEven',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'content',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'contentEven',
			]
		);
	}

	/**
	 * Timeline module styles.
	 *
	 * @since ??
	 *
	 * @param array $args Styles args.
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs       = $args['attrs'] ?? [];
		$elements    = $args['elements'];
		$settings    = $args['settings'] ?? [];
		$order_class = $args['orderClass'] ?? '';

		// Propagate desktop direction to breakpoints that only override position.
		$timeline_attr = self::resolve_timeline_attr( $attrs['module']['advanced']['timeline'] ?? [] );

		// Check all breakpoints to determine if even elements should be rendered.
		// A single alternating breakpoint is enough to require even-element styles.
		$render_even_elements = false;
		foreach ( $timeline_attr as $breakpoint_data ) {
			if ( ! is_array( $breakpoint_data ) ) {
				continue;
			}
			$breakpoint_value = $breakpoint_data['value'] ?? [];
			if ( ! is_array( $breakpoint_value ) ) {
				continue;
			}
			$breakpoint_flags = self::get_behavior_flags( $breakpoint_value );
			if ( $breakpoint_flags['isVerticalAlternating'] || $breakpoint_flags['isHorizontalAlternating'] ) {
				$render_even_elements = true;
				break;
			}
		}

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => array_merge(
					[
						$elements->style(
							[
								'attrName'   => 'module',
								'styleProps' => [
									'defaultPrintedStyleAttrs' => $args['defaultPrintedStyleAttrs']['module']['decoration'] ?? [],
									'disabledOn'     => [
										'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
									],
									'advancedStyles' => [
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline',
												'attr'     => $timeline_attr,
												'declarationFunction' => [ self::class, 'module_horizontal_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline .et_pb_timeline_track_wrapper',
												'attr'     => $timeline_attr,
												'declarationFunction' => [ self::class, 'track_wrapper_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline .et_pb_timeline_track',
												'attr'     => $timeline_attr,
												'declarationFunction' => [ self::class, 'track_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline .et_pb_timeline_track',
												'attr'     => $attrs['track']['decoration']['layout'] ?? [],
												'declarationFunction' => [ self::class, 'track_layout_gap_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline .et_pb_timeline_item',
												'attr'     => array_replace_recursive(
													$timeline_attr,
													$attrs['item']['decoration']['sizing'] ?? []
												),
												'declarationFunction' => [ self::class, 'item_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline :where(.et_pb_timeline_track) > :nth-child(even) .et_pb_timeline_spacer',
												'attr'     => $timeline_attr,
												'declarationFunction' => [ self::class, 'even_spacer_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline :where(.et_pb_timeline_track) > :nth-child(even) .et_pb_timeline_ornament',
												'attr'     => $timeline_attr,
												'declarationFunction' => [ self::class, 'even_ornament_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline :where(.et_pb_timeline_track) > :nth-child(even) .et_pb_timeline_card',
												'attr'     => $timeline_attr,
												'declarationFunction' => [ self::class, 'even_card_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline :where(.et_pb_timeline_track) > :nth-child(odd) .et_pb_timeline_spacer',
												'attr'     => $timeline_attr,
												'declarationFunction' => [ self::class, 'odd_spacer_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline :where(.et_pb_timeline_track) > :nth-child(odd) .et_pb_timeline_ornament',
												'attr'     => $timeline_attr,
												'declarationFunction' => [ self::class, 'odd_ornament_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline :where(.et_pb_timeline_track) > :nth-child(odd) .et_pb_timeline_card',
												'attr'     => $timeline_attr,
												'declarationFunction' => [ self::class, 'odd_card_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline .et_pb_timeline_connector',
												'attr'     => array_replace_recursive(
													$timeline_attr,
													$attrs['track']['decoration']['layout'] ?? [],
													$attrs['connector']['decoration']['sizing'] ?? []
												),
												'declarationFunction' => [ self::class, 'connector_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline .et_pb_timeline_spacer',
												'attr'     => array_replace_recursive(
													$timeline_attr,
													$attrs['spacer']['decoration']['layout'] ?? []
												),
												'declarationFunction' => [ self::class, 'spacer_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline .et_pb_timeline_ornament',
												'attr'     => $timeline_attr,
												'declarationFunction' => [ self::class, 'ornament_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline .et_pb_timeline_ornament',
												'attr'     => $attrs['marker']['advanced'] ?? [],
												'declarationFunction' => [ self::class, 'ornament_marker_position_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline .et_pb_timeline_card',
												'attr'     => array_replace_recursive(
													$timeline_attr,
													$attrs['card']['decoration']['layout'] ?? [],
													$attrs['card']['decoration']['sizing'] ?? []
												),
												'defaultPrintedStyleAttr' => $args['defaultPrintedStyleAttrs']['card']['decoration']['layout'] ?? [],
												'declarationFunction' => [ self::class, 'card_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => $order_class . '.et_pb_timeline :where(.et_pb_timeline_track) > :last-child .et_pb_timeline_connector',
												'attr'     => $timeline_attr,
												'declarationFunction' => [ self::class, 'last_connector_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/text',
											'props' => [
												'attr' => $attrs['module']['advanced']['text'] ?? [],
											],
										],
										[
											'componentName' => 'divi/css',
											'props' => [
												'attr' => $attrs['css'] ?? [],
												'cssFields' => self::custom_css(),
											],
										],
									],
								],
							]
						),
						$elements->style(
							[
								'attrName' => 'track',
							]
						),
						$elements->style(
							[
								'attrName' => 'item',
							]
						),
					],
					$render_even_elements
						? [
							$elements->style(
								[
									'attrName' => 'itemEven',
								]
							),
						]
						: [],
					[
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
								'attrName' => 'marker',
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
					],
					$render_even_elements
						? [
							$elements->style(
								[
									'attrName' => 'spacerEven',
								]
							),
							$elements->style(
								[
									'attrName' => 'cardEven',
								]
							),
							$elements->style(
								[
									'attrName' => 'dateEven',
								]
							),
							$elements->style(
								[
									'attrName' => 'titleEven',
								]
							),
							$elements->style(
								[
									'attrName' => 'contentEven',
								]
							),
						]
						: [],
					[
						CssStyle::style(
							[
								'selector'  => $order_class . '.et_pb_timeline',
								'attr'      => $attrs['css'] ?? [],
								'cssFields' => self::custom_css(),
							]
						),
					]
				),
			]
		);
	}

	/**
	 * Normalize a timeline attribute value to the underlying responsive array.
	 *
	 * This function inspects the structure of a timeline module attribute,
	 * unwrapping it to return the actual value array, supporting responsive and
	 * non-responsive shapes. Used internally by style and behavior resolution.
	 *
	 * @since ??
	 *
	 * @param array $attr_value Timeline attribute value (may be responsive or not).
	 *
	 * @return array Normalized attribute value array.
	 */
	private static function _resolve_attr_value( array $attr_value ): array {
		if ( isset( $attr_value['desktop']['value'] ) && is_array( $attr_value['desktop']['value'] ) ) {
			return $attr_value['desktop']['value'];
		}

		if ( isset( $attr_value['value'] ) && is_array( $attr_value['value'] ) ) {
			return $attr_value['value'];
		}

		return $attr_value;
	}

	/**
	 * Retrieve a string value for a specific style property from a timeline attribute value.
	 *
	 * This method normalizes a potentially responsive (or flattened) attribute value,
	 * then attempts to extract the designated style property's value as a string.
	 * If the requested property does not exist, or its value is not a string,
	 * an empty string is returned.
	 *
	 * @since ??
	 *
	 * @param array  $attr_value Timeline style attribute (may be a responsive shape).
	 * @param string $key        The style property key to retrieve.
	 *
	 * @return string Extracted string value for the given key, or an empty string if not found or not a string.
	 */
	private static function _get_string_style_value( array $attr_value, string $key ): string {
		$resolved = self::_resolve_attr_value( $attr_value );
		$value    = $resolved[ $key ] ?? '';

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Resolves the effective `direction` and `position` for every breakpoint of the timeline attribute.
	 *
	 * Two cases are handled:
	 *  1. A breakpoint stores only a `position` change (no explicit `direction`) → it inherits the
	 *     desktop direction so declaration functions receive a complete value.
	 *  2. A breakpoint stores only a `direction` change (no explicit `position`) → it receives the
	 *     default position for that direction (`right` for vertical, `top` for horizontal) so that all
	 *     layout-specific flags fire correctly and the proper CSS is emitted.
	 *
	 * @since ??
	 *
	 * @param array $timeline_attr Raw responsive timeline attribute (keyed by breakpoint name).
	 *
	 * @return array The same attribute with `direction` and `position` filled in where absent.
	 */
	public static function resolve_timeline_attr( array $timeline_attr ): array {
		// Desktop is the base breakpoint. Fall back to 'vertical' when it is absent or has no direction.
		$base_direction = $timeline_attr['desktop']['value']['direction'] ?? 'vertical';

		foreach ( $timeline_attr as $breakpoint => $breakpoint_data ) {
			if ( ! is_array( $breakpoint_data ) ) {
				continue;
			}
			$breakpoint_value = $breakpoint_data['value'] ?? null;
			if ( ! is_array( $breakpoint_value ) ) {
				continue;
			}

			$changed = false;

			// Fill in direction from the desktop base when the breakpoint omits it.
			if ( ! isset( $breakpoint_value['direction'] ) ) {
				$breakpoint_value['direction'] = $base_direction;
				$changed                       = true;
			}

			// Fill in the default position for the effective direction when the breakpoint omits it.
			// - vertical default position: right (matches the module's defaultValue).
			// - horizontal default position: top.
			if ( ! isset( $breakpoint_value['position'] ) ) {
				$breakpoint_value['position'] = 'horizontal' === $breakpoint_value['direction'] ? 'top' : 'right';
				$changed                      = true;
			}

			if ( $changed ) {
				$timeline_attr[ $breakpoint ]['value'] = $breakpoint_value;
			}
		}

		return $timeline_attr;
	}

	/**
	 * Timeline behavior flags.
	 *
	 * @since ??
	 *
	 * @param array $attr_value Timeline behavior attribute.
	 *
	 * @return array<string, bool>
	 */
	public static function get_behavior_flags( array $attr_value ): array {
		$resolved_attr_value = self::_resolve_attr_value( $attr_value );
		// Default direction to 'vertical' when absent; matches the module's defaultValue.
		$direction                 = $resolved_attr_value['direction'] ?? 'vertical';
		$is_horizontal             = 'horizontal' === $direction;
		$is_vertical               = 'vertical' === $direction;
		$is_vertical_alternating   = $is_vertical && 'alternating' === ( $resolved_attr_value['position'] ?? '' );
		$is_vertical_left          = $is_vertical && 'left' === ( $resolved_attr_value['position'] ?? '' );
		$is_horizontal_top         = $is_horizontal && 'top' === ( $resolved_attr_value['position'] ?? '' );
		$is_horizontal_bottom      = $is_horizontal && 'bottom' === ( $resolved_attr_value['position'] ?? '' );
		$is_horizontal_alternating = $is_horizontal && 'alternating' === ( $resolved_attr_value['position'] ?? '' );
		$start_from_raw            = $resolved_attr_value['startFrom'] ?? '';
		$start_from                = is_string( $start_from_raw )
			? $start_from_raw
			: '';

		if ( $is_vertical_alternating && ! in_array( $start_from, [ 'left', 'right' ], true ) ) {
			$start_from = 'right';
		} elseif ( $is_horizontal_alternating && ! in_array( $start_from, [ 'top', 'bottom' ], true ) ) {
			$start_from = 'bottom';
		}

		$is_alternating_even_order = ( $is_vertical_alternating && 'right' === $start_from )
			|| ( $is_horizontal_alternating && 'bottom' === $start_from );
		$is_alternating_odd_order  = ( $is_vertical_alternating && 'left' === $start_from )
			|| ( $is_horizontal_alternating && 'top' === $start_from );

		return [
			'isHorizontal'            => $is_horizontal,
			'isVertical'              => $is_vertical,
			'isVerticalAlternating'   => $is_vertical_alternating,
			'isVerticalLeft'          => $is_vertical_left,
			'isHorizontalTop'         => $is_horizontal_top,
			'isHorizontalBottom'      => $is_horizontal_bottom,
			'isHorizontalAlternating' => $is_horizontal_alternating,
			'isAlternatingEvenOrder'  => $is_alternating_even_order,
			'isAlternatingOddOrder'   => $is_alternating_odd_order,
		];
	}

	/**
	 * Module root style declaration for horizontal timelines.
	 *
	 * This prevents flex and grid ancestors such as columns from growing to fit the track's intrinsic width.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function module_horizontal_style_declaration( array $params ): string {
		$flags        = self::get_behavior_flags( $params['attrValue'] ?? [] );
		$declarations = new StyleDeclarations( [ 'returnType' => 'string' ] );

		if ( $flags['isHorizontal'] ) {
			$declarations->add( 'max-width', '100%' );
			$declarations->add( 'min-width', '0' );
			$declarations->add( 'width', '100%' );
			$declarations->add( 'contain', 'inline-size' );
		} elseif ( $flags['isVertical'] ) {
			// Reset horizontal-specific containment that may cascade from a wider horizontal breakpoint.
			$declarations->add( 'max-width', 'initial' );
			$declarations->add( 'min-width', 'initial' );
			$declarations->add( 'width', 'initial' );
			$declarations->add( 'contain', 'initial' );
		}

		return $declarations->value();
	}

	/**
	 * Track wrapper module style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function track_wrapper_style_declaration( array $params ): string {
		$flags        = self::get_behavior_flags( $params['attrValue'] ?? [] );
		$declarations = new StyleDeclarations( [ 'returnType' => 'string' ] );

		if ( $flags['isHorizontal'] ) {
			$declarations->add( 'max-width', '100%' );
			$declarations->add( 'width', '100%' );
			$declarations->add( 'min-width', '0' );
			$declarations->add( 'overflow-x', 'auto' );
		} elseif ( $flags['isVertical'] ) {
			// Reset horizontal-specific wrapper sizing that may cascade from a wider horizontal breakpoint.
			$declarations->add( 'max-width', 'initial' );
			$declarations->add( 'width', 'initial' );
			$declarations->add( 'min-width', 'initial' );
			$declarations->add( 'overflow-x', 'initial' );
		}

		return $declarations->value();
	}

	/**
	 * Track module style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function track_style_declaration( array $params ): string {
		$flags        = self::get_behavior_flags( $params['attrValue'] ?? [] );
		$declarations = new StyleDeclarations( [ 'returnType' => 'string' ] );

		if ( $flags['isHorizontal'] ) {
			$declarations->add( 'flex-direction', 'row' );
			$declarations->add( 'min-width', '0' );
		} elseif ( $flags['isVertical'] ) {
			// Reset horizontal-specific flex direction that may cascade from a wider horizontal
			// breakpoint. The CSS initial value for flex-direction is 'row', so 'column' must be
			// set explicitly rather than using 'initial'.
			$declarations->add( 'flex-direction', 'column' );
			$declarations->add( 'min-width', 'initial' );
		}

		return $declarations->value();
	}

	/**
	 * Track layout gap declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function track_layout_gap_style_declaration( array $params ): string {
		$attr_value   = $params['attrValue'] ?? [];
		$row_gap      = self::_get_string_style_value( $attr_value, 'rowGap' );
		$column_gap   = self::_get_string_style_value( $attr_value, 'columnGap' );
		$declarations = new StyleDeclarations( [ 'returnType' => 'string' ] );

		if ( '' !== $row_gap ) {
			$declarations->add( 'row-gap', $row_gap );
		}

		if ( '' !== $column_gap ) {
			$declarations->add( 'column-gap', $column_gap );
		}

		return $declarations->value();
	}

	/**
	 * Item module style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function item_style_declaration( array $params ): string {
		$attr_value     = $params['attrValue'] ?? [];
		$flags          = self::get_behavior_flags( $attr_value );
		$item_width_raw = self::_get_string_style_value( $attr_value, 'width' );
		$item_width     = '' !== $item_width_raw ? $item_width_raw : '200px';
		$declarations   = new StyleDeclarations( [ 'returnType' => 'string' ] );

		if ( $flags['isVerticalAlternating'] ) {
			$declarations->add( 'grid-template-columns', 'minmax(0, 1fr) auto minmax(0, 1fr)' );
			// Reset horizontal-specific row/width that may cascade from a wider horizontal breakpoint.
			$declarations->add( 'grid-template-rows', 'initial' );
			$declarations->add( 'width', 'unset' );
		} elseif ( $flags['isVerticalLeft'] ) {
			$declarations->add( 'grid-template-columns', '1fr auto auto' );
			$declarations->add( 'grid-template-rows', 'initial' );
			$declarations->add( 'width', 'unset' );
		} elseif ( $flags['isVertical'] ) {
			// Right (default) position: restore the static-SCSS baseline value so any wider-breakpoint
			// left/alternating grid-template-columns (which have higher specificity than the static
			// stylesheet) cannot cascade into this breakpoint.
			$declarations->add( 'grid-template-columns', 'auto auto 1fr' );
			$declarations->add( 'grid-template-rows', 'initial' );
			$declarations->add( 'width', 'unset' );
		} elseif ( $flags['isHorizontalTop'] ) {
			$declarations->add( 'grid-template-rows', '1fr auto auto' );
			$declarations->add( 'grid-template-columns', '1fr' );
			$declarations->add( 'width', $item_width );
		} elseif ( $flags['isHorizontalBottom'] ) {
			$declarations->add( 'grid-template-rows', 'auto auto 1fr' );
			$declarations->add( 'grid-template-columns', '1fr' );
			$declarations->add( 'width', $item_width );
		} elseif ( $flags['isHorizontalAlternating'] ) {
			$declarations->add( 'grid-template-rows', '1fr auto 1fr' );
			$declarations->add( 'grid-template-columns', '1fr' );
			$declarations->add( 'width', $item_width );
		}

		return $declarations->value();
	}

	/**
	 * Even spacer module style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function even_spacer_style_declaration( array $params ): string {
		$flags = self::get_behavior_flags( $params['attrValue'] ?? [] );

		// "Natural order" position (right/bottom): spacer at DOM-first position.
		// nth-child-specific rules are still needed here to override any inherited
		// alternating nth-child rules from a wider breakpoint (which have higher specificity
		// than the all-items selector used by spacer_style_declaration).
		$is_natural_order = ( $flags['isVertical'] && ! $flags['isVerticalLeft'] && ! $flags['isVerticalAlternating'] )
			|| ( $flags['isHorizontal'] && ! $flags['isHorizontalTop'] && ! $flags['isHorizontalAlternating'] );

		$declarations = new StyleDeclarations( [ 'returnType' => 'string' ] );

		if ( $flags['isAlternatingEvenOrder'] || $flags['isVerticalLeft'] || $flags['isHorizontalTop'] ) {
			$declarations->add( 'order', '3' );
		} elseif ( $flags['isAlternatingOddOrder'] || $is_natural_order ) {
			$declarations->add( 'order', '1' );
		}

		return $declarations->value();
	}

	/**
	 * Even ornament module style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function even_ornament_style_declaration( array $params ): string {
		$flags = self::get_behavior_flags( $params['attrValue'] ?? [] );

		// Ornament must always be in the middle grid track (order: 2) whenever sibling spacer/card
		// also carry explicit nth-child order values. Without this, the ornament defaults to order: 0
		// and sorts before the spacer (order: 1) into the first grid track, which has 0 size for
		// bottom/right positions and causes the ornament to be clipped.
		$is_natural_order = ( $flags['isVertical'] && ! $flags['isVerticalLeft'] && ! $flags['isVerticalAlternating'] )
			|| ( $flags['isHorizontal'] && ! $flags['isHorizontalTop'] && ! $flags['isHorizontalAlternating'] );

		$declarations = new StyleDeclarations( [ 'returnType' => 'string' ] );

		if ( $flags['isAlternatingEvenOrder'] || $flags['isAlternatingOddOrder']
			|| $flags['isVerticalLeft'] || $flags['isHorizontalTop'] || $is_natural_order ) {
			$declarations->add( 'order', '2' );
		}

		return $declarations->value();
	}

	/**
	 * Even card module style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function even_card_style_declaration( array $params ): string {
		$flags            = self::get_behavior_flags( $params['attrValue'] ?? [] );
		$is_natural_order = ( $flags['isVertical'] && ! $flags['isVerticalLeft'] && ! $flags['isVerticalAlternating'] )
			|| ( $flags['isHorizontal'] && ! $flags['isHorizontalTop'] && ! $flags['isHorizontalAlternating'] );

		$declarations = new StyleDeclarations( [ 'returnType' => 'string' ] );

		if ( $flags['isAlternatingEvenOrder'] || $flags['isVerticalLeft'] || $flags['isHorizontalTop'] ) {
			$declarations->add( 'order', '1' );
		} elseif ( $flags['isAlternatingOddOrder'] || $is_natural_order ) {
			$declarations->add( 'order', '3' );
		}

		return $declarations->value();
	}

	/**
	 * Odd spacer module style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function odd_spacer_style_declaration( array $params ): string {
		$flags            = self::get_behavior_flags( $params['attrValue'] ?? [] );
		$is_natural_order = ( $flags['isVertical'] && ! $flags['isVerticalLeft'] && ! $flags['isVerticalAlternating'] )
			|| ( $flags['isHorizontal'] && ! $flags['isHorizontalTop'] && ! $flags['isHorizontalAlternating'] );

		$declarations = new StyleDeclarations( [ 'returnType' => 'string' ] );

		if ( $flags['isAlternatingOddOrder'] || $flags['isVerticalLeft'] || $flags['isHorizontalTop'] ) {
			$declarations->add( 'order', '3' );
		} elseif ( $flags['isAlternatingEvenOrder'] || $is_natural_order ) {
			$declarations->add( 'order', '1' );
		}

		return $declarations->value();
	}

	/**
	 * Odd ornament module style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function odd_ornament_style_declaration( array $params ): string {
		$flags = self::get_behavior_flags( $params['attrValue'] ?? [] );

		// Ornament must always be in the middle grid track (order: 2) whenever sibling spacer/card
		// also carry explicit nth-child order values. Without this, the ornament defaults to order: 0
		// and sorts before the spacer (order: 1) into the first grid track, which has 0 size for
		// bottom/right positions and causes the ornament to be clipped.
		$is_natural_order = ( $flags['isVertical'] && ! $flags['isVerticalLeft'] && ! $flags['isVerticalAlternating'] )
			|| ( $flags['isHorizontal'] && ! $flags['isHorizontalTop'] && ! $flags['isHorizontalAlternating'] );

		$declarations = new StyleDeclarations( [ 'returnType' => 'string' ] );

		if ( $flags['isAlternatingOddOrder'] || $flags['isAlternatingEvenOrder']
			|| $flags['isVerticalLeft'] || $flags['isHorizontalTop'] || $is_natural_order ) {
			$declarations->add( 'order', '2' );
		}

		return $declarations->value();
	}

	/**
	 * Odd card module style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function odd_card_style_declaration( array $params ): string {
		$flags            = self::get_behavior_flags( $params['attrValue'] ?? [] );
		$is_natural_order = ( $flags['isVertical'] && ! $flags['isVerticalLeft'] && ! $flags['isVerticalAlternating'] )
			|| ( $flags['isHorizontal'] && ! $flags['isHorizontalTop'] && ! $flags['isHorizontalAlternating'] );

		$declarations = new StyleDeclarations( [ 'returnType' => 'string' ] );

		if ( $flags['isAlternatingOddOrder'] || $flags['isVerticalLeft'] || $flags['isHorizontalTop'] ) {
			$declarations->add( 'order', '1' );
		} elseif ( $flags['isAlternatingEvenOrder'] || $is_natural_order ) {
			$declarations->add( 'order', '3' );
		}

		return $declarations->value();
	}

	/**
	 * Spacer module style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function spacer_style_declaration( array $params ): string {
		$attr_value                 = $params['attrValue'] ?? [];
		$flags                      = self::get_behavior_flags( $attr_value );
		$spacer_display             = self::_get_string_style_value( $attr_value, 'display' );
		$spacer_flex_direction_raw  = self::_get_string_style_value( $attr_value, 'flexDirection' );
		$spacer_flex_direction      = '' !== $spacer_flex_direction_raw ? $spacer_flex_direction_raw : 'column';
		$spacer_column_gap_raw      = self::_get_string_style_value( $attr_value, 'columnGap' );
		$spacer_column_gap          = '' !== $spacer_column_gap_raw ? $spacer_column_gap_raw : '10px';
		$spacer_row_gap_raw         = self::_get_string_style_value( $attr_value, 'rowGap' );
		$spacer_row_gap             = '' !== $spacer_row_gap_raw ? $spacer_row_gap_raw : '10px';
		$declarations               = new StyleDeclarations( [ 'returnType' => 'string' ] );
		$allowed_display_variations = [ 'flex', 'grid', 'block' ];

		if ( $flags['isVerticalLeft'] || $flags['isHorizontalTop'] ) {
			$declarations->add( 'order', '3' );
		}

		if ( in_array( $spacer_display, $allowed_display_variations, true ) ) {
			$declarations->add( 'display', $spacer_display );
		}

		if ( 'flex' === $spacer_display ) {
			$declarations->add( 'flex-direction', $spacer_flex_direction );
			$declarations->add( 'column-gap', $spacer_column_gap );
			$declarations->add( 'row-gap', $spacer_row_gap );
		}

		return $declarations->value();
	}

	/**
	 * Ornament module style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function ornament_style_declaration( array $params ): string {
		$attr_value   = $params['attrValue'] ?? [];
		$flags        = self::get_behavior_flags( $attr_value );
		$direction    = self::_get_string_style_value( $attr_value, 'direction' );
		$declarations = new StyleDeclarations( [ 'returnType' => 'string' ] );

		if ( $flags['isVerticalLeft'] || $flags['isHorizontalTop'] ) {
			$declarations->add( 'order', '2' );
		}

		$declarations->add(
			'flex-direction',
			$flags['isHorizontalTop'] || 'horizontal' === $direction ? 'row' : 'column'
		);

		return $declarations->value();
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
		$declarations = new StyleDeclarations( [ 'returnType' => 'string' ] );

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
	 * Card module style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function card_style_declaration( array $params ): string {
		$attr_value              = $params['attrValue'] ?? [];
		$default_attr_value      = $params['defaultAttrValue'] ?? [];
		$flags                   = self::get_behavior_flags( $attr_value );
		$card_width_raw          = self::_get_string_style_value( $attr_value, 'width' );
		$card_width              = '' !== $card_width_raw ? $card_width_raw : '100%';
		$card_min_width_raw      = self::_get_string_style_value( $attr_value, 'minWidth' );
		$card_min_width          = '' !== $card_min_width_raw ? $card_min_width_raw : '0';
		$card_display            = self::_get_string_style_value( $attr_value, 'display' );
		$default_card_display    = self::_get_string_style_value( $default_attr_value, 'display' );
		$card_flex_direction_raw = self::_get_string_style_value( $attr_value, 'flexDirection' );
		$card_flex_direction     = '' !== $card_flex_direction_raw ? $card_flex_direction_raw : 'column';
		$card_column_gap_raw     = self::_get_string_style_value( $attr_value, 'columnGap' );
		$card_column_gap         = '' !== $card_column_gap_raw ? $card_column_gap_raw : '10px';
		$card_row_gap_raw        = self::_get_string_style_value( $attr_value, 'rowGap' );
		$card_row_gap            = '' !== $card_row_gap_raw ? $card_row_gap_raw : '10px';
		$declarations            = new StyleDeclarations( [ 'returnType' => 'string' ] );

		if ( $flags['isVerticalAlternating'] ) {
			$declarations->add( 'min-width', $card_min_width );
			$declarations->add( 'width', $card_width );
		} elseif ( $flags['isVertical'] ) {
			// Left or right position: reset any min-width/width that a wider breakpoint's alternating
			// position may have set, since those are only meaningful for the alternating grid layout.
			$declarations->add( 'min-width', 'unset' );
			$declarations->add( 'width', 'unset' );
		}

		if ( $flags['isVerticalLeft'] || $flags['isHorizontalTop'] ) {
			$declarations->add( 'order', '1' );
		}

		$should_print_card_display = in_array( $card_display, [ 'flex', 'grid', 'block' ], true )
			&& ! ( 'block' === $card_display && 'block' === $default_card_display );

		if ( $should_print_card_display ) {
			$declarations->add( 'display', $card_display );
		}

		if ( 'flex' === $card_display ) {
			$declarations->add( 'flex-direction', $card_flex_direction );
			$declarations->add( 'column-gap', $card_column_gap );
			$declarations->add( 'row-gap', $card_row_gap );
		}

		return $declarations->value();
	}

	/**
	 * Connector module style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function connector_style_declaration( array $params ): string {
		$attr_value       = $params['attrValue'] ?? [];
		$flags            = self::get_behavior_flags( $attr_value );
		$connector_width  = self::_get_string_style_value( $attr_value, 'width' );
		$connector_height = self::_get_string_style_value( $attr_value, 'height' );
		$row_gap          = self::_get_string_style_value( $attr_value, 'rowGap' );
		$column_gap       = self::_get_string_style_value( $attr_value, 'columnGap' );
		$declarations     = new StyleDeclarations( [ 'returnType' => 'string' ] );

		if ( $flags['isHorizontal'] ) {
			$declarations->add( 'top', 'calc(50% - 1px)' );
			$declarations->add( 'left', '0' );
			$declarations->add( 'right', '-24px' );
			$connector_height_value = '' !== $connector_height
				? $connector_height
				: ( '' !== $connector_width ? $connector_width : '2px' );
			$declarations->add( 'height', $connector_height_value );
			$declarations->add( 'width', 'auto' );

			if ( '' !== $column_gap ) {
				$declarations->add( 'right', '-' . $column_gap );
			}
		} elseif ( $flags['isVertical'] ) {
			// Reset horizontal-specific connector positioning that may cascade from a wider horizontal
			// breakpoint. Values match the static-SCSS baseline so specificity is restored correctly.
			$declarations->add( 'top', '0' );
			$declarations->add( 'left', '10px' );
			$declarations->add( 'right', 'initial' );
			$declarations->add( 'height', 'initial' );
			$declarations->add( 'width', '2px' );

			if ( '' !== $row_gap ) {
				$declarations->add( 'bottom', '-' . $row_gap );
			}
		}

		return $declarations->value();
	}

	/**
	 * Last connector module style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function last_connector_style_declaration( array $params ): string {
		$flags        = self::get_behavior_flags( $params['attrValue'] ?? [] );
		$declarations = new StyleDeclarations( [ 'returnType' => 'string' ] );

		if ( $flags['isHorizontal'] ) {
			$declarations->add( 'right', '0' );
		} elseif ( $flags['isVertical'] ) {
			$declarations->add( 'bottom', '0' );
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
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/timeline' )->customCssFields;
	}

	/**
	 * Load timeline module.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/timeline/';

		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
