<?php
/**
 * Global Data: GlobalData Class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\GlobalData;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET_Core_PageResource;

/**
 * Handles the Global Data.
 *
 * @since ??
 */
class GlobalData {
	/**
	 * The cached global data.
	 *
	 * @since ??
	 *
	 * @var array|null
	 */
	private static $_cached_data = [
		'colors'    => null,
		'variables' => null,
	];


	/**
	 * Mapping of accent color types to global color IDs.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private static $_accent_color_map = [
		'primary'   => 'gcid-primary-color',
		'secondary' => 'gcid-secondary-color',
		'heading'   => 'gcid-heading-color',
		'body'      => 'gcid-body-color',
		'link'      => 'gcid-link-color',
	];

	/**
	 * List of customizer colors.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	public static $customizer_colors = [
		'gcid-primary-color'   => [
			'label'       => 'Primary Color',
			'option_name' => 'accent_color',
			'default'     => '#2ea3f2',
		],
		'gcid-secondary-color' => [
			'label'       => 'Secondary Color',
			'option_name' => 'secondary_accent_color',
			'default'     => '#2ea3f2',
		],
		'gcid-heading-color'   => [
			'label'       => 'Heading Text Color',
			'option_name' => 'header_color',
			'default'     => '#666666',
		],
		'gcid-body-color'      => [
			'label'       => 'Body Text Color',
			'option_name' => 'font_color',
			'default'     => '#666666',
		],
		'gcid-link-color'      => [
			'label'       => 'Link Color',
			'option_name' => 'link_color',
			'default'     => '#2ea3f2',
		],
	];

	/**
	 * List of customizer fonts.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	public static $customizer_fonts = [
		'--et_global_heading_font' => [
			'label'       => 'Heading',
			'option_name' => 'heading_font',
			'default'     => 'Open Sans',
		],
		'--et_global_body_font'    => [
			'label'       => 'Body',
			'option_name' => 'body_font',
			'default'     => 'Open Sans',
		],
	];

	/**
	 * Converts the global colors data array into a format compatible with the Divi 5.
	 *
	 * @since ??
	 *
	 * @param array  $data              The global colors data array.
	 * @param string $non_active_status One of: active | inactive | temporary, to be set when `active` is `no` or not defined.
	 *
	 * @return array[] {
	 *     The converted Global Colors array.
	 *
	 *     @type int      $id          The global ID.
	 *     @type string   $color       Global color value
	 *     @type string   $status      Global color status: active | inactive | temporary,
	 *     @type string   $lastUpdated Last updated datetime.
	 *     @type string[] $usedInPosts Array of Post ID where the color has been used.
	 * }
	 *
	 * @example:
	 * ```php
	 * GlobalData::convert_global_colors_data([
	 *   'gcid-8ce98ce1-4460-49e4-9cd7-b148b47c216c' => [
	 *     'color'  => '#fcf6f0',
	 *     'active' => 'yes',
	 *   ],
	 *   'gcid-27d27316-00ff-460e-9cc1-5df31af25225' => [
	 *      'color'  => '#f0f0f0',
	 *      'active' => 'no',
	 *    ],
	 * ]);
	 * ```
	 */
	public static function convert_global_colors_data( array $data, string $non_active_status = 'inactive' ): array {
		if ( empty( $data ) ) {
			return [];
		}

		// Validate $non_active_status.
		if ( ! in_array( $non_active_status, [ 'active', 'inactive' ], true ) ) {
			$non_active_status = 'inactive';
		}

		$converted_data = [];

		foreach ( $data as $key => $value ) {
			// Convert only when `color` value and `active` status is set, and id starts with `gcid-`.
			if (
				! empty( $value['color'] ) &&
				isset( $value['active'] ) &&
				substr( $key, 0, 5 ) === 'gcid-'
			) {
				$converted_data[ sanitize_text_field( $key ) ] = [
					'color'       => sanitize_text_field( $value['color'] ),
					'folder'      => '', // <-- not until D6
					'label'       => '', // <-- not until D6
					'lastUpdated' => wp_date( 'Y-m-d\TH:i:s.v\Z' ),
					'status'      => sanitize_text_field( $value['active'] ?? '' ) === 'yes' ? 'active' : $non_active_status,
					'usedInPosts' => [],
				];
			}
		}

		return $converted_data;
	}

	/**
	 * Transform the state into a global color value's format with HSL adjustments.
	 *
	 * @since ??
	 *
	 * @param string $css_variable The CSS variable to transform.
	 * @param array  $settings     The color settings containing hue, saturation, lightness, and opacity.
	 *
	 * @return string The transformed color value.
	 */
	public static function transform_state_into_global_color_value( $css_variable, $settings ) {
		// If no settings is found, return the original CSS variable.
		if ( empty( $settings ) ) {
			return $css_variable;
		}

		$hue                  = $settings['hue'] ?? 0;
		$saturation           = $settings['saturation'] ?? 0;
		$lightness            = $settings['lightness'] ?? 0;
		$opacity              = $settings['opacity'] ?? null;
		$has_explicit_opacity = is_array( $settings ) && array_key_exists( 'opacity', $settings );

		$printed_opacity = null !== $opacity && ( 100 !== $opacity || $has_explicit_opacity ) ? ' / ' . ( $opacity / 100 ) : '';

		// Build a single relative-HSL component (h/s/l) with a normalized +/- operator.
		$format_component = function ( $base, $adjustment ) {
			$operator = $adjustment >= 0 ? ' + ' : ' - ';
			return "calc({$base}{$operator}" . abs( $adjustment ) . ')';
		};

		$format_saturation_component = function ( $adjustment ) use ( $format_component ) {
			$component = $format_component( 's', $adjustment );
			// Clamp negative saturation math so emitted relative colors never go below valid `s = 0`.
			return $adjustment < 0 ? "max(0, {$component})" : $component;
		};

		// Preserve explicit 100% opacity override by printing `/ 1`.
		if (
			0 === (int) $hue &&
			0 === (int) $saturation &&
			0 === (int) $lightness &&
			( null === $opacity || ( 100 === (int) $opacity && ! $has_explicit_opacity ) )
		) {
			return $css_variable;
		}

		$h_component = $format_component( 'h', $hue );
		$s_component = $format_saturation_component( $saturation );
		$l_component = $format_component( 'l', $lightness );

		return "hsl(from {$css_variable} {$h_component} {$s_component} {$l_component}{$printed_opacity})";
	}

	/**
	 * Recursively collect all global color dependencies for nested global colors.
	 *
	 * This function ensures that when a nested global color is used, all its base
	 * global colors are also included in the CSS output to avoid missing variable declarations.
	 *
	 * @since ??
	 *
	 * @param array $global_color_ids Array of global color IDs to collect dependencies for.
	 *
	 * @return array Array of global color IDs including all dependencies.
	 */
	public static function collect_global_color_dependencies( array $global_color_ids ): array {
		if ( empty( $global_color_ids ) ) {
			return [];
		}

		$all_dependencies = $global_color_ids;
		$global_colors    = self::get_global_colors();
		$processed        = [];

		// Process each global color ID to find dependencies.
		foreach ( $global_color_ids as $color_id ) {
			$dependencies     = self::_get_global_color_dependencies_recursive( $color_id, $global_colors, $processed );
			$all_dependencies = array_merge( $all_dependencies, $dependencies );
		}

		// Return unique global color IDs.
		return array_values( array_unique( $all_dependencies ) );
	}

