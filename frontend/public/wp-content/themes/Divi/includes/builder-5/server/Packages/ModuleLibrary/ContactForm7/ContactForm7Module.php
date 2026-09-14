<?php
/**
 * Module Library: Contact Form 7 Module
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\ContactForm7;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WordPress uses snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\FormField\FormFieldStyle;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * ContactForm7Module class.
 *
 * @since ??
 */
class ContactForm7Module implements DependencyInterface {

	/**
	 * Resolve field attrs with backward-compatible alias support.
	 *
	 * Canonical key is `field`; `inputField` is kept as legacy alias.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $attrs Module attributes.
	 *
	 * @return array<string, mixed>
	 */
	private static function _get_field_attrs( array $attrs ): array {
		$field_attrs = $attrs['field'] ?? $attrs['inputField'] ?? [];

		return is_array( $field_attrs ) ? $field_attrs : [];
	}

	/**
	 * Module custom CSS fields.
	 *
	 * @since ??
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/contact-form-7' )->customCssFields;
	}

	/**
	 * Module classnames function.
	 *
	 * @param array<string, mixed> $args Module classnames arguments.
	 *
	 * @since ??
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$classnames_instance->add( TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? [] ), true );
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
	 * Module script data function.
	 *
	 * @param array<string, mixed> $args Module script data arguments.
	 *
	 * @since ??
	 */
	public static function module_script_data( array $args ): void {
		$elements = $args['elements'];

		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);
	}

	/**
	 * Module styles function.
	 *
	 * @param array<string, mixed> $args Module styles arguments.
	 *
	 * @since ??
	 */
	public static function module_styles( array $args ): void {
		$attrs                               = $args['attrs'] ?? [];
		$elements                            = $args['elements'];
		$settings                            = $args['settings'] ?? [];
		$order_class                         = $args['orderClass'] ?? '';
		$default_printed_style_attrs         = $args['defaultPrintedStyleAttrs'] ?? [];
		$font_group_properties               = [
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
		$input_label_font_property_selectors = array_fill_keys( $font_group_properties, "{$order_class} label" );
		$field_targets                       = [
			"{$order_class} input:not([type=\"radio\"]):not([type=\"checkbox\"]):not([type=\"submit\"])",
			"{$order_class} textarea",
			"{$order_class} select",
		];
		$field_targets_hover                 = [
			"{$order_class} input:not([type=\"radio\"]):not([type=\"checkbox\"]):not([type=\"submit\"]):hover",
			"{$order_class} textarea:hover",
			"{$order_class} select:hover",
		];
		$field_targets_focus                 = [
			"{$order_class} input:not([type=\"radio\"]):not([type=\"checkbox\"]):not([type=\"submit\"]):focus",
			"{$order_class} textarea:focus",
			"{$order_class} select:focus",
			"{$order_class} input:not([type=\"radio\"]):not([type=\"checkbox\"]):not([type=\"submit\"]):focus-visible",
			"{$order_class} textarea:focus-visible",
			"{$order_class} select:focus-visible",
		];
		$radio_targets                       = [
			"{$order_class} input[type=\"radio\"]",
			"{$order_class} .wpcf7-radio .wpcf7-list-item-label",
		];
		$radio_targets_hover                 = [
			"{$order_class} input[type=\"radio\"]:hover",
			"{$order_class} .wpcf7-radio .wpcf7-list-item-label:hover",
		];
		$radio_targets_focus                 = [
			"{$order_class} input[type=\"radio\"]:focus",
			"{$order_class} input[type=\"radio\"]:focus-visible",
		];
		$radio_targets_checked               = [
			"{$order_class} input[type=\"radio\"]:checked",
		];
		$checkbox_targets                    = [
			"{$order_class} input[type=\"checkbox\"]",
			"{$order_class} .wpcf7-checkbox .wpcf7-list-item-label",
		];
		$checkbox_targets_hover              = [
			"{$order_class} input[type=\"checkbox\"]:hover",
			"{$order_class} .wpcf7-checkbox .wpcf7-list-item-label:hover",
		];
		$checkbox_targets_focus              = [
			"{$order_class} input[type=\"checkbox\"]:focus",
			"{$order_class} input[type=\"checkbox\"]:focus-visible",
		];
		$checkbox_targets_checked            = [
			"{$order_class} input[type=\"checkbox\"]:checked",
		];
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
								'defaultPrintedStyleAttrs' => $default_printed_style_attrs['module']['decoration'] ?? [],
								'disabledOn'               => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
							],
						]
					),
					$elements->style(
						[
							'attrName' => 'label',
						]
					),
					FormFieldStyle::style(
						[
							'selector'          => implode( ', ', $field_targets ),
							'attr'              => self::_get_field_attrs( $attrs ),
							'selectors'         => [
								'desktop' => [
									'value' => implode(
										', ',
										$field_targets
									),
									'hover' => implode(
										', ',
										$field_targets_hover
									),
									'focus' => implode(
										', ',
										$field_targets_focus
									),
								],
							],
							'orderClass'        => $order_class,
							'propertySelectors' => [
								'label' => [
									'font' => [
										'font'       => [
											'desktop' => [
												'value' => $input_label_font_property_selectors,
											],
										],
										'textShadow' => [
											'desktop' => [
												'value' => [
													'text-shadow' => "{$order_class} label",
												],
											],
										],
									],
								],
							],
						]
					),
					FormFieldStyle::style(
						[
							'selector'          => implode( ', ', $radio_targets ),
							'attr'              => $attrs['radioButton'] ?? [],
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
							'orderClass'        => $order_class,
							'disableLabelStyle' => true,
						]
					),
					FormFieldStyle::style(
						[
							'selector'          => implode( ', ', $checkbox_targets ),
							'attr'              => $attrs['checkbox'] ?? [],
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
							'orderClass'        => $order_class,
							'disableLabelStyle' => true,
						]
					),
					$elements->style(
						[
							'attrName'   => 'button',
							'styleProps' => [
								'spacing' => [
									'selector'  => implode(
										', ',
										[
											"{$order_class} .et_pb_button.wpcf7-submit",
											"{$order_class} .et_pb_button.wpcf7-submit:hover",
										]
									),
									'important' => true,
								],
							],
						]
					),
					$elements->style(
						[
							'attrName' => 'messageValidation',
						]
					),
					$elements->style(
						[
							'attrName' => 'messageSuccess',
						]
					),
					$elements->style(
						[
							'attrName' => 'messageInvalid',
						]
					),
					$elements->style(
						[
							'attrName' => 'messageAcceptance',
						]
					),
					$elements->style(
						[
							'attrName' => 'messageSpam',
						]
					),
					$elements->style(
						[
							'attrName' => 'messageFailure',
						]
					),
					CssStyle::style(
						[
							'selector'  => $args['orderClass'],
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}

	/**
	 * Render callback.
	 *
	 * @param array<string, mixed> $attrs    Module attributes.
	 * @param string               $content  Module content.
	 * @param WP_Block             $block    Current block instance.
	 * @param ModuleElements       $elements Module elements instance.
	 * @param array<string, mixed> $default_printed_style_attrs Default printed style attributes.
	 *
	 * @since ??
	 */
	public static function render_callback(
		array $attrs,
		string $content,
		WP_Block $block,
		ModuleElements $elements,
		array $default_printed_style_attrs
	): string {
		$layout_display = $attrs['module']['decoration']['layout']['desktop']['value']['display'] ?? 'flex';

		$parent  = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$form_id = $attrs['form']['advanced']['formId']['desktop']['value'] ?? '';
		$html    = ContactForm7Controller::render_form_preview(
			[
				'formId'        => $form_id,
				'layoutDisplay' => $layout_display,
				'button'        => $attrs['button'] ?? [],
			]
		);

		return Module::render(
			[
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],
				'attrs'                    => $attrs,
				'elements'                 => $elements,
				'id'                       => $block->parsed_block['id'],
				'name'                     => $block->block_type->name,
				'moduleCategory'           => $block->block_type->category,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'parentAttrs'              => $parent->attrs ?? [],
				'parentId'                 => $parent->id ?? '',
				'parentName'               => $parent->blockName ?? '',
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
								'class' => 'et_pb_contact_form_7_preview',
							],
							'childrenSanitizer' => 'et_core_esc_previously',
							'children'          => $html,
						]
					),
				],
			]
		);
	}

	/**
	 * Load the module dependency.
	 *
	 * @since ??
	 */
	public function load(): void {
		if ( ! class_exists( '\WPCF7_ContactForm' ) ) {
			return;
		}

		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/contact-form-7/';

		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
