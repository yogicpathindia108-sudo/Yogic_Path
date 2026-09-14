<?php
/**
 * Page Settings class.
 *
 * @package Divi
 *
 * @since ??
 */

namespace ET\Builder\Framework\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;

/**
 * Class for handling builder's page settings.
 *
 * @internal in D4, both builder settings and page settings are handled by `ET_Builder_Settings` class which in D5
 * is ported into ET\Builder\Framework\Settings\Settings class. In D5, the page settings are handled by this class.
 *
 * @since ??
 */
class PageSettings implements DependencyInterface {
	/**
	 * List of items registered via `register_item` method.
	 *
	 * @var array
	 */
	private static $_registered_items = [];

	/**
	 * Load the class.
	 */
	public function load(): void {
		self::setup();
	}

	/**
	 * Setup page settings' items.
	 *
	 * @since ??
	 */
	public static function setup() {
		// Design > Text > Light Text Color.
		self::register_item(
			[
				'get_name'           => 'et_pb_light_text_color',
				'name'               => 'lightTextColor',
				'default_value'      => '#ffffff',
				'get_value_function' => function ( $post_id, $default_value ) {
					$saved_value = get_post_meta( $post_id, '_et_pb_light_text_color', true );
					$value       = '' !== $saved_value ? $saved_value : $default_value;

					return strtolower( $value );
				},
			]
		);

		// Design > Text > Dark Text Color.
		self::register_item(
			[
				'get_name'           => 'et_pb_dark_text_color',
				'name'               => 'darkTextColor',
				'default_value'      => '#666666',
				'get_value_function' => function ( $post_id, $default_value ) {
					$saved_value = get_post_meta( $post_id, '_et_pb_dark_text_color', true );
					$value       = '' !== $saved_value ? $saved_value : $default_value;

					return strtolower( $value );
				},
			]
		);
	}

	/**
	 * Get list of registered items.
	 *
	 * @since ??
	 */
	public static function get_registered_items() {
		return self::$_registered_items;
	}

	/**
	 * Resolve effective page gutter width and default status using explicit/default contract.
	 *
	 * @since ??
	 *
	 * @param int      $post_id       Post ID.
	 * @param int|null $default_value Optional. Default gutter value to use when inherited.
	 *                                If null, uses Theme Customizer gutter setting.
	 *
	 * @return array {
	 *     Resolved gutter width information.
	 *
	 *     @type bool $is_default Whether the gutter width is inherited from customizer (true)
	 *                            or an explicit page override (false).
	 *     @type int  $value      The effective gutter width value.
	 * }
	 */
	public static function resolve_page_gutter_width( int $post_id, ?int $default_value = null ): array {
		$customizer_gutter        = $default_value ?? intval( et_get_option( 'gutter_width', '3' ) );
		$page_custom_gutter       = get_post_meta( $post_id, '_et_pb_gutter_width', true );
		$page_custom_gutter_width = intval( $page_custom_gutter );
		$gutter_is_default_meta   = get_post_meta( $post_id, '_et_pb_page_gutter_width_is_default', true );
		$has_default_meta         = '' !== $gutter_is_default_meta;

		// If explicitness meta exists, honor it.
		// Otherwise, treat non-empty legacy gutter values as explicit custom overrides.
		$is_default = $has_default_meta
			? ( true === filter_var( $gutter_is_default_meta, FILTER_VALIDATE_BOOLEAN ) )
			: ( '' === $page_custom_gutter );

		if ( $is_default || 0 === $page_custom_gutter_width ) {
			return [
				'is_default' => true,
				'value'      => $customizer_gutter,
			];
		}

		return [
			'is_default' => false,
			'value'      => $page_custom_gutter_width,
		];
	}

	/**
	 * Register a page settings item.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *   Arguments for registering a page settings item.
	 *
	 *   @type string $get_name The name of the page settings item. Visual Builder gets the value from here.
	 *   @type string $name The name of the page settings item. Visual Builder saves the value to here.
	 *   @type callable $get_value_function The function to get the value of the page settings item.
	 *   @type callable $save_sanitize_function The function to sanitize the value of the page settings item.
	 *   @type mixed $default_value The default value of the page settings item.
	 * }
	 *
	 * @return void
	 */
	public static function register_item( $args ) {
		$get_name               = $args['get_name'] ?? null;
		$name                   = $args['name'] ?? null;
		$get_value_function     = $args['get_value_function'] ?? null;
		$save_sanitize_function = $args['save_sanitize_function'] ?? null;
		$default_value          = $args['default_value'] ?? null;

		// If `get_name` is not given, use `name` as `get_name`.
		// It is better not to use `get_name`. However older page settings might still use snake case instead of
		// camelCase so `$get_name` will be needed in this case.
		if ( is_null( $get_name ) ) {
			$get_name = $name;
		}

		// Required arguments should be given.
		if ( is_null( $get_name ) || is_null( $name ) || is_null( $default_value ) || is_null( $get_value_function ) ) {
			return;
		}

		// Update list of registered items.
		self::$_registered_items[ $get_name ] = [
			'getName' => $get_name,
			'name'    => $name,
		];

		// Insert custom page settings value to the page settings array.
		if ( is_string( $get_name ) && is_callable( $get_value_function ) ) {
			add_filter(
				'divi_framework_settings_get_page_settings_values',
				function ( $values, $post_id ) use ( $get_name, $get_value_function, $default_value ) {
					$page_settings_value = $get_value_function( $post_id, $default_value );

					// List `get_name` value on `$is_default` array if page_settings_value is identical to default value..
					if ( $page_settings_value === $default_value ) {
						add_filter(
							'divi_framework_settings_get_page_settings_is_default',
							function ( $is_default ) use ( $get_name ) {
								$is_default[] = $get_name;

								return $is_default;
							}
						);
					}

					$values[ $get_name ] = $page_settings_value;

					return $values;
				},
				10,
				2
			);
		}

		// Register setting item as builder page definition (see `ET_Builder_Settings` class' `$_PAGE_SETTINGS_FIELDS`
		// property) because `et_builder_update_settings()` that is called on `SavingUtility::save_page_settings()` is
		// Divi 4 function that uses `ET_Builder_Settings::get_fields()` field definition to verify page settings' fields.
		// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
		// @todo feat(D5, Refactor) Remove this once `SavingUtility::save_page_settings()` is no longer used
		// `et_builder_update_settings()` or once `et_builder_update_settings()` is ported to Divi 5.
		add_filter(
			'et_builder_page_settings_definitions',
			function ( $settings ) use ( $name, $default_value ) {
				$settings[ $name ] = [
					'type'    => '',
					'id'      => $name,
					'default' => $default_value,
				];

				return $settings;
			}
		);

		// Setup save name and its sanitizer. If no `save_sanitize_function` is given, Divi will use `sanitize_text_field`.
		add_filter(
			'divi_visual_builder_saving_page_settings_mapping',
			function ( $mapping ) use ( $get_name, $name, $save_sanitize_function ) {

				$mapping[ $name ] = [
					'd4_key' => $get_name,
				];

				if ( is_callable( $save_sanitize_function ) ) {
					$mapping[ $name ]['sanitizer'] = $save_sanitize_function;
				}

				return $mapping;
			}
		);
	}
}