	/**
	 * Recursively get dependencies for a single global color.
	 *
	 * @since ??
	 *
	 * @param string $color_id The global color ID to check for dependencies.
	 * @param array  $global_colors Array of all global colors data.
	 * @param array  $processed Array to track processed color IDs to prevent infinite loops.
	 *
	 * @return array Array of dependent global color IDs.
	 */
	private static function _get_global_color_dependencies_recursive( string $color_id, array $global_colors, array &$processed ): array {
		// Prevent infinite loops by tracking processed color IDs.
		if ( in_array( $color_id, $processed, true ) ) {
			return [];
		}

		$processed[]  = $color_id;
		$dependencies = [];

		// Check if this global color exists.
		if ( ! isset( $global_colors[ $color_id ] ) ) {
			return [];
		}

		$global_color = $global_colors[ $color_id ];
		$color_value  = $global_color['color'] ?? '';

		// Check if the color value contains a reference to another global color.
		if ( ! empty( $color_value ) && is_string( $color_value ) ) {
			// Check for $variable syntax which indicates a nested global color.
			if ( str_contains( $color_value, '$variable(' ) ) {
				// Parse the $variable syntax to extract the dependency.
				if ( preg_match( '/\$variable\((.+)\)\$/', $color_value, $matches ) ) {
					$decoded       = json_decode( $matches[1], true );
					$dependency_id = $decoded['value']['name'] ?? null;

					if ( $dependency_id ) {
						// Add the dependency.
						$dependencies[] = $dependency_id;

						// Recursively check the dependency for more dependencies.
						$nested_dependencies = self::_get_global_color_dependencies_recursive(
							$dependency_id,
							$global_colors,
							$processed
						);
						$dependencies        = array_merge( $dependencies, $nested_dependencies );
					}
				}
			}

			// Also check for direct CSS variable references like var(--gcid-xyz).
			if ( preg_match_all( '/var\(--([^)]+)\)/', $color_value, $matches ) ) {
				foreach ( $matches[1] as $dependency_id ) {
					// Only process if it's a global color ID.
					if ( str_starts_with( $dependency_id, 'gcid-' ) ) {
						$dependencies[] = $dependency_id;

						// Recursively check this dependency.
						$nested_dependencies = self::_get_global_color_dependencies_recursive(
							$dependency_id,
							$global_colors,
							$processed
						);
						$dependencies        = array_merge( $dependencies, $nested_dependencies );
					}
				}
			}
		}

		return array_unique( $dependencies );
	}

	/**
	 * Retrieves the global colors from the global data option.
	 *
	 * @since ??
	 *
	 * @return array[] {
	 *     The list of Global Colors data.
	 *
	 *     @type int      $id          The global ID.
	 *     @type string   $color       Global color value
	 *     @type string   $status      Global color status: active | inactive | temporary,
	 *     @type string   $lastUpdated Last updated datetime.
	 *     @type string[] $usedInPosts Array of Post ID where the color has been used.
	 * }
	 *
	 * @example:
	 * ```php
	 * GlobalData::get_global_colors();
	 * ```
	 */
	public static function get_global_colors(): array {
		if ( null === self::$_cached_data['colors'] ) {
			$global_data = maybe_unserialize( et_get_option( 'et_global_data' ) );

			$global_colors_full = $global_data['global_colors'] ?? [];

			// Remove customizer global colors if exist for any reason.
			// For example if user imported global colors on old version of Divi which doesn't support customizer colors.
			foreach ( self::$customizer_colors as $color_id => $color_data ) {
				unset( $global_colors_full[ $color_id ] );
			}

			// Add fresh customizer colors.
			self::$_cached_data['colors'] = array_merge(
				self::get_customizer_colors(),
				$global_colors_full
			);
		}

		return self::$_cached_data['colors'];
	}

	/**
	 * Generate the list of Customizer Global Colors.
	 *
	 * @since ??
	 *
	 * @return array[] {
	 *     The list of Global Colors data.
	 *
	 *     @type int      $id          The global ID.
	 *     @type string   $color       Global color value
	 *     @type string   $status      Global color status: active | inactive | temporary,
	 *     @type string   $lastUpdated Last updated datetime.
	 *     @type string[] $usedInPosts Array of Post ID where the color has been used.
	 * }
	 */
	public static function get_customizer_colors(): array {
		static $formatted_colors = null;

		if ( null !== $formatted_colors ) {
			return $formatted_colors;
		}

		$formatted_colors    = [];
		$global_color_labels = [
			'Primary Color'      => esc_html__( 'Primary Color', 'et_builder_5' ),
			'Secondary Color'    => esc_html__( 'Secondary Color', 'et_builder_5' ),
			'Heading Text Color' => esc_html__( 'Heading Text Color', 'et_builder_5' ),
			'Body Text Color'    => esc_html__( 'Body Text Color', 'et_builder_5' ),
			'Link Color'         => esc_html__( 'Link Color', 'et_builder_5' ),
		];

		foreach ( self::$customizer_colors as $color_id => $color_data ) {
			$formatted_colors[ $color_id ] = [
				'color'       => sanitize_text_field( et_get_option( $color_data['option_name'], $color_data['default'] ) ),
				'folder'      => 'customizer',
				'label'       => $global_color_labels[ $color_data['label'] ],
				'lastUpdated' => wp_date( 'Y-m-d\TH:i:s.v\Z' ),
				'status'      => 'active',
				'usedInPosts' => [],
			];
		}

		return $formatted_colors;
	}

	/**
	 * Generate the list of Customizer Global Fonts.
	 *
	 * @since ??
	 *
	 * @return array {
	 *     The list of Global Fonts data.
	 *
	 *     @type string $id     The global font ID.
	 *     @type string $label  The label of the global font.
	 *     @type string $value  The value of the global font.
	 *     @type string $status The status of the global font: active | inactive.
	 *     @type int    $order  The order of the global font.
	 * }
	 */
	public static function get_customizer_fonts(): array {
		static $formatted_fonts = null;

		if ( null !== $formatted_fonts ) {
			return $formatted_fonts;
		}

		$formatted_fonts    = [];
		$global_font_labels = [
			'Heading' => esc_html__( 'Heading', 'et_builder_5' ),
			'Body'    => esc_html__( 'Body', 'et_builder_5' ),
		];

		foreach ( self::$customizer_fonts as $font_id => $font_data ) {
			$formatted_fonts[ $font_id ] = [
				'id'     => $font_id,
				'label'  => $global_font_labels[ $font_data['label'] ],
				'value'  => et_get_option( $font_data['option_name'], $font_data['default'] ),
				'status' => 'active',
				'order'  => '--et_global_heading_font' === $font_id ? 1 : 2,
			];
		}

		return $formatted_fonts;
	}

	/**
	 * Returns the global colors from the incoming data after converting it into Divi 5 data format.
	 *
	 * This helper function used to prepare global colors data to be imported by converting data format that being
	 * imported.
	 *
	 * @since ??
	 *
	 * @param array $incoming_data The incoming data with global colors.
	 *
	 * @return array[] {
	 *     The global colors array
	 *
	 *     @type int $id The global ID.
	 *     @type string $color Global color value
	 *     @type string $status Global color status: active | inactive | temporary,
	 *     @type string $lastUpdated Last updated datetime.
	 *     @type string[] $usedInPosts Array of Post ID where the color has been used.
	 * }.
	 */
	public static function get_imported_global_colors( array $incoming_data ): array {
		$global_colors = [];

		// Sanity check.
		if ( empty( $incoming_data ) ) {
			return $global_colors;
		}

		foreach ( $incoming_data as $data ) {
			// Global ID field.
			$key = sanitize_text_field( $data[0] );

			// Ensure $data[1] is an array before accessing its keys.
			if ( ! is_array( $data[1] ) ) {
				continue;
			}

			// Check for D4 or D5 formatted data, and prepare $global_colors accordingly.
			if (
				isset( $data[1]['active'] ) &&
				! empty( $data[1]['color'] ) &&
				substr( $key, 0, 5 ) === 'gcid-'
			) {
				$global_colors[ $key ] = [
					'color'       => sanitize_text_field( $data[1]['color'] ),
					'folder'      => '', // <-- not until D6
					'label'       => isset( $data[1]['label'] ) ? sanitize_text_field( $data[1]['label'] ) : '',
					'lastUpdated' => wp_date( 'Y-m-d\TH:i:s.v\Z' ),
					'status'      => sanitize_text_field( $data[1]['active'] ) === 'yes' ? 'active' : 'inactive',
					'usedInPosts' => [],
				];
			} elseif (
				isset( $data[1]['status'] ) &&
				! empty( $data[1]['color'] ) &&
				substr( $key, 0, 5 ) === 'gcid-'
			) {
				$global_colors[ $key ] = [
					'color'       => sanitize_text_field( $data[1]['color'] ),
					'folder'      => '', // <-- not until D6
					'label'       => isset( $data[1]['label'] ) ? sanitize_text_field( $data[1]['label'] ) : '',
					'lastUpdated' => wp_date( 'Y-m-d\TH:i:s.v\Z' ),
					'status'      => sanitize_text_field( $data[1]['status'] ) === 'active' ? 'active' : 'inactive',
					'usedInPosts' => [],
				];
			}
		}

		return $global_colors;
	}

