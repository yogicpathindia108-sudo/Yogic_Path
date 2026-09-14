<?php
/**
 * Module: ElementStyle class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Element;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Customizer\Customizer;
use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Packages\Module\Layout\Components\Style\Utils\Utils;
use ET\Builder\Packages\Module\Options\Animation\AnimationUtils;
use ET\Builder\Packages\Module\Options\Background\BackgroundStyle;
use ET\Builder\Packages\Module\Options\Border\BorderStyle;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowStyle;
use ET\Builder\Packages\Module\Options\Button\ButtonStyle;
use ET\Builder\Packages\Module\Options\DisabledOn\DisabledOnStyle;
use ET\Builder\Packages\Module\Options\Filters\FiltersStyle;
use ET\Builder\Packages\Module\Options\Fit\FitStyle;
use ET\Builder\Packages\Module\Options\FontBodyGroup\FontBodyStyle;
use ET\Builder\Packages\Module\Options\FontHeaderGroup\FontHeaderStyle;
use ET\Builder\Packages\Module\Options\Font\FontStyle;
use ET\Builder\Packages\Module\Options\Icon\IconStyle;
use ET\Builder\Packages\Module\Options\Layout\LayoutStyle;
use ET\Builder\Packages\Module\Options\Order\OrderStyle;
use ET\Builder\Packages\Module\Options\Overflow\OverflowStyle;
use ET\Builder\Packages\Module\Options\Position\PositionStyle;
use ET\Builder\Packages\Module\Options\Sizing\SizingStyle;
use ET\Builder\Packages\Module\Options\Spacing\SpacingStyle;
use ET\Builder\Packages\Module\Options\Transform\TransformStyle;
use ET\Builder\Packages\Module\Options\Transition\TransitionStyle;
use ET\Builder\Packages\Module\Options\ZIndex\ZIndexStyle;
use ET\Builder\Packages\Module\Options\Element\ElementFilterFunctions;
use ET\Builder\Packages\Module\Options\Element\ElementStyleAdvancedStyles;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Transition\TransitionUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Transform\Transform as TransformDeclaration;

/**
 * ElementStyle class.
 *
 * This class provides the functionality for handling element styles.
 *
 * @since ??
 */
class ElementStyle {

