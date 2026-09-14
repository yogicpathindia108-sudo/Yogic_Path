<?php
/**
 * Layout declarations class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\Layout;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

/**
 * Layout declarations class.
 *
 * This class has functionality for handling layout style declarations.
 *
 * @since ??
 */
class Layout {

	/**
	 * Get layout style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     Parameters for the layout style declaration.
	 *
	 *     @type bool          $important  Optional. Whether to add !important to the declarations.
	 *     @type array         $attrValue  The layout attribute value.
	 *     @type string        $returnType Optional. The return type of the declaration.
	 *                                     Can be either 'string' or 'key_value_pair'. Default 'string'.
	 *     @type array         $attr       Optional. The full layout attribute structure (normalized; includes merged defaults).
	 *     @type array         $defaultAttrValue Optional. Default layout value for the active breakpoint and state.
	 *     @type array         $render           Optional. With boolean `display` key; whether to emit the `display` property. Default `display` false.
	 * }
	 *
	 * @return string|array Layout style declaration.
	 */
	public static function style_declaration( array $params ) {
		$important          = $params['important'] ?? false;
		$attr_value         = $params['attrValue'] ?? [];
		$default_attr_value = $params['defaultAttrValue'] ?? [];
		$return_type        = $params['returnType'] ?? 'string';
		$attr               = $params['attr'] ?? [];

		// Create new style declarations instance.
		$declarations = new StyleDeclarations(
			[
				'returnType' => $return_type,
				'important'  => $important,
			]
		);

		// Since Layout Style (display) is non-responsive, read display from desktop first, then the active value.
		$desktop_value = $attr['desktop']['value'] ?? [];
		$display       = $desktop_value['display'] ?? $attr_value['display'] ?? '';

		$render                  = $params['render'] ?? [];
		$should_render_display   = ! empty( $render['display'] );
		$display_for_declaration = is_string( $display ) ? $display : '';

		if ( $should_render_display && '' !== $display_for_declaration ) {
			$declarations->add( 'display', $display_for_declaration );
		}
		$column_gap      = $attr_value['columnGap'] ?? '';
		$row_gap         = $attr_value['rowGap'] ?? '';
		$flex_direction  = $attr_value['flexDirection'] ?? '';
		$justify_content = $attr_value['justifyContent'] ?? '';
		$align_items     = $attr_value['alignItems'] ?? '';
		$flex_wrap       = $attr_value['flexWrap'] ?? '';
		$align_content   = $attr_value['alignContent'] ?? '';

		// Grid-specific properties.
		$grid_column_widths           = $attr_value['gridColumnWidths'] ?? '';
		$grid_column_count            = $attr_value['gridColumnCount'] ?? '';
		$collapse_empty_columns       = $attr_value['collapseEmptyColumns'] ?? '';
		$grid_column_min_width        = $attr_value['gridColumnMinWidth'] ?? '';
		$grid_column_width            = $attr_value['gridColumnWidth'] ?? '';
		$grid_template_columns        = $attr_value['gridTemplateColumns'] ?? '';
		$grid_auto_columns            = $attr_value['gridAutoColumns'] ?? '';
		$grid_row_heights             = $attr_value['gridRowHeights'] ?? 'auto';
		$grid_row_count               = $attr_value['gridRowCount'] ?? '';
		$grid_row_min_height          = $attr_value['gridRowMinHeight'] ?? '';
		$grid_row_height              = $attr_value['gridRowHeight'] ?? '';
		$grid_template_rows           = $attr_value['gridTemplateRows'] ?? '';
		$grid_auto_rows               = $attr_value['gridAutoRows'] ?? '';
		$grid_auto_flow               = $attr_value['gridAutoFlow'] ?? 'row';
		$grid_density                 = $attr_value['gridDensity'] ?? '';
		$grid_justify_items           = $attr_value['gridJustifyItems'] ?? '';
		$grid_offset_rules            = $attr_value['gridOffsetRules'] ?? null;
		$effective_grid_column_widths = '' !== $grid_column_widths
			? $grid_column_widths
			: ( $default_attr_value['gridColumnWidths'] ?? 'equal' );

		if ( 'block' !== $display ) {
			if ( $column_gap ) {
				$declarations->add( '--horizontal-gap', $column_gap );
			}

			if ( $row_gap ) {
				$declarations->add( '--vertical-gap', $row_gap );
			}

			if ( 'grid' === $display ) {
				// Set CSS custom properties for grid counts if available.
				if ( $grid_column_count ) {
					$declarations->add( '--column-count', $grid_column_count );
				}
				if ( $grid_row_count ) {
					$declarations->add( '--row-count', $grid_row_count );
				}

				// Grid template columns based on column width setting.
				if ( $effective_grid_column_widths ) {
					if ( 'equal' === $effective_grid_column_widths ) {
						if ( $grid_column_count ) {

							// Apply grid-template-columns, use collapse logic when enabled.
							if ( 'on' === $collapse_empty_columns && 'row' === $grid_auto_flow ) {
								// Use auto-fit with calc formula when collapse is enabled and grid direction is row.
								$declarations->add( 'grid-template-columns', 'repeat(auto-fit, minmax(calc((100% - (var(--column-count) - 1) * var(--horizontal-gap, 0px)) / var(--column-count)), 1fr))' );
							} else {
								$declarations->add( 'grid-template-columns', 'repeat(var(--column-count), minmax(0, 1fr))' );
							}
						}
					} elseif ( 'equalMinimum' === $effective_grid_column_widths ) {
						// Default to 250px when no explicit minimum width is provided.
						$min_column_width = $grid_column_min_width
							? $grid_column_min_width
							: ( $default_attr_value['gridColumnMinWidth'] ?? '250px' );
						$declarations->add( '--min-column-width', $min_column_width );
						$declarations->add( 'grid-template-columns', 'repeat(auto-fill, minmax(min(100%, var(--min-column-width)), 1fr))' );
					} elseif ( 'equalFixed' === $effective_grid_column_widths ) {
						// Default to 250px when no explicit fixed width is provided.
						$fixed_column_width = $grid_column_width
							? $grid_column_width
							: ( $default_attr_value['gridColumnWidth'] ?? '250px' );
						$declarations->add( '--fixed-column-width', $fixed_column_width );
						$declarations->add( 'grid-template-columns', 'repeat(auto-fit, minmax(0, var(--fixed-column-width)))' );
					} elseif ( 'auto' === $effective_grid_column_widths ) {
						if ( $grid_column_count ) {
							// Set grid-template-columns.
							$declarations->add( 'grid-template-columns', "repeat({$grid_column_count}, auto)" );
						}
					} elseif ( 'manual' === $effective_grid_column_widths ) {
						if ( $grid_template_columns ) {
							// Set grid-template-columns.
							$declarations->add( 'grid-template-columns', $grid_template_columns );
						} else {
							// Ensure reset/manual-empty state still applies a single explicit track.
							$declarations->add( 'grid-template-columns', 'repeat(var(--column-count), 1fr)' );
						}
					}
				}

				// Grid template rows based on row height setting.
				if ( $grid_row_heights ) {
					// Reset grid-template-rows for non-manual row heights to allow grid-auto-rows to work.
					if ( 'manual' !== $grid_row_heights ) {
						$declarations->add( 'grid-template-rows', 'none' );
					}

					if ( 'auto' === $grid_row_heights ) {
						// Auto Height Rows: if row count is defined, use grid-template-rows; otherwise use grid-auto-rows.
						if ( $grid_row_count ) {
							$declarations->add( 'grid-template-rows', 'repeat(var(--row-count), auto)' );
						} else {
							// Print grid-auto-rows only if user set a value.
							$declarations->add( 'grid-auto-rows', $grid_auto_rows ? $grid_auto_rows : 'auto' );
						}
					} elseif ( 'equal' === $grid_row_heights ) {
						// Equal Height Rows: if row count is defined, use grid-template-rows; otherwise use grid-auto-rows.
						if ( $grid_row_count ) {
							$declarations->add( 'grid-template-rows', 'repeat(var(--row-count), 1fr)' );
						} else {
							// Set grid-auto-rows only if user provided a value.
							$declarations->add( 'grid-auto-rows', $grid_auto_rows ? $grid_auto_rows : '1fr' );
						}
					} elseif ( 'minimum' === $grid_row_heights && $grid_row_min_height ) {
						// Minimum Height Rows: only process if user set min height.
						$declarations->add( '--min-row-height', $grid_row_min_height );
						if ( $grid_row_count ) {
							$declarations->add( 'grid-template-rows', 'repeat(var(--row-count), minmax(var(--min-row-height), auto))' );
						} else {
							$declarations->add( 'grid-auto-rows', $grid_auto_rows ? $grid_auto_rows : 'minmax(var(--min-row-height), auto)' );
						}
					} elseif ( 'fixed' === $grid_row_heights && $grid_row_height ) {
						// Fixed Height Rows: only process if user set row height.
						$declarations->add( '--fixed-row-height', $grid_row_height );
						if ( $grid_row_count ) {
							$declarations->add( 'grid-template-rows', 'repeat(var(--row-count), var(--fixed-row-height))' );
						} else {
							$declarations->add( 'grid-auto-rows', $grid_auto_rows ? $grid_auto_rows : 'var(--fixed-row-height)' );
						}
					} elseif ( 'manual' === $grid_row_heights ) {
						if ( $grid_template_rows ) {
							$declarations->add( 'grid-template-rows', $grid_template_rows );
						}
						if ( $grid_auto_rows ) {
							$declarations->add( 'grid-auto-rows', $grid_auto_rows );
						}
					}
				}

				// Grid auto columns and rows.
				if ( $grid_auto_columns ) {
					$declarations->add( 'grid-auto-columns', $grid_auto_columns );
				}

				// Note: $grid_auto_rows is handled in the row height settings above.

				// Grid auto flow (direction and density).
				if ( $grid_auto_flow || $grid_density ) {
					$auto_flow_value = 'dense' === $grid_density ? $grid_auto_flow . ' dense' : $grid_auto_flow;

					$declarations->add( 'grid-auto-flow', $auto_flow_value );
				}

				// Grid alignment properties.
				if ( $grid_justify_items ) {
					$declarations->add( 'justify-items', $grid_justify_items );
				}

				if ( $align_items ) {
					// Convert flex values to grid values for CSS Grid.
					$grid_align_items_value = $align_items;
					if ( 'flex-start' === $align_items ) {
						$grid_align_items_value = 'start';
					} elseif ( 'flex-end' === $align_items ) {
						$grid_align_items_value = 'end';
					}
					$declarations->add( 'align-items', $grid_align_items_value );
				}

				if ( $justify_content ) {
					$declarations->add( 'justify-content', $justify_content );
				}

				if ( $align_content ) {
					// Convert flex values to grid values for CSS Grid.
					$grid_align_content_value = $align_content;
					if ( 'flex-start' === $align_content ) {
						$grid_align_content_value = 'start';
					} elseif ( 'flex-end' === $align_content ) {
						$grid_align_content_value = 'end';
					}
					$declarations->add( 'align-content', $grid_align_content_value );
				}
			} elseif ( 'flex' === $display ) {
				// Flex-specific CSS declarations.
				if ( $flex_direction ) {
					$declarations->add( 'flex-direction', $flex_direction );
					$declarations->add( '--flex-direction', $flex_direction );
				}

				if ( $justify_content ) {
					$declarations->add( 'justify-content', $justify_content );
				}

				if ( $align_items ) {
					$declarations->add( 'align-items', $align_items );
				}

				if ( $flex_wrap ) {
					$declarations->add( 'flex-wrap', $flex_wrap );

					if ( 'nowrap' !== $flex_wrap && $align_content ) {
						$declarations->add( 'align-content', $align_content );
					}
				}
			}
		}

		return $declarations->value();
	}
}
