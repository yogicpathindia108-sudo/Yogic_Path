<?php
/**
 * Post Filter field FormFieldStyle helpers.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilter;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Element\ElementStyleAdvancedStyles;
use ET\Builder\Packages\ModuleLibrary\RadioFieldAndIconAttrs;

/**
 * Shared selector/property-selector builders for post-filter field styles.
 *
 * This class centralises the font-property redirect logic used by both
 * PostFilterModule and PostFilterItemModule. It is modelled after the inline
 * approach in ContactFormModule::style(), where identical font-property lists
 * and checkbox/radio selector builders are duplicated per call site. Here the
 * same logic is extracted into named constants and static helper methods so the
 * two post-filter modules can share a single source of truth.
 *
 * @since ??
 */
class PostFilterFieldFormFieldStyle {

	/**
	 * Font group properties used for property selector maps.
	 *
	 * @since ??
	 *
	 * @var array<int, string>
	 */
	private const FONT_GROUP_PROPERTIES = [
		'color',
		'font-family',
		'font-size',
		'font-style',
		'font-weight',
		'letter-spacing',
		'line-height',
		'text-align',
		'text-decoration',
		'text-transform',
	];

	/**
	 * Label text-decoration font properties used for checkbox/radio option label text.
	 *
	 * @since ??
	 *
	 * @var array<int, string>
	 */
	private const LABEL_TEXT_DECORATION_FONT_PROPERTIES = [
		'text-decoration-line',
		'text-decoration-color',
		'text-decoration-style',
		'font-variant',
	];

	/**
	 * Build FormFieldStyle args for post-filter field attrs.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Style args.
	 *
	 *     @type string $orderClass             Module order class.
	 *     @type string $scope                  `parent` or `child`.
	 *     @type array  $attr                   Field attrs.
	 *     @type bool   $isInsideStickyModule   Whether module is inside sticky module.
	 *     @type string $stickyParentOrderClass Sticky parent order class.
	 * }
	 *
	 * @return array FormFieldStyle args.
	 */
	public static function get_style_args( array $args ): array {
		$order_class                = $args['orderClass'] ?? '';
		$scope                      = $args['scope'] ?? 'parent';
		$field_attrs                = $args['attr'] ?? [];
		$is_inside_sticky_module    = $args['isInsideStickyModule'] ?? false;
		$sticky_parent_order_class  = $args['stickyParentOrderClass'] ?? null;
		// Child scope uses a compound selector `{$order_class}.et_pb_post_filter_item` so the
		// child field styles target the element that holds both the unique order class and the
		// module base class. This adds one extra class of specificity vs the parent's single
		// order class, ensuring child-level field design overrides parent aggregate field design
		// without `!important`. The compound form also avoids introducing the parent module
		// class (.et_pb_post_filter) before VB builder prefixes (.et-db, #et-boc, .et-l) that
		// are ancestors of .et_pb_post_filter in the DOM, which would create a selector that
		// never matches in the VB context.
		$scope_prefix               = 'child' === $scope ? "{$order_class}.et_pb_post_filter_item" : $order_class;
		$control_base               = "{$scope_prefix} .et_pb_post_filter__item-control-surface";
		$input_control              = "{$control_base} .et_pb_post_filter__item-control:not([type=checkbox]):not([type=radio]):not(select)";
		$select_control             = "{$control_base} select.et_pb_post_filter__item-control";
		$field_selector             = "{$input_control}, {$select_control}";
		// Placeholder pseudo-elements are intentionally excluded from the comma-separated
		// selector list. Mixing real element selectors with vendor-prefixed pseudo-element
		// selectors (e.g. :-ms-input-placeholder) in a non-forgiving CSS selector list causes
		// the entire rule to be silently dropped in browsers that do not recognise the
		// vendor pseudo-element. Background, border, spacing, and box-shadow styles would
		// therefore never apply. Placeholder font styling is handled separately by
		// FormFieldStyle via its internal placeholderFont / selectorFunction mechanism.
		$value_selector             = "{$input_control}, {$select_control}";
		$hover_selector             = "{$input_control}:hover, {$select_control}:hover";
		$label_selector             = "{$scope_prefix} .et_pb_post_filter__item-label";
		$label_selector_hover       = "{$label_selector}:hover";
		$label_font_selectors       = array_fill_keys( self::FONT_GROUP_PROPERTIES, $label_selector );
		$label_font_selectors_hover = array_fill_keys( self::FONT_GROUP_PROPERTIES, $label_selector_hover );

		return [
			'selector'               => $field_selector,
			'selectors'              => [
				'desktop' => [
					'value' => $value_selector,
					'hover' => $hover_selector,
				],
			],
			'attr'                   => $field_attrs,
			'propertySelectors'      => [
				'label' => [
					'font' => [
						'font'       => [
							'desktop' => [
								'value' => $label_font_selectors,
								'hover' => $label_font_selectors_hover,
							],
						],
						'textShadow' => [
							'desktop' => [
								'value' => [
									'text-shadow' => $label_selector,
								],
								'hover' => [
									'text-shadow' => $label_selector_hover,
								],
							],
						],
					],
				],
			],
			'orderClass'             => $order_class,
			'isInsideStickyModule'   => $is_inside_sticky_module,
			'stickyParentOrderClass' => $sticky_parent_order_class,
		];
	}