	/**
	 * Get element style component.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/ElementStyle ElementStyle} in
	 * `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string        $selector                  Optional. The CSS selector. Default `null`.
	 *     @type array         $attrs                     Optional. An array of module attribute data. Default `[]`.
	 *     @type array         $defaultPrintedStyleAttrs  Optional. An array of default printed style attribute data. Default `[]`.
	 *     @type callable      $attrsFilter               Optional. A callback function to filter the attributes. Default `null`.
	 *     @type string|null   $orderClass                Optional. The selector class name.
	 *     @type string        $type                      Optional. Element type. This might use built in callback for attributes.
	 *                                                    Default `module`.
	 *     @type bool          $isInsideStickyModule      Optional. Whether the module is inside a sticky module or not. Default `false`.
	 *     @type string        $stickyParentOrderClass    Optional. The sticky parent order class name.
	 *     @type bool          $hasBackgroundPresets          Optional. Whether background presets are actively applied. Default `false`.
	 *     @type bool          $hasDefaultBackground        Optional. Whether the module has a default render background. Default `false`.
	 *     @type array         $background                Optional. An array of background style data. Default `[]`.
	 *     @type array         $font                      Optional. An array of font style data. Default `[]`.
	 *     @type array         $icon                      Optional. An array of icon style data. Default `[]`.
	 *     @type array         $bodyFont                  Optional. An array of bodyFont style data. Default `[]`.
	 *     @type array         $spacing                   Optional. An array of spacing style data. Default `[]`.
	 *     @type array         $sizing                    Optional. An array of sizing style data. Default `[]`.
	 *     @type array         $fit                       Optional. An array of fit (object-fit / object-position) style data. Default `[]`.
	 *     @type array         $border                    Optional. An array of border style data. Default `[]`.
	 *     @type array         $boxShadow                 Optional. An array of boxShadow style data. Default `[]`.
	 *     @type array         $filters                   Optional. An array of filter style data. Default `[]`.
	 *     @type array         $transform                 Optional. An array of transform style data. Default `[]`.
	 *     @type array         $transition                Optional. An array of transition style data. Default `[]`.
	 *     @type array         $disabledOn                Optional. An array of disabledOn style data. Default `[]`.
	 *     @type array         $overflow                  Optional. An array of overflow style data. Default `[]`.
	 *     @type array         $position                  Optional. An array of position style data. Default `[]`.
	 *     @type array         $zIndex                    Optional. An array of zIndex style data. Default `[]`.
	 *     @type array         $advanced_styles           Optional. An array of module advanced styles. Default `[]`.
	 *     @type array         $button                    Optional. An array of button style data. Default `[]`.
	 *     @type array         $order                     Optional. An array of order style data. default '[]'.
	 *     @type bool          $asStyle                   Optional. Whether to wrap the style declaration with style tag or not.
	 *                                                    Default `true`
	 *     @type string        $returnType                Optional. This is the type of value that the function will return.
	 *                                                    Can be either `string` or `array`. Default `array`.
	 *     @type bool          $isParentFlexLayout        Optional. Whether the module is inside a parent layout flex or not. Default `false`.
	 *     @type array         $layout                    Optional. An array of layout style data. Default `[]`.
	 *     @type string        $atRules                   Optional. CSS at-rules to wrap the style declarations in.
	 * }
	 * }
	 *
	 * @return string|array The element style component.
	 *
	 * @example:
	 * ```php
	 * // Apply style using default arguments.
	 * $args = [];
	 * $style = ElementStyle::style( $args );
	 *
	 * // Apply style with specific selectors and properties.
	 * $args = [
	 *     'selectors' => [
	 *         '.element1',
	 *         '.element2',
	 *     ],
	 *     'propertySelectors' => [
	 *         '.element1 .property1',
	 *         '.element2 .property2',
	 *     ]
	 * ];
	 * $style = ElementStyle::style( $args );
	 * ```
	 */
	public static function style( $args ) {
		$args = array_replace_recursive(
			[
				'selector'                 => null,
				'attrs'                    => [],
				'defaultPrintedStyleAttrs' => [],
				'attrsFilter'              => null,
				'type'                     => 'module',
				'isInsideStickyModule'     => false,
				'stickyParentOrderClass'   => null,
				'hasBackgroundPresets'     => false,
				'hasDefaultBackground'     => false,
				'background'               => [],
				'font'                     => [],
				'icon'                     => [],
				'bodyFont'                 => [],
				'headingFont'              => [],
				'spacing'                  => [],
				'sizing'                   => [],
				'fit'                      => [],
				'border'                   => [],
				'boxShadow'                => [],
				'filters'                  => [],
				'transform'                => [],
				'transition'               => [],
				'disabledOn'               => [],
				'order'                    => [],
				'overflow'                 => [],
				'position'                 => [],
				'zIndex'                   => [],
				'button'                   => [],
				'layout'                   => [],
				'asStyle'                  => true,
				'advancedStyles'           => [],
				'orderClass'               => null,
				'returnType'               => 'array',
				// phpcs:ignore Universal.Arrays.DuplicateArrayKey.Found -- Intentionally overwriting default value.
				'isParentFlexLayout'       => false,
				'isParentGridLayout'       => false,
				'atRules'                  => '',
			],
			$args
		);

		// Assign attributes.
		$selector                    = $args['selector'];
		$attrs                       = $args['attrs'];
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'];
		$is_inside_sticky_module     = $args['isInsideStickyModule'];
		$sticky_parent_order_class   = $args['stickyParentOrderClass'];
		$is_parent_flex_layout       = $args['isParentFlexLayout'];
		$is_parent_grid_layout       = $args['isParentGridLayout'];
		$has_background_presets      = $args['hasBackgroundPresets'];
		$has_default_background      = $args['hasDefaultBackground'];
		$background                  = $args['background'];
		$font                        = $args['font'];
		$icon                        = $args['icon'];
		$body_font                   = $args['bodyFont'];
		$heading_font                = $args['headingFont'];
		$spacing                     = $args['spacing'];
		$sizing                      = $args['sizing'];
		$fit                         = $args['fit'];
		$border                      = $args['border'];
		$box_shadow                  = $args['boxShadow'];
		$filters                     = $args['filters'];
		$transform                   = $args['transform'];
		$transition                  = $args['transition'];
		$disabled_on                 = $args['disabledOn'];
		$order                       = $args['order'];
		$overflow                    = $args['overflow'];
		$position                    = $args['position'];
		$z_index                     = $args['zIndex'];
		$button                      = $args['button'];
		$layout                      = $args['layout'];
		$as_style                    = $args['asStyle'];
		$advanced_styles             = $args['advancedStyles'];
		$order_class                 = $args['orderClass'];
		$return_as_array             = 'array' === $args['returnType'];
		$at_rules                    = $args['atRules'];

		$attrs_filter_by_type = ElementFilterFunctions::$filter_function_map[ $args['type'] ] ?? null;
		$attrs_filter         = $args['attrsFilter'] ?? null;

		// Filter the attributes if needed.
		// The order of importance for attribute filtering functions:
		// 1. filterFunctionMap (filter function based on type).
		// 2. attrsFilter (optional custom callback to transform attrs before style generation).
		$element_attrs = $attrs;

		if ( is_callable( $attrs_filter_by_type ) ) {
			$element_attrs = call_user_func( $attrs_filter_by_type, $element_attrs );
		}

		if ( is_callable( $attrs_filter ) ) {
			$element_attrs = call_user_func( $attrs_filter, $element_attrs );
		}

		$disable_button_alignment_styles = (bool) ( $button['disableAlignmentStyles'] ?? false );

		// Get relevant customizer options for this element type.
		$customizer_setting_for_element_style = Customizer::get_customizer_setting_for_element_style(
			[
				'elementType' => $args['type'],
			]
		);

		// If customizer setting for current element exist, merge it into `defaultPrintedStyleAttrs`. Customizer style
		// rendering is treated similarly as static css. It is expected to be already exist on the page, its value won't
		// be re-printed by visual builder, but in some occassion renderer component needs to know its value to prevent
		// unnecessary css style being rendered and overwrites customizer setting (eg. border-color when only border-width
		// attribute is defined).
		if ( ! empty( $customizer_setting_for_element_style ) ) {
			$default_printed_style_attrs = array_merge(
				$default_printed_style_attrs,
				$customizer_setting_for_element_style
			);
		}

		// JSON encode the attributes array for faster search.
		$attrs_json = wp_json_encode( $element_attrs );

		// Prepare element styles.
		$element = $return_as_array ? [] : '';

		// Prepare transition `attrs` and `props` by collecting the style data for each
		// element styles with rendered styles. We keep `attrs` and `props` separated to
		// make it easier to process transition styles later. This also aligns with
		// ElementStyle::style_declaration() function that accepts `attrs` and style data
		// (border, boxShadow, etc.) as separate parameters. We're not going to collect all
		// element styles data, only the ones that have rendered style and have supported
		// transition CSS properties for performance reasons.
		$transition_data = [
			'attrs' => [],
			'props' => [],
		];

		$element_background = ! empty( $element_attrs['background'] ) ? BackgroundStyle::style(
			[
				'selector'                => $background['selector'] ?? $selector,
				'selectors'               => $background['selectors'] ?? [],
				'propertySelectors'       => $background['propertySelectors'] ?? [],
				'selectorFunction'        => $background['selectorFunction'] ?? null,
				'attrs_json'              => $attrs_json,
				'attr'                    => $element_attrs['background'],
				'defaultPrintedStyleAttr' => $default_printed_style_attrs['background'] ?? $background['defaultPrintedStyleAttr'] ?? [],
				'important'               => $background['important'] ?? false,
				'featureSelectors'        => $background['featureSelectors'] ?? null,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'hasBackgroundPresets'    => $has_background_presets,
				'hasDefaultBackground'    => $has_default_background,
				'returnType'              => $args['returnType'],
				'atRules'                 => $background['atRules'] ?? $at_rules,

			]
		) : null;

		if ( $element_background ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_background );
			} else {
				$element .= $element_background;
			}