	/**
	 * Maybe converts the global colors data.
	 *
	 * If the et_global_data option is not set, this method retrieves the et_global_colors option,
	 * sanitizes the values, and creates a new et_global_data option with the converted data.
	 *
	 * @since ??
	 *
	 * @return void
	 *
	 * @example:
	 * ```php
	 * GlobalData::maybe_convert_global_colors_data();
	 * ```
	 */
	public static function maybe_convert_global_colors_data() {
		// Reset the cached data.
		self::$_cached_data['colors'] = null;

		// Get Global Data options.
		$global_data = et_get_option( 'et_global_data', false );

		// When $global_data not found, convert and save global colors data from D4 option.
		if ( false === $global_data ) {
			// Get old global colors data.
			$d4_data = maybe_unserialize( et_get_option( 'et_global_colors', false ) );

			$global_data = [
				'global_colors' => false === $d4_data
					? []
					: self::convert_global_colors_data( $d4_data ),
			];

			// Add et_global_data option.
			et_update_option( 'et_global_data', $global_data );

			// We need to clear the entire website cache when updating a global color.
			// VB CSS is prserved, this way Theme Customizer CSS is not cleared after it cannot be regenerated.
			ET_Core_PageResource::remove_static_resources( 'all', 'all', true, 'all', true );
		}
	}

	/**
	 * Sanitizes global colors data.
	 *
	 * Sanitizes the provided global colors data by removing invalid or empty values. The function
	 * applies sanitization to each key and value, ensuring they are safe for further processing.
	 *
	 * @since ??
	 *
	 * @param array $data The global colors data to sanitize.
	 *
	 * @return array The sanitized global colors data.
	 *
	 * @example:
	 *  ```php
	 * $global_colors_data = [
	 *     'gcid-98eb727ac3' => [
	 *         'color'       => 'red',
	 *         'lastUpdated' => '2024-01-01T00:00:00.000Z',
	 *         'status'      => 'active',
	 *         'usedInPosts' => [ 123, 345 ],
	 *     ],
	 *    'gcid-3cf7c930-5f16-4c4e-9613-90a9edb8c65a' => [
	 *          'color'       => '#f0f0f0',
	 *          'lastUpdated' => '2024-01-02T00:00:00.000Z',
	 *          'status'      => 'inactive',
	 *          'usedInPosts' => [],
	 *      ],
	 *  ];
	 *
	 * GlobalData::sanitize_global_colors_data( $global_colors_data );
	 * ```
	 */
	public static function sanitize_global_colors_data( array $data ): array {
		// Sanity check, global colors should not be empty.
		if ( empty( $data ) ) {
			return [];
		}

		// By default, the sanitized values is an empty array.
		$sanitized_data = [];

		foreach ( $data as $id => $item_data ) {
			// Drop bad data.
			if (
				'undefined' === $id ||
				empty( $item_data ) ||
				empty( $item_data['color'] ) ||
				substr( $id, 0, 5 ) !== 'gcid-'
			) {
				continue;
			}

			// Sanitize data_id (e.g: 373c75a2-4440-44da-8d3f-57b75310d4c7 ).
			$global_id = sanitize_text_field( $id );

			foreach ( $item_data as $param_key => $param_value ) {
				// Sanitize key and value ('usedInPosts' has array value).
				$sanitized_data[ $global_id ][ sanitize_text_field( $param_key ) ] = 'usedInPosts' === $param_key
					? array_map( 'sanitize_text_field', $param_value )
					: sanitize_text_field( $param_value );
			}
		}

		return $sanitized_data;
	}

	/**
	 * Sets the global colors for the Divi.
	 *
	 * This method takes an array of color data and stores it in the global data settings.
	 * The color data should be in a specific format and will be sanitized before storing.
	 *
	 * @param array $data              The array of global color data to set.
	 * @param array $already_sanitized Whether the data is sanitized or not.
	 *
	 * @return void
	 *
	 * @example:
	 *   ```php
	 *  $global_colors_data = [
	 *      'gcid-98eb727a-9088-4709-8ec8-2fee0213c5c3' => [
	 *          'color'       => 'red',
	 *          'lastUpdated' => '2024-01-01T00:00:00.000Z',
	 *          'status'      => 'active',
	 *          'usedInPosts' => [ 123, 345 ],
	 *      ],
	 *     'gcid-3cf7c9305a' => [
	 *           'color'       => '#f0f0f0',
	 *           'lastUpdated' => '2024-01-02T00:00:00.000Z',
	 *           'status'      => 'inactive',
	 *           'usedInPosts' => [],
	 *       ],
	 *   ];
	 *
	 *  GlobalData::set_global_colors( $global_colors_data );
	 * ```
	 */
	public static function set_global_colors( array $data, $already_sanitized = false ): void {
		// Reset the cached data.
		self::$_cached_data['colors'] = null;

		// Get the global_data from the option.
		$global_data = maybe_unserialize( et_get_option( 'et_global_data' ) );

		if ( ! is_array( $global_data ) ) {
			$global_data = [];
		}

		if ( ! $already_sanitized ) {
			$data = self::sanitize_global_colors_data( $data );
		}

		// Update Customizer colors which are part of Global Colors payload.
		// Only update customizer colors if they're present in the data (they're filtered out in PortabilityPost
		// when not importing in variable context).
		foreach ( self::$customizer_colors as $color_id => $color_data ) {
			if ( isset( $data[ $color_id ] ) && isset( $data[ $color_id ]['color'] ) ) {
				et_update_option( $color_data['option_name'], $data[ $color_id ]['color'] );
			}

			// Remove Customizer color from Global Colors as it stored in different place.
			unset( $data[ $color_id ] );
		}

		// When importing (already_sanitized=true), merge with existing colors instead of replacing them.
		// This ensures that imported colors are added/updated while preserving existing colors.
		if ( $already_sanitized && ! empty( $global_data['global_colors'] ) && is_array( $global_data['global_colors'] ) ) {
			$global_data['global_colors'] = array_merge( $global_data['global_colors'], $data );
		} else {
			// Set `global_colors` on the $global_data (replace all).
			$global_data['global_colors'] = $data;
		}

		// Update the option.
		et_update_option( 'et_global_data', $global_data );

		// We need to clear the entire website cache when updating a global color.
		ET_Core_PageResource::remove_static_resources( 'all', 'all', true );
	}

	/**
	 * Retrieves the global color by its ID.
	 *
	 * @param string $global_color_id The ID of the global color.
	 * @return array The global color data.
	 */
	public static function get_global_color_by_id( string $global_color_id ): array {
		$data = self::get_global_colors();

		return $data[ $global_color_id ] ?? [];
	}

	/**
	 * Gets the global color id from a CSS variable color value.
	 *
	 * If the value is a valid CSS variable color value e.g var(--gcid-2d8c4bca77), this function will return the
	 * global color id (gcid-2d8c4bca77) from the variable name. If the value is not a valid CSS variable
	 * color value with the correct global color ID pattern (`gcid-{uuid}`), this function will return `null`.
	 * This is equivalent to the JS function {@link /api/js/divi-global-data/functions/getGlobalColorIdFromValue getGlobalColorIdFromValue}
	 *
	 * @since ??
	 *
	 * @param string $value The CSS variable color value.
	 *
	 * @return string|null The global color ID if the value is a CSS variable color value, otherwise `null`.
	 *
	 * @example
	 *
	 * Given a CSS variable color value with a short global color id:
	 *
	 * ```php
	 * GlobalData::get_global_color_id_from_value('var(--gcid-2d8c4bca77)');
	 * // 'gcid-2d8c4bca77'
	 * ```
	 *
	 * @example
	 *
	 * Given a CSS variable color value with a UUIDv4 global color id:
	 *
	 * ```php
	 * GlobalData::get_global_color_id_from_value('var(--gcid-79d0acb1-9057-46e2-b40f-979d24efd874)');
	 * // 'gcid-79d0acb1-9057-46e2-b40f-979d24efd874'
	 * ```
	 *
	 * @example
	 *
	 * Given a CSS variable color value with color id and a fallback value:
	 *
	 * ```php
	 * GlobalData::get_global_color_id_from_value('var(--gcid-4bca772d8c, #ff0000)');
	 * // 'gcid-4bca772d8c'
	 * ```
	 *
	 * @example
	 *
	 * Given no CSS variable, but only a color value:
	 *
	 * ```php
	 * GlobalData::get_global_color_id_from_value('#0ff000');
	 * // null
	 * ```
	 */
	public static function get_global_color_id_from_value( string $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		// see https://regex101.com/r/WbG74r/2.
		$global_color_id_pattern = '/--gcid-([0-9a-z-]*)/';

		preg_match( $global_color_id_pattern, $value, $global_color_id );

		return ! empty( $global_color_id[1] ?? '' ) ? 'gcid-' . $global_color_id[1] : null;
	}

