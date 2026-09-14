<?php
/**
 * ValueExpansion Class
 *
 * @package Divi
 * @since ??
 */

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Temporarily disabled to get the PR CI pass for now. TODO: Fix this later.
// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames -- Reserved keywords used intentionally for clarity in utility functions.

namespace ET\Builder\Packages\Conversion;

use ET\Builder\VisualBuilder\Taxonomies\TaxonomiesUtility;
use WP_Error;

/**
 * ValueExpansion Class
 *
 * @since ??
 * @package ET\Builder\Packages\Conversion
 */
class ValueExpansion {
	/**
	 * Obtains an object of the expanded value of an icon attribute.
	 *
	 * @param string $value Expanded attribute value.
	 *
	 * @return array|bool The expanded value or false if no expansion was found.
	 */
	public static function convertFontIcon( $value ) {
		$value_array = explode( '||', $value );

		$icon = [];

		// Directly access array elements and check if they are set.
		if ( ! empty( $value_array[0] ) ) {
			$icon['unicode'] = $value_array[0];
		}

		if ( ! empty( $value_array[1] ) ) {
			$icon['type'] = $value_array[1];
		}

		if ( ! empty( $value_array[2] ) ) {
			$icon['weight'] = $value_array[2];
		}

		return ! empty( $icon ) ? $icon : false;
	}

	/**
	 * Convert Body Font.
	 *
	 * Wraps the standard font conversion and ensures that when D4 Regular weight
	 * is converted, an explicit weight of '400' is set to prevent fallback to
	 * module default weights.
	 *
	 * @since ??
	 *
	 * @param string $value D4 font value string (pipe-separated).
	 * @param array  $extra_params Additional parameters including attrs, desktopName, etc.
	 *
	 * @return array The expanded font values with weight guaranteed to be set.
	 */
	public static function convertBodyFont( $value, $extra_params ) {
		// Call the original convertFont to get base expansion.
		$expanded = AdvancedOptionConversion::convertFont( $value );

		// Ensure we always return an array.
		if ( ! is_array( $expanded ) ) {
			$expanded = [];
		}

		// D4 Regular body font can resolve to an expanded font object without weight.
		// Ensure migration writes explicit regular weight instead of falling back to module default.
		if ( ! isset( $expanded['weight'] ) ) {
			$expanded['weight'] = '400';
		}

		return $expanded;
	}

	/**
	 * Convert Icon.
	 *
	 * Creates an object from the expanded value of the icon attribute.
	 * Value will look something like: %%4%%.
	 * If value matches regex `^%*[0-9]*%*$`, the returned value will be {unicode: '', type: 'divi', weight: '400'}
	 * Unicode defaults to '', unless the value's integer is found in:
	 * ['&#x22;','&#x33;','&#x37;','&#x3b;','&#x3f;','&#x43;','&#x47;','&#xe03a;','&#xe044;','&#xe048;','&#xe04c;']
	 * Otherwise the object returned with contain any of unicode, type or weight keys provided they are in the value.
	 *
	 * @param string $value Expanded attribute value.
	 *
	 * @return array|bool The icon object value or false if no expansion was found.
	 */
	public static function convertIcon( $value ) {
		// Check if the value matches the regex pattern for isIconIndex
		// looking for: %%4%%.
		$is_icon_index = preg_match( '/^%*[0-9]*%*$/', $value );
		if ( $is_icon_index ) {
			$unicodes = [
				'&#x22;',
				'&#x33;',
				'&#x37;',
				'&#x3b;',
				'&#x3f;',
				'&#x43;',
				'&#x47;',
				'&#xe03a;',
				'&#xe044;',
				'&#xe048;',
				'&#xe04c;',
			];

			// Execute regex to extract the icon index value.
			// looking for: %%4%%.
			// https://regex101.com/r/QUv9Eh/1.
			preg_match( '/^%*([0-9]*)%*$/', $value, $matches );

			$icon_index = isset( $matches[1] ) && '' !== $matches[1] ? (int) $matches[1] : null;

			$icon = [
				'unicode' => null !== $icon_index ? $unicodes[ $icon_index ] : '',
				'type'    => 'divi',
				'weight'  => '400',
			];

			return $icon;
		}

		return self::convertFontIcon( $value );
	}