	/**
	 * Build ElementStyle styleProps for post-filter option row wrappers.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Style args.
	 *
	 *     @type string $orderClass Module order class.
	 *     @type string $scope      `parent` or `child`.
	 * }
	 *
	 * @return array ElementStyle styleProps keyed by decoration.
	 */
	public static function get_option_style_args( array $args ): array {
		$order_class = $args['orderClass'] ?? '';
		$scope       = $args['scope'] ?? 'parent';

		if ( 'child' === $scope ) {
			$value_selector = ".et_pb_post_filter {$order_class} .et_pb_post_filter__item-option";
		} else {
			$value_selector = "{$order_class} .et_pb_post_filter__item-option";
		}

		$checked_selector      = "{$value_selector}:has(input:checked)";
		$decoration_selectors  = [
			'desktop' => [
				'value'   => $value_selector,
				'checked' => $checked_selector,
			],
		];
		$style_props           = [];

		foreach ( self::_get_option_row_decoration_keys() as $decoration_key ) {
			$style_props[ $decoration_key ] = [
				'selectors' => $decoration_selectors,
			];
		}

		return $style_props;
	}

	/**
	 * Collect `ElementStyle` decoration keys from style component map `propName` entries.
	 *
	 * @since ??
	 *
	 * @return array<int, string> Decoration keys for option row style props.
	 */
	private static function _get_option_row_decoration_keys(): array {
		$decoration_keys = [];

		foreach ( ElementStyleAdvancedStyles::style_component_map() as $entry ) {
			if ( isset( $entry['propName'] ) ) {
				$decoration_keys[] = $entry['propName'];
			}
		}

		return array_values( array_unique( $decoration_keys ) );
	}

	/**
	 * Build checkbox/radio selector set using the contact-form field pattern.
	 *
	 * @since ??
	 *
	 * @param string $order_class  Module order class.
	 * @param string $scope        `parent` or `child`.
	 * @param string $control_type `checkbox` or `radio`.
	 *
	 * @return array{
	 *     indicator: string,
	 *     indicatorFocus: string,
	 *     indicatorChecked: string,
	 *     labelText: string,
	 *     labelTextChecked: string,
	 *     checkedIcon: string
	 * }
	 */
	private static function _get_control_selectors( string $order_class, string $scope, string $control_type ): array {
		$input_selector = ".et_pb_post_filter__item-control[type=\"{$control_type}\"]";

		if ( 'child' === $scope ) {
			$field_scope = "{$order_class}.et_pb_post_filter_item {$input_selector}";

			return [
				'indicator'        => "{$field_scope} + label i",
				'indicatorFocus'   => "{$field_scope}:focus + label i",
				'indicatorChecked' => "{$field_scope}:checked + label i",
				'labelText'        => "{$field_scope} + label",
				'labelTextChecked' => "{$field_scope}:checked + label",
				'checkedIcon'      => "{$field_scope}:checked + label i:before",
			];
		}

		$field_scope            = "{$order_class} .et_pb_post_filter_item {$input_selector}";
		$module_compound_scope  = "{$order_class}.et_pb_post_filter {$input_selector}";

		return [
			'indicator'        => "{$field_scope} + label i",
			'indicatorFocus'   => "{$field_scope}:focus + label i",
			'indicatorChecked' => "{$field_scope}:checked + label i",
			'labelText'        => "{$module_compound_scope} + label",
			'labelTextChecked' => "{$module_compound_scope}:checked + label",
			'checkedIcon'      => "{$module_compound_scope}:checked + label i:before",
		];
	}

	/**
	 * Build font property selector map for checkbox/radio option label text.
	 *
	 * @since ??
	 *
	 * @param string $label_selector Label selector.
	 *
	 * @return array<string, string> Font property selector map.
	 */
	private static function _get_option_label_font_property_selectors( string $label_selector ): array {
		return array_merge(
			array_fill_keys( self::FONT_GROUP_PROPERTIES, $label_selector ),
			array_fill_keys( self::LABEL_TEXT_DECORATION_FONT_PROPERTIES, $label_selector )
		);
	}