	/**
	 * Sanitizes global variables data.
	 *
	 * Sanitizes the provided global variables data by removing invalid or empty values. The function
	 * applies sanitization to each key and value, ensuring they are safe for further processing.
	 *
	 * @since ??
	 *
	 * @param array $data The global variables data to sanitize.
	 *
	 * @return array The sanitized global variables data.
	 *
	 * @example:
	 *  ```php
	 * $global_variables_data = [
	 *     'numbers' => [
	 *         'gvid-98eb727ac3' => [
	 *             'label'  => 'Rounder Corners',
	 *             'value'  => '12px',
	 *             'order'  => 1,
	 *             'status' => 'active',
	 *         ],
	 *     ],
	 *     'strings' => [
	 *         'gvid-3cf7c930-5f16-4c4e-9613-90a9edb8c65a' => [
	 *             'label'  => 'Font Size',
	 *             'value'  => '16px',
	 *             'order'  => 2,
	 *             'status' => 'active',
	 *         ],
	 *     ],
	 * ];
	 *
	 * GlobalData::sanitize_global_variables_data( $global_variables_data );
	 * ```
	 */
	public static function sanitize_global_variables_data( array $data ): array {
		if ( empty( $data ) ) {
			return [];
		}

		$sanitized_data = [];

		foreach ( $data as $type => $items ) {
			// Ensure the type is valid and contains items.
			if ( ! in_array( $type, [ 'numbers', 'strings', 'images', 'links', 'fonts', 'gradients' ], true ) || empty( $items ) ) {
				continue;
			}

			foreach ( $items as $id => $item_data ) {
				// Drop bad data.
				if (
					'undefined' === $id ||
					empty( $item_data ) ||
					empty( $item_data['label'] ) ||
					substr( $id, 0, 5 ) !== 'gvid-'
				) {
					continue;
				}

				// Sanitize data_id (e.g: 373c75a2-8d3f-57b75310d4c7).
				$global_id = sanitize_text_field( $id );

				foreach ( $item_data as $param_key => $param_value ) {
					if ( 'allowedActions' === $param_key ) {
						continue;
					}

					if ( in_array( $type, [ 'strings', 'gradients' ], true ) && 'value' === $param_key ) {
						// Keep string and gradient variable values character-identical while still removing invalid UTF-8 and null bytes.
						$string_value = is_scalar( $param_value ) ? wp_check_invalid_utf8( (string) $param_value ) : '';
						$sanitized_data[ $type ][ $global_id ][ sanitize_text_field( $param_key ) ] = wp_kses_no_null( $string_value );
					} elseif ( 'links' === $type && 'value' === $param_key ) {
						// Sanitize URL value while preserving URL encoding.
						$sanitized_data[ $type ][ $global_id ][ sanitize_text_field( $param_key ) ] = esc_url_raw( $param_value );
					} else {
						$sanitized_data[ $type ][ $global_id ][ sanitize_text_field( $param_key ) ] = sanitize_text_field( $param_value );
					}
				}
			}
		}

		return $sanitized_data;
	}

	/**
	 * Retrieves the global variables from the global data option.
	 *
	 * @since ??
	 *
	 * @return array {
	 *     The list of Global Variables data.
	 *
	 *     @type array $numbers {
	 *         @type int    $id     The global variable ID.
	 *         @type string $label  The label of the global variable.
	 *         @type string $value  The value of the global variable.
	 *         @type int    $order  The order of the global variable.
	 *         @type string $status The status of the global variable: active | archived.
	 *         @type array  $allowedActions {
	 *           @type bool $editLabel Whether the label can be edited.
	 *           @type bool $editValue Whether the value can be edited.
	 *           @type bool $reorder   Whether the variable can be reordered.
	 *           @type bool $remove    Whether the variable can be removed.
	 *         }
	 *     }
	 *     @type array $strings {
	 *         ...
	 *     }
	 *     @type array $images {
	 *         ...
	 *     }
	 *     @type array $links {
	 *         ...
	 *     }
	 *     @type array $colors {
	 *         ...
	 *     }
	 *     @type array $fonts {
	 *         ...
	 *     }
	 * }
	 *
	 * @example:
	 * ```php
	 * GlobalData::get_global_variables();
	 * ```
	 */
	public static function get_global_variables(): array {
		if ( null === self::$_cached_data['variables'] ) {
			$global_variables = maybe_unserialize(
				et_get_option( 'global_variables', [], '', true, false, '', '', true )
			);

			// Ensure $global_variables is an array.
			$global_variables = is_array( $global_variables ) ? $global_variables : [];

			$default_global_variables = [
				'numbers'   => [],
				'strings'   => [],
				'images'    => [],
				'links'     => [],
				'colors'    => [],
				'fonts'     => [],
				'gradients' => [],
			];

			$global_variables_full = array_merge( $default_global_variables, $global_variables );

			// Remove customizer fonts if they exist in global_variables for any reason.
			// For example if user imported global variables on old version of Divi which doesn't support customizer fonts.
			if ( is_array( $global_variables_full['fonts'] ) ) {
				foreach ( self::$customizer_fonts as $font_id => $font_data ) {
					if ( isset( $global_variables_full['fonts'][ $font_id ] ) ) {
						unset( $global_variables_full['fonts'][ $font_id ] );
					}
				}
			} else {
				// If fonts is corrupted (stdClass), reset it to empty array.
				$global_variables_full['fonts'] = [];
			}

			// Add fresh customizer fonts.
			$customizer_fonts               = self::get_customizer_fonts();
			$global_variables_full['fonts'] = array_merge(
				$customizer_fonts,
				$global_variables_full['fonts'] ?? []
			);

			// Default allowed actions for every global variable.
			$allowed_actions = [
				'allowedActions' => [
					'editLabel' => true,
					'editValue' => true,
					'reorder'   => true,
					'remove'    => true,
				],
			];

			foreach ( $global_variables_full as $type => &$items ) {
				// Ensure each type is an array to prevent stdClass object access errors.
				if ( ! is_array( $items ) ) {
					$items = [];
					continue;
				}

				foreach ( $items as &$item_data ) {
					$item_data = array_merge( $item_data, $allowed_actions );
				}
			}
			unset( $items, $item_data ); // Clean up references.

			self::$_cached_data['variables'] = array_map(
				fn( $items ) => (object) $items, // Convert each type to an object.
				$global_variables_full
			);
		}

		return self::$_cached_data['variables'];
	}

	/**
	 * Sets the global variables for Divi.
	 *
	 * This method takes an array of variable data and stores it in the global data settings.
	 * The variable data should be in a specific format and will be sanitized before storing.
	 *
	 * @param array $data The array of global variable data to set.
	 *
	 * @return void
	 *
	 * @example:
	 *   ```php
	 *  $global_variables_data = [
	 *      'numbers' => [
	 *          'gvid-98eb727a-9088-4709-8ec8-2fee0213c5c3' => [
	 *              'label'  => 'Rounder Corners',
	 *              'value'  => '12px',
	 *              'order'  => 1,
	 *              'status' => 'active'
	 *          ],
	 *      ],
	 *      'strings' => [
	 *          'gvid-3cf7c9305a' => [
	 *              'label'  => 'Font Size',
	 *              'value'  => '16px',
	 *              'order'  => 2,
	 *              'status' => 'active'
	 *          ],
	 *      ],
	 *  ];
	 *
	 *  GlobalData::set_global_variables( $global_variables_data );
	 * ```
	 */
	public static function set_global_variables( array $data ): void {
		if ( ! current_user_can( 'edit_theme_options' ) || ! et_pb_is_allowed( 'variables_manager' ) ) {
			return;
		}

		// Reset the cached data.
		self::$_cached_data['variables'] = null;

		if ( ! is_array( $data ) ) {
			$data = [];
		}

		$data = self::sanitize_global_variables_data( $data );

		et_update_option( 'global_variables', $data, false, '', '', true );

		// Reset cache when variables are updated.
		ET_Core_PageResource::remove_static_resources( 'all', 'all', true );
	}

