<?php
/**
 * Plugin compatibility for Advanced Custom Fields.
 *
 * @package Divi
 * @since 5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin compatibility for Advanced Custom Fields.
 *
 * @since 5.0.0
 *
 * @link https://www.advancedcustomfields.com/
 */
class ET_Builder_D5_Plugin_Compat_Advanced_Custom_Fields extends ET_Builder_Plugin_Compat_Base {
	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 */
	public function __construct() {
		$this->plugin_id = $this->_get_plugin_id();
		$this->init_hooks();
	}

	/**
	 * Get the currently activated ACF plugin id as the FREE and PRO versions are separate plugins.
	 *
	 * @since 5.0.0
	 *
	 * @return string
	 */
	protected function _get_plugin_id() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$pro  = 'advanced-custom-fields-pro/acf.php';
		$free = 'advanced-custom-fields/acf.php';
		$scf  = 'secure-custom-fields/secure-custom-fields.php';

		// Prioritize ACF Pro, then ACF Free, then SCF as fallback.
		if ( is_plugin_active( $pro ) ) {
			return $pro;
		}

		if ( is_plugin_active( $free ) ) {
			return $free;
		}

		if ( is_plugin_active( $scf ) ) {
			return $scf;
		}

		// Default fallback (shouldn't reach here if plugin is active).
		return $free;
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function init_hooks() {
		// Bail if there's no version found.
		if ( ! $this->get_plugin_version() ) {
			return;
		}

		add_filter( 'divi_module_dynamic_content_resolved_custom_meta_value', [ $this, 'maybe_format_acf_field_value' ], 10, 3 );
		add_filter( 'divi_module_dynamic_content_display_hidden_meta_keys', [ $this, 'maybe_format_acf_display_hidden_meta_keys' ], 10, 2 );
		add_filter( 'divi_module_dynamic_content_custom_meta_label', [ $this, 'maybe_format_acf_custom_meta_label' ], 10, 2 );
	}

	/**
	 * Format ACF field values based on field type.
	 *
	 * @since 5.0.0
	 *
	 * @param string  $value     The field value.
	 * @param string  $meta_key  The meta key.
	 * @param integer $post_id   The post ID.
	 *
	 * @return string
	 */
	public function maybe_format_acf_field_value( $value, $meta_key, $post_id ) {
		global $wp_query;

		$post_type  = get_post_type( $post_id );
		$identifier = $post_id;

		if ( et_theme_builder_is_layout_post_type( $post_type ) ) {
			return $this->_format_placeholder_value( $meta_key, $post_id );
		}

		$is_blog_query = isset( $wp_query->et_pb_blog_query ) && $wp_query->et_pb_blog_query;

		if ( ! $is_blog_query && ( is_category() || is_tag() || is_tax() ) ) {
			$term       = get_queried_object();
			$identifier = "{$term->taxonomy}_{$term->term_id}";
		} elseif ( is_author() ) {
			$user       = get_queried_object();
			$identifier = "user_{$user->ID}";
		}

		$acf_value = get_field( $meta_key, $identifier );

		if ( false === $acf_value ) {
			return $value;
		}

		$acf_field = get_field_object( $meta_key, $post_id, [ 'load_value' => false ] );
		$acf_value = $this->_format_field_value( $acf_value, $acf_field );

		if ( is_array( $acf_value ) || is_object( $acf_value ) ) {
			$original_array = $acf_value;

			if ( is_array( $acf_value ) && ! empty( $acf_value ) ) {
				$string_values = [];
				foreach ( $acf_value as $item ) {
					if ( null !== $item && ! is_array( $item ) && ! is_object( $item ) ) {
						$string_item = (string) $item;
						if ( ! empty( $string_item ) || '0' === $string_item ) {
							$string_values[] = $string_item;
						}
					}
				}

				$acf_value = implode( ', ', $string_values );

				$acf_value = apply_filters(
					'divi_dynamic_content_acf_array_value',
					$acf_value,
					$original_array,
					$meta_key,
					$post_id
				);
			} else {
				$acf_value = '';
			}
		}

		return (string) $acf_value;
	}