	/**
	 * Build layout style args for checkbox/radio options list wrappers.
	 *
	 * @since ??
	 *
	 * @param string $order_class                 Module order class.
	 * @param string $scope                       `parent` or `child`.
	 * @param string $control_type                `checkbox` or `radio`.
	 * @param array  $default_printed_layout_attr Default printed layout attrs.
	 *
	 * @return array Layout style args for FormFieldStyle.
	 */
	private static function _get_options_list_layout_style_args(
		string $order_class,
		string $scope,
		string $control_type,
		array $default_printed_layout_attr
	): array {
		$scope_prefix = 'child' === $scope
			? "{$order_class}.et_pb_post_filter_item"
			: "{$order_class} .et_pb_post_filter_item";

		return [
			'selector'                => "{$scope_prefix} .et_pb_post_filter__item-options-wrapper--{$control_type} .et_pb_post_filter__item-options-list",
			'render'                  => [
				'display' => true,
			],
			'defaultPrintedStyleAttr' => $default_printed_layout_attr,
		];
	}

	/**
	 * Build FormFieldStyle args for post-filter checkbox attrs.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Style args.
	 *
	 *     @type string $orderClass             Module order class.
	 *     @type string $scope                  `parent` or `child`.
	 *     @type array  $attr                   Checkbox attrs.
	 *     @type bool   $isInsideStickyModule   Whether module is inside sticky module.
	 *     @type string $stickyParentOrderClass Sticky parent order class.
	 *     @type array  $defaultPrintedStyleAttrs Default printed style attrs for the module.
	 * }
	 *
	 * @return array FormFieldStyle args.
	 */
	public static function get_checkbox_style_args( array $args ): array {
		$order_class                   = $args['orderClass'] ?? '';
		$scope                         = $args['scope'] ?? 'parent';
		$checkbox_attrs                = $args['attr'] ?? [];
		$is_inside_sticky_module       = $args['isInsideStickyModule'] ?? false;
		$sticky_parent_order_class     = $args['stickyParentOrderClass'] ?? null;
		$default_printed_style_attrs   = $args['defaultPrintedStyleAttrs'] ?? [];
		$selectors                     = self::_get_control_selectors( $order_class, $scope, 'checkbox' );
		$font_property_selectors       = self::_get_option_label_font_property_selectors( $selectors['labelText'] );
		$font_property_selectors_checked = self::_get_option_label_font_property_selectors( $selectors['labelTextChecked'] );

		return [
			'selector'               => $selectors['indicator'],
			'selectors'              => [
				'desktop' => [
					'value'   => $selectors['indicator'],
					'hover'   => $selectors['indicator'],
					'focus'   => $selectors['indicatorFocus'],
					'checked' => $selectors['indicatorChecked'],
				],
			],
			'attr'                   => $checkbox_attrs,
			'propertySelectors'      => [
				'font' => [
					'font'       => [
						'desktop' => [
							'value'   => $font_property_selectors,
							'hover'   => $font_property_selectors,
							'checked' => $font_property_selectors_checked,
						],
					],
					'textShadow' => [
						'desktop' => [
							'value'   => [
								'text-shadow' => $selectors['labelText'],
							],
							'hover'   => [
								'text-shadow' => $selectors['labelText'],
							],
							'checked' => [
								'text-shadow' => $selectors['labelTextChecked'],
							],
						],
					],
				],
			],
			'orderClass'             => $order_class,
			'isInsideStickyModule'   => $is_inside_sticky_module,
			'stickyParentOrderClass' => $sticky_parent_order_class,
			'disableLabelStyle'      => true,
			'layout'                 => self::_get_options_list_layout_style_args(
				$order_class,
				$scope,
				'checkbox',
				$default_printed_style_attrs['checkbox']['decoration']['layout'] ?? []
			),
		];
	}

	/**
	 * Build ElementStyle args for post-filter checked checkbox icon.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Style args.
	 *
	 *     @type string $orderClass             Module order class.
	 *     @type string $scope                  `parent` or `child`.
	 *     @type array  $attr                   Checkbox attrs.
	 *     @type bool   $isInsideStickyModule   Whether module is inside sticky module.
	 *     @type string $stickyParentOrderClass Sticky parent order class.
	 * }
	 *
	 * @return array ElementStyle args.
	 */
	public static function get_checkbox_icon_style_args( array $args ): array {
		$order_class               = $args['orderClass'] ?? '';
		$scope                     = $args['scope'] ?? 'parent';
		$checkbox_attrs            = $args['attr'] ?? [];
		$is_inside_sticky_module   = $args['isInsideStickyModule'] ?? false;
		$sticky_parent_order_class = $args['stickyParentOrderClass'] ?? null;
		$selectors                 = self::_get_control_selectors( $order_class, $scope, 'checkbox' );

		return [
			'selector'               => $selectors['checkedIcon'],
			'attrs'                  => [
				'icon' => $checkbox_attrs['decoration']['icon'] ?? [],
			],
			'orderClass'             => $order_class,
			'isInsideStickyModule'   => $is_inside_sticky_module,
			'stickyParentOrderClass' => $sticky_parent_order_class,
		];
	}