	/**
	 * Imports the global variables for Divi.
	 *
	 * This method takes an array of variable data and merges it with existing Global Variables.
	 * After merge data saved into DB.
	 *
	 * @param array $data The array of global variable data import.
	 *
	 * @return array {
	 *     The list of Global Variables data.
	 *
	 *     @type array $numbers {
	 *         @type int    $id     The global variable ID.
	 *         @type string $label  The label of the global variable.
	 *         @type string $value  The value of the global variable.
	 *         @type int    $order  The order of the global variable.
	 *         @type string $status The status of the global variable: active | archived.
	 *     }
	 *     @type array $strings {
	 *         @type int    $id     The global variable ID.
	 *         @type string $label  The label of the global variable.
	 *         @type string $value  The value of the global variable.
	 *         @type int    $order  The order of the global variable.
	 *         @type string $status The status of the global variable: active | archived.
	 *     }
	 *     @type array $images {
	 *         @type int    $id     The global variable ID.
	 *         @type string $label  The label of the global variable.
	 *         @type string $value  The value of the global variable.
	 *         @type int    $order  The order of the global variable.
	 *         @type string $status The status of the global variable: active | archived.
	 *     }
	 *     @type array $links {
	 *         @type int    $id     The global variable ID.
	 *         @type string $label  The label of the global variable.
	 *         @type string $value  The value of the global variable.
	 *         @type int    $order  The order of the global variable.
	 *         @type string $status The status of the global variable: active | archived.
	 *     }
	 * }
	 *
	 * @example:
	 *   ```php
	 *  $global_variables_data = [
	 *      'numbers' => [
	 *          'gvid-98eb727a-9088-4709-8ec8-2fee0213c5c3' => [
	 *              'label'  => 'Rounder Corners',
	 *              'value'  => '12px',
	 *              'order'  => 1,
	 *              'status' => 'active',
	 *          ],
	 *      ],
	 *      'strings' => [
	 *          'gvid-3cf7c9305a' => [
	 *              'label'  => 'Font Size',
	 *              'value'  => '16px',
	 *              'order'  => 2,
	 *              'status'  => 'active',
	 *          ],
	 *      ],
	 *  ];
	 *
	 *  GlobalData::import_global_variables( $global_variables_data );
	 * ```
	 */
	public static function import_global_variables( array $data ): array {
		$existing_variables = self::get_global_variables();

		if ( empty( $data ) || ! is_array( $data ) ) {
			return $existing_variables;
		}

		// Valid global variable types that can be imported.
		$valid_types = [ 'numbers', 'strings', 'images', 'links', 'fonts', 'gradients' ];

		foreach ( $data as $variable_data ) {
			// Skip null or invalid entries.
			if ( null === $variable_data || ! is_array( $variable_data ) ) {
				continue;
			}

			// Ensure required keys exist.
			if ( ! isset( $variable_data['type'], $variable_data['id'] ) ) {
				continue;
			}

			$variable_type = $variable_data['type'];
			$variable_id   = $variable_data['id'];

			// Validate variable type is allowed.
			if ( ! in_array( $variable_type, $valid_types, true ) ) {
				continue;
			}

			// Ensure the type exists in existing variables.
			if ( ! isset( $existing_variables[ $variable_type ] ) ) {
				$existing_variables[ $variable_type ] = (object) [];
			}

			// Skip customizer fonts - they're stored separately in WordPress options.
			if ( 'fonts' === $variable_type && isset( self::$customizer_fonts[ $variable_id ] ) ) {
				continue;
			}

			$existing_variables[ $variable_type ]->$variable_id = [
				'id'     => $variable_data['id'],
				'label'  => $variable_data['label'] ?? '',
				'value'  => $variable_data['value'] ?? '',
				'status' => $variable_data['status'] ?? 'active',
			];
		}

		self::set_global_variables( $existing_variables );

		return self::get_global_variables();
	}

	/**
	 * Exports the global variables for specified Ids.
	 *
	 * This method takes an array of variable ids and generates array of global variables for export.
	 *
	 * @param array $variable_ids The array of global variable ids.
	 *
	 * @return array The array of global variables for export.
	 */
	public static function export_global_variables( array $variable_ids ): array {
		if ( empty( $variable_ids ) || ! is_array( $variable_ids ) ) {
			return [];
		}

		$existing_variables  = self::get_global_variables();
		$variables_to_export = [];

		foreach ( $existing_variables as $group => $variables ) {
			// Convert object to array for safe iteration.
			$variables_array = (array) $variables;

			foreach ( $variables_array as $variable_id_key => $single_variable ) {
				// Convert object to array if needed.
				$variable_data = is_object( $single_variable ) ? (array) $single_variable : $single_variable;

				// Skip if variable data is not an array or missing required fields.
				if ( ! is_array( $variable_data ) || empty( $variable_data['id'] ) ) {
					continue;
				}

				// Only export variables that are in the requested list.
				if ( ! in_array( $variable_data['id'], $variable_ids, true ) ) {
					continue;
				}

				// Validate required fields exist.
				if ( ! isset( $variable_data['label'] ) || ! isset( $variable_data['value'] ) || ! isset( $variable_data['status'] ) ) {
					continue;
				}

				$value = $variable_data['value'];
				// Handle image variables.
				if ( 'images' === $group && ! empty( $value ) ) {
					// Check if the value is already a base64 string.
					// https://regex101.com/r/CK5XlB/1.
					if ( ! preg_match( '/^data:image\/[a-z]+;base64,/', $value ) ) {
						$id    = 0;
						$image = '';

						// Try to get attachment ID from URL.
						$id = attachment_url_to_postid( $value );

						if ( ! empty( $id ) ) {
							// Try to encode attachment image.
							$file = get_attached_file( $id );
							if ( $file && file_exists( $file ) ) {
								// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Encoding required for portability.
								$image_data = file_get_contents( $file );
								if ( false !== $image_data ) {
									$mime_type = mime_content_type( $file );
									$image     = base64_encode( $image_data );
									$value     = "data:{$mime_type};base64,{$image}";
								}
								// phpcs:enable
							}
						}

						if ( empty( $image ) ) {
							// Try to encode remote image.
							$request = wp_remote_get(
								esc_url_raw( $value ),
								[
									'timeout'     => 2,
									'redirection' => 2,
								]
							);

							if ( ! is_wp_error( $request ) && is_array( $request ) ) {
								$content_type = wp_remote_retrieve_header( $request, 'content-type' );
								if ( str_contains( $content_type, 'image' ) ) {
									$image_data = wp_remote_retrieve_body( $request );
									if ( ! empty( $image_data ) ) {
										// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding required for portability.
										$image = base64_encode( $image_data );
										// phpcs:enable
										$value = "data:{$content_type};base64,{$image}";
									}
								}
							}
						}
					}
				}

				$variables_to_export[] = [
					'id'     => $variable_data['id'],
					'label'  => $variable_data['label'],
					'value'  => $value,
					'status' => $variable_data['status'],
					'type'   => $group,
				];
			}
		}

		return $variables_to_export;
	}

	/**
	 * Resolves the value of a Global Variable from a CSS variable format.
	 *
	 * If the given value is in the form `var(--gvid-xyz)` and corresponds to an active global variable,
	 * returns the resolved `value`. Otherwise, returns the original input string.
	 *
	 * @since ??
	 *
	 * @param string $value CSS variable string, e.g. 'var(--gvid-abc123)'.
	 *
	 * @return string The resolved value if found, otherwise the original value.
	 */
	public static function resolve_global_variable_value( string $value ): string {
		if ( ! is_string( $value ) || ! str_contains( $value, '--gvid-' ) ) {
			return $value;
		}

		// @see https://regex101.com/r/2dsmMA/1
		preg_match( '/--(gvid-[a-z0-9\-]+)/i', $value, $matches );

		if ( empty( $matches[1] ) ) {
			return $value;
		}

		$global_variable_id = $matches[1];
		$global_variables   = self::get_global_variables();

		foreach ( $global_variables as $variable_group ) {
			// $variable_group is a stdClass
			if ( isset( $variable_group->$global_variable_id ) ) {
				$global_variable_data = $variable_group->$global_variable_id;

				// $global_variable_data is an array, not an object
				if (
					is_array( $global_variable_data ) &&
					! empty( $global_variable_data['value'] )
				) {
					return $global_variable_data['value'];
				}

				break; // Found the ID, no need to keep checking.
			}
		}

		return $value;
	}

