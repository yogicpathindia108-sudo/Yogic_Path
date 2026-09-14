<?php
/**
 * Shared layout declarations for Contact Field checkbox/radio option rows.
 *
 * Lets Fields Text Alignment (`text-align` on labels) apply visibly by stretching labels in a flex row.
 *
 * @package Divi
 *
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\ContactField;

use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Contact Field option row layout style helpers (Contact Form, Contact Field, and Email Opt-in).
 */
final class ContactFieldOptionRowLayoutDeclarations {

	/**
	 * Minimal responsive attribute so divi/common emits desktop rules once.
	 *
	 * @since ??
	 *
	 * @return array<string, mixed>
	 */
	public static function layout_attr_placeholder(): array {
		return [
			'desktop' => [
				'value' => [
					'_contactFieldOptionRowLayout' => 'on',
				],
			],
		];
	}

	/**
	 * Checkbox/radio option row wrappers (both nested Contact Field and same-element Contact Field module root).
	 *
	 * @since ??
	 *
	 * @param string $order_class Module order class selector fragment (includes leading `.`).
	 *
	 * @return string Comma-separated selectors.
	 */
	public static function wrapper_selectors( string $order_class ): string {
		return implode(
			', ',
			[
				"{$order_class}.et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_checkbox",
				"{$order_class} .et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_checkbox",
				"{$order_class}.et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_radio",
				"{$order_class} .et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_radio",
			]
		);
	}

	/**
	 * Labels adjacent to checkbox/radio inputs inside option rows.
	 *
	 * @since ??
	 *
	 * @param string $order_class Module order class selector fragment (includes leading `.`).
	 *
	 * @return string Comma-separated selectors.
	 */
	public static function label_selectors( string $order_class ): string {
		return implode(
			', ',
			[
				"{$order_class}.et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_checkbox > label",
				"{$order_class} .et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_checkbox > label",
				"{$order_class}.et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_radio > label",
				"{$order_class} .et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_radio > label",
			]
		);
	}

	/**
	 * Checkbox/radio option rows inside Email Opt-in (`.et_pb_newsletter_form`) custom fields.
	 *
	 * @since ??
	 *
	 * @param string $order_class Email Opt-in module order class (leading `.`).
	 *
	 * @return string Comma-separated selectors.
	 */
	public static function newsletter_wrapper_selectors( string $order_class ): string {
		return implode(
			', ',
			[
				"{$order_class} .et_pb_newsletter_form .et_pb_newsletter_fields p.et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_checkbox",
				"{$order_class} .et_pb_newsletter_form .et_pb_newsletter_fields p.et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_radio",
				"{$order_class} .et_pb_newsletter_form p.et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_checkbox",
				"{$order_class} .et_pb_newsletter_form p.et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_radio",
			]
		);
	}

	/**
	 * Labels for newsletter form checkbox/radio option rows.
	 *
	 * @since ??
	 *
	 * @param string $order_class Email Opt-in module order class (leading `.`).
	 *
	 * @return string Comma-separated selectors.
	 */
	public static function newsletter_label_selectors( string $order_class ): string {
		return implode(
			', ',
			[
				"{$order_class} .et_pb_newsletter_form .et_pb_newsletter_fields p.et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_checkbox > label",
				"{$order_class} .et_pb_newsletter_form .et_pb_newsletter_fields p.et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_radio > label",
				"{$order_class} .et_pb_newsletter_form p.et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_checkbox > label",
				"{$order_class} .et_pb_newsletter_form p.et_pb_contact_field .et_pb_contact_field_options_list > .et_pb_contact_field_radio > label",
			]
		);
	}

	/**
	 * Flex row layout for each checkbox/radio option row.
	 *
	 * @since ??
	 *
	 * @param array $params Style declaration parameters.
	 *
	 * @return string CSS declarations.
	 */
	public static function wrapper_declaration( array $params ): string {
		$attr_value = $params['attrValue'] ?? [];

		if ( ! isset( $attr_value['_contactFieldOptionRowLayout'] ) || 'on' !== $attr_value['_contactFieldOptionRowLayout'] ) {
			return '';
		}

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		$style_declarations->add( 'display', 'flex' );
		$style_declarations->add( 'flex-direction', 'row' );
		$style_declarations->add( 'align-items', 'center' );
		$style_declarations->add( 'width', '100%' );

		return $style_declarations->value();
	}

	/**
	 * Stretch label so `text-align` affects visible copy within the row.
	 *
	 * @since ??
	 *
	 * @param array $params Style declaration parameters.
	 *
	 * @return string CSS declarations.
	 */
	public static function label_declaration( array $params ): string {
		$attr_value = $params['attrValue'] ?? [];

		if ( ! isset( $attr_value['_contactFieldOptionRowLayout'] ) || 'on' !== $attr_value['_contactFieldOptionRowLayout'] ) {
			return '';
		}

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		$style_declarations->add( 'flex', '1 1 auto' );
		$style_declarations->add( 'min-width', '0' );

		return $style_declarations->value();
	}
}
