<?php
/**
 * Module Library: Gravity Forms module.
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\GravityForms;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WordPress uses snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\StyleCommon\CommonStyle;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\FormField\FieldDecorationPresetAttrsMap;
use ET\Builder\Packages\Module\Options\FormField\FormFieldStyle;
use ET\Builder\Packages\Module\Options\FormField\NativeChoicePresetAttrsMap;
use ET\Builder\Packages\Module\Options\Font\FontPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Font\FontStyle;
use ET\Builder\Packages\Module\Options\Image\ImagePresetAttrsMap;
use ET\Builder\Packages\Module\Options\TextShadow\TextShadowPresetAttrsMap;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Border\Border;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * GravityFormsModule class.
 *
 * @since ??
 */
class GravityFormsModule implements DependencyInterface {

	/**
	 * Custom CSS fields.
	 *
	 * @since ??
	 *
	 * @return array<string, mixed>
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/gravity-forms' )->customCssFields;
	}

	/**
	 * Module preset attrs map additions.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $attrs_map   Preset attrs map.
	 * @param string               $module_name Module name.
	 *
	 * @return array<string, mixed>
	 */
	public static function preset_attrs_map( array $attrs_map, string $module_name ): array {
		if ( 'divi/gravity-forms' !== $module_name ) {
			return $attrs_map;
		}

		/*
		 * PHP's generic parser expands an explicitly nested `divi/button` component a second time under
		 * `{element}.decoration.button.*`, while the TS parser correctly keeps the element's outer
		 * background/border/font/sizing/spacing groups only. Remove that duplicate subtree and the one
		 * generic line-height artifact for every GF button element. The complete PHP/TS equality test pins
		 * this contract so a future parser change cannot silently over- or under-filter the map.
		 */
		$button_attr_names = [ 'button', 'nextButton', 'previousButton', 'saveButton', 'fileUploadButton' ];

		foreach ( array_keys( $attrs_map ) as $key ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			foreach ( $button_attr_names as $button_attr_name ) {
				$is_nested_button_entry = str_starts_with( $key, "{$button_attr_name}.decoration.button." );
				$is_generic_line_height = "{$button_attr_name}.decoration.font.font__lineHeight" === $key;

				if ( $is_nested_button_entry || $is_generic_line_height ) {
					unset( $attrs_map[ $key ] );
					break;
				}
			}
		}

		/*
		 * `elementType: field` supplies these groups in the TS parser, but PHP's metadata parser does not
		 * synthesize them from the empty field settings object. Use the same canonical repository maps as
		 * other form modules. The legacy backgroundColor alias and image sizing flexType are not present in
		 * the GF metadata contract and are therefore excluded from PHP as well.
		 */
		$field_decoration_map = FieldDecorationPresetAttrsMap::get_map();
		unset( $field_decoration_map['field.decoration.background__backgroundColor'] );
		unset( $attrs_map['imageChoice.decoration.sizing__flexType'] );

		$image_choice_map = ImagePresetAttrsMap::get_map( 'imageChoice' );
		unset( $image_choice_map['imageChoice.decoration.sizing__flexType'] );

		return array_merge(
			$attrs_map,
			$field_decoration_map,
			FontPresetAttrsMap::get_map( 'radioButton.decoration.font' ),
			FontPresetAttrsMap::get_map( 'checkbox.decoration.font' ),
			NativeChoicePresetAttrsMap::get_map( 'radioButton' ),
			NativeChoicePresetAttrsMap::get_map( 'checkbox' ),
			$image_choice_map,
			TextShadowPresetAttrsMap::get_map( 'validationSummary.decoration.bodyFont.dropCap.textShadow' ),
			TextShadowPresetAttrsMap::get_map( 'formConfirmation.decoration.bodyFont.dropCap.textShadow' )
		);
	}

	/**
	 * Module classnames.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $args Arguments.
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$classnames_instance->add( 'clearfix', true );

		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => $attrs['module']['decoration'] ?? [],
				]
			)
		);
	}

	/**
	 * Module script data.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $args Arguments.
	 */
	public static function module_script_data( array $args ): void {
		$elements = $args['elements'];

		foreach (
			[
				'module',
				'gravityForm',
				'title',
				'sectionHeading',
				'subLabel',
				'description',
				'field',
				'radioButton',
				'checkbox',
				'imageChoice',
				'button',
				'nextButton',
				'previousButton',
				'saveButton',
				'fileUploadButton',
				'progressBar',
				'requiredMarker',
				'validationSummary',
				'validationFieldMessage',
				'formConfirmation',
			] as $attr_name
		) {
			$elements->script_data(
				[
					'attrName' => $attr_name,
				]
			);
		}
	}