	/**
	 * Checks if a CSS value string contains a Global Variable reference.
	 *
	 * This function checks if a given CSS value string contains a Global Variable
	 * pattern (var(--gvid-...)). Global Variables can contain complex CSS expressions
	 * (clamp, calc, viewport units, rem, etc.) that cannot be reliably parsed numerically.
	 *
	 * @since ??
	 *
	 * @param string $value The CSS value string to check.
	 *
	 * @return bool True if the value contains a Global Variable pattern, false otherwise.
	 *
	 * @example:
	 * ```php
	 * GlobalData::is_global_variable_value( 'var(--gvid-abc123)' ); // true
	 * GlobalData::is_global_variable_value( '10px' ); // false
	 * GlobalData::is_global_variable_value( 'clamp(10px, var(--gvid-xyz), 20px)' ); // true
	 * ```
	 */
	public static function is_global_variable_value( string $value ): bool {
		return str_contains( $value, 'var(--gvid-' );
	}

	/**
	 * Resolves a gradient variable reference into gradient settings.
	 *
	 * Supported input formats:
	 * - `$variable(...)$` (gradient payload),
	 * - `var(--gvid-...)` CSS variable reference,
	 * - direct `gvid-...` variable id.
	 *
	 * @since ??
	 *
	 * @param string|array $value Gradient value or variable reference.
	 * @param int          $depth Internal recursion depth.
	 *
	 * @return array|null
	 */
	public static function resolve_global_gradient_variable( $value, int $depth = 0 ): ?array {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( ! is_string( $value ) || '' === $value || $depth >= 10 ) {
			return null;
		}

		if ( str_starts_with( $value, '$variable(' ) && '$' === substr( $value, -1 ) ) {
			$variable_content = substr( $value, 10, -2 );
			$variable_data    = json_decode( $variable_content, true );

			if ( ! is_array( $variable_data ) || 'gradient' !== ( $variable_data['type'] ?? '' ) ) {
				return null;
			}

			$referenced_variable_id = $variable_data['value']['name'] ?? '';
			if ( is_string( $referenced_variable_id ) && str_starts_with( $referenced_variable_id, 'gvid-' ) ) {
				$resolved_value = self::resolve_global_variable_value( "var(--{$referenced_variable_id})" );

				if ( is_string( $resolved_value ) ) {
					$resolved_gradient = self::resolve_global_gradient_variable( $resolved_value, $depth + 1 );

					if ( is_array( $resolved_gradient ) && ! empty( $resolved_gradient ) ) {
						return $resolved_gradient;
					}
				}
			}

			$settings = $variable_data['value']['settings'] ?? null;
			if ( is_array( $settings ) && ! empty( $settings ) ) {
				return $settings;
			}

			return null;
		}

		if ( str_starts_with( $value, 'var(--gvid-' ) ) {
			$resolved_value = self::resolve_global_variable_value( $value );

			if ( is_string( $resolved_value ) && $resolved_value !== $value ) {
				return self::resolve_global_gradient_variable( $resolved_value, $depth + 1 );
			}
		}

		if ( str_starts_with( $value, 'gvid-' ) ) {
			return self::resolve_global_gradient_variable( "var(--{$value})", $depth + 1 );
		}

		return null;
	}