	/**
	 * Build FormFieldStyle args for post-filter radio attrs.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Style args.
	 *
	 *     @type string $orderClass             Module order class.
	 *     @type string $scope                  `parent` or `child`.
	 *     @type array  $attr                   Radio attrs.
	 *     @type bool   $isInsideStickyModule   Whether module is inside sticky module.
	 *     @type string $stickyParentOrderClass Sticky parent order class.
	 *     @type array  $defaultPrintedStyleAttrs Default printed style attrs for the module.
	 * }
	 *
	 * @return array FormFieldStyle args.
	 */
	public static function get_radio_style_args( array $args ): array {
		$order_class                   = $args['orderClass'] ?? '';
		$scope                         = $args['scope'] ?? 'parent';
		$radio_attrs                   = $args['attr'] ?? [];
		$is_inside_sticky_module       = $args['isInsideStickyModule'] ?? false;
		$sticky_parent_order_class     = $args['stickyParentOrderClass'] ?? null;
		$default_printed_style_attrs   = $args['defaultPrintedStyleAttrs'] ?? [];
		$selectors                     = self::_get_control_selectors( $order_class, $scope, 'radio' );
		$font_property_selectors       = self::_get_option_label_font_property_selectors( $selectors['labelText'] );
		$font_property_selectors_checked = self::_get_option_label_font_property_selectors( $selectors['labelTextChecked'] );
		$radio_field_attrs             = RadioFieldAndIconAttrs::get( $radio_attrs )['fieldAttr'];

		return [
			'selector'               => $selectors['indicator'],
			'selectors'              => [
				'desktop' => [
					'value'   => $selectors['indicator'],
					'hover'   => $selectors['indicator'],
					'focus'   => $selectors['indicatorFocus'],
					'checked' => $selectors['indicatorChecked'],
				],
			],
			'attr'                   => $radio_field_attrs,
			'propertySelectors'      => [
				'font' => [
					'font'       => [
						'desktop' => [
							'value'   => $font_property_selectors,
							'hover'   => $font_property_selectors,
							'checked' => $font_property_selectors_checked,
						],
					],
					'textShadow' => [
						'desktop' => [
							'value'   => [
								'text-shadow' => $selectors['labelText'],
							],
							'hover'   => [
								'text-shadow' => $selectors['labelText'],
							],
							'checked' => [
								'text-shadow' => $selectors['labelTextChecked'],
							],
						],
					],
				],
			],
			'orderClass'             => $order_class,
			'isInsideStickyModule'   => $is_inside_sticky_module,
			'stickyParentOrderClass' => $sticky_parent_order_class,
			'disableLabelStyle'      => true,
			'layout'                 => self::_get_options_list_layout_style_args(
				$order_class,
				$scope,
				'radio',
				$default_printed_style_attrs['radio']['decoration']['layout'] ?? []
			),
		];
	}

	/**
	 * Build ElementStyle args for post-filter checked radio icon.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Style args.
	 *
	 *     @type string $orderClass             Module order class.
	 *     @type string $scope                  `parent` or `child`.
	 *     @type array  $attr                   Radio attrs.
	 *     @type bool   $isInsideStickyModule   Whether module is inside sticky module.
	 *     @type string $stickyParentOrderClass Sticky parent order class.
	 * }
	 *
	 * @return array ElementStyle args.
	 */
	public static function get_radio_icon_style_args( array $args ): array {
		$order_class               = $args['orderClass'] ?? '';
		$scope                     = $args['scope'] ?? 'parent';
		$radio_attrs               = $args['attr'] ?? [];
		$is_inside_sticky_module   = $args['isInsideStickyModule'] ?? false;
		$sticky_parent_order_class = $args['stickyParentOrderClass'] ?? null;
		$selectors                 = self::_get_control_selectors( $order_class, $scope, 'radio' );
		$radio_icon_attrs          = RadioFieldAndIconAttrs::get( $radio_attrs )['iconAttr'];

		return [
			'selector'               => $selectors['checkedIcon'],
			'attrs'                  => [
				'icon' => $radio_icon_attrs,
			],
			'orderClass'             => $order_class,
			'isInsideStickyModule'   => $is_inside_sticky_module,
			'stickyParentOrderClass' => $sticky_parent_order_class,
		];
	}
}