			$transition_data['attrs']['background'] = $element_attrs['background'];
			$transition_data['props']['background'] = $background;
		}

		$element_font = ! empty( $element_attrs['font'] ) ? FontStyle::style(
			[
				'selector'                => $font['selector'] ?? $selector,
				'selectors'               => $font['selectors'] ?? [],
				'propertySelectors'       => $font['propertySelectors'] ?? [],
				'selectorFunction'        => $font['selectorFunction'] ?? null,
				'attrs_json'              => $attrs_json,
				'attr'                    => $element_attrs['font'],
				'defaultPrintedStyleAttr' => $default_printed_style_attrs['font'] ?? $font['defaultPrintedStyleAttr'] ?? [],
				'important'               => $font['important'] ?? false,
				'headingLevel'            => $font['headingLevel'] ?? false,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
				'atRules'                 => $font['atRules'] ?? $at_rules,
			]
		) : null;

		if ( $element_font ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_font );
			} else {
				$element .= $element_font;
			}

			$transition_data['attrs']['font'] = $element_attrs['font'];
			$transition_data['props']['font'] = $font;
		}

		$element_icon = ! empty( $element_attrs['icon'] ) ? IconStyle::style(
			[
				'selector'                => $icon['selector'] ?? $selector,
				'selectors'               => $icon['selectors'] ?? [],
				'propertySelectors'       => $icon['propertySelectors'] ?? [],
				'selectorFunction'        => $icon['selectorFunction'] ?? null,
				'attrs_json'              => $attrs_json,
				'attr'                    => $element_attrs['icon'],
				'defaultPrintedStyleAttr' => $default_printed_style_attrs['icon'] ?? $icon['defaultPrintedStyleAttr'] ?? [],
				'important'               => $icon['important'] ?? false,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
				'atRules'                 => $icon['atRules'] ?? $at_rules,
			]
		) : null;

		if ( $element_icon ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_icon );
			} else {
				$element .= $element_icon;
			}

			$transition_data['attrs']['icon'] = $element_attrs['icon'];
			$transition_data['props']['icon'] = $icon;
		}

		$element_font_body = ! empty( $element_attrs['bodyFont'] ) ? FontBodyStyle::font_body_style(
			[
				'selector'                => $body_font['selector'] ?? $selector,
				'selectors'               => $body_font['selectors'] ?? [],
				'propertySelectors'       => $body_font['propertySelectors'] ?? [],
				'selectorFunction'        => $body_font['selectorFunction'] ?? null,
				'attrs_json'              => $attrs_json,
				'attr'                    => $element_attrs['bodyFont'],
				'defaultPrintedStyleAttr' => $default_printed_style_attrs['bodyFont'] ?? $body_font['defaultPrintedStyleAttr'] ?? [],
				'important'               => $body_font['important'] ?? false,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
				'atRules'                 => $body_font['atRules'] ?? $at_rules,
			]
		) : null;

		if ( $element_font_body ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_font_body );
			} else {
				$element .= $element_font_body;
			}

			$transition_data['attrs']['bodyFont'] = $element_attrs['bodyFont'];
			$transition_data['props']['bodyFont'] = $body_font;
		}

		$element_font_heading = ! empty( $element_attrs['headingFont'] ) ? FontHeaderStyle::style(
			[
				'selector'                => $heading_font['selector'] ?? $selector,
				'selectors'               => $heading_font['selectors'] ?? [],
				'propertySelectors'       => $heading_font['propertySelectors'] ?? [],
				'selectorFunction'        => $heading_font['selectorFunction'] ?? null,
				'attrs_json'              => $attrs_json,
				'attr'                    => $element_attrs['headingFont'],
				'defaultPrintedStyleAttr' => $default_printed_style_attrs['headingFont'] ?? $heading_font['defaultPrintedStyleAttr'] ?? [],
				'important'               => $heading_font['important'] ?? false,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
				'atRules'                 => $heading_font['atRules'] ?? $at_rules,
			]
		) : null;

		if ( $element_font_heading ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_font_heading );
			} else {
				$element .= $element_font_heading;
			}

			$transition_data['attrs']['headingFont'] = $element_attrs['headingFont'];
			$transition_data['props']['headingFont'] = $heading_font;
		}

		$element_spacing = ! empty( $element_attrs['spacing'] ) ? SpacingStyle::style(
			[
				'selector'                => $spacing['selector'] ?? $selector,
				'selectors'               => $spacing['selectors'] ?? [],
				'propertySelectors'       => $spacing['propertySelectors'] ?? [],
				'selectorFunction'        => $spacing['selectorFunction'] ?? null,
				'attrs_json'              => $attrs_json,
				'attr'                    => $element_attrs['spacing'],
				'defaultPrintedStyleAttr' => $default_printed_style_attrs['spacing'] ?? $spacing['defaultPrintedStyleAttr'] ?? [],
				'important'               => $spacing['important'] ?? false,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
			]
		) : null;

		if ( $element_spacing ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_spacing );
			} else {
				$element .= $element_spacing;
			}

			$transition_data['attrs']['spacing'] = $element_attrs['spacing'];
			$transition_data['props']['spacing'] = $spacing;
		}

		$element_sizing = ! empty( $element_attrs['sizing'] ) ? SizingStyle::style(
			[
				'selector'                => $sizing['selector'] ?? $selector,
				'selectors'               => $sizing['selectors'] ?? [],
				'propertySelectors'       => $sizing['propertySelectors'] ?? [],
				'selectorFunction'        => $sizing['selectorFunction'] ?? null,
				'attrs_json'              => $attrs_json,
				'attr'                    => $element_attrs['sizing'],
				'defaultPrintedStyleAttr' => $default_printed_style_attrs['sizing'] ?? $sizing['defaultPrintedStyleAttr'] ?? [],
				'important'               => $sizing['important'] ?? false,
				'disableAlignmentStyles'  => $disable_button_alignment_styles,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'isParentFlexLayout'      => $is_parent_flex_layout,
				'isParentGridLayout'      => $is_parent_grid_layout,
				'returnType'              => $args['returnType'],
				'skipDefaults'            => $sizing['skipDefaults'] ?? false,
			]
		) : null;

		if ( $element_sizing ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_sizing );
			} else {
				$element .= $element_sizing;
			}

			$transition_data['attrs']['sizing'] = $element_attrs['sizing'];
			$transition_data['props']['sizing'] = $sizing;
		}

		$has_fit_attr   = ! empty( $element_attrs['fit'] );
		$has_fit_preset = ! empty( $default_printed_style_attrs['fit'] );

		$final_default_printed_fit_attr = $default_printed_style_attrs['fit']
			?? $fit['defaultPrintedStyleAttr']
			?? [];

		$element_fit = ( $has_fit_attr || $has_fit_preset ) ? FitStyle::style(
			[
				'selector'                => $fit['selector'] ?? $selector,
				'selectors'               => $fit['selectors'] ?? [],
				'propertySelectors'       => $fit['propertySelectors'] ?? [],
				'selectorFunction'        => $fit['selectorFunction'] ?? null,
				'attr'                    => $element_attrs['fit'] ?? [],
				'defaultPrintedStyleAttr' => $final_default_printed_fit_attr,
				'important'               => $fit['important'] ?? false,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
				'atRules'                 => $fit['atRules'] ?? $at_rules,
			]
		) : null;

		if ( $element_fit ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_fit );
			} else {
				$element .= $element_fit;
			}

			$transition_data['attrs']['fit'] = $element_attrs['fit'] ?? [];
			$transition_data['props']['fit'] = $fit;
		}

		$element_border = ! empty( $element_attrs['border'] ) ? BorderStyle::style(
			[
				'selector'                => $border['selector'] ?? $selector,
				'selectors'               => $border['selectors'] ?? [],
				'propertySelectors'       => $border['propertySelectors'] ?? [],
				'selectorFunction'        => $border['selectorFunction'] ?? null,
				'attrs_json'              => $attrs_json,
				'attr'                    => $element_attrs['border'],
				'defaultPrintedStyleAttr' => $default_printed_style_attrs['border'] ?? $border['defaultPrintedStyleAttr'] ?? [],
				'important'               => $border['important'] ?? false,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
				'atRules'                 => $border['atRules'] ?? $at_rules,
			]
		) : null;

		if ( $element_border ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_border );
			} else {
				$element .= $element_border;
			}

			$transition_data['attrs']['border'] = $element_attrs['border'];
			$transition_data['props']['border'] = $border;
		}

		// Check if box shadow attribute exists or if preset exists.
		// Call BoxShadowStyle even when local attr is empty IF preset exists.
		// This ensures override logic executes when user selects "None" locally with active preset.
		$has_box_shadow_attr   = ! empty( $element_attrs['boxShadow'] );
		$has_box_shadow_preset = ! empty( $default_printed_style_attrs['boxShadow'] );

		// Extract final default printed style attr from multiple sources.
		$final_default_printed_style_attr = $default_printed_style_attrs['boxShadow']
			?? $box_shadow['defaultPrintedStyleAttr']
			?? [];

		$element_box_shadow = ( $has_box_shadow_attr || $has_box_shadow_preset ) ? BoxShadowStyle::style(
			[
				'selector'                => $box_shadow['selector'] ?? $selector,
				'selectors'               => $box_shadow['selectors'] ?? [],
				'propertySelectors'       => $box_shadow['propertySelectors'] ?? [],
				'selectorFunction'        => $box_shadow['selectorFunction'] ?? null,
				'attrs_json'              => $attrs_json,
				'attr'                    => $element_attrs['boxShadow'] ?? [], // Empty array if no local value.
				'defaultPrintedStyleAttr' => $final_default_printed_style_attr,
				'important'               => $box_shadow['important'] ?? false,
				'useOverlay'              => $box_shadow['useOverlay'] ?? false,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
				'atRules'                 => $box_shadow['atRules'] ?? $at_rules,
			]
		) : null;

		if ( $element_box_shadow ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_box_shadow );
			} else {
				$element .= $element_box_shadow;
			}

			$transition_data['attrs']['boxShadow'] = $element_attrs['boxShadow'];
			$transition_data['props']['boxShadow'] = $box_shadow;
		}

		$element_filter = ! empty( $element_attrs['filters'] ) ? FiltersStyle::style(
			[
				'selector'                => $filters['selector'] ?? $selector,
				'selectors'               => $filters['selectors'] ?? [],
				'propertySelectors'       => $filters['propertySelectors'] ?? [],
				'selectorFunction'        => $filters['selectorFunction'] ?? null,
				'attrs_json'              => $attrs_json,
				'attr'                    => $element_attrs['filters'],
				'defaultPrintedStyleAttr' => $default_printed_style_attrs['filters'] ?? $filters['defaultPrintedStyleAttr'] ?? [],
				'important'               => $filters['important'] ?? false,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
				'atRules'                 => $filters['atRules'] ?? $at_rules,
			]
		) : null;

		if ( $element_filter ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_filter );
			} else {
				$element .= $element_filter;
			}

			$transition_data['attrs']['filters'] = $element_attrs['filters'];
			$transition_data['props']['filters'] = $filters;
		}

		$element_disabled_on = ! empty( $element_attrs['disabledOn'] ) ? DisabledOnStyle::style(
			[
				'selector'                 => $disabled_on['selector'] ?? $selector,
				'selectors'                => $disabled_on['selectors'] ?? [],
				'propertySelectors'        => $disabled_on['propertySelectors'] ?? [],
				'selectorFunction'         => $disabled_on['selectorFunction'] ?? null,
				'attrs_json'               => $attrs_json,
				'attr'                     => $element_attrs['disabledOn'],
				'defaultPrintedStyleAttr'  => $default_printed_style_attrs['disabledOn'] ?? $disabled_on['defaultPrintedStyleAttr'] ?? [],
				'disabledModuleVisibility' => $disabled_on['disabledModuleVisibility'] ?? null,
				'orderClass'               => $order_class,
				'isInsideStickyModule'     => $is_inside_sticky_module,
				'stickyParentOrderClass'   => $sticky_parent_order_class,
				'returnType'               => $args['returnType'],
				'atRules'                  => $disabled_on['atRules'] ?? $at_rules,
			]
		) : null;

		if ( $element_disabled_on && $return_as_array ) {
			array_push( $element, ...$element_disabled_on );
		} elseif ( $element_disabled_on ) {
			$element .= $element_disabled_on;
		}

		$element_overflow = ! empty( $element_attrs['overflow'] ) ? OverflowStyle::style(
			[
				'selector'                => $overflow['selector'] ?? $selector,
				'selectors'               => $overflow['selectors'] ?? [],
				'propertySelectors'       => $overflow['propertySelectors'] ?? [],
				'selectorFunction'        => $overflow['selectorFunction'] ?? null,
				'attrs_json'              => $attrs_json,
				'attr'                    => $element_attrs['overflow'],
				'defaultPrintedStyleAttr' => $default_printed_style_attrs['overflow'] ?? $overflow['defaultPrintedStyleAttr'] ?? [],
				'important'               => $overflow['important'] ?? false,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
				'atRules'                 => $overflow['atRules'] ?? $at_rules,
			]
		) : null;

		if ( $element_overflow && $return_as_array ) {
			array_push( $element, ...$element_overflow );
		} elseif ( $element_overflow ) {
			$element .= $element_overflow;
		}

		$element_position = ! empty( $element_attrs['position'] ) ? PositionStyle::style(
			[
				'selector'                => $position['selector'] ?? $selector,
				'selectors'               => $position['selectors'] ?? [],
				'propertySelectors'       => $position['propertySelectors'] ?? [],
				'selectorFunction'        => $position['selectorFunction'] ?? null,
				'attrs_json'              => $attrs_json,
				'attr'                    => $element_attrs['position'],
				'defaultPrintedStyleAttr' => $default_printed_style_attrs['position'] ?? $position['defaultPrintedStyleAttr'] ?? [],
				'important'               => $position['important'] ?? false,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
			]
		) : null;

		if ( $element_position ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_position );
			} else {
				$element .= $element_position;
			}

			$transition_data['attrs']['position'] = $element_attrs['position'];
			$transition_data['props']['position'] = $position;
		}

		$transformed_animation_breakpoints = self::_get_transformed_animation_breakpoints( $element_attrs );
		$transform_attr_for_style          = $element_attrs['transform'] ?? [];
		$position_attr_for_style           = $element_attrs['position'] ?? [];

		foreach ( $transformed_animation_breakpoints as $breakpoint => $enabled ) {
			if ( ! $enabled ) {
				continue;
			}

			// Default-state transform is handled by transformed animation declarations.
			unset( $transform_attr_for_style[ $breakpoint ]['value'] );
			unset( $position_attr_for_style[ $breakpoint ]['value'] );
		}

		$element_transform = ( ! empty( $transform_attr_for_style ) || ! empty( $position_attr_for_style ) ) ? TransformStyle::style(
			[
				'selector'                        => $transform['selector'] ?? $selector,
				'selectors'                       => $transform['selectors'] ?? [],
				'propertySelectors'               => $transform['propertySelectors'] ?? [],
				'selectorFunction'                => $transform['selectorFunction'] ?? null,
				'attr'                            => $transform_attr_for_style,
				'defaultPrintedStyleAttr'         => $default_printed_style_attrs['transform'] ?? $transform['defaultPrintedStyleAttr'] ?? [],
				'important'                       => $transform['important'] ?? false,
				'orderClass'                      => $order_class,
				'isInsideStickyModule'            => $is_inside_sticky_module,
				'stickyParentOrderClass'          => $sticky_parent_order_class,
				'returnType'                      => $args['returnType'],
				'atRules'                         => $transform['atRules'] ?? $at_rules,
				'positionAttr'                    => $position_attr_for_style,
				'positionDefaultPrintedStyleAttr' => $default_printed_style_attrs['position'] ?? $position['defaultPrintedStyleAttr'] ?? [],
			]
		) : null;

		if ( $element_transform ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_transform );
			} else {
				$element .= $element_transform;
			}

			$transition_data['attrs']['transform'] = $transform_attr_for_style;
			$transition_data['props']['transform'] = $transform;
		}

		$transformed_animation_styles = self::_get_transformed_animation_styles(
			[
				'selector'                        => $transform['selector'] ?? $selector,
				'attrs'                           => $element_attrs,
				'transformedAnimationBreakpoints' => $transformed_animation_breakpoints,
				'returnType'                      => $args['returnType'],
			]
		);

		if ( ! empty( $transformed_animation_styles ) ) {
			if ( $return_as_array ) {
				array_push( $element, ...$transformed_animation_styles );
			} else {
				$element .= $transformed_animation_styles;
			}
		}

		$element_z_index = ! empty( $element_attrs['zIndex'] ) ? ZIndexStyle::style(
			[
				'selector'                => $z_index['selector'] ?? $selector,
				'selectors'               => $z_index['selectors'] ?? [],
				'selectorFunction'        => $z_index['selectorFunction'] ?? null,
				'attrs_json'              => $attrs_json,
				'attr'                    => $element_attrs['zIndex'],
				'defaultPrintedStyleAttr' => $default_printed_style_attrs['zIndex'] ?? $z_index['defaultPrintedStyleAttr'] ?? [],
				'important'               => $z_index['important'] ?? false,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
			]
		) : null;

		if ( $element_z_index ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_z_index );
			} else {
				$element .= $element_z_index;
			}

			$transition_data['attrs']['zIndex'] = $element_attrs['zIndex'];
			$transition_data['props']['zIndex'] = $z_index;
		}

		if ( $is_parent_flex_layout || $is_parent_grid_layout ) {

			$element_order = ! empty( $element_attrs['order'] ) ? OrderStyle::style(
				[
					'selector'                => $order['selector'] ?? $selector,
					'selectors'               => $order['selectors'] ?? [],
					'propertySelectors'       => $order['propertySelectors'] ?? [],
					'selectorFunction'        => $order['selectorFunction'] ?? null,
					'attrs_json'              => $attrs_json,
					'attr'                    => $element_attrs['order'],
					'defaultPrintedStyleAttr' => $default_printed_style_attrs['order'] ?? $order['defaultPrintedStyleAttr'] ?? [],
					'important'               => $order['important'] ?? false,
					'orderClass'              => $order_class,
					'isInsideStickyModule'    => $is_inside_sticky_module,
					'stickyParentOrderClass'  => $sticky_parent_order_class,
					'returnType'              => $args['returnType'],
					'atRules'                 => $order['atRules'] ?? $at_rules,
				]
			) : null;

			if ( $element_order ) {
				if ( $return_as_array ) {
					array_push( $element, ...$element_order );
				} else {
					$element .= $element_order;
				}
			}
		}

		$element_button = ! empty( $element_attrs['button'] ) ? ButtonStyle::style(
			[
				'selector'                => $button['selector'] ?? $selector,
				'selectors'               => $button['selectors'] ?? [],
				'propertySelectors'       => $button['propertySelectors'] ?? [],
				'selectorFunction'        => $button['selectorFunction'] ?? null,
				'attrs_json'              => $attrs_json,
				'attr'                    => $element_attrs['button'],
				'defaultPrintedStyleAttr' => $default_printed_style_attrs['button'] ?? $button['defaultPrintedStyleAttr'] ?? [],
				'affectingAttrs'          => $button['affectingAttrs'] ?? [
					'spacing' => $element_attrs['spacing'] ?? [],
					'sizing'  => $element_attrs['sizing'] ?? [],
				],
				'important'               => $button['important'] ?? false,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
			]
		) : null;

		if ( $element_button ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_button );
			} else {
				$element .= $element_button;
			}

			$transition_data['attrs']['button'] = $element_attrs['button'];
			$transition_data['props']['button'] = $button;
		}

		$element_layout = ! empty( $element_attrs['layout'] ) ? LayoutStyle::style(
			[
				'selector'                => $layout['selector'] ?? $selector,
				'selectors'               => $layout['selectors'] ?? [],
				'propertySelectors'       => $layout['propertySelectors'] ?? [],
				'selectorFunction'        => $layout['selectorFunction'] ?? null,
				'attrs_json'              => $attrs_json,
				'attr'                    => $element_attrs['layout'],
				'defaultPrintedStyleAttr' => $default_printed_style_attrs['layout'] ?? $layout['defaultPrintedStyleAttr'] ?? [],
				'important'               => $layout['important'] ?? false,
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
				'atRules'                 => $layout['atRules'] ?? $at_rules,
				'render'                  => $layout['render'] ?? [ 'display' => false ],
			]
		) : null;

		if ( $element_layout ) {
			if ( $return_as_array ) {
				array_push( $element, ...$element_layout );
			} else {
				$element .= $element_layout;
			}
		}

		// Process transition styles only if one of `attrs` or `advanced_styles` is not empty and has hover/sticky state.
		// In VB, there is no check like this since the `attrs` are always set.
		if ( ! empty( $transition_data['attrs'] ) || ! empty( $advanced_styles ) ) {
			// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
			// TODO: fix(D5, Advanced Styles Transition) Revisit `advanced_styles` check once we start working on selector
			// conflicts issue in advanced styles. There is a chance we can optimize this check by moving it to the Transition
			// Style component itself to cover Element Style usage and Transition Style individually.
			// @see https://github.com/elegantthemes/Divi/issues/39774
			// JSON encode the attributes to detect exact transition state keys.
			$transition_attrs_json = ! empty( $transition_data['attrs'] ) ? wp_json_encode( $transition_data['attrs'] ) : '';
			$advanced_styles_json  = ! empty( $advanced_styles ) ? wp_json_encode( $advanced_styles ) : '';

			$needs_transition = TransitionUtils::has_transition_state_in_json( $transition_attrs_json )
				|| TransitionUtils::has_transition_state_in_json( $advanced_styles_json );

			// If the element needs transition, we need to generate transition attribute for every element.
			if ( $needs_transition ) {
				// Automatically generate transition attribute for every element based on module's transition attribute.
				// Element does not and will not have its own transition options so module's transition attribute
				// needs to be passed into other elements' decoration attribute.
				$element_transition = TransitionStyle::style(
					[
						'selector'                => $transition['selector'] ?? $selector,
						'selectors'               => $transition['selectors'] ?? [],
						'propertySelectors'       => $transition['propertySelectors'] ?? [],
						'selectorFunction'        => $transition['selectorFunction'] ?? null,
						'attrs_json'              => $attrs_json,
						'attrs'                   => $attrs,
						'attr'                    => $element_attrs['transition'] ?? [],
						'defaultPrintedStyleAttr' => $default_printed_style_attrs['transition'] ?? $transition['defaultPrintedStyleAttr'] ?? [],
						'important'               => $transition['important'] ?? false,
						'advancedStyles'          => $advanced_styles ?? [],
						'transitionData'          => $transition_data,
						'orderClass'              => $order_class,
						'isInsideStickyModule'    => $is_inside_sticky_module,
						'stickyParentOrderClass'  => $sticky_parent_order_class,
						'returnType'              => $args['returnType'],
					]
				);

				if ( $element_transition && $return_as_array ) {
					array_push( $element, ...$element_transition );
				} elseif ( $element_transition ) {
					$element .= $element_transition;
				}
			}
		}

		// Advanced styles.
		if ( ! empty( $advanced_styles ) ) {
			$element_advanced_styles = ElementStyleAdvancedStyles::style(
				[
					'selector'               => $selector,
					'advancedStyles'         => $advanced_styles,
					'orderClass'             => $order_class,
					'isInsideStickyModule'   => $is_inside_sticky_module,
					'stickyParentOrderClass' => $sticky_parent_order_class,
					'isParentFlexLayout'     => $args['isParentFlexLayout'] ?? false,
					'isParentGridLayout'     => $args['isParentGridLayout'] ?? false,
					'attrs_json'             => $attrs_json,
					'returnType'             => $args['returnType'],
					'atRules'                => $at_rules,

				]
			);

			if ( $element_advanced_styles ) {
				if ( $return_as_array ) {
					array_push( $element, ...$element_advanced_styles );
				} else {
					$element .= $element_advanced_styles;
				}

				// When the element attrs are empty because there is no modified settings, even
				// though the advanced styles parameter is not empty, the style wrapper will
				// return empty string and all the advanced styles won't be rendered. To fix
				// this issue, we need to set the advanced styles as element attr so that style
				// wrapper can render the children element from advanced styles.
				$element_attrs['advancedStyles'] = $advanced_styles;
			}
		}

		return Utils::style_wrapper(
			[
				'attr'     => $element_attrs,
				'asStyle'  => $as_style,
				'children' => $element,
			]
		);
	}

	/**
	 * Get transformed-animation status map for supported breakpoints.
	 *
	 * @since ??
	 *
	 * @param array $element_attrs Module attrs.
	 *
	 * @return array
	 */
	private static function _get_transformed_animation_breakpoints( array $element_attrs ): array {
		// Get enabled breakpoint names dynamically (supports custom breakpoints).
		$supported_breakpoints = Breakpoint::get_enabled_breakpoint_names();
		$status                = [];

		foreach ( $supported_breakpoints as $breakpoint ) {
			$status[ $breakpoint ] = AnimationUtils::has_transformed_animation_for_breakpoint(
				$element_attrs,
				$breakpoint
			);
		}

		return $status;
	}

	/**
	 * Build transformed-animation styles for affected breakpoints.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments used to build transformed-animation styles.
	 *
	 *     @type string $selector Module selector.
	 *     @type array  $attrs Module attrs.
	 *     @type array  $transformedAnimationBreakpoints Breakpoint status map.
	 *     @type string $returnType Return type (`array` or `string`).
	 * }
	 *
	 * @return array|string
	 */
	private static function _get_transformed_animation_styles( array $args ) {
		$selector                          = $args['selector'] ?? '';
		$attrs                             = $args['attrs'] ?? [];
		$transformed_animation_breakpoints = $args['transformedAnimationBreakpoints'] ?? [];
		$return_type                       = $args['returnType'] ?? 'array';
		$return_as_array                   = 'array' === $return_type;

		if ( empty( $selector ) || empty( $attrs['animation'] ) ) {
			return $return_as_array ? [] : '';
		}

		$styles                    = [];
		$selector_hash             = substr( md5( $selector ), 0, 8 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_md5 -- Hash used only as deterministic CSS key suffix.
		$style_breakpoint_settings = Breakpoint::get_style_breakpoint_settings();
		$animation_selector        = self::_append_animation_marker_classes( $selector );

		// Iterate over breakpoints dynamically.
		foreach ( $transformed_animation_breakpoints as $breakpoint => $enabled ) {
			if ( ! $enabled ) {
				continue;
			}

			$animation_attrs = ModuleUtils::use_attr_value(
				[
					'attr'       => $attrs['animation'],
					'breakpoint' => $breakpoint,
					'state'      => 'value',
					'mode'       => 'getAndInheritAll',
				]
			);

			if ( ! is_array( $animation_attrs ) ) {
				continue;
			}

			$animation_style = $animation_attrs['style'] ?? 'none';

			if ( ! in_array( $animation_style, [ 'slide', 'zoom', 'flip', 'fold', 'roll' ], true ) ) {
				continue;
			}

			$direction = $animation_attrs['direction'] ?? 'center';

			if ( ! in_array( $direction, [ 'center', 'top', 'right', 'bottom', 'left' ], true ) ) {
				$direction = 'center';
			}

			// Extract intensity value from already-resolved animation attrs (avoids duplicate use_attr_value call).
			// Apply style-based extraction logic (exact same as AnimationUtils filter_value callback).
			$intensity_value       = $animation_attrs['intensity'] ?? '50%';
			$styles_with_intensity = [ 'slide', 'zoom', 'flip', 'fold', 'roll' ];
			$intensity_string      = '50%';

			if ( is_array( $intensity_value ) ) {
				if ( in_array( $animation_style, $styles_with_intensity, true ) ) {
					// Style-based array structure - extract value for current animation style.
					$intensity_string = $intensity_value[ $animation_style ] ?? '50%';
				}
			} else {
				// Scalar value - use as-is.
				$intensity_string = $intensity_value;
			}

			$intensity_value = preg_replace( '/[^0-9\-]/', '', (string) $intensity_string );
			$intensity       = '' === $intensity_value ? 50 : (int) $intensity_value;

			$transform_data = self::_get_default_transform_data_for_breakpoint(
				$attrs,
				$breakpoint
			);

			if ( empty( $transform_data['finalDeclaration'] ) ) {
				continue;
			}

			$keyframe_rules = self::_get_transformed_animation_keyframe_rules(
				$animation_style,
				$direction,
				$intensity,
				$transform_data['transformValuePair'],
				$transform_data['finalDeclaration']
			);

			if ( empty( $keyframe_rules ) ) {
				continue;
			}

			$keyframe_name = sprintf(
				'et_pb_transform_anim_%1$s_%2$s',
				$selector_hash,
				$breakpoint
			);

			$animation_rules = "animation-name: {$keyframe_name};";

			if (
				! $transform_data['hasOrigin']
				&& in_array( $animation_style, [ 'zoom', 'fold', 'roll' ], true )
			) {
				$animation_rules .= "transform-origin: {$direction};";
			}

			$at_rules = Utils::get_at_rules( $breakpoint, $style_breakpoint_settings );

			$styles[] = [
				'selector'    => "@keyframes {$keyframe_name}",
				'declaration' => $keyframe_rules,
				'atRules'     => $at_rules,
			];
			$styles[] = [
				'selector'    => $animation_selector,
				'declaration' => $animation_rules,
				'atRules'     => $at_rules,
			];
			$styles[] = [
				'selector'    => $selector,
				'declaration' => $transform_data['finalDeclaration'],
				'atRules'     => $at_rules,
			];
		}

		if ( $return_as_array ) {
			return $styles;
		}

		$output = '';

		foreach ( $styles as $style ) {
			$ruleset = Utils::get_ruleset(
				[
					'selector'    => $style['selector'],
					'declaration' => $style['declaration'],
				]
			);

			$output .= is_string( $style['atRules'] ?? false )
				? Utils::get_statement(
					[
						'atRules' => $style['atRules'],
						'ruleset' => $ruleset,
					]
				)
				: $ruleset;
		}

		return $output;
	}

	/**
	 * Get default-state transform data for a breakpoint.
	 *
	 * @since ??
	 *
	 * @param array  $attrs      Module attrs.
	 * @param string $breakpoint Breakpoint name.
	 *
	 * @return array
	 */
	private static function _get_default_transform_data_for_breakpoint( array $attrs, string $breakpoint ): array {
		$transform_attrs = ModuleUtils::use_attr_value(
			[
				'attr'         => $attrs['transform'] ?? [],
				'breakpoint'   => $breakpoint,
				'state'        => 'value',
				'mode'         => 'getOrInheritAll',
				'defaultValue' => [],
			]
		);

		if ( ! is_array( $transform_attrs ) ) {
			$transform_attrs = [];
		}

		$position_attrs = ModuleUtils::use_attr_value(
			[
				'attr'         => $attrs['position'] ?? [],
				'breakpoint'   => $breakpoint,
				'state'        => 'value',
				'mode'         => 'getOrInheritAll',
				'defaultValue' => [],
			]
		);

		$position_translates = self::_get_position_translates_for_breakpoint(
			is_array( $position_attrs ) ? $position_attrs : [],
			$breakpoint
		);

		$merged_transform_attrs = $transform_attrs;

		if ( ! empty( $position_translates ) ) {
			$merged_transform_attrs['translate'] = array_merge(
				[
					'x' => $transform_attrs['translate']['x'] ?? null,
					'y' => $transform_attrs['translate']['y'] ?? null,
				],
				$position_translates
			);
		}

		$transform_value      = TransformDeclaration::value( $merged_transform_attrs );
		$transform_value_pair = TransformDeclaration::value( $merged_transform_attrs, 'key_value_pair' );
		$origin               = $merged_transform_attrs['origin'] ?? [];
		$has_origin           = ! empty( $origin );
		$final_declaration    = '';

		if ( ! empty( $transform_value ) ) {
			$final_declaration .= "transform: {$transform_value};";
		}

		if ( $has_origin ) {
			$default_origin     = [
				'x' => '50%',
				'y' => '50%',
			];
			$origin             = array_merge( $default_origin, $origin );
			$final_declaration .= "transform-origin: {$origin['x']} {$origin['y']};";
		}

		return [
			'transformValuePair' => is_array( $transform_value_pair ) ? $transform_value_pair : [],
			'finalDeclaration'   => $final_declaration,
			'hasOrigin'          => $has_origin,
		];
	}

	/**
	 * Get position-based translate fallbacks for a breakpoint.
	 *
	 * @since ??
	 *
	 * @param array  $position_attrs Position attrs for the breakpoint value state.
	 * @param string $breakpoint     Breakpoint name.
	 *
	 * @return array
	 */
	private static function _get_position_translates_for_breakpoint( array $position_attrs, string $breakpoint ): array {
		$mode = $position_attrs['mode'] ?? 'default';

		if ( 'default' === $mode || empty( $position_attrs['origin'][ $mode ] ) ) {
			return [];
		}

		$origin_parts = explode( ' ', $position_attrs['origin'][ $mode ] );
		$vertical     = $origin_parts[0] ?? '';
		$horizontal   = $origin_parts[1] ?? '';
		$translates   = [];

		if ( 'center' === $horizontal ) {
			$translates['x'] = '-50%';
		} elseif ( 'desktop' !== $breakpoint ) {
			$translates['x'] = '0px';
		}

		if ( 'center' === $vertical ) {
			$translates['y'] = '-50%';
		} elseif ( 'desktop' !== $breakpoint ) {
			$translates['y'] = '0px';
		}

		return $translates;
	}

	/**
	 * Build keyframe declaration for transformed animations.
	 *
	 * @since ??
	 *
	 * @param string $animation_style      Animation style.
	 * @param string $direction            Animation direction.
	 * @param int    $intensity            Animation intensity.
	 * @param array  $transform_value_pair Final transform key-value pair.
	 * @param string $final_declaration    Final transform declaration.
	 *
	 * @return string
	 */
	private static function _get_transformed_animation_keyframe_rules(
		string $animation_style,
		string $direction,
		int $intensity,
		array $transform_value_pair,
		string $final_declaration
	): string {
		$start = $transform_value_pair;

		if ( 'slide' === $animation_style && 'center' === $direction ) {
			$animation_style = 'zoom';
		}

		switch ( $animation_style ) {
			case 'zoom':
				$scale           = ( 100 - $intensity ) * 0.01;
				$start['scaleX'] = $scale * (float) ( $transform_value_pair['scaleX'] ?? 1 );
				$start['scaleY'] = $scale * (float) ( $transform_value_pair['scaleY'] ?? 1 );
				$start_transform = self::_get_transform_declaration_from_value_pair( $start );

				return "0%{ {$start_transform} }100%{opacity:1;{$final_declaration}}";
			case 'slide':
				$translate_x = $transform_value_pair['translateX'] ?? '0%';
				$translate_y = $transform_value_pair['translateY'] ?? '0%';

				switch ( $direction ) {
					case 'top':
						$start['translateY'] = sprintf( 'calc(%1$s%% + %2$s)', $intensity * -2, $translate_y );
						$start['translateX'] = $translate_x;
						break;
					case 'bottom':
						$start['translateY'] = sprintf( 'calc(%1$s%% + %2$s)', $intensity * 2, $translate_y );
						$start['translateX'] = $translate_x;
						break;
					case 'left':
						$start['translateX'] = sprintf( 'calc(%1$s%% + %2$s)', $intensity * -2, $translate_x );
						$start['translateY'] = $translate_y;
						break;
					case 'right':
						$start['translateX'] = sprintf( 'calc(%1$s%% + %2$s)', $intensity * 2, $translate_x );
						$start['translateY'] = $translate_y;
						break;
				}

				$start_transform = self::_get_transform_declaration_from_value_pair( $start );

				return "0%{ {$start_transform} }100%{opacity:1;{$final_declaration}}";
			case 'flip':
				$intensity_angle      = (int) ceil( ( 90 / 100 ) * $intensity );
				$start['perspective'] = '2000px';
				$base_rotate_x        = self::_parse_angle_value( $transform_value_pair['rotateX'] ?? '0deg' );
				$base_rotate_y        = self::_parse_angle_value( $transform_value_pair['rotateY'] ?? '0deg' );

				switch ( $direction ) {
					case 'bottom':
						$start['rotateX'] = ( $intensity_angle * -1 ) + $base_rotate_x . 'deg';
						break;
					case 'left':
						$start['rotateY'] = ( $intensity_angle * -1 ) + $base_rotate_y . 'deg';
						break;
					case 'right':
						$start['rotateY'] = $intensity_angle + $base_rotate_y . 'deg';
						break;
					case 'top':
					default:
						$start['rotateX'] = $intensity_angle + $base_rotate_x . 'deg';
						break;
				}

				$start_transform = self::_get_transform_declaration_from_value_pair( $start );

				return "0%{ {$start_transform} }100%{opacity:1;{$final_declaration}}";
			case 'fold':
				$intensity_angle      = (int) ceil( ( 90 / 100 ) * $intensity );
				$start['perspective'] = '2000px';
				$base_rotate_x        = self::_parse_angle_value( $transform_value_pair['rotateX'] ?? '0deg' );
				$base_rotate_y        = self::_parse_angle_value( $transform_value_pair['rotateY'] ?? '0deg' );

				switch ( $direction ) {
					case 'top':
						$start['rotateX'] = ( $intensity_angle * -1 ) + $base_rotate_x . 'deg';
						break;
					case 'bottom':
						$start['rotateX'] = $intensity_angle + $base_rotate_x . 'deg';
						break;
					case 'left':
						$start['rotateY'] = $intensity_angle + $base_rotate_y . 'deg';
						break;
					case 'right':
					default:
						$start['rotateY'] = ( $intensity_angle * -1 ) + $base_rotate_y . 'deg';
						break;
				}

				$start_transform = self::_get_transform_declaration_from_value_pair( $start );

				return "0%{ {$start_transform} }100%{opacity:1;{$final_declaration}}";
			case 'roll':
				$intensity_angle = (int) ceil( ( 360 / 100 ) * $intensity );
				$base_rotate_z   = self::_parse_angle_value( $transform_value_pair['rotateZ'] ?? '0deg' );

				if ( in_array( $direction, [ 'bottom', 'right' ], true ) ) {
					$start['rotateZ'] = ( $intensity_angle * -1 ) + $base_rotate_z . 'deg';
				} else {
					$start['rotateZ'] = $intensity_angle + $base_rotate_z . 'deg';
				}

				$start_transform = self::_get_transform_declaration_from_value_pair( $start );

				return "0%{ {$start_transform} }100%{opacity:1;{$final_declaration}}";
			default:
				return '';
		}
	}

	/**
	 * Build transform CSS declaration from transform value pair.
	 *
	 * @since ??
	 *
	 * @param array $value_pair Transform value pair.
	 *
	 * @return string
	 */
	private static function _get_transform_declaration_from_value_pair( array $value_pair ): string {
		$order       = [
			'scaleX',
			'scaleY',
			'translateX',
			'translateY',
			'rotateX',
			'rotateY',
			'rotateZ',
			'skewX',
			'skewY',
		];
		$declaration = [];

		if ( isset( $value_pair['perspective'] ) ) {
			$declaration[] = 'perspective(' . $value_pair['perspective'] . ')';
		}

		foreach ( $order as $option ) {
			if ( isset( $value_pair[ $option ] ) && '' !== (string) $value_pair[ $option ] ) {
				$declaration[] = sprintf( '%1$s(%2$s)', $option, $value_pair[ $option ] );
			}
		}

		return ! empty( $declaration )
			? 'transform: ' . implode( ' ', $declaration ) . ';'
			: '';
	}

	/**
	 * Parse numeric degree value.
	 *
	 * @since ??
	 *
	 * @param string $value Degree value.
	 *
	 * @return float
	 */
	private static function _parse_angle_value( string $value ): float {
		return (float) str_replace( 'deg', '', $value );
	}

	/**
	 * Append transformed-animation marker classes to each selector.
	 *
	 * @since ??
	 *
	 * @param string $selector Selector string.
	 *
	 * @return string
	 */
	private static function _append_animation_marker_classes( string $selector ): string {
		$selector_parts = array_filter( array_map( 'trim', explode( ',', $selector ) ) );
		$selectors      = array_map(
			function ( $part ) {
				return "{$part}.et_animated.transformAnim";
			},
			$selector_parts
		);

		return implode( ', ', $selectors );
	}
}