	/**
	 * Resolve global color variable for SVG usage with recursive nested support.
	 *
	 * This function handles all global color patterns and resolves them to actual color values
	 * suitable for SVG fill attributes. It supports:
	 * - Simple global color IDs (e.g., gcid-xxx)
	 * - Nested global colors (global colors referencing other global colors)
	 * - $variable(...) syntax with filters and opacity
	 * - CSS relative HSL with unresolved CSS variables
	 * - HSL filter compounding (filters add up across levels)
	 * - Opacity inheritance (opacity gets overridden, not compounded)
	 *
	 * @since ??
	 *
	 * @param string $color The color value to resolve.
	 * @param array  $global_colors Optional. Global colors array in flat structure (id => color_value).
	 * @param int    $depth Optional. Current recursion depth for circular dependency protection.
	 * @param bool   $accumulate_filters Optional. Whether to accumulate filters instead of creating nested HSL functions.
	 * @return string The resolved color value suitable for SVG usage.
	 */
	public static function resolve_global_color_variable( $color, $global_colors = null, $depth = 0, $accumulate_filters = false ) {
		// Early return for invalid input.
		if ( ! is_string( $color ) || empty( $color ) ) {
			return $color;
		}

		// Maximum recursion depth to prevent infinite loops.
		$max_depth = 10;

		if ( $depth >= $max_depth ) {
			return $color;
		}

		// Get global colors if not provided.
		if ( null === $global_colors ) {
			$global_colors = self::get_global_colors();
		}

		// Case 1: Handle simple global color IDs (e.g., "gcid-xxx").
		// Note: CSS variables (e.g., "var(--gcid-xxx)") should NOT be resolved here.
		// They should only be resolved when part of $variable(...) syntax (Case 2) or inside HSL relative color functions (Case 3).
		// This matches TypeScript behavior where CSS variables passed directly are returned unchanged.
		// TypeScript checks globalColors?.[color] directly, which would be undefined for CSS variables.
		// So we skip Case 1 for CSS variables and only handle simple global color IDs.
		if ( self::_is_global_color_id( $color ) && ! ( is_string( $color ) && str_starts_with( $color, 'var(--' ) && str_contains( $color, 'gcid-' ) ) ) {
			$global_color_id = $color;

			if ( isset( $global_colors[ $global_color_id ] ) ) {
				$color_data = $global_colors[ $global_color_id ];

				// Extract the actual color value from the data structure.
				$resolved_color = is_array( $color_data ) && isset( $color_data['color'] )
					? $color_data['color']
					: $color_data;

				// If the resolved color is still a $variable syntax, resolve it recursively.
				if ( is_string( $resolved_color ) && str_starts_with( $resolved_color, '$variable(' ) && '$' === substr( $resolved_color, -1 ) ) {
					return self::resolve_global_color_variable( $resolved_color, $global_colors, $depth + 1, $accumulate_filters );
				}

				// If the resolved color is another global color ID, resolve it recursively.
				if ( self::_is_global_color_id( $resolved_color ) ) {
					// Extract global color ID from CSS variable format if needed.
					$nested_global_color_id = $resolved_color;
					if ( is_string( $resolved_color ) && str_starts_with( $resolved_color, 'var(--' ) && str_contains( $resolved_color, 'gcid-' ) ) {
						$extracted_id = self::get_global_color_id_from_value( $resolved_color );
						if ( $extracted_id ) {
							$nested_global_color_id = $extracted_id;
						}
					}

					if ( isset( $global_colors[ $nested_global_color_id ] ) ) {
						return self::resolve_global_color_variable( $resolved_color, $global_colors, $depth + 1, $accumulate_filters );
					}

					// If nested global color ID doesn't exist and $resolved_color is still a CSS variable,
					// don't return it unchanged as it will cause invalid CSS when passed to _apply_accumulated_hsl_settings().
					// Instead, return the original $color to allow other cases to handle it.
					if ( is_string( $resolved_color ) && str_starts_with( $resolved_color, 'var(--' ) && str_contains( $resolved_color, 'gcid-' ) ) {
						return $color;
					}
				}

				// Return the resolved color directly.
				// Note: Simple global color IDs don't have settings to apply.

				return $resolved_color;
			}
		}

		// Case 2: Handle $variable syntax with filters/opacity.
		if ( str_starts_with( $color, '$variable(' ) && '$' === substr( $color, -1 ) ) {
			// Parse $variable syntax. Remove the prefix and suffix so the content can be parsed.
			$variable_content = substr( $color, 10, -2 );
			$variable_data    = json_decode( $variable_content, true );

			if ( ! is_array( $variable_data ) || ! isset( $variable_data['value'] ) ) {
				return $color;
			}

			$global_color_id = $variable_data['value']['name'] ?? '';
			$color_data      = isset( $global_colors[ $global_color_id ] ) ? $global_colors[ $global_color_id ] : null;

			// Extract the actual color value from the data structure.
			$base_color = null;
			if ( $color_data ) {
				$base_color = is_array( $color_data ) && isset( $color_data['color'] )
					? $color_data['color']
					: $color_data;
			}

			if ( $base_color && $global_color_id ) {
				$settings       = $variable_data['value']['settings'] ?? [];
				$color_settings = ( is_array( $settings ) && ! isset( $settings['before'] ) && ! isset( $settings['after'] ) ) ? $settings : [];

				// Recursively resolve the base color first (matching TypeScript approach).
				// Handle $variable syntax.
				if ( str_starts_with( $base_color, '$variable(' ) && '$' === substr( $base_color, -1 ) ) {
					$resolved_base_color = self::resolve_global_color_variable( $base_color, $global_colors, $depth + 1, $accumulate_filters );
				} elseif ( is_string( $base_color ) && str_starts_with( $base_color, 'var(--' ) && str_contains( $base_color, 'gcid-' ) ) {
					// Handle CSS variable format (var(--gcid-xxx)).
					// Extract global color ID and resolve recursively to get concrete color value.
					$css_variable_global_color_id = self::get_global_color_id_from_value( $base_color );
					if ( $css_variable_global_color_id && isset( $global_colors[ $css_variable_global_color_id ] ) ) {
						$css_variable_color_data = $global_colors[ $css_variable_global_color_id ];
						$css_variable_base_color = is_array( $css_variable_color_data ) && isset( $css_variable_color_data['color'] )
							? $css_variable_color_data['color']
							: $css_variable_color_data;
						// Recursively resolve to ensure we get a concrete color value.
						$resolved_base_color = self::resolve_global_color_variable( $css_variable_base_color, $global_colors, $depth + 1, $accumulate_filters );
					} else {
						// Fallback: use base color as-is if resolution fails.
						$resolved_base_color = $base_color;
					}
				} else {
					$resolved_base_color = $base_color;
				}

				// If no settings (filters/opacity), return the resolved base color directly.
				if ( empty( $color_settings ) ) {
					return $resolved_base_color;
				}

				// Get numeric values from settings, defaulting to 0.
				$current_hue          = isset( $color_settings['hue'] ) ? intval( $color_settings['hue'] ) : 0;
				$current_saturation   = isset( $color_settings['saturation'] ) ? intval( $color_settings['saturation'] ) : 0;
				$current_lightness    = isset( $color_settings['lightness'] ) ? intval( $color_settings['lightness'] ) : 0;
				$has_explicit_opacity = array_key_exists( 'opacity', $color_settings );
				$current_opacity      = $has_explicit_opacity ? intval( $color_settings['opacity'] ) : null;

				// If accumulateFilters is true and we have existing HSL, combine values mathematically.
				if ( $accumulate_filters && str_starts_with( $resolved_base_color, 'hsl(from' ) ) {
					// Extract existing HSL values and combine with current settings.
					// Regex test: https://regex101.com/r/rsOSOm/2.
					if ( preg_match( '/hsl\(from ([^)]+) calc\(h ([+-]) ([^)]+)\) (?:calc\(s ([+-]) ([^)]+)\)|max\(0,\s*calc\(s ([+-]) ([^)]+)\)\)) calc\(l ([+-]) ([^)]+)\)/', $resolved_base_color, $matches ) ) {
						$base_color_value            = $matches[1];
						$base_hue                    = intval( $matches[3] ) * ( '+' === $matches[2] ? 1 : -1 );
						$saturation_sign             = '' !== $matches[4] ? $matches[4] : $matches[6];
						$saturation_value            = '' !== $matches[5] ? $matches[5] : $matches[7];
						$base_saturation             = intval( $saturation_value ) * ( '+' === $saturation_sign ? 1 : -1 );
						$base_lightness              = intval( $matches[9] ) * ( '+' === $matches[8] ? 1 : -1 );
						$has_bounded_base_saturation = '' !== $matches[6] && '' !== $matches[7];

						// Combine values mathematically.
						$combined_hue        = $base_hue + $current_hue;
						$combined_saturation = $base_saturation + $current_saturation;
						$combined_lightness  = $base_lightness + $current_lightness;

						// Generate new CSS relative HSL with accumulated values.
						$h_component = $combined_hue >= 0 ? "calc(h + {$combined_hue})" : 'calc(h - ' . abs( $combined_hue ) . ')';
						if ( $has_bounded_base_saturation ) {
							$base_bounded_saturation = $base_saturation >= 0
								? "max(0, calc(s + {$base_saturation}))"
								: 'max(0, calc(s - ' . abs( $base_saturation ) . '))';
							$s_component             = 0 === $current_saturation
								? $base_bounded_saturation
								: 'calc(' . $base_bounded_saturation . ( $current_saturation >= 0 ? ' + ' : ' - ' ) . abs( $current_saturation ) . ')';
						} else {
							$s_component = $combined_saturation >= 0
								? "calc(s + {$combined_saturation})"
								: 'max(0, calc(s - ' . abs( $combined_saturation ) . '))';
						}
						$l_component  = $combined_lightness >= 0 ? "calc(l + {$combined_lightness})" : 'calc(l - ' . abs( $combined_lightness ) . ')';
						$opacity_part = ( null !== $current_opacity && ( 100 !== $current_opacity || $has_explicit_opacity ) )
							? ' / ' . ( $current_opacity / 100 )
							: '';

						return "hsl(from {$base_color_value} {$h_component} {$s_component} {$l_component}{$opacity_part})";
					}
				}

				// Apply current level settings (create nested HSL or direct application).
				$current_settings = [
					'hue'                  => $current_hue,
					'saturation'           => $current_saturation,
					'lightness'            => $current_lightness,
					'opacity'              => $current_opacity,
					'has_explicit_opacity' => $has_explicit_opacity,
				];

				// Only apply settings if they have values.
				if ( 0 !== $current_hue || 0 !== $current_saturation || 0 !== $current_lightness || null !== $current_opacity ) {
					// Ensure $resolved_base_color is not a CSS variable before applying HSL settings.
					// CSS relative color syntax requires concrete color values, not CSS variables.
					if ( is_string( $resolved_base_color ) && str_starts_with( $resolved_base_color, 'var(--' ) && str_contains( $resolved_base_color, 'gcid-' ) ) {
						// Try to resolve the CSS variable one more time.
						$final_resolved_color = self::resolve_global_color_variable( $resolved_base_color, $global_colors, $depth + 1, $accumulate_filters );
						// If still a CSS variable, try to resolve base color as fallback.
						if ( is_string( $final_resolved_color ) && str_starts_with( $final_resolved_color, 'var(--' ) && str_contains( $final_resolved_color, 'gcid-' ) ) {
							// If base color is also a CSS variable, try to resolve it recursively.
							if ( $base_color && is_string( $base_color ) && str_starts_with( $base_color, 'var(--' ) && str_contains( $base_color, 'gcid-' ) ) {
								$resolved_base_color = self::resolve_global_color_variable( $base_color, $global_colors, $depth + 1, $accumulate_filters );
								// If base color resolution also fails and returns a CSS variable, skip HSL settings to avoid invalid CSS.
								if ( is_string( $resolved_base_color ) && str_starts_with( $resolved_base_color, 'var(--' ) && str_contains( $resolved_base_color, 'gcid-' ) ) {
									// Cannot resolve to concrete color - return color unchanged without applying HSL settings.
									return $resolved_base_color;
								}
							} else {
								// Use base color as fallback if it's a concrete color.
								$resolved_base_color = $base_color ?? $final_resolved_color;
							}
						} else {
							$resolved_base_color = $final_resolved_color;
						}
					}
					// Final check: ensure resolved color is not a CSS variable before applying HSL settings.
					if ( is_string( $resolved_base_color ) && str_starts_with( $resolved_base_color, 'var(--' ) && str_contains( $resolved_base_color, 'gcid-' ) ) {
						// Cannot resolve to concrete color - return color unchanged without applying HSL settings.
						return $resolved_base_color;
					}
					return self::_apply_accumulated_hsl_settings( $resolved_base_color, $current_settings );
				}

				return $resolved_base_color;
			}
		}

		// Case 3: Handle CSS relative HSL with unresolved CSS variables.
		// This happens when module-level filters are applied to global colors.
		if ( is_string( $color ) && str_contains( $color, 'hsl(from var(--' ) && str_contains( $color, 'gcid-' ) ) {
			// Extract the CSS variable from the relative HSL.
			if ( preg_match( '/var\(--([^)]+)\)/', $color, $matches ) ) {
				$global_color_id = $matches[1];
				$color_data      = isset( $global_colors[ $global_color_id ] ) ? $global_colors[ $global_color_id ] : null;

				// Extract the actual color value from the data structure.
				$base_color = null;
				if ( $color_data ) {
					$base_color = is_array( $color_data ) && isset( $color_data['color'] )
						? $color_data['color']
						: $color_data;
				}

				if ( $base_color ) {
					// Recursively resolve the base color.
					$resolved_color = self::resolve_global_color_variable( $base_color, $global_colors, $depth + 1, $accumulate_filters );

					// Replace the CSS variable with the resolved color value.
					return str_replace( 'var(--' . $global_color_id . ')', $resolved_color, $color );
				}
			}
		}

		// Return the color unchanged if no cases match.
		return $color;
	}

