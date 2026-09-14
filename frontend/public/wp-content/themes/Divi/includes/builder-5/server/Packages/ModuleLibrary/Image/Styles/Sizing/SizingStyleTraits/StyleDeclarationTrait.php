<?php
/**
 * Module Library: Image Module Sizing Style Declaration Trait
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Image\Styles\Sizing\SizingStyleTraits;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

trait StyleDeclarationTrait {
	/**
	 * Get width and alignment CSS declaration from Sizing style and based on given attrValue.
	 *
	 * @since ??
	 *
	 * @param array $args An array of arguments.
	 *
	 * @return string The CSS declaration.
	 */
	public static function style_declaration( array $args ): string {
		$args = wp_parse_args(
			$args,
			[
				'important'          => false,
				'returnType'         => 'string',
				'isParentFlexLayout' => false,
				'isParentGridLayout' => false,
			]
		);

		$attr_value            = $args['attrValue'];
		$important             = $args['important'];
		$return_type           = $args['returnType'];
		$is_parent_flex_layout = $args['isParentFlexLayout'];
		$is_parent_grid_layout = $args['isParentGridLayout'];
		$additional_args       = $args['additional'] ?? [];
		$spacing_attr          = $additional_args['spacingAttr'] ?? [];
		$breakpoint            = $args['breakpoint'] ?? null;
		$state                 = $args['state'] ?? null;
		$width                 = $attr_value['width'] ?? null;
		$max_width             = $attr_value['maxWidth'] ?? null;
		$alignment             = $attr_value['alignment'] ?? null;
		$align_self            = $attr_value['alignSelf'] ?? null;
		$force_fullwidth       = $attr_value['forceFullwidth'] ?? null;

		// Grid-specific properties.
		$grid_column_span  = $attr_value['gridColumnSpan'] ?? null;
		$grid_row_span     = $attr_value['gridRowSpan'] ?? null;
		$grid_column_start = $attr_value['gridColumnStart'] ?? null;
		$grid_row_start    = $attr_value['gridRowStart'] ?? null;
		$grid_column_end   = $attr_value['gridColumnEnd'] ?? null;
		$grid_row_end      = $attr_value['gridRowEnd'] ?? null;
		$grid_align_self   = $attr_value['gridAlignSelf'] ?? null;
		$grid_justify_self = $attr_value['gridJustifySelf'] ?? null;

		$always_important = [
			'margin-right' => true,
			'margin-left'  => true,
		];

		// Always add important flags for grid properties.
		$grid_important_props = [
			'grid-column'       => true,
			'grid-column-start' => true,
			'grid-column-end'   => true,
			'grid-row'          => true,
			'grid-row-start'    => true,
			'grid-row-end'      => true,
			'align-self'        => true,
			'justify-self'      => true,
		];

		$enhanced_important = $important ? array_merge(
			$grid_important_props,
			$always_important,
			[
				'width'     => true,
				'max-width' => true,
			]
		) : array_merge( $grid_important_props, $always_important );

		$style_declarations = new StyleDeclarations(
			[
				'important'  => $enhanced_important,
				'returnType' => $return_type,
			]
		);

		// Only add alignment, width and max-width if forceFullwidth is not enabled.
		if ( 'on' !== $force_fullwidth ) {
			if ( $width ) {
				$style_declarations->add( 'width', $width );
			}

			if ( $max_width ) {
				$style_declarations->add( 'max-width', $max_width );
			}

			// Handle alignment differently based on parent layout.
			if ( $is_parent_flex_layout ) {
				// Add align-self support for flex layout.
				if ( $align_self ) {
					$style_declarations->add( 'align-self', $align_self );
				}

				// Unset conflicting module alignment margins based on alignSelf value.
				// Only unset margins that would conflict with the specific align-self behavior.
				switch ( $align_self ) {
					case 'flex-start':
					case 'stretch':
						// align-self handles left alignment, so unset conflicting margin-left.
						$style_declarations->add( 'margin-left', 'unset' );
						break;
					case 'center':
						// Set both margins to auto to center the image.
						$style_declarations->add( 'margin-left', 'auto' );
						$style_declarations->add( 'margin-right', 'auto' );
						break;
					case 'end':
						// align-self handles right alignment, so unset conflicting margin-right.
						$style_declarations->add( 'margin-right', 'unset' );
						break;
				}
			} else {
				// In block layout, use standard margin-based alignment.
				// Check for custom spacing values before outputting alignment margins.
				// If custom spacing exists for a margin side, skip outputting alignment margin for that side.
				$has_custom_margin_left  = false;
				$has_custom_margin_right = false;

				if ( ! empty( $spacing_attr ) && null !== $breakpoint && null !== $state ) {
					$spacing_value = ModuleUtils::get_attr_value(
						[
							'attr'            => $spacing_attr,
							'breakpoint'      => $breakpoint,
							'state'           => $state,
							'mode'            => 'getAndInheritAll',
							'defaultValue'    => null,
							'breakpointNames' => Breakpoint::get_default_breakpoint_names(),
							'baseBreakpoint'  => 'desktop',
						]
					);

					// Check if custom margin values exist for left and right sides.
					// Exclude empty strings and '0' values (which are defaults, not custom spacing).
					$has_custom_margin_left  = ! empty( $spacing_value['margin']['left'] ) && '' !== $spacing_value['margin']['left'] && '0' !== $spacing_value['margin']['left'];
					$has_custom_margin_right = ! empty( $spacing_value['margin']['right'] ) && '' !== $spacing_value['margin']['right'] && '0' !== $spacing_value['margin']['right'];
				}

				switch ( $alignment ) {
					case 'left':
						if ( ! $has_custom_margin_left ) {
							$style_declarations->add( 'margin-left', '0' );
						}
						if ( ! $has_custom_margin_right ) {
							$style_declarations->add( 'margin-right', 'auto' );
						}
						break;

					case 'center':
						if ( ! $has_custom_margin_left ) {
							$style_declarations->add( 'margin-left', 'auto' );
						}
						if ( ! $has_custom_margin_right ) {
							$style_declarations->add( 'margin-right', 'auto' );
						}
						break;

					case 'right':
						if ( ! $has_custom_margin_left ) {
							$style_declarations->add( 'margin-left', 'auto' );
						}
						if ( ! $has_custom_margin_right ) {
							$style_declarations->add( 'margin-right', '0' );
						}
						break;
				}
			}
		}

		// Grid-specific sizing properties.
		if ( $is_parent_grid_layout ) {
			// Handle grid-column properties (start, end, span).
			if ( $grid_column_start || $grid_column_end || $grid_column_span ) {
				if ( $grid_column_start && $grid_column_end ) {
					// If both start and end are set, use shorthand syntax.
					$style_declarations->add( 'grid-column', $grid_column_start . ' / ' . $grid_column_end );
				} elseif ( $grid_column_start && $grid_column_span ) {
					// If start and span are set, use shorthand syntax.
					$style_declarations->add( 'grid-column', $grid_column_start . ' / span ' . $grid_column_span );
				} elseif ( $grid_column_end && $grid_column_span ) {
					// If end and span are set, use shorthand syntax.
					$style_declarations->add( 'grid-column', 'span ' . $grid_column_span . ' / ' . $grid_column_end );
				} elseif ( $grid_column_start ) {
					$style_declarations->add( 'grid-column-start', $grid_column_start );
				} elseif ( $grid_column_end ) {
					$style_declarations->add( 'grid-column-end', $grid_column_end );
				} elseif ( $grid_column_span ) {
					$style_declarations->add( 'grid-column', 'span ' . $grid_column_span );
				}
			}

			// Handle grid-row properties (start, end, span).
			if ( $grid_row_start || $grid_row_end || $grid_row_span ) {
				if ( $grid_row_start && $grid_row_end ) {
					// If both start and end are set, use shorthand syntax.
					$style_declarations->add( 'grid-row', $grid_row_start . ' / ' . $grid_row_end );
				} elseif ( $grid_row_start && $grid_row_span ) {
					// If start and span are set, use shorthand syntax.
					$style_declarations->add( 'grid-row', $grid_row_start . ' / span ' . $grid_row_span );
				} elseif ( $grid_row_end && $grid_row_span ) {
					// If end and span are set, use shorthand syntax.
					$style_declarations->add( 'grid-row', 'span ' . $grid_row_span . ' / ' . $grid_row_end );
				} elseif ( $grid_row_start ) {
					$style_declarations->add( 'grid-row-start', $grid_row_start );
				} elseif ( $grid_row_end ) {
					$style_declarations->add( 'grid-row-end', $grid_row_end );
				} elseif ( $grid_row_span ) {
					$style_declarations->add( 'grid-row', 'span ' . $grid_row_span );
				}
			}

			if ( null !== $grid_align_self ) {
				$style_declarations->add( 'align-self', $grid_align_self );
			}

			if ( null !== $grid_justify_self ) {
				$style_declarations->add( 'justify-self', $grid_justify_self );
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Get height CSS declaration from Sizing style and based on given attrValue.
	 *
	 * @since ??
	 *
	 * @param array $args An array of arguments.
	 *
	 * @return string The CSS declaration.
	 */
	public static function height_style_declaration( array $args ): string {
		$args = wp_parse_args(
			$args,
			[
				'important'  => false,
				'returnType' => 'string',
			]
		);

		$attr_value      = $args['attrValue'];
		$important       = $args['important'];
		$return_type     = $args['returnType'];
		$min_height      = $attr_value['minHeight'] ?? null;
		$height          = $attr_value['height'] ?? null;
		$max_height      = $attr_value['maxHeight'] ?? null;
		$aspect_ratio    = $attr_value['aspectRatio'] ?? null;
		$force_fullwidth = $attr_value['forceFullwidth'] ?? null;

		$style_declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);

		if ( null !== $min_height && '' !== $min_height ) {
			$style_declarations->add( 'min-height', $min_height );
		}

		if ( null !== $height ) {
			// `auto` is the Image module's default for height (see svg_style_declaration()).
			// In D4, an empty responsive value means "reset to this default on this breakpoint."
			// Without this, a converted D4 desktop height (e.g. 444px) cascades to smaller viewports.
			$height_default  = 'auto';
			$resolved_height = '' === $height ? $height_default : $height;
			$style_declarations->add( 'height', $resolved_height );

			// Set width to auto if forceFullwidth is not enabled and height is not the default.
			if ( 'on' !== $force_fullwidth && $height_default !== $resolved_height ) {
				$style_declarations->add( 'width', 'auto' );
			}
		}

		if ( null !== $max_height && '' !== $max_height ) {
			$style_declarations->add( 'max-height', $max_height );

			// Set width to auto if forceFullwidth is not enabled and maxHeight is not none.
			if ( 'on' !== $force_fullwidth && 'none' !== $max_height ) {
				$style_declarations->add( 'width', 'auto' );
			}
		}

		$aspect_ratio_width  = is_array( $aspect_ratio ) ? ( $aspect_ratio['width'] ?? null ) : null;
		$aspect_ratio_height = is_array( $aspect_ratio ) ? ( $aspect_ratio['height'] ?? null ) : null;

		if (
			null !== $aspect_ratio_width &&
			null !== $aspect_ratio_height &&
			'' !== $aspect_ratio_width &&
			'' !== $aspect_ratio_height &&
			'auto' !== $aspect_ratio_width &&
			'auto' !== $aspect_ratio_height
		) {
			$style_declarations->add( 'aspect-ratio', $aspect_ratio_width . ' / ' . $aspect_ratio_height );
		}

		return $style_declarations->value();
	}
}