	/**
	 * Module styles.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $args Arguments.
	 */
	public static function module_styles( array $args ): void {
		$attrs                       = $args['attrs'] ?? [];
		$elements                    = $args['elements'];
		$settings                    = $args['settings'] ?? [];
		$order_class                 = $args['orderClass'] ?? '';
		$default_printed_style_attrs = is_array( $args['defaultPrintedStyleAttrs'] ?? null ) ? $args['defaultPrintedStyleAttrs'] : [];
		$field_attr                  = is_array( $attrs['field'] ?? null ) ? $attrs['field'] : [];

		// Block attrs are only shallowly schema-validated. Normalize the nested border boundary before
		// array-typed style helpers receive it, so malformed stored content degrades to no border CSS.
		if ( ! isset( $field_attr['decoration'] ) || ! is_array( $field_attr['decoration'] ) ) {
			$field_attr['decoration'] = [];
		}

		if ( isset( $field_attr['decoration']['border'] ) && ! is_array( $field_attr['decoration']['border'] ) ) {
			$field_attr['decoration']['border'] = [];
		}
		$font_group_properties          = [
			'color',
			'font-family',
			'font-size',
			'font-style',
			'font-weight',
			'font-stretch',
			'font-variation-settings',
			'font-optical-sizing',
			'letter-spacing',
			'line-height',
			'text-align',
			'text-decoration',
			'text-transform',
		];
		$embed                          = "{$order_class} .et_pb_gravity_form_embed";
		$file_upload_input_selector     = "{$embed} input[type=\"file\"]";
		$file_upload_button_selector    = "{$file_upload_input_selector}::file-selector-button";
		$field_font_attr                = is_array( $field_attr['decoration']['font'] ?? null ) ? $field_attr['decoration']['font'] : [];
		$default_field_font_attr        = is_array( $default_printed_style_attrs['field']['decoration']['font'] ?? null ) ? $default_printed_style_attrs['field']['decoration']['font'] : [];
		$file_upload_fallback_font_attr = ! empty( $field_font_attr ) ? $field_font_attr : $default_field_font_attr;
		$radio_attr                     = is_array( $attrs['radioButton'] ?? null ) ? $attrs['radioButton'] : [];
		$checkbox_field_attr            = is_array( $attrs['checkbox'] ?? null ) ? $attrs['checkbox'] : [];
		$image_choice_decoration        = is_array( $attrs['imageChoice']['decoration'] ?? null ) ? $attrs['imageChoice']['decoration'] : [];
		$image_choice_sizing            = is_array( $image_choice_decoration['sizing'] ?? null ) ? $image_choice_decoration['sizing'] : [];
		unset( $image_choice_decoration['sizing'] );

		/*
		 * Gravity Forms chooses `label` versus `legend` from the field container markup, not from a
		 * different semantic role. Keep both primary-caption tags under one Label Text control.
		 */
		$input_primary_caption_label_selector              = implode(
			', ',
			[
				"{$embed} .gform_wrapper label.gfield_label",
				"{$embed} .gform_wrapper legend.gfield_label",
			]
		);
		$text_effects_properties                           = [
			'-webkit-text-stroke-width',
			'-webkit-text-stroke-color',
			'background-image',
			'background-size',
			'background-position',
			'background-repeat',
			'background-blend-mode',
			'-webkit-background-clip',
			'background-clip',
			'-webkit-text-fill-color',
		];
		$input_label_font_property_selectors               = array_fill_keys( $font_group_properties, $input_primary_caption_label_selector );
		$rtl_input_primary_caption_label_selector          = implode(
			', ',
			array_map(
				static fn( string $selector ): string => 'html[dir="rtl"] ' . trim( $selector ),
				explode( ',', $input_primary_caption_label_selector )
			)
		);
		$input_label_font_property_selectors['text-align'] = $rtl_input_primary_caption_label_selector;
		$input_label_text_effects_property_selectors       = array_fill_keys( $text_effects_properties, $input_primary_caption_label_selector );
		$field_targets                                     = [
			"{$embed} input:not([type=\"radio\"]):not([type=\"checkbox\"]):not([type=\"submit\"]):not([type=\"button\"]):not([type=\"hidden\"]):not(.gform-phone__search)",
			"{$embed} textarea",
			"{$embed} select",
		];
		$phone_country_button_selector                     = "{$embed} button.gform-phone__country-selector";
		$phone_dial_code_selector                          = "{$embed} .gform-phone__dial-code";
		$chosen_multi_search_selector                      = "{$embed} .chosen-container-multi .chosen-choices li.search-field input[type=\"text\"]";
		$chosen_retina_search_selector                     = implode(
			', ',
			[
				"{$embed} .chosen-container-single .chosen-search input[type=\"text\"]",
				"{$embed} .chosen-rtl .chosen-search input[type=\"text\"]",
			]
		);
		$active_chosen_search_selector                     = "{$embed} .chosen-container-active .chosen-choices li.search-field input[type=\"text\"]";
		$saved_message_email_selector                      = "{$embed} div.form_saved_message div.form_saved_message_emailform form input[type=\"email\"]";

		/*
		 * Gravity Forms renders the international-phone dial code (`+1`) in its own span inside the
		 * country-selector button, sized by
		 * `.gform-theme--foundation .gfield--type-phone .gform-phone__dial-code { font-size: 14px }`
		 * — specificity (0,3,0) and NOT `!important`, so the field-font selector outranks it. It is
		 * field text and follows the Input Text controls, but through the FONT GROUP ONLY: the span
		 * is inside the button, so giving it the field's border/background/spacing would paint a
		 * second box within the control.
		 *
		 * `line-height` and `text-align` are deliberately routed to the button only. On a field the
		 * line-height carries the `--gf-ctrl-line-height: 2.5714em` height trick from
		 * `gravity_forms.scss`; measured on the dial-code span it produced a 66.9px line box around
		 * 26px text. `text-align` is inert on an inline span inside the button's flex row.
		 */
		$radio_input_selector = "{$embed} input[type=\"radio\"]";
		// Box/background/border/spacing styles belong to the native control only. Label typography
		// is routed separately through the property-selector maps below.
		$radio_targets              = [ $radio_input_selector ];
		$radio_text_targets         = [
			"{$embed} .gfield_radio .gchoice > input[type=\"radio\"] + label",
			"{$embed} .gfield_radio .gchoice > label",
		];
		$radio_text_targets_checked = [
			"{$embed} .gfield_radio .gchoice > input[type=\"radio\"]:checked + label",
		];
		$checkbox_input_selector    = "{$embed} input[type=\"checkbox\"]";
		$checkbox_targets           = [ $checkbox_input_selector ];
		$checkbox_targets_hover     = [ $checkbox_input_selector ];
		$checkbox_text_targets      = [
			"{$embed} .gfield_checkbox .gchoice > input[type=\"checkbox\"] + label",
			"{$embed} .ginput_container_consent input[type=\"checkbox\"] + .gfield_consent_label",
		];
		// Preserve adjacency in hover state so state selectors retain the native label chain.
		$checkbox_text_targets_hover   = $checkbox_text_targets;
		$checkbox_text_targets_checked = [
			"{$embed} .gfield_checkbox .gchoice > input[type=\"checkbox\"]:checked + label",
			"{$embed} .ginput_container_consent input[type=\"checkbox\"]:checked + .gfield_consent_label",
		];
		/*
		 * Base/fallback choice-label typography follows Input Text (not Field Label Text), so changing
		 * Input text size scales ordinary radio/checkbox/consent label copy. `font-size` also reaches
		 * the native choice inputs so the em-based `--gf-ctrl-choice-size` (gravity_forms.scss)
		 * tracks Field Text Size; other font metrics stay off the input (#49927).
		 */
		$field_typography_targets                     = array_merge(
			$field_targets,
			$radio_text_targets,
			$checkbox_text_targets
		);
		$field_font_size_targets                      = array_merge(
			$field_typography_targets,
			[ $radio_input_selector, $checkbox_input_selector ]
		);
		$field_font_property_selectors                = array_fill_keys(
			array_values( array_diff( $font_group_properties, [ 'line-height', 'text-align', 'font-size' ] ) ),
			implode( ', ', $field_typography_targets )
		);
		$field_font_property_selectors['font-size']   = implode( ', ', $field_font_size_targets );
		$phone_font_targets                           = implode( ', ', [ $phone_country_button_selector, $phone_dial_code_selector ] );
		$phone_font_property_selectors                = array_fill_keys( $font_group_properties, $phone_font_targets );
		$phone_font_property_selectors['line-height'] = $phone_country_button_selector;
		$phone_font_property_selectors['text-align']  = $phone_country_button_selector;
		/*
		 * Radio/Checkbox OG: `font-size` lands on labels + native input so the box/circle grows;
		 * remaining font-group properties stay on labels only.
		 */
		$radio_font_size_targets                                    = array_merge( $radio_text_targets, [ $radio_input_selector ] );
		$radio_font_size_targets_checked                            = array_merge( $radio_text_targets_checked, [ $radio_input_selector ] );
		$checkbox_font_size_targets                                 = array_merge( $checkbox_text_targets, [ $checkbox_input_selector ] );
		$checkbox_font_size_targets_hover                           = array_merge( $checkbox_text_targets_hover, [ $checkbox_input_selector ] );
		$checkbox_font_size_targets_checked                         = array_merge( $checkbox_text_targets_checked, [ $checkbox_input_selector ] );
		$radio_text_font_property_selectors                         = array_fill_keys( $font_group_properties, implode( ', ', $radio_text_targets ) );
		$radio_text_font_property_selectors['font-size']            = implode( ', ', $radio_font_size_targets );
		$radio_text_font_property_selectors_checked                 = array_fill_keys( $font_group_properties, implode( ', ', $radio_text_targets_checked ) );
		$radio_text_font_property_selectors_checked['font-size']    = implode( ', ', $radio_font_size_targets_checked );
		$checkbox_text_font_property_selectors                      = array_fill_keys( $font_group_properties, implode( ', ', $checkbox_text_targets ) );
		$checkbox_text_font_property_selectors['font-size']         = implode( ', ', $checkbox_font_size_targets );
		$checkbox_text_font_property_selectors_hover                = array_fill_keys( $font_group_properties, implode( ', ', $checkbox_text_targets_hover ) );
		$checkbox_text_font_property_selectors_hover['font-size']   = implode( ', ', $checkbox_font_size_targets_hover );
		$checkbox_text_font_property_selectors_checked              = array_fill_keys( $font_group_properties, implode( ', ', $checkbox_text_targets_checked ) );
		$checkbox_text_font_property_selectors_checked['font-size'] = implode( ', ', $checkbox_font_size_targets_checked );
		$choice_breakpoints                       = [
			'desktop',
			'tabletWide',
			'tablet',
			'phoneWide',
			'phone',
			'widescreen',
			'ultraWide',
		];
		$radio_selectors                          = [];
		$checkbox_selectors                       = [];
		$radio_font_property_selectors            = [];
		$checkbox_font_property_selectors         = [];
		$radio_text_shadow_property_selectors     = [];
		$checkbox_text_shadow_property_selectors  = [];
		$radio_text_effects_property_selectors    = [];
		$checkbox_text_effects_property_selectors = [];

		foreach ( $choice_breakpoints as $breakpoint ) {
			$radio_selectors[ $breakpoint ]                          = [ 'checked' => $radio_input_selector ];
			$checkbox_selectors[ $breakpoint ]                       = [ 'checked' => $checkbox_input_selector ];
			$radio_font_property_selectors[ $breakpoint ]            = [ 'checked' => $radio_text_font_property_selectors_checked ];
			$checkbox_font_property_selectors[ $breakpoint ]         = [ 'checked' => $checkbox_text_font_property_selectors_checked ];
			$radio_text_shadow_property_selectors[ $breakpoint ]     = [
				'checked' => [ 'text-shadow' => implode( ', ', $radio_text_targets_checked ) ],
			];
			$checkbox_text_shadow_property_selectors[ $breakpoint ]  = [
				'checked' => [ 'text-shadow' => implode( ', ', $checkbox_text_targets_checked ) ],
			];
			$radio_text_effects_property_selectors[ $breakpoint ]    = [
				'checked' => array_fill_keys( $text_effects_properties, implode( ', ', $radio_text_targets_checked ) ),
			];
			$checkbox_text_effects_property_selectors[ $breakpoint ] = [
				'checked' => array_fill_keys( $text_effects_properties, implode( ', ', $checkbox_text_targets_checked ) ),
			];
		}

		$radio_selectors['desktop']                          = array_merge(
			[
				'value' => implode( ', ', $radio_targets ),
				'hover' => implode( ', ', $radio_targets ),
				'focus' => $radio_input_selector,
			],
			$radio_selectors['desktop']
		);
		$checkbox_selectors['desktop']                       = array_merge(
			[
				'value' => implode( ', ', $checkbox_targets ),
				'hover' => implode( ', ', $checkbox_targets_hover ),
				'focus' => $checkbox_input_selector,
			],
			$checkbox_selectors['desktop']
		);
		$radio_font_property_selectors['desktop']            = array_merge(
			[
				'value' => $radio_text_font_property_selectors,
				'hover' => $radio_text_font_property_selectors,
			],
			$radio_font_property_selectors['desktop']
		);
		$checkbox_font_property_selectors['desktop']         = array_merge(
			[
				'value' => $checkbox_text_font_property_selectors,
				'hover' => $checkbox_text_font_property_selectors_hover,
			],
			$checkbox_font_property_selectors['desktop']
		);
		$radio_text_shadow_property_selectors['desktop']     = array_merge(
			[
				'value' => [ 'text-shadow' => implode( ', ', $radio_text_targets ) ],
				'hover' => [ 'text-shadow' => implode( ', ', $radio_text_targets ) ],
			],
			$radio_text_shadow_property_selectors['desktop']
		);
		$checkbox_text_shadow_property_selectors['desktop']  = array_merge(
			[
				'value' => [ 'text-shadow' => implode( ', ', $checkbox_text_targets ) ],
				'hover' => [ 'text-shadow' => implode( ', ', $checkbox_text_targets_hover ) ],
			],
			$checkbox_text_shadow_property_selectors['desktop']
		);
		$radio_text_effects_property_selectors['desktop']    = array_merge(
			[
				'value' => array_fill_keys( $text_effects_properties, implode( ', ', $radio_text_targets ) ),
				'hover' => array_fill_keys( $text_effects_properties, implode( ', ', $radio_text_targets ) ),
			],
			$radio_text_effects_property_selectors['desktop']
		);
		$checkbox_text_effects_property_selectors['desktop'] = array_merge(
			[
				'value' => array_fill_keys( $text_effects_properties, implode( ', ', $checkbox_text_targets ) ),
				'hover' => array_fill_keys( $text_effects_properties, implode( ', ', $checkbox_text_targets_hover ) ),
			],
			$checkbox_text_effects_property_selectors['desktop']
		);
		$rtl_label_important                                 = [
			'label' => [
				'font' => [
					'font' => self::_important_in_every_state( [ 'text-align' ] ),
				],
			],
		];
		$phone_field_important                               = [
			'background' => self::_important_in_every_state( [ 'background-color' ] ),
			'border'     => self::_important_in_every_state(
				[
					'border-radius',
					'border-top-left-radius',
					'border-top-right-radius',
					'border-bottom-right-radius',
					'border-bottom-left-radius',
					'border-top-width',
					'border-right-width',
					'border-bottom-width',
					'border-left-width',
					'border-top-style',
					'border-right-style',
					'border-bottom-style',
					'border-left-style',
					'border-top-color',
					'border-right-color',
					'border-bottom-color',
					'border-left-color',
				]
			),
			'boxShadow'  => self::_important_in_every_state( [ 'box-shadow' ] ),
			'font'       => [
				'font' => self::_important_in_every_state(
					[
						'color',
						'font-family',
						'font-size',
						'font-style',
						'font-weight',
						'letter-spacing',
						'line-height',
						'text-align',
						'text-transform',
						'text-decoration-line',
						'text-decoration-color',
						'text-decoration-style',
						'text-decoration-thickness',
					]
				),
			],
			'spacing'    => self::_important_in_every_state(
				[
					'margin-top',
					'margin-right',
					'margin-bottom',
					'margin-left',
					'padding-top',
					'padding-right',
					'padding-bottom',
					'padding-left',
				]
			),
		];
		/*
		 * Runtime `gravityForm` advanced styles are value-driven only. Structurally constant guardrails
		 * (always-on choice-row alignment / field height-clamp release) are emitted via module
		 * dynamic-assets CSS. Focus-ring neutralization is emitted from the field border path so
		 * breakpoint/state output tracks actual border rendering.
		 */
		$gravity_form_advanced_styles = [];

		/*
		 * Accent-color override (radio + checkbox):
		 * Gravity Forms renders custom choice marks via a `:before` pseudo-element and reads
		 * mark color from `--gf-ctrl-choice-check-color`. Setting that variable on the input
		 * updates the visible mark color for the rendered control.
		 */
		if ( ! empty( $attrs['radioButton']['decoration']['accentColor'] ) ) {
			$gravity_form_advanced_styles[] = [
				'componentName' => 'divi/common',
				'props'         => [
					'selector' => $radio_input_selector,
					'attr'     => $attrs['radioButton']['decoration']['accentColor'],
					'property' => '--gf-ctrl-choice-check-color',
				],
			];
		}

		if ( ! empty( $attrs['checkbox']['decoration']['accentColor'] ) ) {
			$gravity_form_advanced_styles[] = [
				'componentName' => 'divi/common',
				'props'         => [
					'selector' => $checkbox_input_selector,
					'attr'     => $attrs['checkbox']['decoration']['accentColor'],
					'property' => '--gf-ctrl-choice-check-color',
				],
			];
		}

		/*
		 * Validation summary alignment CSS variables (value-driven only). The heading is
		 * GF Orbital flex (column <640px, row >=640px), so text-align does not position
		 * icon+title children — and the summary list's outside marker ignores text-align
		 * too. Emit only the alignment CSS variables on the element's own
		 * `.gform_validation_errors` selector (inherited from metadata; custom properties
		 * inherit into heading + list); dynamic-assets SCSS maps them onto the heading's
		 * flex axis per breakpoint and the list's marker position, while #51005 keeps box
		 * decorations box-only.
		 * @see https://github.com/elegantthemes/Divi/issues/51015.
		 */
		$validation_summary_advanced_styles = [];

		if ( ! empty( $attrs['validationSummary']['decoration']['bodyFont']['body']['font'] ) ) {
			$validation_summary_advanced_styles[] = [
				'componentName' => 'divi/common',
				'props'         => [
					'attr'                => $attrs['validationSummary']['decoration']['bodyFont']['body']['font'],
					'declarationFunction' => [ self::class, 'validation_summary_align_style_declaration' ],
				],
			];
		}

		/*
		 * GF focus-outline neutralization — deliberately focus-only gate (WCAG 2.4.7);
		 * see `_pick_border_focus_states` and the spec.md #51043 subsection.
		 */
		$validation_summary_focus_border_attr = self::_pick_border_focus_states(
			is_array( $attrs['validationSummary']['decoration']['border'] ?? null )
				? $attrs['validationSummary']['decoration']['border']
				: []
		);

		if ( ! empty( $validation_summary_focus_border_attr ) ) {
			$validation_summary_advanced_styles[] = [
				'componentName' => 'divi/common',
				'props'         => [
					'attr'                => $validation_summary_focus_border_attr,
					'declarationFunction' => [ self::class, 'validation_summary_focus_ring_neutralization_style_declaration' ],
				],
			];
		}

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => array_values(
					array_filter(
						[
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
									'attrName'   => 'gravityForm',
									'styleProps' => [
										'advancedStyles' => $gravity_form_advanced_styles,
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
									'attrName' => 'sectionHeading',
								]
							),
							$elements->style(
								[
									'attrName' => 'subLabel',
								]
							),
							$elements->style(
								[
									'attrName' => 'description',
								]
							),
							$elements->style(
								[
									'attrName' => 'requiredMarker',
								]
							),
							$elements->style(
								[
									'attrName'   => 'validationSummary',
									'styleProps' => [
										'advancedStyles' => $validation_summary_advanced_styles,
									],
								]
							),
							$elements->style(
								[
									'attrName' => 'validationFieldMessage',
								]
							),
							$elements->style(
								[
									'attrName' => 'formConfirmation',
								]
							),
							$elements->style(
								[
									'attrName' => 'progressBar',
								]
							),
							FormFieldStyle::style(
								[
									'selector'          => implode( ', ', $field_targets ),
									'attr'              => $field_attr,
									'important'         => $rtl_label_important,
									'selectors'         => [
										'desktop' => [
											'value' => implode( ', ', $field_targets ),
											'hover' => implode( ', ', $field_targets ),
											'focus' => implode( ', ', $field_targets ),
										],
									],
									'orderClass'        => $order_class,
									'propertySelectors' => [

										/*
										 * Font-group properties only; every other group keeps `selector`
										 * / `selectors` above, so the dial-code span never receives the
										 * field's background, border or spacing.
										 */
										'font'  => [
											'font' => [
												'desktop' => [
													'value' => $field_font_property_selectors,
												],
											],
										],
										'label' => [
											'font' => [
												'font' => [
													'desktop' => [
														'value' => $input_label_font_property_selectors,
													],
												],
												'textShadow' => [
													'desktop' => [
														'value' => [
															'text-shadow' => $input_primary_caption_label_selector,
														],
													],
												],
												'textEffects' => [
													'desktop' => [
														'value' => $input_label_text_effects_property_selectors,
													],
												],
											],
										],
									],
								]
							),
							FormFieldStyle::style(
								[
									'selector'          => $chosen_multi_search_selector,
									'attr'              => $field_attr,
									'important'         => [
										'background' => self::_important_in_every_state(
											[ 'background-color', 'background-image', 'background-position', 'background-repeat', 'background-size' ]
										),
										'border'     => self::_important_in_every_state(
											[
												'border',
												'border-width',
												'border-style',
												'border-color',
												'border-top-width',
												'border-right-width',
												'border-bottom-width',
												'border-left-width',
												'border-top-style',
												'border-right-style',
												'border-bottom-style',
												'border-left-style',
												'border-top-color',
												'border-right-color',
												'border-bottom-color',
												'border-left-color',
											]
										),
									],
									'orderClass'        => $order_class,
									'disableLabelStyle' => true,
								]
							),
							FormFieldStyle::style(
								[
									'selector'          => $chosen_retina_search_selector,
									'attr'              => $field_attr,
									'important'         => [
										'background' => self::_important_in_every_state( [ 'background-image', 'background-repeat', 'background-size' ] ),
									],
									'orderClass'        => $order_class,
									'disableLabelStyle' => true,
								]
							),
							FormFieldStyle::style(
								[
									'selector'          => $active_chosen_search_selector,
									'attr'              => $field_attr,
									'important'         => [
										'font' => [
											'font' => self::_important_in_every_state( [ 'color' ] ),
										],
									],
									'selectors'         => array_fill_keys(
										$choice_breakpoints,
										[
											'value' => $active_chosen_search_selector,
											'hover' => $active_chosen_search_selector,
											'focus' => $active_chosen_search_selector,
										]
									),
									'orderClass'        => $order_class,
									'disableLabelStyle' => true,
								]
							),
							FormFieldStyle::style(
								[
									'selector'          => $saved_message_email_selector,
									'attr'              => $field_attr,
									'important'         => [
										'spacing' => self::_important_in_every_state( [ 'padding-top', 'padding-right', 'padding-bottom', 'padding-left' ] ),
									],
									'orderClass'        => $order_class,
									'disableLabelStyle' => true,
								]
							),
							FormFieldStyle::style(
								[
									'selector'          => $phone_country_button_selector,
									'attr'              => $field_attr,
									'important'         => $phone_field_important,
									'selectors'         => [
										'desktop' => [
											'value' => $phone_country_button_selector,
											'hover' => $phone_country_button_selector,
											'focus' => $phone_country_button_selector,
										],
									],
									'orderClass'        => $order_class,
									'disableLabelStyle' => true,
									'propertySelectors' => [
										'font' => [
											'font' => [
												'desktop' => [
													'value' => $phone_font_property_selectors,
												],
											],
										],
									],
								]
							),
							CommonStyle::style(
								[
									'selector'            => implode( ', ', $field_targets ),
									'attr'                => self::_ensure_border_resting_state( $field_attr['decoration']['border'] ?? [] ),
									'orderClass'          => $order_class,
									'declarationFunction' => [ self::class, 'field_focus_transition_neutralization_style_declaration' ],
								]
							),
							CommonStyle::style(
								[
									'selector'            => implode( ', ', $field_targets ),
									'attr'                => self::_pick_border_focus_states( $field_attr['decoration']['border'] ?? [] ),
									'orderClass'          => $order_class,
									'declarationFunction' => [ self::class, 'field_focus_ring_neutralization_style_declaration' ],
									'selectorFunction'    => [ self::class, 'field_focus_neutralization_selector_function' ],
								]
							),
							CommonStyle::style(
								[
									'selector'            => $phone_country_button_selector,
									'attr'                => self::_ensure_border_resting_state( $field_attr['decoration']['border'] ?? [] ),
									'important'           => true,
									'orderClass'          => $order_class,
									'declarationFunction' => [ self::class, 'field_focus_transition_neutralization_style_declaration' ],
								]
							),
							CommonStyle::style(
								[
									'selector'            => $phone_country_button_selector,
									'attr'                => self::_pick_border_focus_states( $field_attr['decoration']['border'] ?? [] ),
									'important'           => true,
									'orderClass'          => $order_class,
									'declarationFunction' => [ self::class, 'field_focus_ring_neutralization_style_declaration' ],
									'selectorFunction'    => [ self::class, 'field_focus_neutralization_selector_function' ],
								]
							),
							FormFieldStyle::style(
								[
									'selector'          => implode( ', ', $radio_targets ),
									'attr'              => $radio_attr,
									'selectors'         => $radio_selectors,
									'orderClass'        => $order_class,
									'disableLabelStyle' => true,
									'propertySelectors' => [
										'font' => [
											'font'        => $radio_font_property_selectors,
											'textShadow'  => $radio_text_shadow_property_selectors,
											'textEffects' => $radio_text_effects_property_selectors,
										],
									],
								]
							),
							FormFieldStyle::style(
								[
									'selector'          => implode( ', ', $checkbox_targets ),
									'attr'              => $checkbox_field_attr,
									'selectors'         => $checkbox_selectors,
									'orderClass'        => $order_class,
									'disableLabelStyle' => true,
									'propertySelectors' => [
										'font' => [
											'font'        => $checkbox_font_property_selectors,
											'textShadow'  => $checkbox_text_shadow_property_selectors,
											'textEffects' => $checkbox_text_effects_property_selectors,
										],
									],
								]
							),
							$elements->style(
								[
									'attrName'   => 'imageChoice',
									'styleProps' => [
										'attrs'          => $image_choice_decoration,
										'advancedStyles' => [
											[
												'componentName' => 'divi/image-sizing',
												'props' => [
													'selector' => "{$embed} .gfield--type-image_choice .gfield-choice-image-wrapper",
													'imageSelector' => "{$embed} .gfield--type-image_choice .gfield-choice-image",
													'attr' => $image_choice_sizing,
													'important' => false,
													'orderClass' => $order_class,
												],
											],
											[
												'componentName' => 'divi/common',
												'props' => [
													'selector' => "{$embed} .gfield--type-image_choice .gfield-choice-image-wrapper",
													'attr' => $image_choice_sizing,
													'declarationFunction' => [ self::class, 'image_choice_wrapper_height_style_declaration' ],
												],
											],
											[
												'componentName' => 'divi/common',
												'props' => [
													'selector' => "{$embed} .gfield--type-image_choice .gfield-choice-image-wrapper",
													'attr' => $image_choice_sizing,
													'declarationFunction' => [ self::class, 'image_choice_wrapper_alignment_style_declaration' ],
												],
											],
											[
												'componentName' => 'divi/common',
												'props' => [
													'selector' => "{$embed} .gfield--type-image_choice .gfield-choice-image-wrapper",
													'attr' => $attrs['imageChoice']['decoration']['border'] ?? [],
													'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
												],
											],
										],
									],
								]
							),
							$elements->style(
								[
									'attrName' => 'button',
								]
							),
							$elements->style(
								[
									'attrName' => 'nextButton',
								]
							),
							$elements->style(
								[
									'attrName' => 'previousButton',
								]
							),
							$elements->style(
								[
									'attrName' => 'saveButton',
								]
							),
							CommonStyle::style(
								[
									'selector'            => "{$embed} button.gform_save_link",
									'attr'                => $attrs['saveButton']['decoration']['font']['font'] ?? [],
									'orderClass'          => $order_class,
									'declarationFunction' => [ self::class, 'save_button_svg_fill_style_declaration' ],
									'selectorFunction'    => [ self::class, 'save_button_svg_fill_selector_function' ],
								]
							),
							CommonStyle::style(
								[
									'selector'            => "{$embed} button.gform_save_link",
									'attr'                => $attrs['saveButton']['decoration']['font']['font'] ?? [],
									'important'           => true,
									'orderClass'          => $order_class,
									'declarationFunction' => [ self::class, 'save_button_svg_icon_style_declaration' ],
									'selectorFunction'    => [ self::class, 'save_button_svg_icon_selector_function' ],
								]
							),
							FontStyle::style(
								[
									'selector'   => $file_upload_button_selector,
									'selectors'  => [
										'desktop' => [
											'value' => $file_upload_button_selector,
											'hover' => "{$file_upload_input_selector}:hover::file-selector-button",
											'focus' => "{$file_upload_input_selector}:focus::file-selector-button",
										],
									],
									'attr'       => $file_upload_fallback_font_attr,
									'defaultPrintedStyleAttr' => $default_field_font_attr,
									'orderClass' => $order_class,
									'returnType' => $args['returnType'] ?? 'array',
								]
							),
							$elements->style(
								[
									'attrName' => 'fileUploadButton',
								]
							),
							CssStyle::style(
								[
									'selector'  => $args['orderClass'],
									'attr'      => $attrs['css'] ?? [],
									'cssFields' => self::custom_css(),
								]
							),
						]
					)
				),
			]
		);
	}

	/**
	 * Apply Image Choice minimum/fixed height controls to GF's clipping wrapper.
	 *
	 * @param array<string, mixed> $params Declaration parameters.
	 *
	 * @return string|array<string, string> CSS declarations.
	 */
	public static function image_choice_wrapper_height_style_declaration( array $params ) {
		$attr_value  = is_array( $params['attrValue'] ?? null ) ? $params['attrValue'] : [];
		$min_height  = $attr_value['minHeight'] ?? null;
		$height      = array_key_exists( 'height', $attr_value ) ? $attr_value['height'] : null;
		$return_type = $params['returnType'] ?? 'string';

		$style_declarations = new StyleDeclarations(
			[
				'important'  => $params['important'] ?? false,
				'returnType' => $return_type,
			]
		);

		if ( null !== $min_height && '' !== $min_height ) {
			$style_declarations->add( 'min-height', $min_height );
		}

		if ( null !== $height ) {
			$style_declarations->add( 'height', '' === $height ? 'auto' : $height );
		}

		return $style_declarations->value();
	}

	/**
	 * Make the Image composable's End alignment work on GF's nested block wrapper.
	 *
	 * @param array<string, mixed> $params Declaration parameters.
	 *
	 * @return string|array<string, string> CSS declarations.
	 */
	public static function image_choice_wrapper_alignment_style_declaration( array $params ) {
		$attr_value  = is_array( $params['attrValue'] ?? null ) ? $params['attrValue'] : [];
		$return_type = $params['returnType'] ?? 'string';

		if ( 'end' !== ( $attr_value['alignSelf'] ?? null ) ) {
			return 'string' === $return_type ? '' : [];
		}

		$style_declarations = new StyleDeclarations(
			[
				'important'  => [
					'margin-left'  => true,
					'margin-right' => true,
				],
				'returnType' => $return_type,
			]
		);

		$style_declarations->add( 'margin-left', 'auto' );
		$style_declarations->add( 'margin-right', '0' );

		return $style_declarations->value();
	}

	/**
	 * Route Save & Continue button font color to Gravity Forms icon paint.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $params Declaration params.
	 *
	 * @return string|array<string, string>
	 */
	public static function save_button_svg_fill_style_declaration( array $params ) {
		$attr_value  = isset( $params['attrValue'] ) && is_array( $params['attrValue'] ) ? $params['attrValue'] : [];
		$important   = $params['important'] ?? false;
		$return_type = $params['returnType'] ?? 'string';
		$color       = isset( $attr_value['color'] ) && is_string( $attr_value['color'] ) ? $attr_value['color'] : '';

		if ( '' === $color ) {
			return 'string' === $return_type ? '' : [];
		}

		$declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);

		$declarations->add( 'fill', $color );
		$declarations->add( 'stroke', $color );

		return $declarations->value();
	}

	/**
	 * Ensure Save & Continue icon glyph tracks the button typography when `::before` is used.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $params Declaration params.
	 *
	 * @return string|array<string, string>
	 */
	public static function save_button_svg_icon_style_declaration( array $params ) {
		$important   = $params['important'] ?? false;
		$return_type = $params['returnType'] ?? 'string';

		$declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);

		$declarations->add( 'color', 'currentColor' );
		$declarations->add( 'font-size', '1em' );

		return $declarations->value();
	}

	/**
	 * Append the Save & Continue SVG icon target after the generated button interaction state.
	 *
	 * CommonStyle adds `:hover` to the button selector before this callback runs, which keeps the
	 * state on `button:hover svg ...` instead of incorrectly producing `svg ...:hover`.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $params Selector function params.
	 *
	 * @return string
	 */
	public static function save_button_svg_fill_selector_function( array $params ): string {
		$selector = isset( $params['selector'] ) ? (string) $params['selector'] : '';

		if ( '' === $selector ) {
			return '';
		}

		return implode(
			', ',
			array_map(
				static function ( string $button_selector ): string {
					return trim( $button_selector ) . ' svg *';
				},
				explode( ',', $selector )
			)
		);
	}

	/**
	 * Append the Save & Continue pseudo-icon target after the generated button interaction state.
	 *
	 * CommonStyle adds `:hover` to the button selector before this callback runs, which keeps the
	 * state on `button:hover::before` instead of incorrectly producing `::before:hover`.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $params Selector function params.
	 *
	 * @return string
	 */
	public static function save_button_svg_icon_selector_function( array $params ): string {
		$selector = isset( $params['selector'] ) ? (string) $params['selector'] : '';

		if ( '' === $selector ) {
			return '';
		}

		return implode(
			', ',
			array_map(
				static function ( string $button_selector ): string {
					return trim( $button_selector ) . '::before';
				},
				explode( ',', $selector )
			)
		);
	}

	/**
	 * Expand base field selectors to include focus pseudos used by GF focus-ring neutralization.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $params Selector function params.
	 *
	 * @return string
	 */
	public static function field_focus_neutralization_selector_function( array $params ): string {
		$selector = isset( $params['selector'] ) ? (string) $params['selector'] : '';

		if ( '' === $selector ) {
			return '';
		}

		$base_selectors = array_filter( array_map( 'trim', explode( ',', $selector ) ) );
		$focus_variants = [];

		foreach ( $base_selectors as $base_selector ) {
			$focus_variants[] = "{$base_selector}:focus";
			$focus_variants[] = "{$base_selector}:focus-visible";
			$focus_variants[] = "{$base_selector}:focus-within";
		}

		return implode( ', ', $focus_variants );
	}

	/**
	 * Build a responsive importance map for the value, hover and focus states.
	 *
	 * @param string[] $properties CSS properties that have a verified competing declaration.
	 *
	 * @return array<string, array<string, array<string, bool>>>
	 */
	private static function _important_in_every_state( array $properties ): array {
		$property_map = array_fill_keys( $properties, true );
		$result       = [];

		foreach ( [ 'desktop', 'tabletWide', 'tablet', 'phoneWide', 'phone', 'widescreen', 'ultraWide' ] as $breakpoint ) {
			$result[ $breakpoint ] = [
				'value' => $property_map,
				'hover' => $property_map,
				'focus' => $property_map,
			];
		}

		return $result;
	}

	/**
	 * Ensure every breakpoint that defines a field border in any interaction state also carries a
	 * resting (`value`) state.
	 *
	 * The transition neutralizer is gated per breakpoint/state by border emission. A border set only on
	 * `hover` or `focus` would otherwise leave GF's `transition: var(--gf-local-transition)` (~`all
	 * 0.15s`, governed by the destination state) active on the resting selector, animating GF defaults
	 * back in on un-hover/blur. Supplying a resting state makes `transition:none` cover that destination.
	 * The focus-ring neutralizer deliberately does not use this synthesized state; it receives the
	 * focus-only slice from `_pick_border_focus_states()` so a hover/value border never removes GF's
	 * native keyboard focus indicator.
	 *
	 * Note: the synthesized `value` is always copied from a real, user-set interaction state — never
	 * fabricated — so style emission stays a function of the real border attr (not a faux trigger).
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $border_attr Field border breakpoint/state attr.
	 *
	 * @return array<string, mixed>
	 */
	private static function _ensure_border_resting_state( array $border_attr ): array {
		$result = [];

		foreach ( $border_attr as $breakpoint => $states ) {
			if ( ! is_array( $states ) || isset( $states['value'] ) ) {
				$result[ $breakpoint ] = $states;
				continue;
			}

			$resting_value = null;

			foreach ( $states as $state_value ) {
				if ( ! empty( $state_value ) ) {
					$resting_value = $state_value;
					break;
				}
			}

			$result[ $breakpoint ] = null === $resting_value
				? $states
				: array_merge( $states, [ 'value' => $resting_value ] );
		}

		return $result;
	}

	/**
	 * Slice a border breakpoint/state attr down to its focus states.
	 *
	 * Field and validation-summary focus-ring neutralizers are gated on FOCUS-state border declarations
	 * only. A resting or hover border must never remove the native focus indicator (WCAG 2.4.7). Passing
	 * the sliced attr to `CommonStyle` lets the framework append `:focus` per breakpoint — the same
	 * selector the user's focus border rides — so neutralization emits exactly where a focus border
	 * emits. Empty focus objects are treated as absent, mirroring the TS twin's behavior.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $border_attr Border breakpoint/state attr.
	 *
	 * @return array<string, mixed> Focus-only slice; empty when no breakpoint carries a focus state.
	 */
	private static function _pick_border_focus_states( array $border_attr ): array {
		$result = [];

		foreach ( $border_attr as $breakpoint => $states ) {
			if ( ! is_array( $states ) || empty( $states['focus'] ) ) {
				continue;
			}

			$result[ $breakpoint ] = [ 'focus' => $states['focus'] ];
		}

		return $result;
	}

	/**
	 * Emit transition reset on the base field selectors.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $params Declaration params.
	 *
	 * @return string|array<string, string>
	 */
	public static function field_focus_transition_neutralization_style_declaration( array $params ) {
		$attr_value         = isset( $params['attrValue'] ) && is_array( $params['attrValue'] ) ? $params['attrValue'] : [];
		$default_attr_value = isset( $params['defaultAttrValue'] ) && is_array( $params['defaultAttrValue'] ) ? $params['defaultAttrValue'] : [];
		$important          = $params['important'] ?? false;
		$return_type        = $params['returnType'] ?? 'string';
		$border_declaration = Border::style_declaration(
			[
				'attrValue'        => $attr_value,
				'defaultAttrValue' => $default_attr_value,
				'returnType'       => 'string',
			]
		);

		if ( empty( $border_declaration ) ) {
			return 'string' === $return_type ? '' : [];
		}

		$declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);

		$declarations->add( 'transition', 'none' );

		return $declarations->value();
	}

	/**
	 * Emit GF focus-ring reset when the current border attr emits CSS.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $params Declaration params.
	 *
	 * @return string|array<string, string>
	 */
	public static function field_focus_ring_neutralization_style_declaration( array $params ) {
		$attr_value         = isset( $params['attrValue'] ) && is_array( $params['attrValue'] ) ? $params['attrValue'] : [];
		$default_attr_value = isset( $params['defaultAttrValue'] ) && is_array( $params['defaultAttrValue'] ) ? $params['defaultAttrValue'] : [];
		$important          = $params['important'] ?? false;
		$return_type        = $params['returnType'] ?? 'string';
		$border_declaration = Border::style_declaration(
			[
				'attrValue'        => $attr_value,
				'defaultAttrValue' => $default_attr_value,
				'returnType'       => 'string',
			]
		);

		if ( empty( $border_declaration ) ) {
			return 'string' === $return_type ? '' : [];
		}

		$declaration_important = true === $important
			? [
				'outline'    => true,
				'box-shadow' => true,
			]
			: $important;

		$declarations = new StyleDeclarations(
			[
				'important'  => $declaration_important,
				'returnType' => $return_type,
			]
		);

		$declarations->add( 'outline', 'none' );
		$declarations->add( 'box-shadow', 'none' );
		$declarations->add( '--gf-local-outline-color', 'transparent' );
		$declarations->add( '--gf-local-outline-width', '0' );

		return $declarations->value();
	}

	/**
	 * Emit the Gravity Forms validation-summary focus-outline reset.
	 *
	 * Fed a focus-states-only border slice (see `_pick_border_focus_states`), so any attrValue it
	 * receives is a focus-state border. Neutralizes only GF Orbital's
	 * `.gform_validation_errors:focus` `outline` — border properties are left to the cascade
	 * (user-set focus props out-specify GF's rule naturally; unset props inherit GF's focus
	 * values) and no `!important` is needed because the module selector out-specifies GF's.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $params Declaration params.
	 *
	 * @return string|array<string, string>
	 */
	public static function validation_summary_focus_ring_neutralization_style_declaration( array $params ) {
		$attr_value         = isset( $params['attrValue'] ) && is_array( $params['attrValue'] ) ? $params['attrValue'] : [];
		$default_attr_value = isset( $params['defaultAttrValue'] ) && is_array( $params['defaultAttrValue'] ) ? $params['defaultAttrValue'] : [];
		$important          = $params['important'] ?? false;
		$return_type        = $params['returnType'] ?? 'string';
		$border_declaration = Border::style_declaration(
			[
				'attrValue'        => $attr_value,
				'defaultAttrValue' => $default_attr_value,
				'returnType'       => 'string',
			]
		);

		if ( empty( $border_declaration ) ) {
			return 'string' === $return_type ? '' : [];
		}

		$declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);

		$declarations->add( 'outline', 'none' );

		return $declarations->value();
	}

	/**
	 * Emit CSS variables for validation summary alignment.
	 *
	 * Emitted on the `.gform_validation_errors` box; custom properties inherit into the
	 * heading and the summary list, where the dynamic-assets SCSS consumes them.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $params Declaration params.
	 *
	 * @return string|array<string, string>
	 */
	public static function validation_summary_align_style_declaration( array $params ) {
		$attr_value  = isset( $params['attrValue'] ) && is_array( $params['attrValue'] ) ? $params['attrValue'] : [];
		$important   = $params['important'] ?? false;
		$return_type = $params['returnType'] ?? 'string';
		$text_align  = isset( $attr_value['textAlign'] ) ? $attr_value['textAlign'] : '';

		$text_align_to_flex = [
			'left'    => 'flex-start',
			'center'  => 'center',
			'right'   => 'flex-end',
			'justify' => 'flex-start',
		];

		if ( ! is_string( $text_align ) || ! isset( $text_align_to_flex[ $text_align ] ) ) {
			return 'string' === $return_type ? '' : [];
		}

		$declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);

		$declarations->add( '--et_pb_gf_validation_heading_align', $text_align_to_flex[ $text_align ] );

		/*
		 * Only `center`/`right` strand GF's native outside disc away from the aligned
		 * text; `left`/`justify` keep the native marker position correct as-is.
		 */
		if ( 'center' === $text_align || 'right' === $text_align ) {
			$declarations->add( '--et_pb_gf_validation_list_marker', 'inside' );
		}

		return $declarations->value();
	}

	/**
	 * Render callback.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $attrs                       Attributes.
	 * @param string               $child_modules_content       Inner blocks (nested modules).
	 * @param WP_Block             $block                       Block instance.
	 * @param ModuleElements       $elements                    Elements.
	 * @param array<string, mixed> $default_printed_style_attrs Painted defaults supplied by the framework.
	 *
	 * @return string
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs = [] ): string {
		$children_ids = ChildrenUtils::extract_children_ids( $block );
		$parent       = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$form_id      = $attrs['gravityForm']['innerContent']['desktop']['value']['formId'] ?? '';
		$use_ajax     = 'on' === ( $attrs['gravityForm']['advanced']['useAjax']['desktop']['value'] ?? 'off' );
		$html         = GravityFormsController::render_form_preview(
			[
				'formId'  => $form_id,
				'useAjax' => $use_ajax ? 'on' : 'off',
			]
		);

		return Module::render(
			[
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],
				'attrs'                    => $attrs,
				'elements'                 => $elements,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'id'                       => $block->parsed_block['id'],
				'name'                     => $block->block_type->name,
				'moduleCategory'           => $block->block_type->category,
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'parentAttrs'              => $parent->attrs ?? [],
				'parentId'                 => $parent->id ?? '',
				'parentName'               => $parent->blockName ?? '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP uses camelCase in \WP_Block_Parser_Block.
				'childrenIds'              => $children_ids,
				'children'                 => [
					$elements->style_components(
						[
							'attrName' => 'module',
						]
					),
					HTMLUtility::render(
						[
							'tag'               => 'div',
							'tagEscaped'        => true,
							'attributes'        => [

								/*
								 * `et_pb_module_inner` supplies the framework's `position: relative`, which keeps
								 * the form above the absolutely-positioned background layers (parallax / pattern /
								 * mask) that `style_components` renders before it.
								 *
								 * @see https://github.com/elegantthemes/Divi/issues/51004
								 */
								'class' => 'et_pb_gravity_form_embed et_pb_module_inner',
							],
							'childrenSanitizer' => 'et_core_esc_previously',
							'children'          => $html,
						]
					),
					$child_modules_content,
				],
			]
		);
	}

	/**
	 * Load module.
	 *
	 * @since ??
	 */
	public function load(): void {
		add_filter( 'divi_conversion_presets_attrs_map', [ self::class, 'preset_attrs_map' ], 10, 2 );

		if ( ! class_exists( '\\GFForms' ) ) {
			return;
		}

		GravityFormsService::register_ajax_button_class_filters();

		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/gravity-forms/';

		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
