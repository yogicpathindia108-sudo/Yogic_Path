<?php
/**
 * Module Library: Contact Form 7 Module REST Controller class.
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\ContactForm7;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Packages\ModuleUtils\FormMarkupSecurityUtils;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles Contact Form 7 module REST endpoints.
 *
 * @since ??
 */
class ContactForm7Controller extends RESTController {

	/**
	 * Adds Divi button class to CF7 submit controls.
	 *
	 * @since ??
	 *
	 * @param string $html Form HTML output.
	 *
	 * @return string
	 */
	public static function add_divi_button_class_to_submit( string $html ): string {
		$processed_html = preg_replace_callback(
			'/<(input|button)\b[^>]*>/i',
			static function ( array $tag_matches ): string {
				$tag = $tag_matches[0] ?? '';

				if ( '' === $tag ) {
					return '';
				}

				$class_match_result = preg_match( '/\bclass\s*=\s*([\'"])(.*?)\1/i', $tag, $class_matches );

				if ( 1 !== $class_match_result ) {
					return $tag;
				}

				$classes = FormMarkupSecurityUtils::normalize_class_names( (string) ( $class_matches[2] ?? '' ) );

				if ( ! in_array( 'wpcf7-submit', $classes, true ) ) {
					return $tag;
				}

				$is_button_tag   = 1 === preg_match( '/^<button\b/i', trim( $tag ) );
				$is_submit_input = 1 === preg_match( '/^<input\b/i', trim( $tag ) )
					&& 1 === preg_match( '/\btype\s*=\s*(["\']?)submit\1/i', $tag );

				// Only transform submit controls; non-submit inputs should be left untouched.
				if ( ! $is_button_tag && ! $is_submit_input ) {
					return $tag;
				}

				if ( ! in_array( 'et_pb_button', $classes, true ) ) {
					$classes[] = 'et_pb_button';
				}

				if ( ! in_array( 'et_pb_bg_layout_light', $classes, true ) ) {
					$classes[] = 'et_pb_bg_layout_light';
				}

				if ( ! in_array( 'et_pb_module', $classes, true ) ) {
					$classes[] = 'et_pb_module';
				}

				$quote_char      = $class_matches[1] ?? '"';
				$updated_class   = 'class=' . $quote_char . implode( ' ', $classes ) . $quote_char;
				$full_class_attr = $class_matches[0] ?? '';

				$updated_tag = str_replace( $full_class_attr, $updated_class, $tag );

				if ( $is_button_tag ) {
					$sanitized_button_tag = wp_kses(
						$updated_tag,
						[
							'button' => FormMarkupSecurityUtils::get_allowed_button_attributes_for_kses(),
						]
					);

					if ( '' === $sanitized_button_tag ) {
						return '<button type="submit"></button>';
					}

					return $sanitized_button_tag;
				}

				$value_match_result = preg_match( '/\bvalue\s*=\s*(?:(["\'])(.*?)\1|([^\s>]+))/i', $updated_tag, $value_matches );
				$button_text        = '';

				if ( 1 === $value_match_result ) {
					$button_text = '' !== ( $value_matches[2] ?? '' ) ? $value_matches[2] : ( $value_matches[3] ?? '' );
				}

				$button_attributes = preg_replace( '/^<input\b/i', '', $updated_tag );
				$button_attributes = is_string( $button_attributes ) ? preg_replace( '/\s*\/?>$/', '', $button_attributes ) : '';
				$button_attributes = is_string( $button_attributes ) ? preg_replace( '/\s+\btype\s*=\s*(?:(["\'])submit\1|submit)/i', '', $button_attributes ) : '';
				$button_attributes = is_string( $button_attributes ) ? preg_replace( '/\s+\bvalue\s*=\s*(?:(["\']).*?\1|[^\s>]+)/i', '', $button_attributes ) : '';
				$button_attributes = is_string( $button_attributes ) ? trim( $button_attributes ) : '';

				$button_text = esc_html( wp_specialchars_decode( $button_text, ENT_QUOTES ) );
				$button_html = '<button type="submit"';

				if ( '' !== $button_attributes ) {
					$button_html .= ' ' . $button_attributes;
				}

				$button_html .= '>' . $button_text . '</button>';

				// Re-sanitize rebuilt button markup to prevent unsafe attribute passthrough.
				$button_html = wp_kses(
					$button_html,
					[
						'button' => FormMarkupSecurityUtils::get_allowed_button_attributes_for_kses(),
					]
				);

				if ( '' === $button_html ) {
					return '<button type="submit">' . $button_text . '</button>';
				}

				return $button_html;
			},
			$html
		);

		if ( ! is_string( $processed_html ) ) {
			return $html;
		}

		return $processed_html;
	}

