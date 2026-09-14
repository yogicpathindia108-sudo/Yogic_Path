<?php
/**
 * Module Library: Charts Module.
 *
 * @package Divi
 * @since   ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Charts;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block.

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\ScriptData;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * ChartsModule class.
 *
 * @since ??
 */
class ChartsModule implements DependencyInterface {

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
		$attrs               = $args['attrs'] ?? [];

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
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];
		$settings = $args['settings'] ?? [];

		$module_style = $elements->style(
			[
				'attrName'   => 'module',
				'styleProps' => [
					'disabledOn' => [
						'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
					],
				],
			]
		);

		$chart_style = $elements->style(
			[
				'attrName' => 'chart',
			]
		);

		$css_style = CssStyle::style(
			[
				'selector'  => $args['orderClass'] ?? '',
				'attr'      => $attrs['css'] ?? [],
				'cssFields' => WP_Block_Type_Registry::get_instance()->get_registered( 'divi/charts' )->customCssFields ?? [],
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
					$chart_style,
					$css_style,
				],
			]
		);
	}

	/**
	 * Module script data callback.
	 *
	 * @since ??
	 *
	 * @param array $args Script data arguments.
	 *
	 * @return void
	 */
	public static function module_script_data( array $args ): void {
		$elements = $args['elements'];
		$selector = $args['selector'] ?? '';
		$attrs    = $args['attrs'] ?? [];
		$id       = $args['id'] ?? '';

		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'chart',
			]
		);

		if ( Conditions::is_vb_enabled() || '' === $selector || '' === $id ) {
			return;
		}

		$chart_config = self::_build_chart_config( $attrs );

		if ( null === $chart_config ) {
			return;
		}

		ScriptData::add_data_item(
			[
				'data_name'    => 'charts',
				'data_item_id' => null,
				'data_item'    => [
					'selector' => $selector,
					'moduleId' => $id,
					'config'   => $chart_config,
					'attrs'    => $attrs,
				],
			]
		);
	}

	/**
	 * Render callback.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                 Block attributes.
	 * @param string         $child_modules_content Child modules content.
	 * @param WP_Block       $block                 Block instance.
	 * @param ModuleElements $elements              Module elements helper.
	 *
	 * @return string
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements ): string {
		$children_ids = ChildrenUtils::extract_children_ids( $block );
		$parent       = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$module_id    = (string) ( $block->parsed_block['id'] ?? '' );
		$chart_data   = self::_resolve_mode_value( $attrs['chart']['innerContent'] ?? [] );
		$chart_title  = isset( $chart_data['title'] ) ? trim( wp_strip_all_tags( (string) $chart_data['title'] ) ) : '';

		$canvas_aria_label = '' !== $chart_title
			? $chart_title
			: esc_attr__( 'Chart', 'et_builder_5' );

		$chart_render_target = HTMLUtility::render(
			[
				'tag'        => 'canvas',
				'attributes' => [
					'class'      => 'et_pb_charts__canvas',
					'role'       => 'img',
					'aria-label' => $canvas_aria_label,
				],
			]
		);

		$parent_attrs = is_object( $parent ) ? ( $parent->attrs ?? [] ) : [];
		$parent_id    = is_object( $parent ) ? ( $parent->id ?? '' ) : '';
		$parent_name  = is_object( $parent ) ? ( $parent->{'blockName'} ?? '' ) : '';

		$chart_content = $elements->render(
			[
				'attrName'          => 'chart',
				'skipAttrChildren'  => true,
				'attributes'        => [
					'data-module-id' => $module_id,
				],
				'children'          => $elements->style_components(
					[
						'attrName' => 'chart',
					]
				) . $chart_render_target,
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		return Module::render(
			[
				// FE only.
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'               => $attrs,
				'elements'            => $elements,
				'id'                  => $module_id,
				'name'                => $block->block_type->name,
				'moduleCategory'      => $block->block_type->category,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'parentAttrs'         => $parent_attrs,
				'parentId'            => $parent_id,
				'parentName'          => $parent_name,
				'childrenIds'         => $children_ids,
				'children'            => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $chart_content . $child_modules_content,
				'childrenSanitizer'   => 'et_core_esc_previously',
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
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/charts/';

		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}

	/**
	 * Build a Chart.js compatible config from module attrs.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attributes.
	 *
	 * @return array|null
	 */
	private static function _build_chart_config( array $attrs ): ?array {
		$chart_content = self::_resolve_mode_value( $attrs['chart']['innerContent'] ?? [] );
		$chart_config  = self::_resolve_mode_value( $attrs['chart']['advanced']['config'] ?? [] );

		// Pass the raw design values (not mode-resolved) so downstream helpers can read sibling
		// subgroups such as `layout`, `labels`, `markers`, `title`, `box`, `colorBoxes`, `titleFont`,
		// and `bodyFont`. Resolving the mode value here would return only the base object and discard
		// those siblings, matching the VB `buildChartConfig` behavior.
		$chart_design = [
			'title'    => $attrs['chart']['advanced']['title'] ?? [],
			'subtitle' => $attrs['chart']['advanced']['subtitle'] ?? [],
			'legend'   => $attrs['chart']['advanced']['legend'] ?? [],
			'tooltip'  => $attrs['chart']['advanced']['tooltip'] ?? [],
		];
		$palette       = self::_default_palette();
		$chart_type    = $chart_config['type'] ?? 'line';
		$raw_columns   = is_array( $chart_content['data']['columns'] ?? null ) ? $chart_content['data']['columns'] : [];
		$role_columns  = self::_get_chart_columns_by_role( $chart_type, $raw_columns );
		$rows          = $chart_content['data']['rows'] ?? [];

		if ( null === $role_columns || 0 === count( $rows ) ) {
			return null;
		}

		if ( 'scatter' === $chart_type ) {
			return self::_build_scatter_config( $role_columns, $rows, $chart_content, $chart_config, $chart_design, $palette );
		}

		if ( 'bubble' === $chart_type ) {
			return self::_build_bubble_config( $role_columns, $rows, $chart_content, $chart_config, $chart_design, $palette );
		}

		return self::_build_standard_config( $chart_type, $role_columns, $rows, $raw_columns, $chart_content, $chart_config, $chart_design );
	}

	/**
	 * Build scatter chart config.
	 *
	 * @since ??
	 *
	 * @param array $role_columns  Role-grouped chart columns.
	 * @param array $rows          Data rows.
	 * @param array $chart_content Chart content values.
	 * @param array $chart_config  Chart config values.
	 * @param array $chart_design  Chart design values.
	 * @param array $palette       Resolved palette values.
	 *
	 * @return array
	 */
	private static function _build_scatter_config(
		array $role_columns,
		array $rows,
		array $chart_content,
		array $chart_config,
		array $chart_design,
		array $palette
	): array {
		$x_column = $role_columns['x'] ?? [];
		$y_column = $role_columns['y'] ?? [];
		$labels   = [];

		foreach ( $rows as $index => $row ) {
			unset( $row );
			$labels[] = (string) ( $index + 1 );
		}

		$row_point_colors = self::_build_row_point_color_arrays( $rows );

		return [
			'type'    => 'scatter',
			'data'    => [
				'labels'   => $labels,
				'datasets' => [
					[
						'label'           => $y_column['label'] ?? '',
						'data'            => array_map(
							function ( $row ) use ( $x_column, $y_column ) {
								$cells = self::_get_row_cells( $row );

								return [
									'x' => self::_to_number( $cells[ $x_column['index'] ?? 0 ] ?? null ),
									'y' => self::_to_number( $cells[ $y_column['index'] ?? 0 ] ?? null ),
								];
							},
							$rows
						),
						'backgroundColor' => $row_point_colors['backgroundColor'],
						'borderColor'     => $row_point_colors['borderColor'],
						'borderWidth'     => 1,
					],
				],
			],
			'options' => self::_build_chart_options( $chart_content, $chart_config, $chart_design ),
		];
	}

	/**
	 * Build bubble chart config.
	 *
	 * @since ??
	 *
	 * @param array $role_columns  Role-grouped chart columns.
	 * @param array $rows          Data rows.
	 * @param array $chart_content Chart content values.
	 * @param array $chart_config  Chart config values.
	 * @param array $chart_design  Chart design values.
	 * @param array $palette       Resolved palette values.
	 *
	 * @return array
	 */
	private static function _build_bubble_config(
		array $role_columns,
		array $rows,
		array $chart_content,
		array $chart_config,
		array $chart_design,
		array $palette
	): array {
		$x_column    = $role_columns['x'] ?? [];
		$y_column    = $role_columns['y'] ?? [];
		$size_column = $role_columns['size'] ?? [];
		$labels      = [];

		foreach ( $rows as $index => $row ) {
			unset( $row );
			$labels[] = (string) ( $index + 1 );
		}

		$row_point_colors = self::_build_row_point_color_arrays( $rows );

		return [
			'type'    => 'bubble',
			'data'    => [
				'labels'   => $labels,
				'datasets' => [
					[
						'label'           => $y_column['label'] ?? '',
						'data'            => array_map(
							function ( $row ) use ( $x_column, $y_column, $size_column ) {
								$cells = self::_get_row_cells( $row );

								return [
									'x' => self::_to_number( $cells[ $x_column['index'] ?? 0 ] ?? null ),
									'y' => self::_to_number( $cells[ $y_column['index'] ?? 0 ] ?? null ),
									'r' => self::_to_number( $cells[ $size_column['index'] ?? 0 ] ?? null ),
								];
							},
							$rows
						),
						'backgroundColor' => $row_point_colors['backgroundColor'],
						'borderColor'     => $row_point_colors['borderColor'],
						'borderWidth'     => 1,
					],
				],
			],
			'options' => self::_build_chart_options( $chart_content, $chart_config, $chart_design ),
		];
	}

	/**
	 * Build config for line/area/bar/radar/pie/doughnut/polarArea.
	 *
	 * @since ??
	 *
	 * @param string $chart_type    Requested chart type.
	 * @param array  $role_columns  Role-grouped chart columns.
	 * @param array  $rows          Data rows.
	 * @param array  $raw_columns   Raw chart columns.
	 * @param array  $chart_content Chart content values.
	 * @param array  $chart_config  Chart config values.
	 * @param array  $chart_design  Chart design values.
	 *
	 * @return array
	 */
	private static function _build_standard_config(
		string $chart_type,
		array $role_columns,
		array $rows,
		array $raw_columns,
		array $chart_content,
		array $chart_config,
		array $chart_design
	): array {
		$normalized_type = 'area' === $chart_type ? 'line' : $chart_type;
		$labels          = [];
		$datasets        = [];

		if ( 'categoryValue' === ( $role_columns['family'] ?? '' ) ) {
			$category_column = $role_columns['category'] ?? [];
			$value_column    = $role_columns['value'] ?? [];
			$labels          = array_map(
				function ( $row ) use ( $category_column ) {
					$cells       = self::_get_row_cells( $row );
					$label_index = $category_column['index'] ?? 0;

					return isset( $cells[ $label_index ] ) ? (string) $cells[ $label_index ] : '';
				},
				$rows
			);
			$dataset         = [
				'label'           => $value_column['label'] ?? 'Value',
				'data'            => array_map(
					function ( $row ) use ( $value_column ) {
						$cells = self::_get_row_cells( $row );

						return self::_to_number( $cells[ $value_column['index'] ?? 0 ] ?? null );
					},
					$rows
				),
				'backgroundColor' => [],
				'borderColor'     => [],
				'borderWidth'     => 1,
				'fill'            => false,
			];

			foreach ( $rows as $index => $row ) {
				unset( $index );
				$row_color                    = self::_resolve_persisted_data_color( self::_get_row_color( $row ) );
				$dataset['backgroundColor'][] = $row_color;
				$dataset['borderColor'][]     = $row_color;
			}

			$datasets[] = $dataset;
		} else {
			$category_column = $role_columns['category'] ?? [];
			$series_columns  = $role_columns['series'] ?? [];
			$labels          = array_map(
				function ( $row ) use ( $category_column ) {
					$cells       = self::_get_row_cells( $row );
					$label_index = $category_column['index'] ?? 0;

					return isset( $cells[ $label_index ] ) ? (string) $cells[ $label_index ] : '';
				},
				$rows
			);

			foreach ( $series_columns as $column ) {
				$storage_index = $column['index'] ?? null;
				$series_color  = self::_resolve_persisted_data_color(
					is_int( $storage_index ) ? self::_get_column_color( $raw_columns, $storage_index ) : null
				);

				$datasets[] = [
					'label'           => isset( $column['label'] ) ? (string) $column['label'] : '',
					'data'            => array_map(
						function ( $row ) use ( $column ) {
							$cells = self::_get_row_cells( $row );

							return self::_to_number( $cells[ $column['index'] ?? null ] ?? null );
						},
						$rows
					),
					'borderColor'     => $series_color,
					'backgroundColor' => $series_color,
					'borderWidth'     => 1,
					'fill'            => 'area' === $chart_type,
				];
			}
		}

		return [
			'type'    => $normalized_type,
			'data'    => [
				'labels'   => $labels,
				'datasets' => $datasets,
			],
			'options' => self::_build_chart_options( $chart_content, $chart_config, $chart_design ),
		];
	}

	/**
	 * Normalize chart columns into indexed/visible objects.
	 *
	 * Mirrors the Visual Builder column normalization behavior.
	 *
	 * @param array $columns Raw chart columns.
	 *
	 * @return array<int, array{index:int,label:string,visible:bool,role?:string}>
	 */
	private static function _normalize_chart_columns( array $columns ): array {
		$normalized_columns = [];

		foreach ( array_values( $columns ) as $index => $column ) {
			if ( is_array( $column ) && isset( $column['label'] ) && is_string( $column['label'] ) ) {
				$normalized_column = [
					'index'   => $index,
					'label'   => $column['label'],
					'visible' => false !== ( $column['visible'] ?? null ),
				];

				if ( isset( $column['role'] ) && is_string( $column['role'] ) && '' !== $column['role'] ) {
					$normalized_column['role'] = $column['role'];
				}

				$normalized_columns[] = $normalized_column;

				continue;
			}

			$normalized_columns[] = [
				'index'   => $index,
				'label'   => 'Column ' . ( $index + 1 ),
				'visible' => true,
			];
		}

		return $normalized_columns;
	}

	/**
	 * Return visible chart columns only.
	 *
	 * @param array $columns Raw chart columns.
	 *
	 * @return array<int, array{index:int,label:string,visible:bool,role?:string}>
	 */
	private static function _get_visible_chart_columns( array $columns ): array {
		return array_values(
			array_filter(
				self::_normalize_chart_columns( $columns ),
				static function ( array $column ): bool {
					return true === $column['visible'];
				}
			)
		);
	}

	/**
	 * Returns whether a string is a persisted chart column role slug.
	 *
	 * @param string $role Candidate role slug.
	 *
	 * @return bool
	 */
	private static function _is_chart_column_role( string $role ): bool {
		return in_array( $role, [ 'category', 'value', 'series', 'x', 'y', 'size' ], true );
	}

	/**
	 * Returns storage-index → role slug map for visible columns with persisted roles.
	 *
	 * @param string $_chart_type  Unused. Kept for call-site compatibility.
	 * @param array  $raw_columns  Raw chart columns.
	 *
	 * @return array<int, string>
	 */
	private static function _get_column_roles( string $_chart_type, array $raw_columns ): array {
		$visible_columns = self::_get_visible_chart_columns( $raw_columns );
		$roles           = [];

		foreach ( $visible_columns as $column ) {
			$persisted_role = isset( $column['role'] ) && is_string( $column['role'] ) && self::_is_chart_column_role( $column['role'] )
				? $column['role']
				: null;

			if ( is_string( $persisted_role ) && '' !== $persisted_role ) {
				$roles[ $column['index'] ] = $persisted_role;
			}
		}

		return $roles;
	}

	/**
	 * Returns visible normalized columns that match a role slug, sorted by storage index.
	 *
	 * @param array  $normalized_columns Normalized chart columns.
	 * @param array  $roles              Storage-index role map.
	 * @param string $role               Role slug to match.
	 *
	 * @return array<int, array{index:int,label:string,visible:bool,role?:string}>
	 */
	private static function _get_columns_with_role( array $normalized_columns, array $roles, string $role ): array {
		$matches = array_values(
			array_filter(
				$normalized_columns,
				static function ( array $column ) use ( $roles, $role ): bool {
					$index = $column['index'] ?? null;

					return is_int( $index ) && isset( $roles[ $index ] ) && $role === $roles[ $index ];
				}
			)
		);

		usort(
			$matches,
			static function ( array $left_column, array $right_column ): int {
				return ( $left_column['index'] ?? 0 ) <=> ( $right_column['index'] ?? 0 );
			}
		);

		return $matches;
	}

	/**
	 * Resolves chart columns by persisted role for the active chart type.
	 *
	 * @param string $chart_type  Active chart type.
	 * @param array  $raw_columns Raw chart columns.
	 *
	 * @return array|null
	 */
	private static function _get_chart_columns_by_role( string $chart_type, array $raw_columns ): ?array {
		$family = self::_get_chart_column_role_family( $chart_type );

		if ( null === $family ) {
			return null;
		}

		$normalized_columns = self::_normalize_chart_columns( $raw_columns );
		$roles              = self::_get_column_roles( $chart_type, $raw_columns );

		if ( 'categorySeries' === $family ) {
			$category_columns = self::_get_columns_with_role( $normalized_columns, $roles, 'category' );
			$series_columns   = self::_get_columns_with_role( $normalized_columns, $roles, 'series' );

			if ( 0 === count( $category_columns ) || 0 === count( $series_columns ) ) {
				return null;
			}

			return [
				'family'   => 'categorySeries',
				'category' => $category_columns[0],
				'series'   => $series_columns,
			];
		}

		if ( 'categoryValue' === $family ) {
			$category_columns = self::_get_columns_with_role( $normalized_columns, $roles, 'category' );
			$value_columns    = self::_get_columns_with_role( $normalized_columns, $roles, 'value' );

			if ( 0 === count( $category_columns ) || 0 === count( $value_columns ) ) {
				return null;
			}

			return [
				'family'   => 'categoryValue',
				'category' => $category_columns[0],
				'value'    => $value_columns[0],
			];
		}

		if ( 'scatter' === $family ) {
			$x_columns = self::_get_columns_with_role( $normalized_columns, $roles, 'x' );
			$y_columns = self::_get_columns_with_role( $normalized_columns, $roles, 'y' );

			if ( 0 === count( $x_columns ) || 0 === count( $y_columns ) ) {
				return null;
			}

			return [
				'family' => 'scatter',
				'x'      => $x_columns[0],
				'y'      => $y_columns[0],
			];
		}

		$x_columns    = self::_get_columns_with_role( $normalized_columns, $roles, 'x' );
		$y_columns    = self::_get_columns_with_role( $normalized_columns, $roles, 'y' );
		$size_columns = self::_get_columns_with_role( $normalized_columns, $roles, 'size' );

		if ( 0 === count( $x_columns ) || 0 === count( $y_columns ) || 0 === count( $size_columns ) ) {
			return null;
		}

		return [
			'family' => 'bubble',
			'x'      => $x_columns[0],
			'y'      => $y_columns[0],
			'size'   => $size_columns[0],
		];
	}

	/**
	 * Resolves the column-role family for a chart type.
	 *
	 * @param string $chart_type Active chart type.
	 *
	 * @return string|null
	 */
	private static function _get_chart_column_role_family( string $chart_type ): ?string {
		if ( 'scatter' === $chart_type ) {
			return 'scatter';
		}

		if ( 'bubble' === $chart_type ) {
			return 'bubble';
		}

		if ( in_array( $chart_type, [ 'pie', 'doughnut', 'polarArea' ], true ) ) {
			return 'categoryValue';
		}

		if ( in_array( $chart_type, [ 'line', 'area', 'bar', 'radar' ], true ) ) {
			return 'categorySeries';
		}

		return null;
	}

	/**
	 * Build common chart options.
	 *
	 * @since ??
	 *
	 * @param array $chart_content Chart content values.
	 * @param array $chart_config  Chart config values.
	 * @param array $chart_design  Chart design values.
	 *
	 * @return array
	 */
	private static function _build_chart_options( array $chart_content, array $chart_config, array $chart_design ): array {
		$title_text          = isset( $chart_content['title'] ) ? trim( (string) $chart_content['title'] ) : '';
		$subtitle_text       = isset( $chart_content['subtitle'] ) ? trim( (string) $chart_content['subtitle'] ) : '';
		$is_title_visible    = 'off' !== ( $chart_config['showTitle'] ?? 'on' ) && '' !== $title_text;
		$is_subtitle_visible = 'off' !== ( $chart_config['showSubtitle'] ?? 'on' ) && '' !== $subtitle_text;
		$is_legend_visible   = 'off' !== ( $chart_config['showLegend'] ?? 'on' );
		$legend_title_text       = isset( $chart_content['legendTitle'] ) ? trim( (string) $chart_content['legendTitle'] ) : '';
		$is_legend_title_visible = $is_legend_visible && 'off' !== self::_to_on_off( $chart_config['showLegendTitle'] ?? 'off', 'off' ) && '' !== $legend_title_text;
		$is_tooltip_visible  = 'off' !== ( $chart_config['showTooltip'] ?? 'on' );
		$title_design_raw    = isset( $chart_design['title'] ) && is_array( $chart_design['title'] ) ? $chart_design['title'] : [];
		$subtitle_design_raw = isset( $chart_design['subtitle'] ) && is_array( $chart_design['subtitle'] ) ? $chart_design['subtitle'] : [];
		$legend_design_raw   = isset( $chart_design['legend'] ) && is_array( $chart_design['legend'] ) ? $chart_design['legend'] : [];
		$title_design        = self::_resolve_mode_value( $title_design_raw );
		$subtitle_design     = self::_resolve_mode_value( $subtitle_design_raw );
		$legend_layout_raw   = isset( $legend_design_raw['layout'] ) && is_array( $legend_design_raw['layout'] ) ? $legend_design_raw['layout'] : [];
		$legend_labels_raw   = isset( $legend_design_raw['labels'] ) && is_array( $legend_design_raw['labels'] ) ? $legend_design_raw['labels'] : [];
		$legend_markers_raw  = isset( $legend_design_raw['markers'] ) && is_array( $legend_design_raw['markers'] ) ? $legend_design_raw['markers'] : [];
		$legend_title_raw    = isset( $legend_design_raw['title'] ) && is_array( $legend_design_raw['title'] ) ? $legend_design_raw['title'] : [];
		$legend_layout       = self::_resolve_mode_value( $legend_layout_raw );
		$legend_markers      = self::_resolve_mode_value( $legend_markers_raw );
		$legend_title        = self::_resolve_mode_value( $legend_title_raw );
		$tooltip_design_raw      = isset( $chart_design['tooltip'] ) && is_array( $chart_design['tooltip'] ) ? $chart_design['tooltip'] : [];
		$tooltip_box_raw         = isset( $tooltip_design_raw['box'] ) && is_array( $tooltip_design_raw['box'] ) ? $tooltip_design_raw['box'] : [];
		$tooltip_color_boxes_raw = isset( $tooltip_design_raw['colorBoxes'] ) && is_array( $tooltip_design_raw['colorBoxes'] ) ? $tooltip_design_raw['colorBoxes'] : [];
		$tooltip_box             = self::_resolve_mode_value( $tooltip_box_raw );
		$tooltip_color_boxes     = self::_resolve_mode_value( $tooltip_color_boxes_raw );
		$title_font          = self::_normalize_font( $title_design_raw );
		$subtitle_font       = self::_normalize_font( $subtitle_design_raw );
		$legend_label_font   = self::_normalize_font( $legend_labels_raw );
		$legend_title_font   = self::_normalize_font( $legend_title_raw );
		$tooltip_title_style = isset( $tooltip_design_raw['titleFont'] ) && is_array( $tooltip_design_raw['titleFont'] ) ? $tooltip_design_raw['titleFont'] : [];
		$tooltip_body_style  = isset( $tooltip_design_raw['bodyFont'] ) && is_array( $tooltip_design_raw['bodyFont'] ) ? $tooltip_design_raw['bodyFont'] : [];
		$tooltip_title_font  = self::_normalize_font( $tooltip_title_style );
		$tooltip_body_font   = self::_normalize_font( $tooltip_body_style );
		$tooltip_title_color = self::_get_color_from_style( $tooltip_title_style );
		$tooltip_body_color  = self::_get_color_from_style( $tooltip_body_style );
		$title_color         = self::_get_color_from_style( $title_design_raw );
		$subtitle_color      = self::_get_color_from_style( $subtitle_design_raw );
		$title_align         = self::_get_chart_js_align_from_style( $title_design_raw );
		$subtitle_align      = self::_get_chart_js_align_from_style( $subtitle_design_raw );
		$title_text          = self::_apply_text_transform( $title_text, self::_get_text_transform_from_style( $title_design_raw ) );
		$subtitle_text       = self::_apply_text_transform( $subtitle_text, self::_get_text_transform_from_style( $subtitle_design_raw ) );
		$legend_label_color  = self::_get_color_from_style( $legend_markers_raw );
		$legend_title_color  = self::_get_color_from_style( $legend_title_raw );

		$title_options = [
			'display' => $is_title_visible,
			'text'    => $title_text,
		];

		if ( null !== $title_align && '' !== $title_align ) {
			$title_options['align'] = $title_align;
		}

		if ( null !== $title_color && '' !== $title_color ) {
			$title_options['color'] = $title_color;
		}

		if ( ! empty( $title_font ) ) {
			$title_options['font'] = $title_font;
		}

		$subtitle_options = [
			'display' => $is_subtitle_visible,
			'text'    => $subtitle_text,
		];

		if ( null !== $subtitle_align && '' !== $subtitle_align ) {
			$subtitle_options['align'] = $subtitle_align;
		}

		if ( null !== $subtitle_color && '' !== $subtitle_color ) {
			$subtitle_options['color'] = $subtitle_color;
		}

		if ( ! empty( $subtitle_font ) ) {
			$subtitle_options['font'] = $subtitle_font;
		}

		$legend_labels_options = [];
		$legend_options        = [
			'display'  => $is_legend_visible,
			'position' => $legend_layout['position'] ?? 'top',
			'title'    => [
				'display' => $is_legend_title_visible,
			],
		];

		if ( isset( $legend_layout['align'] ) && '' !== $legend_layout['align'] ) {
			$legend_options['align'] = $legend_layout['align'];
		}

		if ( null !== $legend_label_color && '' !== $legend_label_color ) {
			$legend_labels_options['color'] = $legend_label_color;
		}

		$legend_box_width = self::_to_optional_number( $legend_markers['boxWidth'] ?? null );
		if ( null !== $legend_box_width ) {
			$legend_labels_options['boxWidth'] = $legend_box_width;
		}

		$legend_box_height = self::_to_optional_number( $legend_markers['boxHeight'] ?? null );
		if ( null !== $legend_box_height ) {
			$legend_labels_options['boxHeight'] = $legend_box_height;
		}

		$legend_padding = self::_to_optional_number( $legend_markers['padding'] ?? null );
		if ( null !== $legend_padding ) {
			$legend_labels_options['padding'] = $legend_padding;
		}

		if ( isset( $legend_markers['usePointStyle'] ) ) {
			$legend_labels_options['usePointStyle'] = 'on' === self::_to_on_off( $legend_markers['usePointStyle'], 'off' );
		}

		if ( isset( $legend_markers['pointStyle'] ) && '' !== $legend_markers['pointStyle'] ) {
			$legend_labels_options['pointStyle'] = $legend_markers['pointStyle'];
		}

		if ( ! empty( $legend_label_font ) ) {
			$legend_labels_options['font'] = $legend_label_font;
		}

		$chart_type = $chart_config['type'] ?? 'line';
		if ( in_array( $chart_type, [ 'pie', 'doughnut', 'polarArea' ], true ) ) {
			$legend_labels_options['textAlign'] = 'center';
		}

		if ( ! empty( $legend_labels_options ) ) {
			$legend_options['labels'] = $legend_labels_options;
		} else {
			$legend_options['labels'] = (object) [];
		}

		if ( null !== $legend_title_color && '' !== $legend_title_color ) {
			$legend_options['title']['color'] = $legend_title_color;
		}

		if ( '' !== $legend_title_text ) {
			$legend_options['title']['text'] = $legend_title_text;
		}

		if ( ! empty( $legend_title_font ) ) {
			$legend_options['title']['font'] = $legend_title_font;
		}

		$tooltip_options = [
			'enabled' => $is_tooltip_visible,
		];

		foreach ( [ 'backgroundColor', 'borderColor' ] as $tooltip_color_key ) {
			if ( isset( $tooltip_box[ $tooltip_color_key ] ) && '' !== $tooltip_box[ $tooltip_color_key ] ) {
				$tooltip_options[ $tooltip_color_key ] = self::_resolve_chart_color( $tooltip_box[ $tooltip_color_key ] ) ?? $tooltip_box[ $tooltip_color_key ];
			}
		}

		if ( null !== $tooltip_title_color && '' !== $tooltip_title_color ) {
			$tooltip_options['titleColor'] = $tooltip_title_color;
		}

		if ( null !== $tooltip_body_color && '' !== $tooltip_body_color ) {
			$tooltip_options['bodyColor'] = $tooltip_body_color;
		}

		foreach ( [ 'borderWidth', 'cornerRadius', 'padding' ] as $tooltip_box_number_key ) {
			$tooltip_box_number_value = self::_to_optional_number( $tooltip_box[ $tooltip_box_number_key ] ?? null );
			if ( null !== $tooltip_box_number_value ) {
				$tooltip_options[ $tooltip_box_number_key ] = $tooltip_box_number_value;
			}
		}

		foreach ( [ 'boxWidth', 'boxHeight' ] as $tooltip_color_box_number_key ) {
			$tooltip_color_box_number_value = self::_to_optional_number( $tooltip_color_boxes[ $tooltip_color_box_number_key ] ?? null );
			if ( null !== $tooltip_color_box_number_value ) {
				$tooltip_options[ $tooltip_color_box_number_key ] = $tooltip_color_box_number_value;
			}
		}

		if ( isset( $tooltip_color_boxes['displayColors'] ) ) {
			$tooltip_options['displayColors'] = 'on' === self::_to_on_off( $tooltip_color_boxes['displayColors'], 'off' );
		}

		if ( ! empty( $tooltip_title_font ) ) {
			$tooltip_options['titleFont'] = $tooltip_title_font;
		}

		if ( ! empty( $tooltip_body_font ) ) {
			$tooltip_options['bodyFont'] = $tooltip_body_font;
		}

		return [
			'responsive'          => true,
			'maintainAspectRatio' => false,
			'plugins'             => [
				'title'    => $title_options,
				'subtitle' => $subtitle_options,
				'legend'   => $legend_options,
				'tooltip'  => $tooltip_options,
			],
		];
	}

	/**
	 * Normalizes toggle-like values to on/off.
	 *
	 * @param mixed  $value         Raw value.
	 * @param string $default_value Default normalized value.
	 *
	 * @return string
	 */
	private static function _to_on_off( $value, string $default_value = 'off' ): string {
		if ( 'on' === $value || 'off' === $value ) {
			return $value;
		}

		if ( true === $value || 'true' === $value ) {
			return 'on';
		}

		if ( false === $value || 'false' === $value ) {
			return 'off';
		}

		return $default_value;
	}

	/**
	 * Parse an optional number value.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return float|int|null
	 */
	private static function _to_optional_number( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			if ( is_numeric( $value ) ) {
				return (float) $value;
			}

			if ( preg_match( '/[-+]?(?:\d+\.?\d*|\.\d+)/', $value, $matches ) ) {
				$parsed = (float) ( $matches[0] ?? '' );

				if ( is_finite( $parsed ) ) {
					return $parsed;
				}
			}
		}

		return null;
	}

	/**
	 * Parse optional line-height as a numeric value.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return float|int|null
	 */
	private static function _to_optional_line_height( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$normalized_value = trim( $value );

			if ( '' === $normalized_value ) {
				return null;
			}

			if ( is_numeric( $normalized_value ) ) {
				return (float) $normalized_value;
			}

			if ( preg_match( '/[-+]?(?:\d+\.?\d*|\.\d+)/', $normalized_value, $matches ) ) {
				$parsed = (float) ( $matches[0] ?? '' );

				if ( is_finite( $parsed ) ) {
					return $parsed;
				}
			}
		}

		return null;
	}

	/**
	 * Normalize font style into Chart.js-compatible value.
	 *
	 * @param mixed $value Raw font style value.
	 *
	 * @return string|null
	 */
	private static function _normalize_font_style( $value ): ?string {
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}

		if ( self::_has_style_choice( $value, 'italic' ) ) {
			return 'italic';
		}

		if ( self::_has_style_choice( $value, 'oblique' ) ) {
			return 'oblique';
		}

		if ( self::_has_style_choice( $value, 'normal' ) ) {
			return 'normal';
		}

		return null;
	}

	/**
	 * Get color from a design value.
	 *
	 * @param array $value Design value.
	 *
	 * @return string|null
	 */
	private static function _get_color_from_style( array $value ): ?string {
		$source_value = $value;
		$value        = self::_resolve_mode_value( $value );

		if ( isset( $value['color'] ) && is_string( $value['color'] ) && '' !== $value['color'] ) {
			return self::_resolve_chart_color( $value['color'] );
		}

		$font_source = isset( $source_value['font'] ) && is_array( $source_value['font'] ) ? $source_value['font'] : ( $value['font'] ?? [] );
		$font        = self::_resolve_mode_value( $font_source );

		if (
			is_array( $font ) &&
			isset( $font['color'] ) &&
			is_string( $font['color'] ) &&
			'' !== $font['color']
		) {
			return self::_resolve_chart_color( $font['color'] );
		}

		return null;
	}

	/**
	 * Resolve chart color values, including global color variables.
	 *
	 * @param mixed $value Raw color value.
	 *
	 * @return string|null
	 */
	private static function _resolve_chart_color( $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$normalized_value = trim( $value );

		if ( '' === $normalized_value ) {
			return null;
		}

		return GlobalData::resolve_global_color_variable(
			$normalized_value,
			GlobalData::get_global_colors()
		);
	}

	/**
	 * Get text transform token from style value.
	 *
	 * @param mixed $value Raw style value.
	 *
	 * @return string|null
	 */
	private static function _get_text_transform( $value ): ?string {
		if ( self::_has_style_choice( $value, 'uppercase' ) ) {
			return 'uppercase';
		}

		if ( self::_has_style_choice( $value, 'lowercase' ) ) {
			return 'lowercase';
		}

		if ( self::_has_style_choice( $value, 'capitalize' ) ) {
			return 'capitalize';
		}

		return null;
	}

	/**
	 * Determine whether a style token is enabled in a mixed value shape.
	 *
	 * @param mixed  $value  Raw style value.
	 * @param string $choice Target style token.
	 *
	 * @return bool
	 */
	private static function _has_style_choice( $value, string $choice ): bool {
		if ( is_string( $value ) ) {
			return $choice === $value;
		}

		if ( ! is_array( $value ) ) {
			return false;
		}

		if ( in_array( $choice, $value, true ) ) {
			return true;
		}

		if ( ! array_key_exists( $choice, $value ) ) {
			return false;
		}

		$state = $value[ $choice ];

		return in_array( $state, [ 'on', true, 'true', 1, '1' ], true );
	}

	/**
	 * Get text transform from a design value.
	 *
	 * @param array $value Design value.
	 *
	 * @return string|null
	 */
	private static function _get_text_transform_from_style( array $value ): ?string {
		$source_value = $value;
		$value        = self::_resolve_mode_value( $value );
		$font_source  = isset( $source_value['font'] ) && is_array( $source_value['font'] ) ? $source_value['font'] : ( $value['font'] ?? [] );
		$font         = self::_resolve_mode_value( $font_source );

		if ( is_array( $font ) ) {
			$from_font = self::_get_text_transform( $font['capitalization'] ?? null );

			if ( null !== $from_font ) {
				return $from_font;
			}
		}

		return self::_get_text_transform( $value['capitalization'] ?? null );
	}

	/**
	 * Apply text transform to plain text.
	 *
	 * @param string      $value     Raw text.
	 * @param string|null $transform Transform token.
	 *
	 * @return string
	 */
	private static function _apply_text_transform( string $value, ?string $transform ): string {
		if ( null === $transform ) {
			return $value;
		}

		if ( 'uppercase' === $transform ) {
			return strtoupper( $value );
		}

		if ( 'lowercase' === $transform ) {
			return strtolower( $value );
		}

		if ( 'capitalize' === $transform ) {
			return ucwords( strtolower( $value ) );
		}

		return $value;
	}

	/**
	 * Maps Divi font text alignment values to Chart.js title/subtitle align values.
	 *
	 * @param string $text_align Divi textAlign value.
	 *
	 * @return string|null
	 */
	private static function _map_text_align_to_chart_js_align( string $text_align ): ?string {
		if ( 'left' === $text_align || 'start' === $text_align ) {
			return 'start';
		}

		if ( 'center' === $text_align ) {
			return 'center';
		}

		if ( 'right' === $text_align || 'end' === $text_align ) {
			return 'end';
		}

		return null;
	}

	/**
	 * Resolves Chart.js title/subtitle align from the design font group branch.
	 *
	 * @param array $value Raw title/subtitle design object.
	 *
	 * @return string|null
	 */
	private static function _get_chart_js_align_from_style( array $value ): ?string {
		$source_value = $value;
		$source       = self::_resolve_mode_value( $source_value );

		$font_source = isset( $source_value['font'] ) && is_array( $source_value['font'] )
			? $source_value['font']
			: ( isset( $source['font'] ) && is_array( $source['font'] ) ? $source['font'] : [] );
		$font        = self::_resolve_mode_value( $font_source );

		if ( isset( $font['textAlign'] ) && '' !== $font['textAlign'] && is_string( $font['textAlign'] ) ) {
			return self::_map_text_align_to_chart_js_align( $font['textAlign'] );
		}

		return null;
	}

	/**
	 * Normalize `divi/font` output for Chart.js `font` options.
	 *
	 * @param array $font_value Raw font value.
	 *
	 * @return array
	 */
	private static function _normalize_font( array $font_value ): array {
		$source_value = $font_value;
		$font_value   = self::_resolve_mode_value( $font_value );
		$source       = isset( $source_value['font'] ) && is_array( $source_value['font'] )
			? $source_value['font']
			: ( isset( $font_value['font'] ) && is_array( $font_value['font'] ) ? $font_value['font'] : $font_value );
		$source       = self::_resolve_mode_value( $source );
		$font         = [];

		if ( isset( $source['family'] ) && '' !== $source['family'] ) {
			$font['family'] = $source['family'];
		}

		$size = self::_to_optional_number( $source['size'] ?? null );
		if ( null !== $size ) {
			$font['size'] = $size;
		}

		$style = self::_normalize_font_style( $source['style'] ?? null );
		if ( null !== $style ) {
			$font['style'] = $style;
		}

		if ( isset( $source['weight'] ) && '' !== $source['weight'] ) {
			$font['weight'] = $source['weight'];
		}

		$line_height = self::_to_optional_line_height( $source['lineHeight'] ?? null );
		if ( null !== $line_height ) {
			$font['lineHeight'] = $line_height;
		}

		return $font;
	}

	/**
	 * Resolve mode-shaped values to active mode value.
	 *
	 * @param mixed $value Value that may contain mode keys.
	 *
	 * @return mixed
	 */
	private static function _resolve_mode_value( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( [ 'desktop', 'tablet', 'phone' ] as $mode ) {
			if ( isset( $value[ $mode ] ) && is_array( $value[ $mode ] ) && array_key_exists( 'value', $value[ $mode ] ) ) {
				return $value[ $mode ]['value'];
			}
		}

		return $value;
	}

	/**
	 * Resolves cell values from a chart row object.
	 *
	 * @since ??
	 *
	 * @param mixed $row Chart data row.
	 *
	 * @return array
	 */
	private static function _get_row_cells( $row ): array {
		if ( is_array( $row ) && isset( $row['cells'] ) && is_array( $row['cells'] ) ) {
			return $row['cells'];
		}

		return [];
	}

	/**
	 * Normalize numeric values for chart datasets.
	 *
	 * @since ??
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return float|int
	 */
	private static function _to_number( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return (float) $value;
		}

		return 0;
	}

	/**
	 * Get a persisted color from a raw chart column.
	 *
	 * @since ??
	 *
	 * @param array $raw_columns   Raw chart columns.
	 * @param int   $storage_index Column storage index.
	 *
	 * @return string|null
	 */
	private static function _get_column_color( array $raw_columns, int $storage_index ): ?string {
		if ( ! isset( $raw_columns[ $storage_index ] ) || ! is_array( $raw_columns[ $storage_index ] ) ) {
			return null;
		}

		$color = $raw_columns[ $storage_index ]['color'] ?? null;

		return is_string( $color ) && '' !== $color ? $color : null;
	}

	/**
	 * Build per-point background and border color arrays from chart data rows.
	 *
	 * @since ??
	 *
	 * @param array $rows Chart data rows.
	 *
	 * @return array{backgroundColor: array<int, string>, borderColor: array<int, string>}
	 */
	private static function _build_row_point_color_arrays( array $rows ): array {
		$background_colors = [];
		$border_colors     = [];

		foreach ( $rows as $index => $row ) {
			unset( $index );
			$row_color            = self::_resolve_persisted_data_color( self::_get_row_color( $row ) );
			$background_colors[] = $row_color;
			$border_colors[]     = $row_color;
		}

		return [
			'backgroundColor' => $background_colors,
			'borderColor'     => $border_colors,
		];
	}

	/**
	 * Get a persisted color from a chart data row.
	 *
	 * @since ??
	 *
	 * @param mixed $row Chart data row.
	 *
	 * @return string|null
	 */
	private static function _get_row_color( $row ): ?string {
		if ( ! is_array( $row ) ) {
			return null;
		}

		$color = $row['color'] ?? null;

		return is_string( $color ) && '' !== $color ? $color : null;
	}

	/**
	 * Resolve a persisted chart data color, including global color variables.
	 *
	 * @since ??
	 *
	 * @param string|null $color Persisted color value.
	 *
	 * @return string
	 */
	private static function _resolve_persisted_data_color( ?string $color ): string {
		if ( null === $color || '' === $color ) {
			return '';
		}

		return self::_resolve_chart_color( $color ) ?? $color;
	}

	/**
	 * Get the default chart palette.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	private static function _default_palette(): array {
		return DefaultPalette::values();
	}

	/**
	 * Get a deterministic palette color by index.
	 *
	 * @since ??
	 *
	 * @param int $index Dataset or row index.
	 * @param array<int, string>|null $palette Palette values.
	 *
	 * @return string
	 */
	private static function _palette_color( int $index, ?array $palette = null ): string {
		$palette_values = is_array( $palette ) && ! empty( $palette ) ? array_values( $palette ) : self::_default_palette();

		if ( 0 === count( $palette_values ) ) {
			return (string) ( DefaultPalette::values()[0] ?? '' );
		}

		return $palette_values[ $index % count( $palette_values ) ];
	}
}