	/**
	 * Converts module inline font format.
	 *
	 * Converts D4 module `inline_font` attribute format to D5 format.
	 * In D4, `inline_font` is a string that contains comma separated values.
	 * This is converted to an array of strings in D5.
	 *
	 * @param string $value String of inline font values.
	 *
	 * @return string[] An array of inline font values.
	 */
	public static function convertInlineFont( $value ) {
		// Check if the value is a string.
		if ( is_string( $value ) ) {
			// Split the string by commas and return as an array.
			return explode( ',', $value );
		}
		// If the value is not a string, return an empty array.
		return [];
	}

	/**
	 * Convert D4 spacing attribute value to D5 format.
	 *
	 * This is used to parse the string passed as argument into D5 spacing format.
	 *
	 * @param string $value Shortcode attribute value for spacing.
	 *
	 * @return array|bool The expanded value or false if no expansion was found.
	 *
	 * @example
	 * ```php
	 * convertSpacing('5px|10px|15px|20px|false|false')
	 * // Returns following spacing object
	 * // [
	 * //   'top'            => '5px',
	 * //   'right'          => '10px',
	 * //   'bottom'         => '15px',
	 * //   'left'           => '20px',
	 * //   'syncVertical'   => 'off',
	 * //   'syncHorizontal' => 'off',
	 * // ]
	 * ```
	 *
	 * @example
	 * ```php
	 * convertSpacing('5px|10px|15px')
	 * // Returns following spacing object
	 * // [
	 * //   'top'            => '5px',
	 * //   'right'          => '10px',
	 * //   'bottom'         => '15px',
	 * //   'left'           => '',
	 * //   'syncVertical'   => 'off',
	 * //   'syncHorizontal' => 'off',
	 * // ]
	 * ```
	 */
	public static function convertSpacing( $value ) {
		$value_array = explode( '|', $value );

		$sync_vertical   = isset( $value_array[4] ) ? $value_array[4] : 'false';
		$sync_horizontal = isset( $value_array[5] ) ? $value_array[5] : 'false';

		$spacing = [
			'top'            => isset( $value_array[0] ) ? $value_array[0] : '',
			'right'          => isset( $value_array[1] ) ? $value_array[1] : '',
			'bottom'         => isset( $value_array[2] ) ? $value_array[2] : '',
			'left'           => isset( $value_array[3] ) ? $value_array[3] : '',
			'syncVertical'   => 'true' === $sync_vertical ? 'on' : 'off',
			'syncHorizontal' => 'true' === $sync_horizontal ? 'on' : 'off',
		];

		return $spacing;
	}

	/**
	 * Get included categories.
	 *
	 * @since ??
	 *
	 * @param string $categories The categories to include.
	 *
	 * @return string[] The included categories.
	 */
	public static function includedCategories( $categories ) {
		// Divi Taxonomies.
		// In VB, this is expressed as `const postCategories = select('divi/settings').getSetting(['postCategories']);`.
		$layout_taxonomies = TaxonomiesUtility::get_taxonomy_terms();
		$post_categories   = array_key_exists( 'category', $layout_taxonomies )
		? $layout_taxonomies['category']
		: (object) [];

		$categories_array = array_filter(
			explode( ',', $categories ),
			function ( $item ) {
				return '' !== $item;
			}
		);

		$filter_categories = array_map(
			function ( $item ) use ( $post_categories ) {
				if ( 'all' === $item || 'current' === $item ) {
					return $item;
				}
				foreach ( $post_categories as $category ) {
					if ( $category->term_id === (int) $item ) {
						return (int) $item;
					}
				}
			},
			$categories_array
		);

		$filter_categories = array_filter(
			$filter_categories,
			function ( $item ) {
				return null !== $item;
			}
		);

		return array_map( 'strval', $filter_categories );
	}