	/**
	 * Sanitize button module attrs from REST request.
	 *
	 * @since ??
	 *
	 * @param mixed $button_attrs Raw button attrs from REST request.
	 *
	 * @return array<string, mixed>
	 */
	public static function sanitize_button_attrs( $button_attrs ): array {
		if ( ! is_array( $button_attrs ) ) {
			return [];
		}

		return $button_attrs;
	}

	/**
	 * Resolve custom button icon HTML attributes from module button attrs.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $button_attrs Module `button` attribute subtree.
	 *
	 * @return array<string, string>
	 */
	public static function get_custom_button_icon_html_attributes( array $button_attrs ): array {
		static $cache = [];

		$button_decoration = $button_attrs['decoration']['button'] ?? [];
		$cache_key         = md5( wp_json_encode( $button_decoration ) );

		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$desktop_value = $button_decoration['desktop']['value'] ?? [];
		// Match module default `icon.enable` and ButtonIcon fallback when enable is unset.
		$enable = $desktop_value['icon']['enable'] ?? 'on';

		if ( 'on' !== $enable ) {
			$cache[ $cache_key ] = [];

			return [];
		}

		$icon_desktop = $button_decoration['desktop']['value']['icon']['settings'] ?? [];
		$icon_tablet  = $button_decoration['tablet']['value']['icon']['settings'] ?? [];
		$icon_phone   = $button_decoration['phone']['value']['icon']['settings'] ?? [];

		$has_icon_settings = ! empty( $icon_desktop ) || ! empty( $icon_tablet ) || ! empty( $icon_phone );

		if ( ! $has_icon_settings ) {
			$cache[ $cache_key ] = [];

			return [];
		}

		$html_attrs = [];

		if ( ! empty( $icon_desktop ) ) {
			$processed_icon_desktop = Utils::process_font_icon( $icon_desktop );

			if ( ! empty( $processed_icon_desktop ) ) {
				$html_attrs['data-icon'] = esc_attr( $processed_icon_desktop );
			}
		}

		if ( ! empty( $icon_tablet ) ) {
			$processed_icon_tablet = Utils::process_font_icon( $icon_tablet );

			if ( ! empty( $processed_icon_tablet ) ) {
				$html_attrs['data-icon-tablet'] = esc_attr( $processed_icon_tablet );
			}
		}

		if ( ! empty( $icon_phone ) ) {
			$processed_icon_phone = Utils::process_font_icon( $icon_phone );

			if ( ! empty( $processed_icon_phone ) ) {
				$html_attrs['data-icon-phone'] = esc_attr( $processed_icon_phone );
			}
		}

		$cache[ $cache_key ] = $html_attrs;

		return $html_attrs;
	}

