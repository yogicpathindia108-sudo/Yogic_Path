<?php
/**
 * ModuleLibrary: Contact Form Module class.
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\ContactForm;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WordPress uses snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Script;
use ET\Builder\FrontEnd\Module\ScriptData;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Element\ElementStyle;
use ET\Builder\Packages\Module\Options\FormField\FormFieldStyle;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ContactField\ContactFieldOptionRowLayoutDeclarations;
use ET\Builder\Packages\ModuleLibrary\ContactForm\ContactFormHandler;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleLibrary\RadioFieldAndIconAttrs;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use WP_Block_Type_Registry;
use WP_Block;

/**
 * `ContactFormModule` is consisted of functions used for Contact Form Module such as Front-End rendering, REST API Endpoints etc.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class ContactFormModule implements DependencyInterface {

	/**
	 * Module custom CSS fields.
	 *
	 * This function is equivalent of JS function cssFields located in
	 * visual-builder/packages/module-library/src/components/contact-form/custom-css.ts.
	 *
	 * @since ??
	 *
	 * @return array The array of custom CSS fields.
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/contact-form' )->customCssFields;
	}

	/**
	 * Keep Contact Form custom CSS hover behavior scoped to the child element target.
	 *
	 * For custom CSS fields that define explicit child selectors (for example, the
	 * Contact Button field), this preserves child-level hover targeting by appending
	 * `:hover` to the resolved selector in hover state. Sticky handling keeps
	 * `.et_pb_sticky` on the module selector while preserving the child segment.
	 *
	 * @since ??
	 *
	 * @param array $params Selector function params.
	 *
	 * @return string
	 */
	public static function custom_css_selector_function( array $params ): string {
		$selector = (string) ( $params['selector'] ?? '' );
		$state    = (string) ( $params['state'] ?? 'value' );

		if ( '' === $selector ) {
			return $selector;
		}

		if ( 'sticky' === $state ) {
			$selectors = array_filter(
				array_map( 'trim', explode( ',', $selector ) ),
				static function ( string $item ): bool {
					return '' !== $item;
				}
			);

			$sticky_selectors = array_map(
				static function ( string $item ): string {
					$parts = explode( ' ', $item );

					if ( count( $parts ) < 2 ) {
						return str_contains( $item, '.et_pb_sticky' ) ? $item : $item . '.et_pb_sticky';
					}

					$module_selector = $parts[0];
					$child_parts     = implode( ' ', array_slice( $parts, 1 ) );
					$sticky_module   = str_contains( $module_selector, '.et_pb_sticky' )
						? $module_selector
						: $module_selector . '.et_pb_sticky';

					return $sticky_module . ' ' . $child_parts;
				},
				$selectors
			);

			return implode( ', ', array_unique( $sticky_selectors ) );
		}

		if ( 'hover' !== $state ) {
			return $selector;
		}

		$selectors = array_filter(
			array_map( 'trim', explode( ',', $selector ) ),
			static function ( string $item ): bool {
				return '' !== $item;
			}
		);

		$hover_selectors = array_map(
			static function ( string $item ): string {
				if ( str_contains( $item, ':hover' ) ) {
					return $item;
				}

				return $item . ':hover';
			},
			$selectors
		);

		return implode( ', ', $hover_selectors );
	}

	/**
	 * Set CSS class names to the module.
	 *
	 * This function is equivalent of JS function moduleClassnames located in
	 * visual-builder/packages/module-library/src/components/contact-form/module-classnames.ts.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $id                  Module unique ID.
	 *     @type string $name                Module name with namespace.
	 *     @type array  $attrs               Module attributes.
	 *     @type array  $childrenIds         Module children IDs.
	 *     @type bool   $hasModule           Flag that indicates if module has child modules.
	 *     @type bool   $isFirst             Flag that indicates if module is first in the row.
	 *     @type bool   $isLast              Flag that indicates if module is last in the row.
	 *     @type object $classnamesInstance  Instance of Instance of ET\Builder\Packages\Module\Layout\Components\Classnames class.
	 *
	 *     // FE only.
	 *     @type int|null $storeInstance The ID of instance where this block stored in BlockParserStore.
	 *     @type int      $orderIndex    The order index of the element.
	 * }
	 */
	public static function module_classnames( $args ) {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Text options.
		$classnames_instance->add( TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? [] ), true );

		$classnames_instance->add( 'clearfix', true );

		// This class is only applicable in the FE.
		if ( 'on' === $attrs['module']['advanced']['spamProtection']['desktop']['value']['enabled'] ?? 'off' ) {
			$classnames_instance->add( 'et_pb_recaptcha_enabled', true );
		}

		// Module.

		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => array_merge(
						$attrs['module']['decoration'] ?? [],
						[
							'link' => $args['attrs']['module']['advanced']['link'] ?? [],
						]
					),
				]
			)
		);
	}

	/**
	 * Set script data to the module.
	 *
	 * This function is equivalent of JS function ModuleScriptData located in
	 * visual-builder/packages/module-library/src/components/contact-form/module-script-data.tsx.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string         $id            Module unique ID.
	 *     @type string         $name          Module name with namespace.
	 *     @type string         $selector      Module CSS selector.
	 *     @type array          $attrs         Module attributes.
	 *     @type array          $parentAttrs   Parent module attributes.
	 *     @type ModuleElements $elements      Instance of ModuleElements class.
	 *
	 *     // FE only.
	 *     @type int|null $storeInstance The ID of instance where this block stored in BlockParserStore.
	 *     @type int      $orderIndex    The order index of the element.
	 * }
	 */
	public static function module_script_data( $args ) {
		// Assign variables.
		$selector = $args['selector'] ?? '';
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);

		// Set module specific front-end data.
		self::set_front_end_data(
			[
				'selector' => $selector,
			]
		);
	}

	/**
	 * Set CSS styles to the module.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * visual-builder/packages/module-library/src/components/contact-form/module-styles.tsx.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $id                       Module unique ID.
	 *     @type string $name                     Module name with namespace.
	 *     @type array  $attrs                    Module attributes.
	 *     @type array  $parentAttrs              Parent module attributes.
	 *     @type array  $siblingAttrs             Sibling module attributes.
	 *     @type array  $defaultPrintedStyleAttrs Default printed style attributes.
	 *     @type string $orderClass               Module CSS selector.
	 *     @type string $parentOrderClass         Parent module CSS selector.
	 *     @type string $wrapperOrderClass        Wrapper module CSS selector.
	 *     @type array  $settings                 Custom settings.
	 *
	 *     // VB only.
	 *     @type string $state                    Attributes state.
	 *     @type string $mode                     Style mode.
	 *
	 *     // FE only.
	 *     @type int|null $storeInstance          The ID of instance where this block stored in BlockParserStore.
	 *     @type int      $orderIndex             The order index of the element.
	 *     @type ModuleElements $elements         The ModuleElements instance.
	 * }
	 */
	public static function module_styles( array $args ): void {
		$attrs                       = $args['attrs'] ?? [];
		$elements                    = $args['elements'];
		$settings                    = $args['settings'] ?? [];
		$order_class                 = $args['orderClass'] ?? '';
		$base_order_class            = $args['baseOrderClass'] ?? '';
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];
		$is_custom_post_type         = $args['isCustomPostType'] ?? false;
		$is_inside_sticky_module     = $elements->get_is_inside_sticky_module();
		$sticky_parent_order_class   = $elements->get_sticky_parent_order_class();

		$base_selector                             = $is_custom_post_type
			? 'body.et-db #page-container #et-boc .et-l .et_pb_section'
			: 'body #page-container .et_pb_section';
		$input_indicator_targets                   = [
			"{$order_class} .et_pb_contact_field .input:not([type=\"checkbox\"]):not([type=\"radio\"])",
		];
		$input_indicator_targets_hover             = [
			"{$order_class} .et_pb_contact_field .input:not([type=\"checkbox\"]):not([type=\"radio\"]):hover",
		];
		$input_indicator_targets_focus             = [
			"{$order_class} .et_pb_contact_field .input:not([type=\"checkbox\"]):not([type=\"radio\"]):focus",
		];
		$text_group_targets                        = [
			"{$order_class}.et_pb_contact_form_container .input:not([type=\"checkbox\"]):not([type=\"radio\"])",
		];
		$text_group_targets_hover                  = [
			"{$order_class}.et_pb_contact_form_container .input:not([type=\"checkbox\"]):not([type=\"radio\"]):hover",
		];
		$field_label_targets                       = [
			"{$order_class} .et_pb_contact_field .et_pb_contact_field_options_title",
		];
		$field_label_targets_hover                 = [
			"{$order_class} .et_pb_contact_field .et_pb_contact_field_options_title:hover",
		];
		$checkbox_targets                          = [
			"{$order_class} .et_pb_contact_field .input[type=\"checkbox\"] + label i",
		];
		$checkbox_targets_hover                    = [
			"{$order_class} .et_pb_contact_field .input[type=\"checkbox\"]:hover + label i",
		];
		$checkbox_targets_focus                    = [
			"{$order_class} .et_pb_contact_field .input[type=\"checkbox\"]:focus + label i",
		];
		$checkbox_targets_checked                  = [
			"{$order_class} .et_pb_contact_field .input[type=\"checkbox\"]:checked + label i",
		];
		$checkbox_text_targets                     = [
			"{$order_class}.et_pb_contact_form_container .input[type=\"checkbox\"] + label",
		];
		$checkbox_text_targets_hover               = [
			"{$order_class}.et_pb_contact_form_container .input[type=\"checkbox\"]:hover + label",
		];
		$checkbox_text_targets_checked             = [
			"{$order_class}.et_pb_contact_form_container .input[type=\"checkbox\"]:checked + label",
		];
		$radio_targets                             = [
			"{$order_class} .et_pb_contact_field .input[type=\"radio\"] + label i",
		];
		$radio_targets_hover                       = [
			"{$order_class} .et_pb_contact_field .input[type=\"radio\"]:hover + label i",
		];
		$radio_targets_focus                       = [
			"{$order_class} .et_pb_contact_field .input[type=\"radio\"]:focus + label i",
		];
		$radio_targets_checked                     = [
			"{$order_class} .et_pb_contact_field .input[type=\"radio\"]:checked + label i",
		];
		$radio_text_targets                        = [
			"{$order_class}.et_pb_contact_form_container .input[type=\"radio\"] + label",
		];
		$radio_text_targets_hover                  = [
			"{$order_class}.et_pb_contact_form_container .input[type=\"radio\"]:hover + label",
		];
		$radio_text_targets_checked                = [
			"{$order_class}.et_pb_contact_form_container .input[type=\"radio\"]:checked + label",
		];
		$font_group_properties                     = [
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
		$label_text_decoration_font_properties      = [
			'text-decoration-line',
			'text-decoration-color',
			'text-decoration-style',
			// Keep small-caps on the label text selector for both legacy and new capitalization outputs.
			'font-variant',
			'font-variant-caps',
		];
		$font_property_selectors                   = array_fill_keys( $font_group_properties, implode( ', ', $text_group_targets ) );
		$font_property_selectors_hover             = array_fill_keys( $font_group_properties, implode( ', ', $text_group_targets_hover ) );
		$checkbox_font_property_selectors          = array_merge(
			array_fill_keys( $font_group_properties, implode( ', ', $checkbox_text_targets ) ),
			array_fill_keys( $label_text_decoration_font_properties, implode( ', ', $checkbox_text_targets ) )
		);
		$checkbox_font_property_selectors_hover    = array_merge(
			array_fill_keys( $font_group_properties, implode( ', ', $checkbox_text_targets_hover ) ),
			array_fill_keys( $label_text_decoration_font_properties, implode( ', ', $checkbox_text_targets_hover ) )
		);
		$radio_font_property_selectors             = array_merge(
			array_fill_keys( $font_group_properties, implode( ', ', $radio_text_targets ) ),
			array_fill_keys( $label_text_decoration_font_properties, implode( ', ', $radio_text_targets ) )
		);
		$radio_font_property_selectors_hover       = array_merge(
			array_fill_keys( $font_group_properties, implode( ', ', $radio_text_targets_hover ) ),
			array_fill_keys( $label_text_decoration_font_properties, implode( ', ', $radio_text_targets_hover ) )
		);
		$field_label_font_property_selectors       = array_fill_keys( $font_group_properties, implode( ', ', $field_label_targets ) );
		$field_label_font_property_selectors_hover = array_fill_keys( $font_group_properties, implode( ', ', $field_label_targets_hover ) );
		$radio_attrs                               = RadioFieldAndIconAttrs::get( $attrs['radio'] ?? [] );
		$radio_attr                                = $radio_attrs['fieldAttr'];
		$radio_icon_attr                           = $radio_attrs['iconAttr'];
		$bottom_row_has_basic_captcha              = self::contact_form_bottom_row_has_basic_captcha( $attrs );

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					// Module.
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'defaultPrintedStyleAttrs' => $default_printed_style_attrs['module']['decoration'] ?? [],
								'disabledOn'               => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
								'advancedStyles'           => [
									[
										'componentName' => 'divi/text',
										'props'         => [
											'selector' => "{$order_class}.et_pb_contact_form_container",
											'attr'     => $attrs['module']['advanced']['text'] ?? [],
											'propertySelectors' => [
												'text' => [
													'desktop' => [
														'value' => [
															'text-align' => "{$order_class} input, {$order_class} textarea, {$order_class} label",
														],
													],
												],
												'textShadow' => [
													'desktop' => [
														'value' => [
															'text-shadow' => "{$order_class}, {$order_class} input, {$order_class} textarea, {$order_class} label, {$order_class} select",
														],
													],
												],
											],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector'            => ContactFieldOptionRowLayoutDeclarations::wrapper_selectors( $order_class ),
											'attr'                => ContactFieldOptionRowLayoutDeclarations::layout_attr_placeholder(),
											'declarationFunction' => [ ContactFieldOptionRowLayoutDeclarations::class, 'wrapper_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector'            => ContactFieldOptionRowLayoutDeclarations::label_selectors( $order_class ),
											'attr'                => ContactFieldOptionRowLayoutDeclarations::layout_attr_placeholder(),
											'declarationFunction' => [ ContactFieldOptionRowLayoutDeclarations::class, 'label_declaration' ],
										],
									],
								],
							],
						]
					),
					// title.
					$elements->style(
						[
							'attrName' => 'title',
						]
					),
					// captcha.
					$elements->style(
						[
							'attrName' => 'captcha',
						]
					),
					// button.
					$elements->style(
						[
							'attrName'   => 'button',
							'styleProps' => [
								'button'         => [
									'disableAlignmentStyles' => true,
								],
								'spacing'        => [
									'selector'  => implode(
										', ',
										[
											"{$base_selector} {$base_order_class}.et_pb_contact_form_container.et_pb_module .et_pb_button",
											"{$base_selector} {$base_order_class}.et_pb_contact_form_container.et_pb_module .et_pb_button:hover",
										]
									),
									'important' => true,
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector'            => "{$base_selector} {$base_order_class} .et_contact_bottom_container",
											'attr'                => $attrs['button']['decoration']['sizing'] ?? [],
											'declarationFunction' => function ( $params ) use ( $bottom_row_has_basic_captcha ) {
												$params['contactFormHasBasicCaptchaRow'] = $bottom_row_has_basic_captcha;

												return self::button_alignment_declaration( $params );
											},
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector'            => "{$base_selector} {$base_order_class} .et_contact_bottom_container .et_pb_button_wrapper",
											'attr'                => $attrs['button']['decoration']['sizing'] ?? [],
											'declarationFunction' => function ( $params ) {
												return self::button_wrapper_sizing_declaration( $params );
											},
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector'            => implode(
												', ',
												[
													"{$base_selector} {$base_order_class} .et_contact_bottom_container .et_pb_contact_right.et_pb_contact_field",
													"{$base_selector} {$base_order_class} .et_contact_bottom_container .et_pb_contact_right.et_pb_contact_field label",
												]
											),
											'attr'                => $attrs['button']['decoration']['sizing'] ?? [],
											'declarationFunction' => function ( $params ) use ( $bottom_row_has_basic_captcha ) {
												$params['contactFormHasBasicCaptchaRow'] = $bottom_row_has_basic_captcha;

												return self::captcha_row_sizing_declaration( $params );
											},
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector'            => implode(
												', ',
												[
													"{$base_selector} {$base_order_class}.et_pb_contact_form_container.et_pb_module .et_pb_button.et_pb_contact_submit",
													"{$base_selector} {$base_order_class}.et_pb_contact_form_container.et_pb_module .et_pb_button.et_pb_contact_submit:hover",
												]
											),
											'attr'                => $attrs['button']['decoration']['sizing'] ?? [],
											'declarationFunction' => function ( $params ) {
												return self::submit_button_layout_declaration( $params );
											},
										],
									],
								],
							],
						]
					),
					FormFieldStyle::style(
						[
							'attr'              => $attrs['field'] ?? [],
							'selector'          => "{$order_class} .et_pb_contact_field .input:not([type=\"checkbox\"]):not([type=\"radio\"])",
							'selectors'         => [
								'desktop' => [
									'value' => implode(
										', ',
										$input_indicator_targets
									),
									'hover' => implode(
										', ',
										$input_indicator_targets_hover
									),
									'focus' => implode(
										', ',
										$input_indicator_targets_focus
									),
								],
							],
							'important'         => [
								'spacing' => [
									'desktop' => [
										'value' => [
											'margin-bottom' => true,
										],
									],
								],
								'border'  => [
									'desktop' => [
										'value' => [
											'border-radius' => true,
										],
										'hover' => [
											'border-radius' => true,
										],
									],
								],
							],
							'propertySelectors' => [
								'spacing' => [
									'desktop' => [
										'value' => [
											'margin'  => "{$order_class} .et_pb_contact_field",
											'padding' => "{$order_class} .et_pb_contact_field .input:not([type=\"checkbox\"]):not([type=\"radio\"])",
										],
									],
								],
								'font'    => [
									'font'       => [
										'desktop' => [
											'value' => array_merge(
												[],
												$font_property_selectors
											),
											'hover' => array_merge(
												[],
												$font_property_selectors_hover
											),
										],
									],
									'textShadow' => [
										'desktop' => [
											'value' => [
												'text-shadow' => implode( ', ', $text_group_targets ),
											],
											'hover' => [
												'text-shadow' => implode( ', ', $text_group_targets_hover ),
											],
										],
									],
								],
								'label'   => [
									'font' => [
										'font'       => [
											'desktop' => [
												'value' => array_merge(
													[],
													$field_label_font_property_selectors
												),
												'hover' => array_merge(
													[],
													$field_label_font_property_selectors_hover
												),
											],
										],
										'textShadow' => [
											'desktop' => [
												'value' => [
													'text-shadow' => implode( ', ', $field_label_targets ),
												],
												'hover' => [
													'text-shadow' => implode( ', ', $field_label_targets_hover ),
												],
											],
										],
									],
								],
							],
							'orderClass'        => $order_class,
						]
					),
					FormFieldStyle::style(
						[
							'attr'              => $attrs['checkbox'] ?? [],
							'selector'          => "{$order_class} .et_pb_contact_field .input[type=\"checkbox\"] + label i",
							'selectors'         => [
								'desktop' => [
									'value'   => implode(
										', ',
										$checkbox_targets
									),
									'hover'   => implode(
										', ',
										$checkbox_targets_hover
									),
									'focus'   => implode(
										', ',
										$checkbox_targets_focus
									),
									'checked' => implode(
										', ',
										$checkbox_targets_checked
									),
								],
							],
							'propertySelectors' => [
								'font' => [
									'font'       => [
										'desktop' => [
											'value'   => array_merge(
												[],
												$checkbox_font_property_selectors
											),
											'hover'   => array_merge(
												[],
												$checkbox_font_property_selectors_hover
											),
											'checked' => array_merge(
												[],
												array_fill_keys( $font_group_properties, implode( ', ', $checkbox_text_targets_checked ) ),
												array_fill_keys( $label_text_decoration_font_properties, implode( ', ', $checkbox_text_targets_checked ) )
											),
										],
									],
									'textShadow' => [
										'desktop' => [
											'value'   => [
												'text-shadow' => implode( ', ', $checkbox_text_targets ),
											],
											'hover'   => [
												'text-shadow' => implode( ', ', $checkbox_text_targets_hover ),
											],
											'checked' => [
												'text-shadow' => implode( ', ', $checkbox_text_targets_checked ),
											],
										],
									],
								],
							],
							'orderClass'        => $order_class,
							'disableLabelStyle' => true,
						]
					),
					ElementStyle::style(
						[
							'selector'               => "{$order_class}.et_pb_contact_form_container .input[type=\"checkbox\"]:checked + label i:before",
							'attrs'                  => [
								'icon' => $attrs['checkbox']['decoration']['icon'] ?? [],
							],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					FormFieldStyle::style(
						[
							'attr'              => $radio_attr,
							'selector'          => "{$order_class} .et_pb_contact_field .input[type=\"radio\"] + label i",
							'selectors'         => [
								'desktop' => [
									'value'   => implode(
										', ',
										$radio_targets
									),
									'hover'   => implode(
										', ',
										$radio_targets_hover
									),
									'focus'   => implode(
										', ',
										$radio_targets_focus
									),
									'checked' => implode(
										', ',
										$radio_targets_checked
									),
								],
							],
							'propertySelectors' => [
								'font' => [
									'font'       => [
										'desktop' => [
											'value'   => array_merge(
												[],
												$radio_font_property_selectors
											),
											'hover'   => array_merge(
												[],
												$radio_font_property_selectors_hover
											),
											'checked' => array_merge(
												[],
												array_fill_keys( $font_group_properties, implode( ', ', $radio_text_targets_checked ) ),
												array_fill_keys( $label_text_decoration_font_properties, implode( ', ', $radio_text_targets_checked ) )
											),
										],
									],
									'textShadow' => [
										'desktop' => [
											'value'   => [
												'text-shadow' => implode( ', ', $radio_text_targets ),
											],
											'hover'   => [
												'text-shadow' => implode( ', ', $radio_text_targets_hover ),
											],
											'checked' => [
												'text-shadow' => implode( ', ', $radio_text_targets_checked ),
											],
										],
									],
								],
							],
							'orderClass'        => $order_class,
							'disableLabelStyle' => true,
						]
					),
					ElementStyle::style(
						[
							'selector'               => "{$order_class}.et_pb_contact_form_container .input[type=\"radio\"]:checked + label i:before",
							'attrs'                  => [
								'icon' => $radio_icon_attr,
							],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					ElementStyle::style(
						[
							'selector'               => "{$order_class} .et_pb_contact_field .input:not([type=\"checkbox\"]):not([type=\"radio\"])::placeholder",
							'attrs'                  => [
								'font' => $attrs['field']['decoration']['placeholderFont'] ?? [],
							],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					// Module - Only for Custom CSS.
					CssStyle::style(
						[
							'selector'               => $args['orderClass'] . '.et_pb_contact_form_container',
							'attr'                   => $attrs['css'] ?? [],
							'cssFields'              => self::custom_css(),
							'selectorFunction'       => [ self::class, 'custom_css_selector_function' ],
							'orderClass'             => $order_class,
							'baseOrderClass'         => $base_order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
							'isCustomPostType'       => $is_custom_post_type,
						]
					),
				],
			]
		);
	}

	/**
	 * Module render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * This function is equivalent of JS function ContactFormEdit located in
	 * visual-builder/packages/module-library/src/components/contact-form/edit.tsx.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string The module HTML output.
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ) {
		global $half_width_counter;

		// Reset the $half_width_counter.
		$half_width_counter = 0;

		$children_ids = $block->parsed_block['innerBlocks'] ? array_map(
			function ( $inner_block ) {
				return $inner_block['id'];
			},
			$block->parsed_block['innerBlocks']
		) : [];

		// Apply filtering to child Contact Fields for form submission validation.
		// This ensures Contact Field attributes (like 'required') are properly filtered.
		$filtered_field_attrs = [];
		if ( ! empty( $children_ids ) ) {
			foreach ( $children_ids as $child_id ) {
				$child_block = BlockParserStore::get( $child_id, $block->parsed_block['storeInstance'] );
				if ( $child_block && 'divi/contact-field' === $child_block->blockName ) {
					// Apply the same filtering as used during rendering.
					$default_child_attrs = ModuleRegistration::get_default_attrs( 'divi/contact-field' );
					$merged_child_attrs  = array_replace_recursive( $default_child_attrs, $child_block->attrs ?? [] );

					// Apply the same filter that was applied to the parent.
					$child_filter_args = [
						'id'            => $child_id,
						'name'          => $child_block->blockName,
						'parentId'      => $block->parsed_block['id'],
						'parentName'    => $block->name,
						'parentAttrs'   => $attrs,
						'storeInstance' => $block->parsed_block['storeInstance'],
					];

					/**
					 * Filters module attributes before registration.
					 *
					 * This filter is documented in includes/builder-5/server/FrontEnd/ModuleRegistration.php.
					 *
					 * Note: We apply this filter here for Contact Field children because the main
					 * `divi_module_library_register_module_attrs` filter is applied during the rendering
					 * phase in ModuleRegistration::register_module(). However, ContactFormHandler processes
					 * form submissions and validates Contact Field attributes (such as 'required') before
					 * the normal rendering/filtering cycle occurs. To ensure third-party modifications via
					 * the filter hook are properly applied during form submission, we manually apply the
					 * filter here and pass the filtered attributes to ContactFormHandler.
					 *
					 * @since ??
					 *
					 * @param array $merged_child_attrs Module attributes merged with defaults.
					 * @param array $child_filter_args  Filter arguments containing id, name, parentId, parentName, parentAttrs, and storeInstance.
					 */
					$filtered_child_attrs = apply_filters(
						'divi_module_library_register_module_attrs',
						$merged_child_attrs,
						$child_filter_args
					);

					// Store filtered field attributes keyed by field ID.
					$filtered_field_attrs[ $child_id ] = $filtered_child_attrs;
				}
			}
		}

		$children_ids = ChildrenUtils::extract_children_ids( $block );
		$parent       = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$form_handler = new ContactFormHandler( $block->parsed_block['id'], $block->parsed_block['storeInstance'], $attrs, $filtered_field_attrs );

		// Use uniqueId (module UUID) instead of orderIndex for form field names to ensure
		// globally unique field names across all Theme Builder areas (Header, Body, Footer).
		$unique_id = ContactFormUtils::get_unique_id( $attrs, $block->parsed_block );

		// Contact Form Title.
		$title = $elements->render(
			[
				'attrName' => 'title',
			]
		);

		$message_children   = '';
		$should_render_form = true;

		if ( $form_handler->is_submitted() ) {
			$should_emit_submitted_message = ContactFormHandler::claim_submitted_contact_form_outcome_message_render( $unique_id, $block->parsed_block['storeInstance'] );

			if ( $form_handler->get_error()->has_errors() ) {
				if ( $should_emit_submitted_message ) {
					$message_children = HTMLUtility::render(
						[
							'tag'        => 'p',
							'tagEscaped' => true,
							'attributes' => [
								'class' => 'et_pb_contact_error_text',
							],
							'children'   => $form_handler->get_error()->get_error_message(),
						]
					);
				} else {
					$message_children = '';
				}
			} else {
				if ( $form_handler->is_mail_sent() ) {
					if ( $should_emit_submitted_message ) {
						$success_message = $attrs['module']['advanced']['successMessage']['desktop']['value'] ?? '';

						if ( '' === $success_message ) {
							$success_message = __( 'Thanks for contacting us', 'et_builder_5' );
						}

						$message_children = HTMLUtility::render(
							[
								'tag'               => 'p',
								'tagEscaped'        => true,
								'children'          => $success_message,
								'childrenSanitizer' => 'et_core_esc_previously',
							]
						);
					} else {
						$message_children = '';
					}
				} else {
					if ( $should_emit_submitted_message ) {
						$message_children = HTMLUtility::render(
							[
								'tag'        => 'p',
								'tagEscaped' => true,
								'attributes' => [
									'class' => 'et_pb_contact_error_text',
								],
								'children'   => __( 'There was an error trying to send your message. Please try again later.', 'et_builder_5' ),
							]
						);
					} else {
						$message_children = '';
					}
				}

				// By default, the form should be rendered all the time. The only time it should
				// not be rendered is when the form is submitted and no error found.
				$should_render_form = false;
			}
		}

		$message = HTMLUtility::render(
			[
				'tag'               => 'div',
				'tagEscaped'        => true,
				'attributes'        => [
					'class' => 'et-pb-contact-message',
				],
				'children'          => $message_children,
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		// Contact Form.
		$form = '';

		if ( $should_render_form ) {
			// Contact Form - Fields - Input.
			$process_input = HTMLUtility::render(
				[
					'tag'        => 'input',
					'tagEscaped' => true,
					'attributes' => [
						'type'  => 'hidden',
						'name'  => 'et_pb_contactform_submit_' . $unique_id,
						'value' => 'et_contact_proccess',
					],
				]
			);

			// Contact Form - Fields - Button & Captcha.
			$button = $elements->render(
				[
					'attrName' => 'button',
				]
			);

			$basic_captcha = self::render_element_basic_captcha( $attrs, $unique_id, $elements );

			$bottom_container = HTMLUtility::render(
				[
					'tag'               => 'div',
					'tagEscaped'        => true,
					'attributes'        => [
						'class' => 'et_contact_bottom_container',
					],
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => [
						$basic_captcha,
						$button,
					],
				]
			);

			// Contact Form - Fields.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- intentionally done.
			$current_url = ( is_ssl() ? 'https://' : 'http://' ) . ( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) ) ) . ( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );

			// Get layout display to add appropriate classes to the form element.
			$layout_display = $attrs['module']['decoration']['layout']['desktop']['value']['display'] ?? 'flex';
			$form_classes   = array_filter(
				[
					'et_pb_contact_form',
					'flex' === $layout_display ? 'et_flex_module' : '',
					'grid' === $layout_display ? 'et_grid_module' : '',
					'block' === $layout_display ? 'et_block_module' : '',
				]
			);

			$form_fields = HTMLUtility::render(
				[
					'tag'               => 'form',
					'tagEscaped'        => true,
					'attributes'        => [
						'class'  => implode( ' ', $form_classes ),
						'method' => 'post',
						'action' => esc_url( $current_url ),
					],
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => [
						$content,
						$process_input,
						$bottom_container,
						wp_nonce_field( 'et-pb-contact-form-submit-' . $unique_id, '_wpnonce-et-pb-contact-form-submitted-' . $unique_id, true, false ),
					],
				]
			);

			$form = HTMLUtility::render(
				[
					'tag'               => 'div',
					'tagEscaped'        => true,
					'attributes'        => [
						'class' => 'et_pb_contact',
					],
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $form_fields,
				]
			);
		}

		$use_redirect = $attrs['redirect']['advanced']['useRedirect']['desktop']['value'] ?? 'off';
		$redirect_url = $attrs['redirect']['innerContent']['desktop']['value'] ?? '';

		return Module::render(
			[
				// FE only.
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'                    => $attrs,
				'id'                       => $block->parsed_block['id'],
				'elements'                 => $elements,
				'name'                     => $block->block_type->name,
				'moduleCategory'           => $block->block_type->category,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'parentId'                 => $parent->id ?? '',
				'parentName'               => $parent->blockName ?? '',
				'parentAttrs'              => $parent->attrs ?? [],
				'childrenIds'              => $children_ids,
				'htmlAttrs'                => [
					'data-form_unique_num' => $block->parsed_block['orderIndex'],
					'data-form_unique_id'  => $unique_id,
					'data-redirect_url'    => 'on' === $use_redirect && '' !== $redirect_url ? $redirect_url : null,
				],
				'children'                 => [
					$elements->style_components(
						[
							'attrName' => 'module',
						]
					),
					$title,
					$message,
					$form,
				],
			]
		);
	}

	/**
	 * ContactFrom module front-end render_block_data filter.
	 *
	 * @since ??
	 *
	 * @param array         $parsed_block The block being rendered.
	 * @param array         $source_block An un-modified copy of $parsed_block, as it appeared in the source content.
	 * @param null|WP_Block $parent_block If this is a nested block, a reference to the parent block.
	 *
	 * @return array Filtered block that being rendered.
	 */
	public static function render_block_data( array $parsed_block, array $source_block, ?WP_Block $parent_block ): array {
		if ( 'divi/contact-form' !== $parsed_block['blockName'] ) {
			return $parsed_block;
		}

		/**
		 * Contact form module must have an id attribute.
		 * If it doesn't have one, we will add it here.
		 */
		$id = $parsed_block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] ?? '';

		if ( ! $id ) {
			// Use uniqueId (module UUID) instead of orderIndex to ensure globally unique IDs
			// across all Theme Builder areas (Header, Body, Footer).
			$unique_id = ContactFormUtils::get_unique_id( $parsed_block['attrs'], $parsed_block );
			$parsed_block['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] = 'et_pb_contact_form_' . $unique_id;
		}

		return $parsed_block;
	}

	/**
	 * Render element basic captcha.
	 *
	 * @param array          $attrs     Module attributes.
	 * @param string         $unique_id Module unique ID (UUID).
	 * @param ModuleElements $elements  ModuleElements instance.
	 *
	 * @return string
	 */
	public static function render_element_basic_captcha( array $attrs, string $unique_id, ModuleElements $elements ): string {
		$use_spam_service = $attrs['module']['advanced']['spamProtection']['desktop']['value']['enabled'] ?? 'off';

		if ( 'on' === $use_spam_service ) {
			return '';
		}

		$use_basic_captcha = $attrs['module']['advanced']['spamProtection']['desktop']['value']['useBasicCaptcha'] ?? 'on';

		if ( 'off' === $use_basic_captcha ) {
			return '';
		}

		// generate digits for captcha.
		$et_pb_first_digit  = wp_rand( 1, 15 );
		$et_pb_second_digit = wp_rand( 1, 15 );

		$basic_captcha_question = HTMLUtility::render(
			[
				'tag'               => 'span',
				'tagEscaped'        => true,
				'attributes'        => [
					'class' => 'et_pb_contact_captcha_question',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => sprintf( '%1$s + %2$s', esc_html( $et_pb_first_digit ), esc_html( $et_pb_second_digit ) ),
			]
		);

		$basic_captcha_input = HTMLUtility::render(
			[
				'tag'        => 'input',
				'tagEscaped' => true,
				'attributes' => [
					'type'               => 'text',
					'size'               => '2',
					'class'              => 'input et_pb_contact_captcha',
					'data-first_digit'   => $et_pb_first_digit,
					'data-second_digit'  => $et_pb_second_digit,
					'data-required_mark' => 'required',
					'name'               => 'et_pb_contact_captcha_' . $unique_id,
					'autocomplete'       => 'off',
				],
			]
		);

		// Wrap question, equals sign, and input in <label> so the captcha control has an accessible name.
		$basic_captcha_label = HTMLUtility::render(
			[
				'tag'               => 'label',
				'tagEscaped'        => true,
				'attributes'        => [],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $basic_captcha_question . ' = ' . $basic_captcha_input,
			]
		);

		$basic_captcha_wrapper = HTMLUtility::render(
			[
				'tag'               => 'p',
				'tagEscaped'        => true,
				'attributes'        => [
					'class' => 'clearfix',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $basic_captcha_label,
			]
		);

		return $elements->render(
			[
				'attrName'          => 'captcha',
				'tagName'           => 'div',
				'attributes'        => [
					'class' => 'et_pb_contact_right et_pb_contact_field',
				],
				'skipAttrChildren'  => true,
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $basic_captcha_wrapper,
			]
		);
	}

	/**
	 * Set the module specific front-end data.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments for setting the front-end script data.
	 *
	 *     @type string $selector The module selector.
	 * }
	 * @return void
	 *
	 * @example
	 * ```php
	 * ContactFormModule::set_front_end_data( [
	 *   'selector' => '.et_pb_contact_form_0',
	 * ] );
	 * ```
	 */
	public static function set_front_end_data( array $args ): void {
		// Script data is not needed in VB.
		if ( Conditions::is_vb_enabled() ) {
			return;
		}

		$selector = $args['selector'] ?? '';

		// Register front-end data item.
		ScriptData::add_data_item(
			[
				'data_name'    => 'contact_form',
				'data_item_id' => null,
				'data_item'    => [
					'selector' => $selector,
				],
			]
		);
	}

	/**
	 * Loads `ContactFormModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load() {
		// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctionParameters.dirname_levelsFound -- We have PHP 7 support now, This can be deleted once PHPCS config is updated.
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/contact-form/';

		add_filter( 'divi_conversion_presets_attrs_map', [ ContactFormPresetAttrsMap::class, 'get_map' ], 10, 2 );

		add_filter(
			'render_block_data',
			[ self::class, 'render_block_data' ],
			10,
			3
		);

		// Ensure that all filters and actions applied during module registration are registered before calling `ModuleRegistration::register_module()`.
		// However, for consistency, register all module-specific filters and actions prior to invoking `ModuleRegistration::register_module()`.
		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}

	/**
	 * Whether basic math captcha renders in `.et_contact_bottom_container` before the submit button.
	 *
	 * Mirrors Visual Builder captcha visibility rules and PHP {@see self::render_element_basic_captcha()}.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attributes.
	 *
	 * @return bool True when captcha markup is output beside the button.
	 */
	public static function contact_form_bottom_row_has_basic_captcha( array $attrs ): bool {
		$spam = $attrs['module']['advanced']['spamProtection']['desktop']['value'] ?? [];

		return 'on' === ( $spam['useBasicCaptcha'] ?? 'on' )
			&& 'off' === ( $spam['enabled'] ?? 'off' );
	}

	/**
	 * Whether button sizing implies full-row width intent (`width` or lone `max-width` is `100%`).
	 *
	 * @since ??
	 *
	 * @param mixed $width     Width value for current breakpoint/state.
	 * @param mixed $max_width Max-width value for current breakpoint/state.
	 *
	 * @return bool True when percentage sizing should use the full submit row width.
	 */
	public static function contact_form_button_is_full_row_width_intent( $width, $max_width ): bool {
		$width_trimmed = trim( (string) $width );

		if ( '100%' === $width_trimmed ) {
			return true;
		}

		if ( '' === $width_trimmed && '' !== $max_width && null !== $max_width ) {
			return '100%' === trim( (string) $max_width );
		}

		return false;
	}

	/**
	 * Button alignment for contact form bottom container.
	 *
	 * @since ??
	 *
	 * @param array $params Style declaration parameters. May include `contactFormHasBasicCaptchaRow` from Contact Form styles.
	 *
	 * @return string The CSS for button alignment.
	 */
	public static function button_alignment_declaration( array $params ): string {
		$attr_value                  = $params['attrValue'] ?? [];
		$alignment                   = $attr_value['alignment'] ?? '';
		$width                       = isset( $attr_value['width'] ) ? $attr_value['width'] : '';
		$max_width                   = isset( $attr_value['maxWidth'] ) ? $attr_value['maxWidth'] : '';
		$min_width                   = isset( $attr_value['minWidth'] ) ? $attr_value['minWidth'] : '';
		$has_horizontal_sizing       = (
			'' !== $width
			|| '' !== $max_width
			|| '' !== $min_width
		);
		$has_basic_captcha_bottom_row   = ! empty( $params['contactFormHasBasicCaptchaRow'] );

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		// Stretch submit row; theme margins need !important (#49471).
		if ( $has_horizontal_sizing ) {
			$sizing_declarations = new StyleDeclarations(
				[
					'returnType' => 'string',
					'important'  => true,
				]
			);

			$sizing_declarations->add( 'width', '100%' );
			$sizing_declarations->add( 'margin-left', '0' );
			$sizing_declarations->add( 'margin-right', '0' );

			if ( $has_basic_captcha_bottom_row ) {
				// Theme spacing came from `.et_pb_contact_submit { margin-left: 18px }`, cleared by sizing overrides.
				$sizing_declarations->add( 'gap', '18px' );
				$sizing_declarations->add( 'align-items', 'flex-start' );
			}

			// Needed when `.et_pb_button_wrapper` uses `display:contents` for partial widths (#49471).
			switch ( $alignment ) {
				case 'left':
					$sizing_declarations->add( 'justify-content', 'flex-start' );
					break;
				case 'center':
					$sizing_declarations->add( 'justify-content', 'center' );
					break;
				default:
					$sizing_declarations->add( 'justify-content', 'flex-end' );
					break;
			}

			return $sizing_declarations->value();
		}

		switch ( $alignment ) {
			case 'left':
				$style_declarations->add( 'margin-left', '0' );
				$style_declarations->add( 'margin-right', 'auto' );
				break;
			case 'center':
				$style_declarations->add( 'margin-left', 'auto' );
				$style_declarations->add( 'margin-right', 'auto' );
				break;
			default:
				$style_declarations->add( 'margin-left', 'auto' );
				$style_declarations->add( 'margin-right', '0' );
				break;
		}

		return $style_declarations->value();
	}

	/**
	 * Button wrapper layout when horizontal sizing is set: full-row flex wrapper; partial widths use `display:contents`.
	 *
	 * @since ??
	 *
	 * @return string The CSS for the button wrapper, or empty string when no horizontal sizing is set.
	 */
	public static function button_wrapper_sizing_declaration( array $params ): string {
		$attr_value = $params['attrValue'] ?? [];
		$width      = isset( $attr_value['width'] ) ? $attr_value['width'] : '';
		$max_width  = isset( $attr_value['maxWidth'] ) ? $attr_value['maxWidth'] : '';
		$min_width  = isset( $attr_value['minWidth'] ) ? $attr_value['minWidth'] : '';

		if ( '' === $width && '' === $max_width && '' === $min_width ) {
			return '';
		}

		$is_full_row_width_button = self::contact_form_button_is_full_row_width_intent( $width, $max_width );

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		// Partial widths: remove wrapper from layout so `%` sizing resolves against `.et_contact_bottom_container`.
		if ( ! $is_full_row_width_button ) {
			$style_declarations->add( 'display', 'contents' );

			return $style_declarations->value();
		}

		$style_declarations->add( 'flex', '1 1 auto' );
		$style_declarations->add( 'width', '100%' );
		$style_declarations->add( 'min-width', '0' );
		$style_declarations->add( 'justify-content', 'flex-start' );

		return $style_declarations->value();
	}

	/**
	 * Captcha column in the submit row: reserve horizontal space when button sizing is active (#49471).
	 *
	 * @since ??
	 *
	 * @param array $params Style declaration parameters. May include `contactFormHasBasicCaptchaRow` from Contact Form styles.
	 *
	 * @return string The CSS for the captcha cell and label, or empty string when not applicable.
	 */
	public static function captcha_row_sizing_declaration( array $params ): string {
		if ( empty( $params['contactFormHasBasicCaptchaRow'] ) ) {
			return '';
		}

		$attr_value            = $params['attrValue'] ?? [];
		$width                 = isset( $attr_value['width'] ) ? $attr_value['width'] : '';
		$max_width             = isset( $attr_value['maxWidth'] ) ? $attr_value['maxWidth'] : '';
		$min_width             = isset( $attr_value['minWidth'] ) ? $attr_value['minWidth'] : '';
		$has_horizontal_sizing = (
			'' !== $width
			|| '' !== $max_width
			|| '' !== $min_width
		);

		if ( ! $has_horizontal_sizing ) {
			return '';
		}

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => true,
			]
		);

		$style_declarations->add( 'flex-shrink', '0' );
		$style_declarations->add( 'min-width', 'max-content' );
		$style_declarations->add( 'white-space', 'nowrap' );

		return $style_declarations->value();
	}

	/**
	 * Submit element layout overrides when sizing is active (theme `.et_pb_contact_submit` uses inline-block + left margin).
	 *
	 * @since ??
	 *
	 * @param array $params Style declaration parameters.
	 *
	 * @return string The CSS for the submit button, or empty string when no horizontal sizing is set.
	 */
	public static function submit_button_layout_declaration( array $params ): string {
		$attr_value            = $params['attrValue'] ?? [];
		$width                 = isset( $attr_value['width'] ) ? $attr_value['width'] : '';
		$max_width             = isset( $attr_value['maxWidth'] ) ? $attr_value['maxWidth'] : '';
		$min_width             = isset( $attr_value['minWidth'] ) ? $attr_value['minWidth'] : '';
		$has_horizontal_sizing = (
			'' !== $width
			|| '' !== $max_width
			|| '' !== $min_width
		);

		if ( ! $has_horizontal_sizing ) {
			return '';
		}

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => true,
			]
		);

		$style_declarations->add( 'display', 'block' );
		$style_declarations->add( 'margin-left', '0' );
		$style_declarations->add( 'margin-right', '0' );

		return $style_declarations->value();
	}
}