	/**
	 * Format a field value based on the field type.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $value The field value.
	 * @param array $field The field configuration.
	 *
	 * @return mixed
	 */
	protected function _format_field_value( $value, $field ) {
		if ( ! is_array( $field ) || empty( $field['type'] ) ) {
			return $value;
		}

		switch ( $field['type'] ) {
			case 'image':
				$value = $this->_format_image_field_value( $value, $field );
				break;
			case 'gallery':
				$value = $this->_format_gallery_field_value( $value, $field );
				break;
			case 'radio':
				$value = $this->_format_choice_field_value( $value, $field, false );
				break;

			case 'select':
				$allow_multiple = isset( $field['multiple'] ) && $field['multiple'];
				$value          = $this->_format_choice_field_value( $value, $field, $allow_multiple );
				break;

			case 'checkbox':
				$value = $this->_format_choice_field_value( $value, $field, true );
				break;

			case 'true_false':
				$value = et_builder_i18n( $value ? 'Yes' : 'No' );
				break;

			case 'taxonomy':
				// If taxonomy configuration exists, get HTML output of given value (IDs).
				if ( isset( $field['taxonomy'] ) ) {
					$term_ids = [];
					$values   = is_array( $value ) ? $value : [ $value ];
					
					foreach ( $values as $val ) {
						if ( is_object( $val ) && isset( $val->term_id ) ) {
							// ACF returned WP_Term objects (return format = "Object").
							$term_ids[] = $val->term_id;
						} elseif ( is_numeric( $val ) && $val > 0 ) {
							// ACF returned term IDs (return format = "ID")
							$term_ids[] = (int) $val;
						}
					}

					if ( empty( $term_ids ) ) {
						break;
					}

					$terms     = get_terms(
						[
							'taxonomy' => $field['taxonomy'],
							'include'  => $term_ids,
						]
					);
					$link      = 'on';
					$separator = ', ';

					if ( is_array( $terms ) ) {
						$term_names = [];
						foreach ( $terms as $term ) {
							if ( isset( $term->name ) ) {
								$term_names[] = esc_html( $term->name );
							}
						}
						$value = implode( $separator, $term_names );
					}
				}
				break;

			case 'page_link':
				if ( is_numeric( $value ) ) {
					$permalink = get_permalink( $value );
					if ( $permalink ) {
						return $permalink;
					}
				}
				return $value;

			default:
				// Handle multiple values for which a more appropriate formatting method is not available.
				if ( isset( $field['multiple'] ) && $field['multiple'] && is_array( $value ) ) {
					$string_values = [];
					foreach ( $value as $item ) {
						if ( ! is_array( $item ) && ! is_object( $item ) ) {
							$string_values[] = (string) $item;
						}
					}
					$value = implode( ', ', $string_values );
				}
				break;
		}

		// Value escaping left to the user to decide since some fields hold rich content.
		$value = et_core_esc_previously( $value );

		return $value;
	}

	/**
	 * Format ACF image field value based on return format setting.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $value The image field value.
	 * @param array $field The field configuration.
	 *
	 * @return string|int
	 */
	protected function _format_image_field_value( $value, $field ) {
		$format = isset( $field['return_format'] ) ? $field['return_format'] : 'url';
		$result = $this->_extract_image_value( $value, $format );

		return ! empty( $result ) ? $result : $value;
	}

	/**
	 * Format ACF gallery field value based on return format.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $value The gallery field value.
	 * @param array $field The field configuration.
	 *
	 * @return string
	 */
	protected function _format_gallery_field_value( $value, $field ) {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return $value;
		}

		$format = isset( $field['return_format'] ) ? $field['return_format'] : 'array';
		$items  = [];

		foreach ( $value as $image ) {
			$item = $this->_extract_image_value( $image, $format );
			if ( ! empty( $item ) ) {
				$items[] = $item;
			}
		}