	/**
	 * Inject custom button icon data attributes onto CF7 submit controls.
	 *
	 * @since ??
	 *
	 * @param string               $html       Form HTML output.
	 * @param array<string, string> $html_attrs Icon-related HTML attributes.
	 *
	 * @return string
	 */
	public static function inject_custom_button_icon_attributes( string $html, array $html_attrs ): string {
		if ( '' === $html || [] === $html_attrs ) {
			return $html;
		}

		$attributes_to_inject = '';

		foreach ( $html_attrs as $attr_name => $attr_value ) {
			if ( '' !== $attr_value ) {
				$attributes_to_inject .= sprintf( ' %s="%s"', esc_attr( $attr_name ), esc_attr( $attr_value ) );
			}
		}

		if ( '' === $attributes_to_inject ) {
			return $html;
		}

		// Regex test: https://regex101.com/r/8vJqYx/1.
		$updated_html = preg_replace(
			'/(<button\b(?![^>]*\bdata-icon=)[^>]*\bclass=(["\'])[^"\']*\bwpcf7-submit\b[^"\']*\2[^>]*)(>)/i',
			"$1{$attributes_to_inject}$3",
			$html
		);

		if ( is_string( $updated_html ) && $updated_html !== $html ) {
			return $updated_html;
		}

		return $html;
	}

	/**
	 * Adds Divi layout utility class to the first CF7 form element.
	 *
	 * @since ??
	 *
	 * @param string $html           Form HTML output.
	 * @param string $layout_display Layout display value.
	 *
	 * @return string
	 */
	public static function add_divi_layout_class_to_form( string $html, string $layout_display ): string {
		$layout_class = 'et_flex_module';

		if ( 'grid' === $layout_display ) {
			$layout_class = 'et_grid_module';
		} elseif ( 'block' === $layout_display ) {
			$layout_class = 'et_block_module';
		}

		$processed_html = preg_replace_callback(
			'/<form\b[^>]*>/i',
			static function ( array $tag_matches ) use ( $layout_class ): string {
				$tag = $tag_matches[0] ?? '';

				if ( '' === $tag ) {
					return '';
				}

				$class_match_result = preg_match( '/\bclass\s*=\s*([\'"])(.*?)\1/i', $tag, $class_matches );

				if ( 1 !== $class_match_result ) {
					$replaced_tag = preg_replace( '/<form\b/i', '<form class="' . esc_attr( $layout_class ) . '"', $tag, 1 );

					if ( is_string( $replaced_tag ) ) {
						return $replaced_tag;
					}

					return $tag;
				}

				$classes = FormMarkupSecurityUtils::normalize_class_names( (string) ( $class_matches[2] ?? '' ) );

				if ( ! in_array( $layout_class, $classes, true ) ) {
					$classes[] = $layout_class;
				}

				$quote_char      = $class_matches[1] ?? '"';
				$updated_class   = 'class=' . $quote_char . implode( ' ', $classes ) . $quote_char;
				$full_class_attr = $class_matches[0] ?? '';

				return str_replace( $full_class_attr, $updated_class, $tag );
			},
			$html,
			1
		);

		if ( ! is_string( $processed_html ) ) {
			return $html;
		}

		return $processed_html;
	}

