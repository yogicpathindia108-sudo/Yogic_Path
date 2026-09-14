<?php
/**
 * Module Library: Tooltip Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Tooltip;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\ScriptData;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use WP_Block;

/**
 * TooltipModule class.
 *
 * @since ??
 */
class TooltipModule implements DependencyInterface {

	/**
	 * Module script data registration.
	 *
	 * @since ??
	 *
	 * @param array $args Script data arguments.
	 *
	 * @return void
	 */
	public static function module_script_data( array $args ): void {
		$elements = $args['elements'];
		$attrs    = $args['attrs'] ?? [];
		$selector = $args['selector'] ?? '';

		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'content',
			]
		);

		// Register tooltip config in script data for the frontend (VB uses the React registry).
		if ( ! Conditions::is_vb_enabled() && '' !== $selector ) {
			ScriptData::add_data_item(
				[
					'data_name'    => 'tooltip',
					'data_item_id' => null,
					'data_item'    => [
						'selector' => $selector,
						'config'   => self::_get_tooltip_config_array( $attrs ),
					],
				]
			);
		}
	}

	/**
	 * Module classnames callback.
	 *
	 * @since ??
	 *
	 * @param array $args Classnames arguments.
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
	 * Module styles callback.
	 *
	 * @since ??
	 *
	 * @param array $args Style arguments.
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs                    = $args['attrs'] ?? [];
		$elements                 = $args['elements'];
		$settings                 = $args['settings'] ?? [];
		$order_class              = $args['orderClass'] ?? '';
		$tooltip_arrow_style_attr = array_replace_recursive(
			[],
			$attrs['module']['advanced']['tooltip'] ?? [],
			$attrs['module']['decoration']['background'] ?? []
		);

		$module_style = $elements->style(
			[
				'attrName'   => 'module',
				'styleProps' => [
					'disabledOn'     => [
						'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
					],
					'advancedStyles' => [
						[
							'componentName' => 'divi/common',
							'props'         => [
								'selector'            => "{$order_class}.et_pb_tooltip--open[data-et-tooltip-placement]",
								'important'           => true,
								'attr'                => $tooltip_arrow_style_attr,
								'declarationFunction' => function ( array $params ) use ( $attrs ) {
									return self::_tooltip_arrow_overflow_visible_declaration( $params, $attrs );
								},
							],
						],
						[
							'componentName' => 'divi/common',
							'props'         => [
								'selector'            => "{$order_class}[data-et-tooltip-placement]",
								'important'           => true,
								'attr'                => $tooltip_arrow_style_attr,
								'declarationFunction' => function ( array $params ) use ( $attrs ) {
									return self::_tooltip_arrow_css_variables_declaration( $params, $attrs );
								},
							],
						],
					],
				],
			]
		);

		$content_style = $elements->style(
			[
				'attrName' => 'content',
			]
		);

		$css_style = CssStyle::style(
			[
				'selector'  => $order_class,
				'attr'      => $attrs['css'] ?? [],
				'cssFields' => \WP_Block_Type_Registry::get_instance()->get_registered( 'divi/tooltip' )->customCssFields ?? [],
			]
		);

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					$module_style,
					$content_style,
					$css_style,
				],
			]
		);
	}

	/**
	 * Keeps the tooltip surface uncropped so the `::after` arrow can extend past border radius / overflow rules.
	 *
	 * @since ??
	 *
	 * @param array $params Style declaration params (`breakpoint`, `state`, etc.).
	 * @param array $attrs  Full module attributes.
	 *
	 * @return string
	 */
	private static function _tooltip_arrow_overflow_visible_declaration( array $params, array $attrs ): string {
		$tooltip_attr = $attrs['module']['advanced']['tooltip'] ?? [];
		$breakpoint   = $params['breakpoint'] ?? 'desktop';

		$merged = ModuleUtils::use_attr_value(
			[
				'attr'         => $tooltip_attr,
				'breakpoint'   => $breakpoint,
				'defaultValue' => [],
				'mode'         => 'getAndInheritAll',
				'state'        => $params['state'] ?? 'value',
			]
		);

		if ( ! is_array( $merged ) ) {
			$merged = [];
		}

		$show_arrow = isset( $merged['showArrow'] ) && ( 'on' === $merged['showArrow'] || true === $merged['showArrow'] );

		if ( ! $show_arrow ) {
			return '';
		}

		$declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => true,
			]
		);

		$declarations->add( 'overflow', 'visible' );

		return $declarations->value();
	}

	/**
	 * Outputs tooltip arrow custom properties for the FE static style pipeline (matches `module-styles.tsx`).
	 *
	 * @since ??
	 *
	 * @param array $params Style declaration params (`breakpoint`, `state`, etc.).
	 * @param array $attrs  Full module attributes.
	 *
	 * @return string
	 */
	private static function _tooltip_arrow_css_variables_declaration( array $params, array $attrs ): string {
		$tooltip_attr = $attrs['module']['advanced']['tooltip'] ?? [];
		$breakpoint   = $params['breakpoint'] ?? 'desktop';

		$merged = ModuleUtils::use_attr_value(
			[
				'attr'         => $tooltip_attr,
				'breakpoint'   => $breakpoint,
				'defaultValue' => [],
				'mode'         => 'getAndInheritAll',
				'state'        => $params['state'] ?? 'value',
			]
		);

		if ( ! is_array( $merged ) ) {
			$merged = [];
		}

		$show_arrow = isset( $merged['showArrow'] ) && ( 'on' === $merged['showArrow'] || true === $merged['showArrow'] );

		if ( ! $show_arrow ) {
			return '';
		}

		$declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => true,
			]
		);

		$background_attr = $attrs['module']['decoration']['background'] ?? [];
		$background      = ModuleUtils::use_attr_value(
			[
				'attr'         => $background_attr,
				'breakpoint'   => $breakpoint,
				'defaultValue' => [ 'color' => '#000000' ],
				'mode'         => 'getAndInheritAll',
				'state'        => $params['state'] ?? 'value',
			]
		);

		if ( ! is_array( $background ) ) {
			$background = [];
		}

		$stored_arrow = array_key_exists( 'arrowColor', $merged ) ? trim( (string) $merged['arrowColor'] ) : null;

		if ( is_string( $stored_arrow ) && '' !== $stored_arrow ) {
			$arrow_color = $stored_arrow;
		} elseif ( array_key_exists( 'arrowColor', $merged ) ) {
			$arrow_color = $stored_arrow;
		} else {
			$arrow_color = isset( $background['color'] ) ? trim( (string) $background['color'] ) : '';

			if ( '' === $arrow_color ) {
				$arrow_color = '#000000';
			}
		}

		$arrow_offset = isset( $merged['arrowOffset'] ) ? trim( (string) $merged['arrowOffset'] ) : '';
		$arrow_size   = isset( $merged['arrowSize'] ) ? trim( (string) $merged['arrowSize'] ) : '';

		$declarations->add( '--et-tooltip-arrow-color', $arrow_color );

		if ( '' !== $arrow_offset ) {
			$declarations->add( '--et-tooltip-arrow-offset', $arrow_offset );
		}

		if ( '' !== $arrow_size ) {
			$declarations->add( '--et-tooltip-arrow-size', $arrow_size );
		}

		return $declarations->value();
	}

	/**
	 * Parses delay from `divi/range` strings (e.g. `100ms`) or numeric values.
	 *
	 * @param mixed $raw Raw attribute value.
	 * @param int   $default Default milliseconds when missing or invalid.
	 *
	 * @return int Non-negative milliseconds.
	 */
	private static function _tooltip_delay( $raw, int $default ): int {
		if ( null === $raw || '' === $raw ) {
			return $default;
		}

		if ( is_numeric( $raw ) ) {
			return max( 0, (int) round( (float) $raw ) );
		}

		if ( is_string( $raw ) && 1 === preg_match( '/^(-?\d+(?:\.\d+)?)\s*ms$/i', trim( $raw ), $matches ) ) {
			return max( 0, (int) round( (float) $matches[1] ) );
		}

		return max( 0, (int) $raw );
	}

	/**
	 * Inner grid picker strings (keep in sync with module-library decode-tooltip-picker-cell).
	 *
	 * @return string[]
	 */
	private static function _valid_inside_picker_values(): array {
		$values = [];

		foreach ( [ 'top', 'center', 'bottom' ] as $row ) {
			foreach ( [ 'left', 'center', 'right' ] as $col ) {
				$values[] = "inside {$row} {$col}";
			}
		}

		return $values;
	}

	/**
	 * Outside ring picker strings (keep in sync with module-library decode-tooltip-picker-cell).
	 *
	 * @return string[]
	 */
	private static function _valid_outside_picker_values(): array {
		$values = [];

		foreach ( [ 'top', 'bottom' ] as $edge ) {
			foreach ( [ 'left', 'center', 'right' ] as $align ) {
				$values[] = "outside {$edge} {$align}";
			}
		}

		foreach ( [ 'left', 'right' ] as $edge ) {
			foreach ( [ 'top', 'center', 'bottom' ] as $align ) {
				$values[] = "outside {$edge} {$align}";
			}
		}

		return $values;
	}

	/**
	 * All tooltip position picker strings (inside + outside).
	 *
	 * @return string[]
	 */
	private static function _valid_tooltip_placement_picker_values(): array {
		return array_merge( self::_valid_inside_picker_values(), self::_valid_outside_picker_values() );
	}

	/**
	 * Parses a placement-grid picker cell.
	 *
	 * @param string $stored Picker string.
	 *
	 * @return array{placement: string, placementBounds: string, edgeAlignment: string}|null
	 */
	private static function _parse_picker_cell( string $stored ): ?array {
		$parts = explode( ' ', $stored );

		if ( 3 !== count( $parts ) ) {
			return null;
		}

		$placement_bounds = $parts[0];
		$first            = $parts[1];
		$second           = $parts[2];

		if ( ! in_array( $placement_bounds, [ 'inside', 'outside' ], true ) ) {
			return null;
		}

		if ( 'inside' === $placement_bounds && 'center' === $first ) {
			if ( 'left' === $second ) {
				return [
					'placement'       => 'left',
					'placementBounds' => $placement_bounds,
					'edgeAlignment'   => 'center',
				];
			}

			if ( 'right' === $second ) {
				return [
					'placement'       => 'right',
					'placementBounds' => $placement_bounds,
					'edgeAlignment'   => 'center',
				];
			}

			if ( 'center' === $second ) {
				return [
					'placement'       => 'top',
					'placementBounds' => $placement_bounds,
					'edgeAlignment'   => 'center',
				];
			}

			return null;
		}

		return [
			'placement'       => $first,
			'placementBounds' => $placement_bounds,
			'edgeAlignment'   => $second,
		];
	}

	/**
	 * Decodes stored `placement` picker string.
	 *
	 * @param array $tooltip Tooltip field values (flat keys).
	 *
	 * @return array{placement: string, placementBounds: string, edgeAlignment: string}
	 */
	private static function _resolve_tooltip_placement_triple( array $tooltip ): array {
		$stored = isset( $tooltip['placement'] ) ? trim( (string) $tooltip['placement'] ) : '';
		$valid  = self::_valid_tooltip_placement_picker_values();

		if ( ! in_array( $stored, $valid, true ) ) {
			$stored = 'outside top center';
		}

		$cell = self::_parse_picker_cell( $stored );

		if ( null === $cell ) {
			return [
				'placement'       => 'top',
				'placementBounds' => 'outside',
				'edgeAlignment'   => 'center',
			];
		}

		return $cell;
	}

	/**
	 * Decodes stored `arrowPlacement` picker string.
	 *
	 * @param array $tooltip Tooltip field values (flat keys).
	 *
	 * @return array{arrowPlacement: string, arrowAlignment: string}
	 */
	private static function _resolve_tooltip_arrow_pair( array $tooltip ): array {
		$stored = isset( $tooltip['arrowPlacement'] ) ? trim( (string) $tooltip['arrowPlacement'] ) : '';
		$valid  = self::_valid_outside_picker_values();

		if ( ! in_array( $stored, $valid, true ) ) {
			$stored = 'outside bottom center';
		}

		$cell = self::_parse_picker_cell( $stored );

		if ( null === $cell || 'outside' !== $cell['placementBounds'] ) {
			return [
				'arrowPlacement' => 'bottom',
				'arrowAlignment' => 'center',
			];
		}

		return [
			'arrowPlacement' => $cell['placement'],
			'arrowAlignment' => $cell['edgeAlignment'],
		];
	}

	/**
	 * Builds a single tooltip runtime config array from the merged `value` blob (one breakpoint).
	 *
	 * @param array $tooltip Tooltip field values (flat keys).
	 * @param array $sizing  Module sizing values (flat keys).
	 *
	 * @return array<string, mixed>
	 */
	private static function _build_tooltip_config_array( array $tooltip, array $sizing = [] ): array {
		$open_raw  = $tooltip['openDelay'] ?? null;
		$close_raw = $tooltip['closeDelay'] ?? null;

		$show_arrow = isset( $tooltip['showArrow'] ) && ( 'on' === $tooltip['showArrow'] || true === $tooltip['showArrow'] );

		$position_mode = $tooltip['positionMode'] ?? 'anchored';
		if ( 'followCursor' !== $position_mode ) {
			$position_mode = 'anchored';
		}

		$placement_triple = self::_resolve_tooltip_placement_triple( $tooltip );
		$arrow_pair       = self::_resolve_tooltip_arrow_pair( $tooltip );

		$width_value = isset( $sizing['width'] ) ? (string) $sizing['width'] : '';

		return [
			'trigger'            => $tooltip['trigger'] ?? 'hover',
			'positionMode'       => $position_mode,
			'placement'          => $placement_triple['placement'],
			'placementBounds'    => $placement_triple['placementBounds'],
			'edgeAlignment'      => $placement_triple['edgeAlignment'],
			'skid'               => isset( $tooltip['skid'] ) ? (string) $tooltip['skid'] : '',
			'distance'           => isset( $tooltip['distance'] ) ? (string) $tooltip['distance'] : '',
			'openDelay'          => self::_tooltip_delay( $open_raw, 0 ),
			'closeDelay'         => self::_tooltip_delay( $close_raw, 0 ),
			'runtimeAnchorWidth' => 'max-content' === $width_value,
			'showArrow'          => $show_arrow,
			'arrowPlacement'     => $arrow_pair['arrowPlacement'],
			'arrowAlignment'     => $arrow_pair['arrowAlignment'],
			'arrowOffset'        => isset( $tooltip['arrowOffset'] ) ? (string) $tooltip['arrowOffset'] : '',
			'arrowSize'          => isset( $tooltip['arrowSize'] ) ? (string) $tooltip['arrowSize'] : '',
		];
	}

	/**
	 * Builds a breakpoint-keyed config array for the frontend tooltip script data.
	 *
	 * Emits breakpoint-keyed configs using merged values (`getAndInheritAll`) for each enabled breakpoint.
	 * Used by `ScriptData::add_data_item`; the result is localised via `wp_localize_script` so no JSON encoding needed.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attributes.
	 *
	 * @return array<string, array<string, mixed>> Breakpoint-keyed config map.
	 */
	private static function _get_tooltip_config_array( array $attrs ): array {
		$tooltip_attr = $attrs['module']['advanced']['tooltip'] ?? [];
		$sizing_attr  = $attrs['module']['decoration']['sizing'] ?? [];
		$configs      = [];

		foreach ( Breakpoint::get_enabled_breakpoint_names() as $breakpoint ) {
			$merged = ModuleUtils::use_attr_value(
				[
					'attr'         => $tooltip_attr,
					'breakpoint'   => $breakpoint,
					'defaultValue' => [],
					'mode'         => 'getAndInheritAll',
					'state'        => 'value',
				]
			);

			if ( ! is_array( $merged ) ) {
				$merged = [];
			}

			$sizing = ModuleUtils::use_attr_value(
				[
					'attr'         => $sizing_attr,
					'breakpoint'   => $breakpoint,
					'defaultValue' => [],
					'mode'         => 'getAndInheritAll',
					'state'        => 'value',
				]
			);

			if ( ! is_array( $sizing ) ) {
				$sizing = [];
			}

			$configs[ $breakpoint ] = self::_build_tooltip_config_array( $merged, $sizing );
		}

		if ( empty( $configs ) ) {
			$configs['desktop'] = self::_build_tooltip_config_array( [] );
		}

		return $configs;
	}

	/**
	 * Initial `data-et-tooltip-placement` for SSR when the arrow is enabled (matches VB `htmlAttrs`).
	 *
	 * @since ??
	 *
	 * @param array $attrs Block attributes.
	 *
	 * @return string|null Placement edge or null when the arrow is off.
	 */
	private static function _initial_tooltip_placement_attr( array $attrs ): ?string {
		$tooltip = $attrs['module']['advanced']['tooltip']['desktop']['value'] ?? [];

		if ( ! isset( $tooltip['showArrow'] ) || ( 'on' !== $tooltip['showArrow'] && true !== $tooltip['showArrow'] ) ) {
			return null;
		}

		$arrow_pair = self::_resolve_tooltip_arrow_pair( $tooltip );

		return $arrow_pair['arrowPlacement'];
	}

	/**
	 * Stable DOM id for the tooltip surface (role="tooltip") used with aria-describedby.
	 *
	 * @since ??
	 *
	 * @param string $block_id Parsed block id (builder-generated, e.g. UUID).
	 *
	 * @return string
	 */
	private static function _tooltip_dom_id( string $block_id ): string {
		return 'et_pb_tooltip_' . $block_id;
	}

	/**
	 * Render callback.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes.
	 * @param string         $child_modules_content       Inner blocks HTML.
	 * @param WP_Block       $block                       Block instance.
	 * @param ModuleElements $elements                    Module elements helper.
	 * @param array          $default_printed_style_attrs Default printed style attrs from registration (unused).
	 *
	 * @return string
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ): string {
		$children_ids = ChildrenUtils::extract_children_ids( $block );
		$parent       = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$is_last      = BlockParserStore::is_last( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		// Render body rich text only here; append nested modules after `.et_pb_tooltip_inner`.
		$content_html = $elements->render(
			[
				'attrName' => 'content',
			]
		);

		$tooltip_id = self::_tooltip_dom_id( (string) ( $block->parsed_block['id'] ?? '' ) );

		$html_attrs = [
			'role'        => 'tooltip',
			'aria-hidden' => 'true',
			'id'          => $tooltip_id,
		];

		$parent_id = ( null !== $parent && isset( $parent->id ) ) ? (string) $parent->id : '';

		if ( '' !== $parent_id ) {
			$html_attrs['data-et-tooltip-parent-id'] = esc_attr( $parent_id );
		}

		$initial_placement = self::_initial_tooltip_placement_attr( $attrs );

		if ( null !== $initial_placement ) {
			$html_attrs['data-et-tooltip-placement'] = esc_attr( $initial_placement );
		}

		return Module::render(
			[
				'orderIndex'                => $block->parsed_block['orderIndex'],
				'storeInstance'             => $block->parsed_block['storeInstance'],
				'attrs'                     => $attrs,
				'elements'                  => $elements,
				'id'                        => $block->parsed_block['id'],
				'isLast'                    => $is_last,
				'childrenIds'               => $children_ids,
				'name'                      => $block->block_type->name,
				'moduleCategory'            => $block->block_type->category,
				'defaultPrintedStyleAttrs'  => $default_printed_style_attrs,
				'classnamesFunction'        => [ self::class, 'module_classnames' ],
				'stylesComponent'           => [ self::class, 'module_styles' ],
				'scriptDataComponent'       => [ self::class, 'module_script_data' ],
				'parentAttrs'               => $parent->attrs ?? [],
				'parentId'                  => $parent->id ?? '',
				'parentName'                => $parent->blockName ?? '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
				'htmlAttrs'                 => $html_attrs,
				'children'                  => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $content_html . $child_modules_content,
			]
		);
	}

	/**
	 * Registers the module.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/tooltip/';

		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