	/**
	 * Convert Include Categories Value.
	 *
	 * Converts D4 WooCommerce module `include_categories` attribute format to D5 format.
	 * In D4, `include_categories` is a string that contains comma separated category values.
	 * This is converted to an array of strings in D5, with validation against available
	 * WooCommerce product categories when possible.
	 *
	 * Handles special category values:
	 * - 'current': Include categories from current product
	 * - 'all': Include all categories
	 * - Numeric IDs: Validated against available WooCommerce product categories (product_cat taxonomy).
	 *
	 * @since ??
	 *
	 * @param string $value String of comma-separated category values from shortcode.
	 *
	 * @return string[] An array of validated category values.
	 *
	 * @example
	 * ```php
	 * convertIncludeCategoriesValue('current,17,21,22,34');
	 * // => ['current', '17', '21', '22', '34'] (if categories 17,21,22,34 exist)
	 *
	 * convertIncludeCategoriesValue('all,999');
	 * // => ['all', '999'] (in test environments where validation data unavailable)
	 *
	 * convertIncludeCategoriesValue('');
	 * // => []
	 * ```
	 */
	public static function convertIncludeCategoriesValue( $value ): array {
		// Type safety check: return empty array for non-string values.
		if ( ! is_string( $value ) ) {
			return [];
		}

		// Handle empty string.
		if ( empty( trim( $value ) ) ) {
			return [];
		}

		// Split by comma and process each item.
		$category_items = array_filter(
			array_map( 'trim', explode( ',', $value ) ),
			function ( $item ) {
				return ! empty( $item );
			}
		);

		// Get available WooCommerce product categories for validation (if available).
		// This uses the product_cat taxonomy as defined in the shortcode module definition.
		$product_categories  = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			]
		);
		$has_validation_data = is_array( $product_categories ) && ! is_wp_error( $product_categories ) && ! empty( $product_categories );

		// Individually process and validate each category item.
		$validated_categories = array_map(
			function ( $item ) use ( $product_categories, $has_validation_data ) {
				// Handle special meta_categories values (always valid).
				if ( 'all' === $item || 'current' === $item ) {
					return $item;
				}

				// For numeric category IDs, we will validate if validation data is available.
				$numeric_id = intval( $item );

				// Return null for invalid/non-numeric categories (will be filtered out).
				if ( 0 === $numeric_id && '0' !== $item ) {
					return null;
				}

				// If we have validation data, check against available categories.
				if ( $has_validation_data ) {
					$category_exists = false;
					foreach ( $product_categories as $category ) {
						if ( $category->term_id === $numeric_id ) {
							$category_exists = true;
							break;
						}
					}
					if ( $category_exists ) {
						return $item;
					}

					// Category doesn't exist, filter it out.
					return null;
				}

				// No validation data available (e.g., test environment), allow all numeric IDs.
				return $item;
			},
			$category_items
		);

		// Remove null values and return.
		return array_values(
			array_filter(
				$validated_categories,
				function ( $item ) {
					return null !== $item;
				}
			)
		);
	}

	/**
	 * Convert Include Tabs Value.
	 *
	 * Converts D4 WooCommerce Product Tabs module `include_tabs` attribute format to D5 format.
	 * In D4, `include_tabs` is a string that contains pipe separated tab identifiers.
	 * This is converted to an array of strings in D5.
	 *
	 * @since ??
	 *
	 * @param string $value String of pipe-separated tab identifiers from shortcode.
	 *
	 * @return string[] An array of tab identifiers.
	 *
	 * @example
	 * ```php
	 * convertIncludeTabsValue('description|reviews|additional_information');
	 * // => ['description', 'reviews', 'additional_information']
	 *
	 * convertIncludeTabsValue('description||reviews');
	 * // => ['description', 'reviews'] (empty strings filtered out)
	 *
	 * convertIncludeTabsValue('');
	 * // => []
	 * ```
	 */
	public static function convertIncludeTabsValue( $value ): array {
		// Return empty array for non-string values or empty strings.
		if ( ! is_string( $value ) || empty( trim( $value ) ) ) {
			return [];
		}

		// Split by pipe and filter out empty strings.
		$tab_items = array_filter(
			array_map( 'trim', explode( '|', $value ) ),
			function ( $item ) {
				return ! empty( $item );
			}
		);

		// Return array values (reset keys).
		return array_values( $tab_items );
	}

	/**
	 * Get included project categories.
	 *
	 * @since ??
	 *
	 * @param string $categories The categories to include.
	 *
	 * @return string[] The included categories.
	 */
	public static function includedProjectCategories( $categories ) {
		// Divi Taxonomies.
		// In VB, this is expressed as `const postCategories = select('divi/settings').getSetting(['projectCategories']);`.
		$layout_taxonomies = TaxonomiesUtility::get_taxonomy_terms();
		$post_categories   = array_key_exists( 'project_category', $layout_taxonomies )
		? $layout_taxonomies['project_category']
		: (object) [];

		$categories_array = array_filter(
			explode( ',', $categories ),
			function ( $item ) {
				return '' !== $item;
			}
		);

		$filter_categories = array_map(
			function ( $item ) use ( $post_categories ) {
				if ( 'all' === $item || 'current' === $item ) {
					return $item;
				}
				foreach ( $post_categories as $category ) {
					if ( $category->term_id === (int) $item ) {
						return (int) $item;
					}
				}
			},
			$categories_array
		);

		$filter_categories = array_filter(
			$filter_categories,
			function ( $item ) {
				return null !== $item;
			}
		);

		return array_map( 'strval', $filter_categories );
	}

	/**
	 * Replaces the line break placeholder in a string with the actual line break characters.
	 *
	 * Convert the line break placeholder used in the code module to actual line break characters
	 * during the conversion process. This is necessary because the line break placeholder is added in D4 during the
	 * saving process.
	 *
	 * @since ??
	 *
	 * @param string $string The string containing the line break placeholder.
	 *
	 * @return string The string with the line break placeholder replaced by line break characters.
	 */
	public static function replaceLineBreakPlaceholder( $string ) {
		$string = str_replace( '<!-- [et_pb_line_break_holder] -->', "\n", $string );
		$string = str_replace( '||et_pb_line_break_holder||', "\r\n", $string );
		return $string;
	}

	/**
	 * Process code field content during D4 to D5 conversion.
	 *
	 * This function handles:
	 * - URL decoding of encoded attribute values
	 * - Converting data-et-target-link back to target attribute
	 * - Replacing line break placeholders
	 *
	 * @since ??
	 *
	 * @param string $string The code field content from D4.
	 *
	 * @return string The processed code field content for D5.
	 */
	public static function convertCodeFieldContent( $string ) {
		// First decode URL-encoded values.
		$string = urldecode( $string );

		// Convert data-et-target-link back to target attribute.
		$string = str_replace( ' data-et-target-link=', ' target=', $string );

		// Replace line break placeholders.
		$string = str_replace( '<!-- [et_pb_line_break_holder] -->', "\n", $string );
		$string = str_replace( '||et_pb_line_break_holder||', "\r\n", $string );

		return $string;
	}

	/**
	 * This function parses the conversion string from D4 to `SortableList` fields value.
	 *
	 * @param string $value String conversion string from which we need to parse.
	 *
	 * @return array
	 */
	public static function sortableListConverter( $value ) {
		// Replace %91 with [, %93 with ].
		$options = str_replace( [ '%91', '%93', '%92', '%22' ], [ '[', ']', '\\', '"' ], $value );

		// Decode the URI component.
		$options = urldecode( $options );

		// Decode the JSON string into an associative array.
		$sortable_options = json_decode( $options, true );

		// Map over the array and adjust dragID, checked fields, and link structure.
		return array_map(
			function ( $option ) {
				$converted_option = array_merge(
					$option,
					[
						'dragID'  => isset( $option['dragID'] ) ? (string) $option['dragID'] : null,
						'checked' => isset( $option['checked'] ) ? (string) $option['checked'] : null,
					]
				);

				// Transform link_url/link_text to nested link object.
				if ( isset( $option['link_url'] ) && '' !== $option['link_url'] ) {
					$converted_option['link'] = [
						'url'  => $option['link_url'],
						'text' => isset( $option['link_text'] ) ? $option['link_text'] : '',
					];
					unset( $converted_option['link_url'] );
					unset( $converted_option['link_text'] );
				}

				return $converted_option;
			},
			$sortable_options
		);
	}

	/**
	 * Convert image and icon width.
	 *
	 * @since ??
	 *
	 * @param string $value Original value in D4.
	 *
	 * @return array Converted value.
	 */
	public static function convertImageAndIconWidth( string $value ): array {
		return [
			'image' => $value,
			'icon'  => $value,
		];
	}

	/**
	 * Convert true/false to on/off.
	 *
	 * @since ??
	 *
	 * @param string $value The input string value to be converted.
	 *
	 * @return string Converted value.
	 */
	public static function convertTrueFalseToOnOff( string $value ): string {
		return 'true' === $value ? 'on' : 'off';
	}

	/**
	 * Convert success redirect query attribute value to an array of strings.
	 *
	 * @since ??
	 *
	 * @param string $value Original value in D4.
	 *
	 * @return array An array of strings that satisfy the conversion condition.
	 */
	public static function convertSuccessRedirectQuery( string $value ): array {
		$converted   = [];
		$value_pair  = [ 'name', 'last_name', 'email', 'ip_address', 'css_id' ];
		$value_array = explode( '|', $value );

		if ( count( $value_array ) === count( $value_pair ) ) {
			foreach ( $value_array as $index => $item ) {
				if ( 'on' === $item ) {
					$converted[] = $value_pair[ $index ];
				}
			}
		}

		return $converted;
	}

	/**
	 * Converts an email provider account value.
	 *
	 * @since ??
	 *
	 * @param string $value The value to be converted.
	 * @param array  $extra_params {
	 *   An array of arguments.
	 *
	 *   @type array  $attrs       The module attributes.
	 *   @type string $desktopName The desktop name.
	 * }
	 *
	 * @return string|WP_Error The converted value or an error if the conversion fails.
	 */
	public static function convertEmailServiceAccount( string $value, array $extra_params ) {
		$attrs        = $extra_params['attrs'] ?? [];
		$desktop_name = $extra_params['desktopName'] ?? '';

		$provider = $attrs['provider'] ?? '';

		if ( ! $provider ) {
			$provider = 'mailchimp';
		}

		$allowed_names = [
			$provider . '_list',
			$provider . '_account_name',
		];

		if ( ! in_array( $desktop_name, $allowed_names, true ) ) {
			return new WP_Error( 'invalid_desktop_name', "The attribute name $desktop_name did match with selected provider $provider." );
		}

		return $value;
	}

	/**
	 * Convert legacy gradient property.
	 *
	 * @param string $value The value to be converted.
	 *
	 * @since ??
	 */
	public static function convertLegacyGradientProperty( string $value ): string {
		return strval( intval( $value ) );
	}

	/**
	 * Converts spam provider account from pipe-separated format.
	 *
	 * Converts D4's pipe-separated format "Account|Account-0" to just "Account" for D5.
	 * D4 stores both display name and internal ID separated by a pipe, but D5 only
	 * needs the display name portion.
	 *
	 * @since ??
	 *
	 * @param string $value The pipe-separated account value from D4.
	 *
	 * @return string The account name (part before the pipe) or original value if no pipe found.
	 */
	public static function convertSpamProviderAccount( string $value ): string {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$parts = explode( '|', $value );
		return $parts[0] ?? $value;
	}

	/**
	 * Convert D4 text_color to D5 text.text.*.color value for Post Title modules.
	 *
	 * D4 and D5 use OPPOSITE semantics for text color in Post Title modules:
	 *
	 * D4 PostTitle Architecture:
	 * - Stores: text_color="light" (text IS white)
	 * - Derives in render code: background_layout="dark" (inverted from text_color)
	 * - Output class: et_pb_bg_layout_dark (renders white text)
	 *
	 * D5 PostTitle Architecture:
	 * - Stores: text.text.*.color="dark" (background context IS dark)
	 * - Output class: et_pb_bg_layout_dark (renders white text)
	 *
	 * Key Difference:
	 * - D4 text_color describes the TEXT color itself ("light" = white text)
	 * - D5 color describes the BACKGROUND context ("dark" = dark background)
	 * - D4 derives background_layout by INVERTING text_color in PHP render code
	 * - D5 uses the color value DIRECTLY for the background_layout class
	 *
	 * Conversion Logic:
	 * To maintain visual appearance during migration:
	 * - D4 text_color="light" (white text) → D5 color="dark" (dark bg → white text)
	 * - D4 text_color="dark" (black text) → D5 color="light" (light bg → black text)
	 * - Invalid/empty values → Returns original value (preserves data integrity)
	 *
	 * Example Migration:
	 * D4 Shortcode: text_color="light"
	 * D4 Render: background_layout="dark" (inverted) → et_pb_bg_layout_dark (white text)
	 * D5 Storage: color="dark" (inverted by this function)
	 * D5 Render: et_pb_bg_layout_dark (white text) ✓
	 *
	 * @since ??
	 *
	 * @param string $value Original D4 text_color value ("light" or "dark").
	 *
	 * @return string Inverted D5 value for text.text.*.color, or original value if invalid.
	 */
	public static function convertTextColorValue( string $value ): string {
		$conversion_map = [
			'light' => 'dark',
			'dark'  => 'light',
		];

		// Invert valid D4 values, preserve invalid ones for data integrity.
		return $conversion_map[ $value ] ?? $value;
	}

	/**
	 * Convert Toggle title_text_color only when closed_toggle_text_color is absent.
	 *
	 * @since ??
	 *
	 * @param string $value Original D4 title_text_color value.
	 * @param array  $extra_params {
	 *   Additional conversion context.
	 *
	 *   @type array $attrs Raw D4 shortcode attributes.
	 * }
	 *
	 * @return string|WP_Error Converted value or WP_Error when conversion should be skipped.
	 */
	public static function convertToggleTitleTextColor( string $value, array $extra_params ) {
		$attrs                    = $extra_params['attrs'] ?? [];
		$closed_toggle_text_color = $attrs['closed_toggle_text_color'] ?? '';

		if ( is_string( $closed_toggle_text_color ) && '' !== trim( $closed_toggle_text_color ) ) {
			return new WP_Error( 'skip_title_text_color_mapping', 'closed_toggle_text_color already exists.' );
		}

		return $value;
	}

	/**
	 * Convert conditional logic rules from D4 URL-encoded JSON string to array.
	 *
	 * @since ??
	 *
	 * @param string $value URL-encoded JSON string from D4 conditional logic rules.
	 *
	 * @return array Array of conditional logic rule objects.
	 */
	public static function conditionalLogicConverter( string $value ): array {
		// Replace %91 with [, %93 with ], %22 with ".
		$decoded_value = str_replace( [ '%91', '%93', '%22' ], [ '[', ']', '"' ], $value );

		// Decode URI component.
		$decoded_value = urldecode( $decoded_value );

		// Decode JSON string into associative array.
		$conditional_rules = json_decode( $decoded_value, true );

		// Return empty array if JSON decode failed.
		if ( null === $conditional_rules || ! is_array( $conditional_rules ) ) {
			return [];
		}

		return $conditional_rules;
	}
}