	/**
	 * Apply accumulated HSL settings to any color.
	 *
	 * @since ??
	 *
	 * @param string $color The color to apply settings to (hex, hsl, etc.).
	 * @param array  $accumulated_settings The accumulated HSL settings.
	 * @return string The CSS relative HSL string.
	 */
	private static function _apply_accumulated_hsl_settings( $color, $accumulated_settings ) {
		$hue                  = $accumulated_settings['hue'];
		$saturation           = $accumulated_settings['saturation'];
		$lightness            = $accumulated_settings['lightness'];
		$opacity              = $accumulated_settings['opacity'];
		$has_explicit_opacity = $accumulated_settings['has_explicit_opacity'] ?? false;

		// Format component with proper operator.
		$format_component = function ( $base, $adjustment ) {
			$operator = $adjustment >= 0 ? ' + ' : ' - ';
			return "calc({$base}{$operator}" . abs( $adjustment ) . ')';
		};

		$h_component = $format_component( 'h', $hue );
		$s_component = $saturation < 0 ? 'max(0, ' . $format_component( 's', $saturation ) . ')' : $format_component( 's', $saturation );
		$l_component = $format_component( 'l', $lightness );

		// Handle opacity: include explicit 100% as `/ 1` so it can override base alpha values.
		$opacity_part = ( null !== $opacity && ( 100 !== $opacity || $has_explicit_opacity ) )
			? ' / ' . ( $opacity / 100 )
			: '';

		return "hsl(from {$color} {$h_component} {$s_component} {$l_component}{$opacity_part})";
	}

	/**
	 * Check if a value is a global color ID.
	 *
	 * @since ??
	 *
	 * @param string $value The value to check.
	 * @return bool True if the value is a global color ID.
	 */
	private static function _is_global_color_id( $value ) {
		return et_builder_is_global_color( $value ) ||
			( is_string( $value ) && preg_match( '/^var\(--gcid-[^)]+\)$/', $value ) );
	}



	/**
	 * Gets a specific accent color as a CSS variable.
	 *
	 * This function provides a simple way for third-party developers to access
	 * Divi's accent colors. It returns the color as a CSS variable that can be
	 * used directly in styles or as default values in module definitions.
	 *
	 * @since ??
	 *
	 * @param string $type The type of accent color to retrieve.
	 *                     Valid values: 'primary', 'secondary', 'heading', 'body'.
	 *
	 * @return string The CSS variable string (e.g., "var(--gcid-primary-color)"), or empty string if invalid type.
	 *
	 * @example
	 *
	 * Get primary accent color:
	 * ```php
	 * $primary_color = GlobalData::get_accent_color( 'primary' );
	 * // Returns: "var(--gcid-primary-color)"
	 * ```
	 *
	 * Use in module attribute filter:
	 * ```php
	 * add_filter( 'block_type_metadata_settings', function( $settings ) {
	 *     if ( 'my-plugin/my-module' === $settings['name'] ) {
	 *         $settings['attributes']['content']['color']['default'] = GlobalData::get_accent_color( 'primary' );
	 *     }
	 *     return $settings;
	 * } );
	 * ```
	 */
	public static function get_accent_color( string $type ): string {
		$color_id = self::get_accent_color_id( $type );

		if ( empty( $color_id ) ) {
			return '';
		}

		return "var(--{$color_id})";
	}

	/**
	 * Gets all accent colors as CSS variables.
	 *
	 * This function returns an associative array containing all available accent colors
	 * as CSS variables, providing convenient access to all accent colors at once.
	 *
	 * @since ??
	 *
	 * @return array Array with accent color types as keys and CSS variables as values.
	 *               Keys: 'primary', 'secondary', 'heading', 'body'.
	 *
	 * @example
	 *
	 * Get all accent colors:
	 * ```php
	 * $accent_colors = GlobalData::get_accent_colors();
	 * // Returns: [
	 * //   'primary'   => "var(--gcid-primary-color)",
	 * //   'secondary' => "var(--gcid-secondary-color)",
	 * //   'heading'   => "var(--gcid-heading-color)",
	 * //   'body'      => "var(--gcid-body-color)"
	 * // ]
	 * ```
	 *
	 * Use in module setup:
	 * ```php
	 * $colors = GlobalData::get_accent_colors();
	 * $settings['attributes']['content']['backgroundColor']['default'] = $colors['primary'];
	 * $settings['attributes']['content']['textColor']['default'] = $colors['heading'];
	 * ```
	 */
	public static function get_accent_colors(): array {
		$result = [];
		foreach ( array_keys( self::$_accent_color_map ) as $type ) {
			$result[ $type ] = self::get_accent_color( $type );
		}
		return $result;
	}

	/**
	 * Gets the global color ID for a specific accent color type.
	 *
	 * This function returns the internal global color ID used by Divi's global
	 * color system. This is useful for advanced integrations that need to work
	 * directly with the global color system.
	 *
	 * @since ??
	 *
	 * @param string $type The type of accent color.
	 *                     Valid values: 'primary', 'secondary', 'heading', 'body'.
	 *
	 * @return string The global color ID (e.g., "gcid-primary-color"), or empty string if invalid type.
	 *
	 * @example
	 *
	 * Get global color ID for use with global color methods:
	 * ```php
	 * $primary_color_id = GlobalData::get_accent_color_id( 'primary' );
	 * $customizer_colors = GlobalData::get_customizer_colors();
	 * $primary_color_data = $customizer_colors[ $primary_color_id ];
	 * ```
	 */
	public static function get_accent_color_id( string $type ): string {
		if ( ! array_key_exists( $type, self::$_accent_color_map ) ) {
			return '';
		}

		return self::$_accent_color_map[ $type ];
	}

	/**
	 * Gets the resolved accent color value (actual color like D4's et_builder_accent_color).
	 *
	 * This function returns the actual color value (e.g., "#2ea3f2") similar to
	 * D4's et_builder_accent_color() function, providing backward compatibility
	 * for 3PS developers who need the resolved color value instead of CSS variables.
	 *
	 * @since ??
	 *
	 * @param string $type The type of accent color.
	 *                     Valid values: 'primary', 'secondary', 'heading', 'body'.
	 *
	 * @return string The resolved color value (e.g., "#2ea3f2"), or empty string if invalid type.
	 *
	 * @example
	 *
	 * Get actual color value like D4's et_builder_accent_color():
	 * ```php
	 * $primary_color = GlobalData::get_accent_color_value( 'primary' );     // "#2ea3f2"
	 * $secondary_color = GlobalData::get_accent_color_value( 'secondary' ); // "#8800FF"
	 *
	 * // D4 to D5 migration example:
	 * // OLD: $color = et_builder_accent_color();
	 * // NEW: $color = GlobalData::get_accent_color_value( 'primary' );
	 * ```
	 */
	public static function get_accent_color_value( string $type ): string {
		$color_id          = self::get_accent_color_id( $type );
		$customizer_colors = self::get_customizer_colors();

		if ( isset( $customizer_colors[ $color_id ]['color'] ) ) {
			return $customizer_colors[ $color_id ]['color'];
		}

		// Fallback to customizer_colors defaults to maintain single source of truth.
		if ( isset( self::$customizer_colors[ $color_id ]['default'] ) ) {
			return self::$customizer_colors[ $color_id ]['default'];
		}

		// Final fallback if color_id not found.
		return '#2ea3f2';
	}

	/**
	 * Gets all resolved accent color values (actual colors like D4).
	 *
	 * This function returns an associative array of all accent color types
	 * with their resolved color values, providing D4-style direct color access.
	 *
	 * @since ??
	 *
	 * @return array Array with accent color types as keys and resolved color values.
	 *               Example: ['primary' => '#2ea3f2', 'secondary' => '#8800FF', ...]
	 *
	 * @example
	 *
	 * Get all resolved accent color values:
	 * ```php
	 * $colors = GlobalData::get_accent_color_values();
	 * // Returns: [
	 * //   'primary'   => '#2ea3f2',
	 * //   'secondary' => '#8800FF',
	 * //   'heading'   => '#666666',
	 * //   'body'      => '#666666'
	 * // ]
	 *
	 * // Usage:
	 * $primary_color = $colors['primary'];     // "#2ea3f2"
	 * $secondary_color = $colors['secondary']; // "#8800FF"
	 * ```
	 */
	public static function get_accent_color_values(): array {
		$result = [];
		foreach ( array_keys( self::$_accent_color_map ) as $type ) {
			$result[ $type ] = self::get_accent_color_value( $type );
		}
		return $result;
	}
}