	/**
	 * Retrieve rendered HTML for Contact Form 7 module.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$form_id        = sanitize_text_field( (string) $request->get_param( 'formId' ) );
		$include_forms  = rest_sanitize_boolean( $request->get_param( 'includeForms' ) );
		$layout_display = sanitize_text_field( (string) $request->get_param( 'layoutDisplay' ) );
		$button_attrs   = self::sanitize_button_attrs( $request->get_param( 'button' ) );

		$response = [
			'html' => self::render_form_preview(
				[
					'formId'        => $form_id,
					'layoutDisplay' => $layout_display,
					'button'        => $button_attrs,
				]
			),
		];

		if ( $include_forms ) {
			$response['forms'] = self::get_forms_options();
		}

		return self::response_success( $response );
	}

	/**
	 * Endpoint arguments as used in `register_rest_route()`.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function index_args(): array {
		return [
			'formId'        => [
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'includeForms'  => [
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			],
			'layoutDisplay' => [
				'type'              => 'string',
				'default'           => 'flex',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'button'        => [
				'type'              => 'object',
				'default'           => [],
				'required'          => false,
				'sanitize_callback' => [ self::class, 'sanitize_button_attrs' ],
			],
		];
	}

	/**
	 * Endpoint permission callback.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function index_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}

	/**
	 * Returns Contact Form 7 form options.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function get_forms_options(): array {
		$options = [];
		$forms   = get_posts(
			[
				'post_type'      => 'wpcf7_contact_form',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		foreach ( $forms as $form ) {
			$options[ (string) $form->ID ] = [
				'label' => $form->post_title,
			];
		}

		return $options;
	}

	/**
	 * Returns the first available Contact Form 7 form ID.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private static function _get_default_form_id(): string {
		$forms_options = self::get_forms_options();
		$form_ids      = array_keys( $forms_options );

		return isset( $form_ids[0] ) ? (string) $form_ids[0] : '';
	}

	/**
	 * Determine whether rendered markup looks like a usable CF7 preview.
	 *
	 * @since ??
	 *
	 * @param string $rendered_html Rendered form HTML.
	 *
	 * @return bool
	 */
	private static function _is_usable_cf7_preview_markup( string $rendered_html ): bool {
		if ( '' === trim( wp_strip_all_tags( $rendered_html ) ) ) {
			return false;
		}

		if ( ! str_contains( $rendered_html, 'wpcf7-form' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Build deterministic preview error markup for invalid/unrenderable forms.
	 *
	 * @since ??
	 *
	 * @param string $form_id Form ID.
	 *
	 * @return string
	 */
	private static function _get_preview_error_markup( string $form_id ): string {
		return sprintf(
			'<div class="wpcf7"><div class="wpcf7-response-output">Unable to load Contact Form 7 preview for form ID %s.</div></div>',
			esc_html( $form_id )
		);
	}

	/**
	 * Renders Contact Form 7 preview html.
	 *
	 * @since ??
	 *
	 * @param array $args Render args.
	 *
	 * @return string
	 */
	public static function render_form_preview( array $args ): string {
		$form_id        = sanitize_text_field( (string) ( $args['formId'] ?? '' ) );
		$layout_display = sanitize_text_field( (string) ( $args['layoutDisplay'] ?? 'flex' ) );
		$button_attrs   = self::sanitize_button_attrs( $args['button'] ?? [] );

		if ( '' === $form_id ) {
			$form_id = self::_get_default_form_id();
		}

		if ( '' === $form_id ) {
			return '';
		}

		if ( class_exists( '\WPCF7_ContactForm' ) ) {
			$form_post = get_post( (int) $form_id );

			if (
				! $form_post instanceof \WP_Post
				|| 'wpcf7_contact_form' !== $form_post->post_type
			) {
				return self::_get_preview_error_markup( $form_id );
			}

			$rendered = do_shortcode( sprintf( '[contact-form-7 id="%s"]', esc_attr( $form_id ) ) );
			$rendered = self::add_divi_button_class_to_submit( $rendered );
			$rendered = self::inject_custom_button_icon_attributes(
				$rendered,
				self::get_custom_button_icon_html_attributes( $button_attrs )
			);
			$rendered = self::add_divi_layout_class_to_form( $rendered, $layout_display );

			if ( self::_is_usable_cf7_preview_markup( $rendered ) ) {
				return $rendered;
			}

			return self::_get_preview_error_markup( $form_id );
		}

		// Deterministic fallback for non-CF7 test environments.
		$fallback_markup = sprintf(
			'<div class="wpcf7"><form class="wpcf7-form"><label>Name<input class="wpcf7-form-control" type="text" /></label><input class="wpcf7-form-control wpcf7-submit" type="submit" value="Send" /><div class="wpcf7-response-output">Preview form %s</div></form></div>',
			esc_html( $form_id )
		);

		$fallback_markup = self::add_divi_button_class_to_submit( $fallback_markup );
		$fallback_markup = self::inject_custom_button_icon_attributes(
			$fallback_markup,
			self::get_custom_button_icon_html_attributes( $button_attrs )
		);

		return self::add_divi_layout_class_to_form( $fallback_markup, $layout_display );
	}
}