		return implode(
			', ',
			array_filter(
				$items,
				function( $item ) {
					return ! empty( $item );
				}
			)
		);
	}

	/**
	 * Extract appropriate value from image data based on return format.
	 * - 'array': Returns URL (ID, WIDTHxHEIGHT) format
	 * - 'url': Returns just the URL
	 * - 'id': Returns attachment ID
	 *
	 * @since 5.0.0
	 *
	 * @param mixed  $image  The image data (array, ID, or URL string).
	 * @param string $format The return format ('array', 'id', 'url').
	 *
	 * @return string|int
	 */
	protected function _extract_image_value( $image, $format ) {
		switch ( $format ) {
			case 'array':
				// For array format, show compact details: URL (ID, WIDTHxHEIGHT).
				if ( is_array( $image ) && isset( $image['url'] ) ) {
					$url    = esc_url( $image['url'] );
					$width  = ! empty( $image['width'] ) ? intval( $image['width'] ) : 0;
					$height = ! empty( $image['height'] ) ? intval( $image['height'] ) : 0;

					// Build compact info string: URL (ID, WIDTHxHEIGHT).
					$details = [];

					if ( ! empty( $image['id'] ) ) {
						$details[] = intval( $image['id'] );
					}

					if ( $width && $height ) {
						$details[] = $width . '×' . $height;
					}

					if ( empty( $details ) ) {
						return $url;
					}

					return $url . ' (' . implode( ', ', $details ) . ')';

				} elseif ( is_array( $image ) && isset( $image['id'] ) ) {
					$url = wp_get_attachment_url( intval( $image['id'] ) );
					return $url ? esc_url( $url ) : '';
				}
				break;
			case 'id':
				// Return ID for ID format.
				if ( is_array( $image ) && isset( $image['id'] ) ) {
					return intval( $image['id'] );
				} elseif ( is_numeric( $image ) ) {
					return intval( $image );
				}
				break;
			case 'url':
			default:
				// Return URL for URL format.
				if ( is_array( $image ) && isset( $image['url'] ) ) {
					return esc_url( $image['url'] );
				} elseif ( is_array( $image ) && isset( $image['id'] ) ) {
					$url = wp_get_attachment_url( intval( $image['id'] ) );
					return $url ? esc_url( $url ) : '';
				} elseif ( is_string( $image ) && ! empty( $image ) ) {
					return esc_url( $image );
				}
				break;
		}

		return '';
	}

	/**
	 * Format choice field values (radio, select, checkbox) based on field configuration.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $value         The field value.
	 * @param array $field         The field configuration.
	 * @param bool  $allow_multiple Whether the field allows multiple values.
	 *
	 * @return string
	 */
	protected function _format_choice_field_value( $value, $field, $allow_multiple ) {
		$format = isset( $field['return_format'] ) ? $field['return_format'] : 'value';

		// Handle single value fields (radio, single select).
		if ( ! $allow_multiple ) {
			// Only process array format for single value fields.
			if ( 'array' === $format && is_array( $value ) && isset( $value['value'] ) ) {
				return $value['value'];
			}
			// For other formats, return value as-is (ACF has already processed it).
			return $value;
		}

		// Handle multiple value fields (checkbox, multi-select).
		$values        = is_array( $value ) ? $value : [ $value ];
		$output_values = [];

		foreach ( $values as $single_value ) {
			// Skip empty values, but allow '0'.
			if ( empty( $single_value ) && '0' !== $single_value ) {
				continue;
			}

			// Handle array format returned by ACF (value + label structure).
			if ( is_array( $single_value ) && isset( $single_value['value'] ) ) {
				$output_values[] = $single_value['value'];
			} else {
				$output_values[] = $single_value;
			}
		}

		return implode( ', ', $output_values );
	}

	/**
	 * Format a placeholder value based on the field type.
	 *
	 * @since 5.0.0
	 *
	 * @param string  $meta_key The meta key.
	 * @param integer $post_id  The post ID.
	 *
	 * @return string
	 */
	protected function _format_placeholder_value( $meta_key, $post_id ) {
		if ( function_exists( 'acf_get_field' ) ) {
			$field = acf_get_field( $meta_key );
		} else {
			$field = get_field_object( $meta_key, false, [ 'load_value' => false ] );
		}

		if ( ! is_array( $field ) || empty( $field['type'] ) ) {
			return esc_html__( 'Your ACF Field Value Will Display Here', 'et_builder' );
		}

		$value = esc_html(
			sprintf(
				// Translators: %1$s: ACF Field name.
				__( 'Your "%1$s" ACF Field Value Will Display Here', 'et_builder' ),
				$field['label']
			)
		);

		switch ( $field['type'] ) {
			case 'image':
				$value = ET_BUILDER_PLACEHOLDER_LANDSCAPE_IMAGE_DATA;
				break;

			case 'taxonomy':
				$value = esc_html(
					implode(
						', ',
						[
							__( 'Category 1', 'et_builder' ),
							__( 'Category 2', 'et_builder' ),
							__( 'Category 3', 'et_builder' ),
						]
					)
				);
				break;
		}

		return $value;
	}

	/**
	 * Filter dynamic content display hidden meta keys.
	 *
	 * @since 5.0.0
	 *
	 * @param array $post_meta_keys Post meta keys.
	 * @param int   $post_id        Post ID.
	 *
	 * @return array
	 */
	public function maybe_format_acf_display_hidden_meta_keys( $post_meta_keys, $post_id ) {
		$groups = 0 !== $post_id ? acf_get_field_groups( [ 'post_id' => $post_id ] ) : acf_get_field_groups();

		foreach ( $groups as $group ) {
			$fields = $this->_expand_fields( acf_get_fields( $group['ID'] ) );

			foreach ( $fields as $field ) {
				if ( 'group' === $field['type'] ) {
					continue;
				}

				$post_meta_keys[] = $field['name'];
			}
		}

		return $post_meta_keys;
	}

	/**
	 * Filter dynamic content custom meta label.
	 *
	 * @since 5.0.0
	 *
	 * @param string $label    Custom meta label.
	 * @param string $meta_key Custom meta key.
	 *
	 * @return string
	 */
	public function maybe_format_acf_custom_meta_label( $label, $meta_key ) {
		if ( function_exists( 'acf_get_field' ) ) {
			$field = acf_get_field( $meta_key );
		} else {
			$field = get_field_object( $meta_key, false, [ 'load_value' => false ] );
		}

		// Only override if this is a valid ACF field with a label and matching name.
		if ( is_array( $field ) && ! empty( $field['label'] ) && ! empty( $field['name'] ) && $field['name'] === $meta_key ) {
			return $field['label'];
		}

		return $label;
	}

	/**
	 * Expand ACF fields into their subfields in the order they are specified, if any.
	 *
	 * @since 5.0.0
	 *
	 * @param array[] $fields       The fields to expand.
	 * @param string  $name_prefix  The name prefix.
	 * @param string  $label_prefix The label prefix.
	 *
	 * @return array[]
	 */
	protected function _expand_fields( $fields, $name_prefix = '', $label_prefix = '' ) {
		$expanded = [];

		foreach ( $fields as $field ) {
			$expanded[] = [
				array_merge(
					$field,
					[
						'name'  => $name_prefix . $field['name'],
						'label' => $label_prefix . $field['label'],
					]
				),
			];

			if ( 'group' === $field['type'] ) {
				$expanded[] = $this->_expand_fields(
					$field['sub_fields'],
					$name_prefix . $field['name'] . '_',
					$label_prefix . $field['label'] . ': '
				);
			}
		}

		if ( empty( $expanded ) ) {
			return [];
		}

		// We need to use array_merge to flatten the array of arrays returned by expand_fields.
		// @phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- array_merge is required to flatten nested arrays.
		return call_user_func_array( 'array_merge', $expanded );
	}
}

new ET_Builder_D5_Plugin_Compat_Advanced_Custom_Fields();
