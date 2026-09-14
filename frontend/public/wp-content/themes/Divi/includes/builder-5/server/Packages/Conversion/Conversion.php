<?php
/**
 * Conversion: Conversion Class
 *
 * @package Divi
 * @since ??
 */

// phpcs:disable ET -- Temporarily disabled to get the PR CI pass for now. TODO: Fix this later.
// phpcs:disable Generic -- Temporarily disabled to get the PR CI pass for now. TODO: Fix this later.
// phpcs:disable PEAR -- Temporarily disabled to get the PR CI pass for now. TODO: Fix this later.
// phpcs:disable Squiz -- Temporarily disabled to get the PR CI pass for now. TODO: Fix this later.
// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames -- Reserved keywords used intentionally for clarity in utility functions.
// phpcs:disable WordPress -- Temporarily disabled to get the PR CI pass for now. TODO: Fix this later.
// phpcs:disable PSR2 -- Temporarily disabled to get the PR CI pass for now. TODO: Fix this later.

namespace ET\Builder\Packages\Conversion;

use ET\Builder\Packages\GlobalData\GlobalData;
use WP_Block_Type_Registry;
use ET\Builder\Packages\Module\Options\ModuleOptionsPresetAttrs;
use ET\Builder\Packages\Conversion\ShortcodeMigration;
use ET\Builder\Packages\Conversion\LegacyAttributeNames;
use ET\Builder\Packages\Conversion\DeprecatedAttributeMapping;
use ET\Builder\Packages\GlobalLayout\GlobalLayout;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Sizing\Sizing;
use ET\Builder\Migration\Migration;
use ET\Builder\VisualBuilder\Saving\SavingUtility;

if (!defined('ABSPATH')) {
	die('Direct access forbidden.');
}

// Example usage of str_ends_with in userland PHP prior to PHP 8.0, for compatibility.
// phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- Compatibility function required before class definition.
if (!function_exists('str_ends_with')) {
	// phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- Compatibility function required before class definition.
	function str_ends_with($haystack, $needle) {
		$length = strlen($needle);
		return $length > 0 ? substr($haystack, -$length) === $needle : true;
	}
}

/**
 * Handles Conversion
 *
 * @since ??
 */
// phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- Compatibility function required before class definition.
class Conversion {

	const DYNAMIC_CONTENT_REGEX = '/@ET-DC@([^@]+)@/';

	/**
	 * Preset Attributes Map for Conversion.
	 *
	 * This map is used to define the preset attributes type for a module during conversion.
	 * It will be used to cache the preset attributes map for all modules.
	 *
	 * @param array $conversionOutline Module's conversion map.
	 * @return array Module's full conversion map.
	 */
	public static $preset_attrs_maps = [];

	/**
	 * The static property that holds the WooCommerce modules.
	 *
	 * @var array
	 */
	private static $_woo_modules = [];

	/**
	 * The private static variable that holds the third-party modules.
	 *
	 * @var array|null
	 */
	private static $_third_party_modules = [];

	/**
	 * Indicates whether the Shortcode framework has been initialized.
	 *
	 * @var bool
	 */
	// phpcs:ignore ET.NamingConventions.VisibilityUnderscore.PrivateProperty -- Property name follows existing codebase conventions.
	private static $is_initialized = false;

	/**
	 * Initializes the shortcode framework for the Conversion class.
	 *
	 * This method checks if the shortcode framework has already been initialized. If not, it loads the Divi shortcode framework
	 * and executes actions for initializing third party modules. It also sets static variables for WooCommerce modules and third
	 * party modules.
	 *
	 * @return void
	 */
	static function initialize_shortcode_framework() {
		if (self::$is_initialized) {
			return;
		}

		// Load Divi shortcode framework, so we can check for shortcodes.
		et_load_shortcode_framework();

		// Execute actions where third party modules are initialized.
		do_action( 'divi_extensions_init' );
		do_action( 'et_builder_ready' );

		// Set static variables.
		self::$_woo_modules = \ET_Builder_Element::get_woocommerce_modules();
		self::$_third_party_modules = array_keys( \ET_Builder_Element::get_third_party_modules() );

		self::$is_initialized = true;
	}

	/**
	 * Get Module Meta Conversion Map.
	 *
	 * @return array
	 */
	static function getMetaConversionMap(): array {
		$privateAttrs = [
			'_builder_version' => 'builderVersion',
			'_module_preset' => 'modulePreset',
			'nonconvertible' => 'nonconvertible',
			'shortcodeName' => 'shortcodeName',
		];

		/**
		 * Filters the meta conversion map for the Divi module during conversion.
		 *
		 * This filter allows developers to modify the meta conversion map for the Divi module during conversion.
		 * The meta conversion map is used to define the different meta fields and their corresponding conversion functions
		 * for a module. By default, the meta conversion map is generated using the `privateAttrs` object.
		 *
		 * @param array $privateAttrs The default meta conversion map.
		 * @param string $privateAttrs['_builder_version'] The module's builder version.
		 * @param string $privateAttrs['_module_preset'] The module's preset.
		 * @param string $privateAttrs['nonconvertible'] The module's nonconvertible.
		 * @param string $privateAttrs['shortcodeName'] The module's shortcode name.
		 */
		return apply_filters('divi.moduleLibrary.conversion.metaConversionMap', $privateAttrs);
	}

	/**
	 * Get Module Conversion Map.
	 *
	 * Get Module's Full Attributes Conversion map based on module's conversion outline.
	 *
	 * @param array $conversionOutline Module's conversion map.
	 * @return array Module's full conversion map.
	 */
	static function getModuleConversionMap(array $conversionOutline): array {

		$advancedOptionConversionFunctionMapping = [
			'admin_label'     => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getAdminLabelConversionMap',
			'animation'       => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getAnimationConversionMap',
			'background'      => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getBackgroundConversionMap',
			'borders'         => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getBorderConversionMap',
			'box_shadow'      => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getBoxShadowConversionMap',
			'button'          => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getButtonConversionMap',
			'display_conditions' => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getConditionsConversionMap',
			'dividers'        => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getDividersConversionMap',
			'form_field'      => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getFormFieldConversionMap',
			'filters'         => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getFiltersConversionMap',
			'fonts'           => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getFontConversionMap',
			'gutter'          => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getGutterConversionMap',
			'height'          => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getSizingHeightConversionMap',
			'image_icon'      => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getImageIconConversionMap',
			'max_width'       => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getSizingMaxWidthConversionMap',
			'link_options'    => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getLinkConversionMap',
			'margin_padding'  => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getSpacingConversionMap',
			'module'          => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getIdClassesConversionMap',
			'overflow'        => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getOverflowConversionMap',
			'disabled_on'     => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getDisabledOnConversionMap',
			'position_fields' => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getPositionConversionMap',
			'scroll'          => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getScrollConversionMap',
			'sticky'          => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getStickyConversionMap',
			'text'            => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getTextConversionMap',
			'text_shadow'     => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getTextShadowConversionMap',
			'transform'       => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getTransformConversionMap',
			'transition'      => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getTransitionConversionMap',
			'z_index'         => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::getZIndexConversionMap',
		];

		$advancedOptionConversionFunctionMap = apply_filters('divi.moduleLibrary.conversion.advancedOptionConversionFunctionMap', $advancedOptionConversionFunctionMapping);

		$moduleAttrsConversionMap = [
			'attributeMap' => array_merge(self::getMetaConversionMap(), [ 'content' => 'content.*' ]),
			'optionEnableMap' => [],
			'nonResponsiveAttributes' => [],
			'valueExpansionFunctionMap' => [],
			'conditionalAttributeConversionFunctionMap' => [],
		];

		// Loop advanced options equivalent at $conversionOutline['advanced'].
		if (isset($conversionOutline['advanced']) && is_array($conversionOutline['advanced'])) {
			foreach ($conversionOutline['advanced'] as $advancedOptionName => $advancedOptionValue) {
				if (isset($advancedOptionConversionFunctionMap[$advancedOptionName])) {
					$advancedOptionConversionFunction = $advancedOptionConversionFunctionMap[$advancedOptionName];

					if (is_callable($advancedOptionConversionFunction)) {
						if (is_array($advancedOptionValue)) {
							// Advanced option that is capable of having multiple settings.
							foreach ($advancedOptionValue as $advancedOptionSubName => $advancedOptionSubValue) {
								$advancedOptionMap = $advancedOptionConversionFunction([
									'd4AdvancedOptionName' => $advancedOptionSubName,
									'd5AttrName' => $advancedOptionSubValue,
								]);

								// Push the value to moduleAttrsConversionMap.
								$moduleAttrsConversionMap['attributeMap'] = array_merge($moduleAttrsConversionMap['attributeMap'], $advancedOptionMap['attributeMap'] ?? []);
								$moduleAttrsConversionMap['valueExpansionFunctionMap'] = array_merge($moduleAttrsConversionMap['valueExpansionFunctionMap'], $advancedOptionMap['valueExpansionFunctionMap'] ?? []);
								$moduleAttrsConversionMap['conditionalAttributeConversionFunctionMap'] = array_merge( $moduleAttrsConversionMap['conditionalAttributeConversionFunctionMap'], $advancedOptionMap['conditionalAttributeConversionFunctionMap'] ?? [] );
								$moduleAttrsConversionMap['optionEnableMap'] = array_merge($moduleAttrsConversionMap['optionEnableMap'], $advancedOptionMap['optionEnableMap'] ?? []);
							}
						} else {
							$advancedOptionMap = $advancedOptionConversionFunction([
								'd4AdvancedOptionName' => $advancedOptionName,
								'd5AttrName' => $advancedOptionValue,
							]);

							// Push the value to moduleAttrsConversionMap.
							$moduleAttrsConversionMap['attributeMap'] = array_merge($moduleAttrsConversionMap['attributeMap'], $advancedOptionMap['attributeMap'] ?? []);
							$moduleAttrsConversionMap['valueExpansionFunctionMap'] = array_merge($moduleAttrsConversionMap['valueExpansionFunctionMap'], $advancedOptionMap['valueExpansionFunctionMap'] ?? []);
							$moduleAttrsConversionMap['conditionalAttributeConversionFunctionMap'] = array_merge( $moduleAttrsConversionMap['conditionalAttributeConversionFunctionMap'], $advancedOptionMap['conditionalAttributeConversionFunctionMap'] ?? [] );
							$moduleAttrsConversionMap['optionEnableMap'] = array_merge($moduleAttrsConversionMap['optionEnableMap'], $advancedOptionMap['optionEnableMap'] ?? []);
						}
					} else {
						throw new \Exception('advancedOptionConversionFunction is not callable! $advancedOptionConversionFunction:' . print_r($advancedOptionConversionFunction, true));
					}
				}
			}
		}

		// Loop CSS options equivalent at $conversionOutline['css'].
		if (isset($conversionOutline['css']) && is_array($conversionOutline['css'])) {
			foreach ($conversionOutline['css'] as $cssD4AttrName => $cssD5Path) {
				$moduleAttrsConversionMap['attributeMap']["custom_css_$cssD4AttrName"] = $cssD5Path;
			}
		}

		// Set $conversionOutline['module'] and $conversionOutline['valueExpansionFunctionMap'].
		// and $conversionOutline['conditionalAttributeConversionFunctionMap'].
		// to $moduleAttrsConversionMap.
		if (isset($conversionOutline['module'])) {
			$moduleAttrsConversionMap['attributeMap'] = array_merge($moduleAttrsConversionMap['attributeMap'], $conversionOutline['module']);
		}
		if (isset($conversionOutline['valueExpansionFunctionMap'])) {
			$moduleAttrsConversionMap['valueExpansionFunctionMap'] = array_merge($moduleAttrsConversionMap['valueExpansionFunctionMap'], $conversionOutline['valueExpansionFunctionMap']);
		}
		if ( isset( $conversionOutline['conditionalAttributeConversionFunctionMap'] ) ) {
			$moduleAttrsConversionMap['conditionalAttributeConversionFunctionMap'] = array_merge( $moduleAttrsConversionMap['conditionalAttributeConversionFunctionMap'], $conversionOutline['conditionalAttributeConversionFunctionMap'] );
		}
		if ( isset( $conversionOutline['nonResponsiveAttributes'] ) ) {
			$moduleAttrsConversionMap['nonResponsiveAttributes'] = array_merge( $moduleAttrsConversionMap['nonResponsiveAttributes'], $conversionOutline['nonResponsiveAttributes'] );
		}

		// Set deprecated attributes map if provided in conversion outline.
		if ( isset( $conversionOutline['deprecatedMap'] ) ) {
			$moduleAttrsConversionMap['deprecatedMap'] = $conversionOutline['deprecatedMap'];
		}

		return $moduleAttrsConversionMap;
	}

	/**
	 * Checks if a given color string is a global CSS variable color.
	 *
	 * This function checks if a given color string is a global CSS variable color. A global CSS variable color
	 * is a color string that is a valid CSS variable and has a name that starts with `--gcid-`.
	 *
	 * @param string $color The color string to be checked.
	 *
	 * @return bool True if the color string is a global CSS variable color, false otherwise.
	 */
	static function isGlobalColor( $color ) {
		// Regular expression to match global CSS variable color.
		$regex = '/^var\(--gcid-[0-9a-z-]+\)$/';

		return preg_match( $regex, $color ) === 1;
	}

	/**
	 * Get Module Global Colors.
	 *
	 * @param array $attrs Module attributes.
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
	static function getModuleGlobalColors( array $attrs ) {
		$globalColors         = [];
		$unparsedGlobalColors = $attrs['global_colors_info'] ?? '';

		// Attributes to check for gcid colors.
		$colorAttributes = [
			'background_color_gradient_stops',
			'button_bg_gradient_stops',
		];

		if ( ! empty( $unparsedGlobalColors ) && '{}' !== $unparsedGlobalColors ) {
			try {
				$decodedValue = str_replace( [ '%22', '%91', '%93' ], [ '"', '[', ']' ], $unparsedGlobalColors );
				$jsonDecode   = json_decode( $decodedValue, true );

				if ( is_array( $jsonDecode ) ) {
					$globalColors = $jsonDecode;
				}
			} catch ( \Exception $e ) {
				// error_log( 'Error decoding global colors: ' . json_last_error_msg() );
			}
		}

		// Iterate through attributes to find or add gcid colors.
		foreach ( $colorAttributes as $attr ) {
			$attrValue = $attrs[$attr] ?? '';

			if ( empty( $attrValue ) ) {
				continue;
			}

			preg_match_all( '/\bgcid-[\w-]+/', $attrValue, $matches );
			if ( empty( $matches[0] ) ) {
				continue;
			}

			foreach ( $matches[0] as $color ) {
				if ( ! isset( $globalColors[$color] ) ) {
					$globalColors[$color] = [];
				}
				if ( ! in_array( $attr, $globalColors[$color], true ) ) {
					$globalColors[$color][] = $attr;
				}
			}
		}

		return $globalColors;
	}

	/**
	 * Convert global colors data to CSS variable format.
	 *
	 * @param string $encodedValue The encoded value.
	 * @param string $name The name of the attribute.
	 * @param array  $moduleGlobalColors The module global colors.
	 * @return string The converted global color.
	 */
	static function convertGlobalColor( $encodedValue, $name, $moduleGlobalColors ) {
		$keys = array_keys( $moduleGlobalColors );

		foreach ( $keys as $globalColorId ) {
			if ( in_array( $name, $moduleGlobalColors[ $globalColorId ] ) ) {
				// Catch all gradient stops attributes. For example:
				// 1. background_color_gradient_stops.
				// 2. button_bg_gradient_stops.

				if ( is_array( $encodedValue ) && strpos( $name, '_gradient_stops' ) !== false ) {
					if ( self::isGlobalColor( "var(--{$encodedValue['color']})" ) ) {
						$globalColorData = GlobalData::get_global_color_by_id( $encodedValue['color'] );

						// Proceed only if the global color id exists in the global color data
						// and the value is the same as the global color id.
						if ( $globalColorData && $encodedValue['color'] === $globalColorId ) {
							// Gradient Stops value consists of position and color.
							$encodedValue = [
								'position' => $encodedValue['position'],
								// Check if the global color id has active status, if so set the global
								// color id as $variable syntax otherwise get the color value from the store
								// and set as the color value.
								'color' => 'active' === $globalColorData['status']
								? self::formatDynamicContent( $globalColorId, [], 'color' )
								: $globalColorData['color'],
							];

							// Only break if the value is converted to global color.
							break;
						} elseif ( $encodedValue['color'] === $globalColorId ) {
							// Color not found in D5 global data option, try to convert from global color legacy option on-demand.
							$legacy_colors = et_get_option( 'et_global_colors', [] );

							// Only proceed if legacy colors exist and contain our ID.
							if ( ! empty( $legacy_colors ) && isset( $legacy_colors[ $globalColorId ] ) ) {
								$legacy_color_data = $legacy_colors[ $globalColorId ];

								// Skip corrupted entries where the value is not a valid array.
								if ( ! is_array( $legacy_color_data ) ) {
									continue;
								}

								// Convert the gradient stop value.
								$converted_color_status = 'yes' === ( $legacy_color_data['active'] ?? 'no' ) ? 'active' : 'inactive';

								// Convert and save legacy global color to D5 global data option.
								self::_convert_global_color_to_global_data(
									$globalColorId, $legacy_color_data['color'],
									$converted_color_status
								);

								// Gradient Stops value consists of position and color.
								$encodedValue = [
									'position' => $encodedValue['position'],

									// Check if the legacy global color is active, if so set as $variable syntax
									// otherwise get the color value from legacy data.
									'color' => 'active' === $converted_color_status
										? self::formatDynamicContent( $globalColorId, [], 'color' )
										: $legacy_color_data['color'],
								];

								// Only break if the value is converted to global color.
								break;
							}
						}
					}
				} elseif ( self::isGlobalColor( "var(--{$globalColorId})" ) ) {
					$globalColorData = GlobalData::get_global_color_by_id( $globalColorId );

					// Proceed only if the global color id exists in the global color data
					// and the value is the same as the global color id.
					if ( $globalColorData && $encodedValue === $globalColorId ) {
						// Check if the global color id has active status, if so set the global
						// color id as $variable syntax otherwise get the color value from the store
						// and set as the color value.
						$encodedValue = 'active' === $globalColorData['status']
						? self::formatDynamicContent( $globalColorId, [], 'color' )
						: $globalColorData['color'];

						// Only break if the value is converted to global color.
						break;
					} elseif ( $encodedValue === $globalColorId ) {
						// Color not found in D5 global color option, try to convert from legacy global color option on-demand.
						$legacy_colors = et_get_option( 'et_global_colors', [] );

						// Proceed if legacy colors exist and contain our ID.
						if ( ! empty( $legacy_colors ) && isset( $legacy_colors[ $globalColorId ] ) ) {
							$legacy_color_data = $legacy_colors[ $globalColorId ];

							// Skip corrupted entries where the value is not a valid array.
							if ( ! is_array( $legacy_color_data ) ) {
								continue;
							}

							// Convert the color value.
							$converted_color_status = 'yes' === ( $legacy_color_data['active'] ?? 'no' ) ? 'active' : 'inactive';

							// Convert and save legacy global color to D5 global data option.
							self::_convert_global_color_to_global_data(
								$globalColorId,
								$legacy_color_data['color'],
								$converted_color_status
							);

							// Check if the legacy global color is active, if so set as $variable syntax
							// otherwise get the color value from legacy data
							$encodedValue = 'active' === $converted_color_status
								? self::formatDynamicContent( $globalColorId, [], 'color' )
								: $legacy_color_data['color'];

							// Only break if the value is converted to global color.
							break;
						}
					}
				}
			}
		}

		// Fallback: If the value is still a gcid-* string and hasn't been converted,
		// check if it's a known global color and convert to $variable() format, otherwise CSS variable
		if ( is_string( $encodedValue ) && strpos( $encodedValue, 'gcid-' ) === 0 ) {
			// Check if this GCID is in the moduleGlobalColors (meaning it's a valid global color for this module)
			foreach ( $moduleGlobalColors as $gcid => $attrs ) {
				if ( $encodedValue === $gcid ) {
					return self::formatDynamicContent( $gcid, [], 'color' );
				}
			}
			// If not found in moduleGlobalColors, fall back to CSS variable
			return "var(--{$encodedValue})";
		}

		// Handle gradient stops fallback
		if ( is_array( $encodedValue ) && isset( $encodedValue['color'] ) && is_string( $encodedValue['color'] ) && strpos( $encodedValue['color'], 'gcid-' ) === 0 ) {
			// Check if this GCID is in the moduleGlobalColors
			$gradientColor = $encodedValue['color'];
			$isModuleGlobalColor = false;
			foreach ( $moduleGlobalColors as $gcid => $attrs ) {
				if ( $gradientColor === $gcid ) {
					$isModuleGlobalColor = true;
					break;
				}
			}
			$encodedValue = [
				'position' => $encodedValue['position'],
				'color' => $isModuleGlobalColor ? self::formatDynamicContent( $gradientColor, [], 'color' ) : "var(--{$gradientColor})",
			];
		}

		return $encodedValue;
	}

	/**
	 * Convert legacy global color to D5 global data format.
	 *
	 * This is the PHP equivalent of the JavaScript dispatch('divi/global-data').addGlobalColor()
	 * call that persists converted legacy colors to et_global_data['global_colors'].
	 *
	 * @since ??
	 *
	 * @param string $global_color_id The global color ID (e.g., 'gcid-xxx')
	 * @param string $color The color value (e.g., '#5e5e4e')
	 * @param string $status The color status ('active' or 'inactive')
	 *
	 * @return void
	 */
	private static function _convert_global_color_to_global_data( $global_color_id, $color, $status ) {
		// Get current D5 global colors
		$current_colors = GlobalData::get_global_colors();

		// Don't add if the color already exists in D5 global data
		if ( isset( $current_colors[ $global_color_id ] ) ) {
			return;
		}

		// Create the new D5 global color with all required properties
		$new_color_data = [
			'color'       => sanitize_text_field( $color ),
			'folder'      => '',
			'label'       => '',
			'lastUpdated' => wp_date( 'Y-m-d\TH:i:s.v\Z' ),
			'status'      => sanitize_text_field( $status ),
			'usedInPosts' => [],
		];

		// Add the new color to the current colors array
		$current_colors[ $global_color_id ] = $new_color_data;

		// Save the updated colors back to global data
		GlobalData::set_global_colors( $current_colors, true );
	}

	/**
	 * Filters split test attributes from the given array.
	 *
	 * @since ??
	 *
	 * @param array $attrs               The array containing the attributes.
	 * @param bool  $is_ab_testing_active Whether split testing is active for the post. Default false.
	 *
	 * @return array The filtered array without split test attributes.
	 */
	public static function filterSplitTestAttributes( array $attrs, bool $is_ab_testing_active = false ): array {
		// Only overwrite disabled_on when split testing is actually active for the post.
		// When inactive, the original disabled_on value from D4 must be preserved as-is.
		if ( $is_ab_testing_active && isset( $attrs['ab_subject'] ) && isset( $attrs['ab_subject_id'] ) ) {
			// Interpolate ab_subject_id as disabled_on attribute value.
			$attrs['disabled_on'] = '1' === strval( $attrs['ab_subject_id'] ) ? 'off|off|off' : 'on|on|on';
		}

		// Omit all split test attributes regardless of active state.
		unset( $attrs['ab_subject'] );
		unset( $attrs['ab_subject_id'] );
		unset( $attrs['ab_goal'] );

		return $attrs;
	}

	/**
	 * Normalizes the ab_subject_id value in the given content.
	 *
	 * If the content does not contain 'ab_subject_id' string, it returns the content as is.
	 * If there is only one occurrence of 'ab_subject_id' and its value is not '1', it replaces the value with '1'.
	 * If there is any occurrence of 'ab_subject_id' with value '1', it returns the content as is.
	 * Otherwise, it replaces the first occurrence of 'ab_subject_id' with value '1'.
	 *
	 * @since ??
	 *
	 * @param string $content The content to normalize.
	 * @return string The normalized content.
	 */
	public static function normalizeAbSubjectId( string $content ): string {
		// Check if the content has 'ab_subject_id' string, if not, bail early.
		if (strpos($content, 'ab_subject_id') === false) {
			return $content;
		}

		// Check if there is any occurrence with value one, if so, bail early.
		if (strpos($content, 'ab_subject_id="1"') !== false) {
			return $content;
		}

		// Count how many 'ab_subject_id' strings present.
		$count = substr_count($content, 'ab_subject_id');

		// If only once, replace the value to one if it is not one.
		if ($count === 1) {
			if (preg_match('/ab_subject_id="(\d+)"/', $content, $matches) && $matches[1] !== '1') {
				return preg_replace('/ab_subject_id="\d+"/', 'ab_subject_id="1"', $content);
			}
		}

		// Otherwise, replace the first occurrence to one.
		return preg_replace('/ab_subject_id="\d+"/', 'ab_subject_id="1"', $content, 1);
	}

	/**
     * Checks if a given string matches a regular expression for dynamic content.
     *
     * @since ??
     *
     * @param string $value String to check.
     *
     * @return bool True if the string matches the regular expression for dynamic content.
     */
    public static function isDynamicContent($value) {
        return preg_match(self::DYNAMIC_CONTENT_REGEX, $value) === 1;
    }

	/**
     * Converts dynamic content in a string to a JSON-like format.
     *
     * @since ??
     *
     * @param string $value The string to convert.
     *
     * @return string The converted string. Will return the original string if the conversion fails.
     */
    public static function convertDynamicContent($value) {
        try {
            return preg_replace_callback(self::DYNAMIC_CONTENT_REGEX, function ($matches) {
                $encoded = $matches[1];
                $decoded = base64_decode($encoded);

                // Verify that the decoded string can be encoded back to the original value.
                if ($encoded !== base64_encode($decoded)) {
                    return $matches[0];
                }

                $parsed = json_decode($decoded, true);
                if (!isset($parsed['dynamic'], $parsed['content'], $parsed['settings'])) {
                    return $matches[0];
                }

                return self::formatDynamicContent($parsed['content'], $parsed['settings']);
            }, $value);
        } catch (\Exception $e) {
            return $value;
        }
    }

	/**
     * Formats dynamic content into JSON-like string.
     *
     * @since ??
     *
     * @param string $name The type of dynamic content being formatted.
     * @param array $settings The additional settings for the dynamic content.
     * @param string $type The type of dynamic content (default: 'content').
     *
     * @return string Returns a string with the formatted dynamic content.
     */
    public static function formatDynamicContent($name, $settings, $type = 'content') {
        return '$variable(' . json_encode([
            'type' => $type,
            'value' => [
                'name' => $name,
                'settings' => (object) $settings,
            ],
        ], JSON_UNESCAPED_UNICODE) . ')$';
    }

	/**
     * Maybe Parse Value.
     *
     * Converts a value to an number based on whether the provided `attributeName` is found in the array:
     * `['address_lat', 'address_lng', 'pin_address_lat', 'pin_address_lng', 'zoom_level']`
     * or `['pin.desktop.value.lat', 'pin.desktop.value.lng', 'map.desktop.value.lat',`
     * `'map.desktop.value.lng', 'pin.desktop.value.zoom', 'map.desktop.value.zoom']`.
     * If the `attributeName` is not found, the value is returned as is.
     *
     * @since ??
     *
     * @param string $attributeName The name to be used to search in the paths/attrs that contain number type.
     * @param string|number $value The value to be parsed, can be number or string.
     *
     * @return string|number Parsed/unparsed value.
     */
    public static function maybeParseValue($attributeName, $value) {
        $numberTypeAttrs = [
            'address_lat',
            'address_lng',
            'pin_address_lat',
            'pin_address_lng',
            'zoom_level',
        ];

        $numberTypeObjectPaths = [
            'pin.desktop.value.lat',
            'pin.desktop.value.lng',
            'map.desktop.value.lat',
            'map.desktop.value.lng',
            'pin.desktop.value.zoom',
            'map.desktop.value.zoom',
        ];

        if (in_array($attributeName, $numberTypeAttrs) || in_array($attributeName, $numberTypeObjectPaths)) {
            return (float)$value;
        }

        return $value;
    }

	static function camelCase($string) {
		// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
		// TODO: Implement or use an existing library to convert strings to camelCase
		return lcfirst(str_replace(' ', '', ucwords(str_replace([ '-', '_' ], ' ', $string))));
	}

	/**
     * Responsive, hover or sticky state enabled.
     *
     * Determines if responsive, hover or sticky are enabled on an attribute, based on the desktop name.
     * The return value is determined by whether the attribute (responsive/state) starts with 'on' or 'off'.
     *
     * @since ??
     *
     * @param string $type 'hover' | 'sticky' | 'responsive'.
     * @param string $desktopName Attribute name without prefix.
     * @param array $attrs A string object of all module attributes.
     * @param array $optionEnableMap Enable option map to get correct attribute that define option status.
     *
     * @return bool True if responsive/state attribute starts with 'on'.
     */
    public static function enabled($type, $desktopName, $attrs, $optionEnableMap = []) {
        $suffix = $type === 'responsive' ? '_last_edited' : "__{$type}_enabled";

        // Some module options such as Background Options uses one attribute name to determine
        // responsive / hover / sticky status state of every field that existed inside of it.
        // Since the name of the attribute name can be anything depending to the advanced options
        // slug, optionEnableMap that is generated by `getModuleConversionMap()` should be passed.
        $attrName = $optionEnableMap[$desktopName] ?? $desktopName;

		if (isset($attrs["{$attrName}{$suffix}"])) {
			$attribute = $attrs["{$attrName}{$suffix}"];
			return is_string($attribute) && strpos($attribute, 'on') === 0;
		}

		return false;
    }

	/**
     * Determine Sticky status.
     *
     * Determines if sticky options are enabled on a module and on what view port it is activated.
     * If sticky is only enabled on a responsive view then viewport would be the view where it is activated.
     *
     * @since ??
     *
     * @param array $attrs A string object of all module attributes.
     *
     * @return array An object that looks like: ['active' => boolean, 'viewport' => 'desktop' | 'tablet' | 'phone'].
     */
    public static function stickyStatus($attrs) {
        $status = [
            'active' => false,
            'viewport' => 'desktop',
        ];
        $name = 'sticky_position';

        if (self::enabled('responsive', $name, $attrs)) {
            if (array_key_exists("{$name}_phone", $attrs) && $attrs["{$name}_phone"] !== 'none' && $attrs["{$name}_phone"] !== '') {
                $status['active'] = true;
                $status['viewport'] = 'phone';
            }

            if (array_key_exists("{$name}_tablet", $attrs) && $attrs["{$name}_tablet"] !== 'none' && $attrs["{$name}_tablet"] !== '') {
                $status['active'] = true;
                $status['viewport'] = 'tablet';
            }

            if (array_key_exists($name, $attrs) && $attrs[$name] !== 'none' && $attrs[$name] !== '') {
                $status['active'] = true;
                $status['viewport'] = 'desktop';
            }
        } else if (array_key_exists($name, $attrs) && $attrs[$name] !== 'none' && $attrs[$name] !== '') {
            $status['active'] = true;
        }

        return $status;
    }

	/**
	 * Rewrites legacy D4 free-form order-class tokens to `selector` during import conversion.
	 *
	 * @since ??
	 *
	 * @param string $css Free-form CSS.
	 * @param string $moduleName Module name.
	 *
	 * @return string Rewritten CSS.
	 */
	private static function _rewriteLegacyFreeFormOrderClassToSelector( $css, $moduleName ) {
		$moduleOrderClassBase = ModuleUtils::get_module_order_class_name_base( $moduleName );

		if ( ! $moduleOrderClassBase ) {
			return $css;
		}

		$legacyOrderClassToken = '.' . $moduleOrderClassBase . '_';

		if ( false === strpos( $css, $legacyOrderClassToken ) ) {
			return $css;
		}

		// Regex test: https://regex101.com/r/bMmJMg/1.
		$pattern = '/(?<![A-Za-z0-9_-])\.' . preg_quote( $moduleOrderClassBase, '/' ) . '_\d+(?![A-Za-z0-9_-])/';
		$rewrittenCss = preg_replace( $pattern, 'selector', $css );

		return null !== $rewrittenCss ? $rewrittenCss : $css;
	}

	/**
     * Sanitizes attribute values.
     *
     * Some values/keys have been changed from D4 format.
     * This function will return the new mapping of the provided value for any that have changed based on `desktopName`.
     * For example `top_left` is updated to `top left`.
     *
     * @since ??
     *
     * @param string|number $value Value to sanitize.
     * @param string $desktopName Attribute name.
     * @param string $moduleName Module name.
     *
     * @return string|number Sanitized value.
     */
    public static function valueSanitization($value, $desktopName, $moduleName) {
        $sanitizedValue = $value;

        if (in_array($moduleName, [ 'divi/map', 'divi/map-pin', 'divi/fullwidth-map' ]) && is_string($sanitizedValue)) {
            $sanitizedValue = self::maybeParseValue($desktopName, $sanitizedValue);
        }

        if ('divi/section' === $moduleName) {
            if (in_array($desktopName, [ 'fullwidth', 'specialty' ])) {
                $sanitizedValue = $desktopName;
            }
        }

        // diff(D4, Converted Value) For position_origin_a, position_origin_f and position_origin_r
        // field value: 'top_left', 'top_center', 'top_right', 'center_left', 'center_right', 'bottom_left', 'bottom_center'
        // and 'bottom_right' are migrated to 'top left', 'top center', 'top right', 'center left', 'center right',
        // 'bottom left', 'bottom center' and 'bottom right' respectively to keep consistent with D5 values.
        if (in_array($desktopName, [ 'position_origin_a', 'position_origin_f', 'position_origin_r' ])) {
            $sanitizedValue = str_replace('_', ' ', $value);
        }

        // diff(D4, Converted Value) For background_position, background_pattern_repeat_origin and background_mask_position
        // field value: 'top_left', 'top_center', 'top_right', 'center_left', 'center_right', 'bottom_left', 'bottom_center'
        // and 'bottom_right' are migrated to 'left top', 'center top', 'right top', 'left center', 'right center',
        // 'left bottom', 'center bottom' and 'right bottom' respectively to keep consistent with CSS rule, for example,
        // (X = left, Y = top) instead of (Y = top, X = left).
        if (in_array($desktopName, [ 'background_position', 'background_pattern_repeat_origin', 'background_mask_position' ])) {
            switch ($value) {
                case 'top_left':
                    $sanitizedValue = 'left top';
                    break;
                case 'top_center':
                    $sanitizedValue = 'center top';
                    break;
                case 'top_right':
                    $sanitizedValue = 'right top';
                    break;
                case 'center_left':
                    $sanitizedValue = 'left center';
                    break;
                case 'center_right':
                    $sanitizedValue = 'right center';
                    break;
                case 'bottom_left':
                    $sanitizedValue = 'left bottom';
                    break;
                case 'bottom_center':
                    $sanitizedValue = 'center bottom';
                    break;
                case 'bottom_right':
                    $sanitizedValue = 'right bottom';
                    break;
                default:
                    break;
            }
        }

        // Converted Gradient Unit to Gradient Length (e.g. '100vw' or '100%' or '100mm').
        if ('background_color_gradient_unit' === $desktopName) {
            $gradientUnit   = trim( (string) $value );
            $sanitizedValue = '' === $gradientUnit ? '100%' : '100' . $gradientUnit;
        }

        // diff(D4, Converted Value) Update the value of Custom CSS attributes, replacing '||' with '\n'.
        // While saving the value in D4, '||' was used to separate the lines, but in D5 we just use '\n'
        // in the encoded JSON string to separate the lines when decoded.
        // Sanitize custom CSS so invalid or incomplete declarations do not break block comment JSON or
        // parse_blocks() during save-time sanitization (see #47867).
        if (strpos($desktopName, 'custom_css_') === 0) {
            $sanitizedValue = str_replace('||', "\n", $value);
            if (is_string($sanitizedValue)) {
                // custom_css_free_form holds full CSS rules (selector + declarations + braces),
                // so sanitize_css() must be used to preserve selectors. All other custom_css_*
                // attributes hold declarations only and continue to use sanitize_css_properties().
                if ('custom_css_free_form' === $desktopName) {
                    $sanitizedValue = SavingUtility::sanitize_css($sanitizedValue, false, false, true);
					$sanitizedValue = self::_rewriteLegacyFreeFormOrderClassToSelector( $sanitizedValue, $moduleName );
                } else {
                    // Pass `true` to allow comments so D4 inline comments are preserved.
                    $sanitizedValue = SavingUtility::sanitize_css_properties($sanitizedValue, true);
                }
            }
        }

        if ('divi/portfolio' === $moduleName && 'fullwidth' === $desktopName) {
            $sanitizedValue = ('off' === $value) ? 'grid' : 'fullwidth';
        }

        // Some modules has `justified` as `text_orientation` value instead of `justify` ( i.e Menu, Text module etc. )
        // So we need to convert `justified` to `justify` to make it work correctly in D5 text options.
        if ('text_orientation' === $desktopName && 'justified' === $value) {
            $sanitizedValue = 'justify';
        }

        // Converts Divider arrangement value from D4 format to D5 format
        // above_content becomes above and below_content becomes below
        $dividerArrangements = [
            'bottom_divider_arrangement',
            'bottom_divider_arrangement_phone',
            'bottom_divider_arrangement_tablet',
            'top_divider_arrangement',
            'top_divider_arrangement_phone',
            'top_divider_arrangement_tablet',
        ];
        if (in_array($desktopName, $dividerArrangements)) {
            $sanitizedValue = ('above_content' === $value) ? 'above' : 'below';
        }

        // Restore specific encoded characters in the given value.
        $sanitizedValue = self::restoreSpecialChars( $sanitizedValue );

        return $sanitizedValue;
    }

	/**
	 * Infer gradient unit token from D4 gradient stops string.
	 *
	 * @since ??
	 *
	 * @param string $gradient_stops_value Raw D4 gradient stops value.
	 *
	 * @return string Inferred unit (for example `px`, `%`) or empty string.
	 */
	private static function _get_gradient_unit_from_stops( string $gradient_stops_value ): string {
		$supported_units = Sizing::$sizing_units;

		$stop_tokens = explode( '|', $gradient_stops_value );
		foreach ( $stop_tokens as $stop_token ) {
			// https://regex101.com/r/MjAPC8/1/unit-tests - Regex.
			if ( preg_match( '/\s-?\d+(?:\.\d+)?([a-z%]+)\s*$/i', trim( $stop_token ), $matches ) ) {
				$unit = strtolower( $matches[1] );
				if ( in_array( $unit, $supported_units, true ) ) {
					return $unit;
				}
			}
		}

		return '';
	}

	public static function getAttrMap($attrs, $attrName, $moduleName) {
		$value = $attrs[$attrName] ?? '';
		$generated = [];
		$desktopName = preg_replace('/(_tablet|_phone|__hover|__focus|__checked|__active|__sticky)$/', '', $attrName);
		$viewport = 'desktop';
		$state = 'value';

		$valueExpansionFunctionMapping = [
			'convertFontIcon'               => 'ET\Builder\Packages\Conversion\ValueExpansion::convertFontIcon',
			'convertBodyFont'               => 'ET\Builder\Packages\Conversion\ValueExpansion::convertBodyFont',
			'convertIcon'                   => 'ET\Builder\Packages\Conversion\ValueExpansion::convertIcon',
			'convertInlineFont'             => 'ET\Builder\Packages\Conversion\ValueExpansion::convertInlineFont',
			'convertSpacing'                => 'ET\Builder\Packages\Conversion\ValueExpansion::convertSpacing',
			'includedCategories'            => 'ET\Builder\Packages\Conversion\ValueExpansion::includedCategories',
			'convertIncludeCategoriesValue' => 'ET\Builder\Packages\Conversion\ValueExpansion::convertIncludeCategoriesValue',
			'convertIncludeTabsValue'       => 'ET\Builder\Packages\Conversion\ValueExpansion::convertIncludeTabsValue',
			'replaceLineBreakPlaceholder'   => 'ET\Builder\Packages\Conversion\ValueExpansion::replaceLineBreakPlaceholder',
			'convertCodeFieldContent'       => 'ET\Builder\Packages\Conversion\ValueExpansion::convertCodeFieldContent',
			'sortableListConverter'         => 'ET\Builder\Packages\Conversion\ValueExpansion::sortableListConverter',
			'convertImageAndIconWidth'      => 'ET\Builder\Packages\Conversion\ValueExpansion::convertImageAndIconWidth',
			'convertGradientStops'          => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::convertGradientStops',
			'convertSvgTransform'           => 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::convertSvgTransform',
			'convertTrueFalseToOnOff'       => 'ET\Builder\Packages\Conversion\ValueExpansion::convertTrueFalseToOnOff',
			'convertSuccessRedirectQuery'   => 'ET\Builder\Packages\Conversion\ValueExpansion::convertSuccessRedirectQuery',
			'convertEmailServiceAccount'    => 'ET\Builder\Packages\Conversion\ValueExpansion::convertEmailServiceAccount',
			'includedProjectCategories'     => 'ET\Builder\Packages\Conversion\ValueExpansion::includedProjectCategories',
			'convertLegacyGradientProperty' => 'ET\Builder\Packages\Conversion\ValueExpansion::convertLegacyGradientProperty',
			'convertSpamProviderAccount'    => 'ET\Builder\Packages\Conversion\ValueExpansion::convertSpamProviderAccount',
			'convertTextColorValue'         => 'ET\Builder\Packages\Conversion\ValueExpansion::convertTextColorValue',
			'convertToggleTitleTextColor'   => 'ET\Builder\Packages\Conversion\ValueExpansion::convertToggleTitleTextColor',
			'conditionalLogicConverter'     => 'ET\Builder\Packages\Conversion\ValueExpansion::conditionalLogicConverter',
		];

		$valueExpansionFunctionMap = apply_filters('divi.moduleLibrary.conversion.valueExpansionFunctionMap', $valueExpansionFunctionMapping);

		// Get all module's conversion map.
		/**
		 * Filters the module's conversion map for the Divi module during conversion.
		 * This filter allows developers to modify the module's conversion map for the Divi module during conversion.
		 *
		 * @param array $moduleConversionMap The module's conversion map.
		 */
		$moduleLibraryConversionMap = apply_filters('divi.conversion.moduleLibrary.conversionMap', []);

		$moduleConversionMap = $moduleLibraryConversionMap[$moduleName] ?? [];

		// error_log('$moduleConversionMap...');
		// error_log('$moduleConversionMap: ' . print_r($moduleConversionMap, true));

		// Check if this attribute should bypass responsive suffix detection.
		// Third-party modules can use the 'nonResponsiveAttributes' key in their conversion map
		// to specify attributes that end with _phone/_tablet but are not responsive attributes.
		$nonResponsiveAttributes = $moduleConversionMap['nonResponsiveAttributes'] ?? [];
		$isNonResponsive         = in_array($attrName, $nonResponsiveAttributes, true);

		// Get the proper viewport based on the attribute's suffix.
		// Check non-responsive first (most common case - empty or very small array).
		if ( $isNonResponsive ) {
			// Explicitly marked as non-responsive, treat as desktop attribute.
			$desktopName = $attrName; // Use full name as desktop name.
			$viewport    = 'desktop';
		} elseif (preg_match('/_tablet$/', $attrName)) {
			if (!isset($moduleConversionMap['optionEnableMap']) || !self::enabled('responsive', $desktopName, $attrs, ( $moduleConversionMap['optionEnableMap'] ?? [] ))) {
				return [];
			}
			$viewport = 'tablet';
		} elseif (preg_match('/_phone$/', $attrName)) {
			if (!isset($moduleConversionMap['optionEnableMap']) || !self::enabled('responsive', $desktopName, $attrs, ( $moduleConversionMap['optionEnableMap'] ?? [] ))) {
				return [];
			}
			$viewport = 'phone';
		}

		// Get the proper state based on the attribute's suffix
		if (preg_match('/__hover$/', $attrName)) {
			// If the attribute itself has __hover suffix, check if hover is enabled.
			// For attributes with optionEnableMap entries (like Background Options), use the mapped enable flag.
			// For standalone attributes, check the individual __hover_enabled attribute.
			// Only if no enable flag exists, assume hover is enabled based on __hover suffix presence.
			$optionEnableMap = $moduleConversionMap['optionEnableMap'] ?? [];
			$hasOptionEnableMapEntry = isset($optionEnableMap[$desktopName]);

			if ($hasOptionEnableMapEntry) {
				// Use optionEnableMap to check parent enable flag (e.g., background__hover_enabled for background_blend__hover).
				if (!self::enabled('hover', $desktopName, $attrs, $optionEnableMap)) {
					return [];
				}
			} else {
				// For standalone attributes, check if {desktopName}__hover_enabled exists and is 'off'.
				$hoverEnabledAttr = "{$desktopName}__hover_enabled";
				if (isset($attrs[$hoverEnabledAttr])) {
					$hoverEnabledValue = $attrs[$hoverEnabledAttr];
					// If hover_enabled is explicitly 'off', skip conversion.
					if (is_string($hoverEnabledValue) && strpos($hoverEnabledValue, 'off') === 0) {
						return [];
					}
				}
			}
			$state = 'hover';
		} elseif (preg_match('/__focus$/', $attrName)) {
			if (!self::enabled('focus', $desktopName, $attrs, ( $moduleConversionMap['optionEnableMap'] ?? [] ))) {
				return [];
			}
			$state = 'focus';
		} elseif (preg_match('/__checked$/', $attrName)) {
			if (!self::enabled('checked', $desktopName, $attrs, ( $moduleConversionMap['optionEnableMap'] ?? [] ))) {
				return [];
			}
			$state = 'checked';
		} elseif (preg_match('/__active$/', $attrName)) {
			if (!self::enabled('active', $desktopName, $attrs, ( $moduleConversionMap['optionEnableMap'] ?? [] ))) {
				return [];
			}
			$state = 'active';
		} elseif (preg_match('/__sticky$/', $attrName)) {
			$status = self::stickyStatus($attrs);

			$enabled_result = self::enabled('sticky', $desktopName, $attrs, ( $moduleConversionMap['optionEnableMap'] ?? [] ));

			// If the individual attribute has sticky enablement, process it regardless of whether
			// the element itself has sticky positioning. This handles cases where sticky is enabled
			// at a parent level and child elements have sticky state attributes.
			if (!$enabled_result) {
				return [];
			}

			// Use the sticky viewport if element is positioned stickily, otherwise default to desktop.
			$viewport = $status['active'] ? $status['viewport'] : 'desktop';
			$state = 'sticky';
		}

		// Handle known internal attributes that should not be converted
		if (in_array($desktopName, [
			'fb_built',
			'sticky_enabled',
			'hover_enabled',
			'focus_enabled',
			'checked_enabled',
			'active_enabled',
			'_dynamic_attributes',
			'_address',
			'_i',
		])) {
			return [];
		}

		// Discard attributes whose base name is a known invalid JavaScript stringify value.
		// These are produced by a null-safety bug in the D4 Visual Builder's background.jsx
		// where _getFieldByTemplate() returned undefined, which was then appended with
		// state suffixes (e.g. '__hover', '__focus', '__checked', '__active', '__sticky') via template literals, resulting in
		// corrupted attribute names like 'null__hover' or 'undefined__hover' being saved to
		// the database. If allowed through, these attributes end up in unknownAttributes and
		// incorrectly trigger backward-compatibility mode for the entire module.
		//
		// @see https://github.com/elegantthemes/Divi/issues/48821
		$invalid_js_base_names = [ 'null', 'undefined', 'false', 'true' ];
		if ( in_array( $desktopName, $invalid_js_base_names, true ) ) {
			return [];
		}

		// Check if the attribute is deprecated in the module's conversion outline
		$deprecatedAttributes = $moduleConversionMap['deprecatedMap'] ?? [];

		if (in_array($desktopName, $deprecatedAttributes, true)) {
			return [];
		}

		// Check if the attribute should be deprecated via filter hook (for pattern-based deprecation).
		/**
		 * Filters whether an attribute should be deprecated during conversion.
		 *
		 * This filter allows pattern-based deprecation of attributes that cannot be handled
		 * by exact string matching in deprecatedMap (e.g., attributes with numeric suffixes).
		 *
		 * @param bool   $should_deprecate Whether the attribute should be deprecated. Default false.
		 * @param string $desktopName      The desktop name of the attribute (without responsive/hover/sticky suffixes).
		 * @param string $moduleName       The module name (e.g., 'divi/row', 'divi/row-inner').
		 * @param array  $attrs            The full attributes array for the module.
		 */
		$should_deprecate = apply_filters('divi_conversion_deprecated_attribute', false, $desktopName, $moduleName, $attrs);

		if ($should_deprecate) {
			return [];
		}

		// Special handling for use_background_color: set background color to empty string if off.
		if ('use_background_color' === $desktopName) {
			// If use_background_color is 'off', explicitly set the background color to empty string.
			if ('off' === $value) {
				$colorPath               = str_replace('*', "{$viewport}.{$state}", 'module.decoration.background.*.color');
				$generated[$colorPath] = ''; // Empty string overrides default color.
			}

			return $generated;
		}

		// Determine the conversion key for the attribute
		$attrNameConversionMap = $moduleConversionMap['attributeMap'][$desktopName] ?? null;

		if (is_null($attrNameConversionMap) || $attrNameConversionMap === '') {
			// Handle attributes not present in attributeMap
			if (!empty($moduleConversionMap['attributeMap']) && array_key_exists($desktopName, $moduleConversionMap['attributeMap'])) {
				$attrNameConversionMap = $moduleConversionMap['attributeMap'][$desktopName];
			} elseif (in_array($desktopName, [
				'admin_label',
				'theme_builder_area',
				'global_colors_info',
				'on',
				'locked',
				'open',
			])) {
				$attrNameConversionMap = self::camelCase($desktopName) . '.*';
			} elseif (in_array($desktopName, [
				'global_module',
				'global_parent',
				'nonconvertible',
				'shortcodeName',
			])) {
				$attrNameConversionMap = self::camelCase($desktopName);
			} elseif (in_array($desktopName, [
				'_builder_version',
				'_module_preset',
			])) {
				$attrNameConversionMap = self::camelCase(substr($desktopName, 1));
			} else {
				$attrNameConversionMap = "unknownAttributes.{$desktopName}";
			}
		}

		// We need to make it possible to convert a single D4 module attribute to one of multiple D5 module attributes.
		// The corresponding D5 conversion is handled by the `conditionalConversionMap` property. This map defines a
		// callback function that returns the correct conversion path based on the value of the D4 attribute. Only one
		// path from the `conditionalConversionMap` is used based on the conditions.
		// For example:
		// In the Blurb module,
		// The `image_icon_width` should be converted to either `imageIcon.advanced.width.*.image`
		// or `imageIcon.advanced.width.*.icon` because unlike in D4, the Blurb image width
		// and icon width are separated in D5.
		// The path is picked based on whether the `use_icon` attribute is `on` or `off` in the imported layout.
		// If the `use_icon` attribute is `on`,
		// the `imageIcon.advanced.width.*.icon` is used, otherwise the path `imageIcon.advanced.width.*.image` is used.
		// @see https://github.com/elegantthemes/Divi/issues/34247.
		if (isset($moduleConversionMap['conditionalAttributeConversionFunctionMap'][$desktopName])) {
			$callback = $moduleConversionMap['conditionalAttributeConversionFunctionMap'][$attrName];
			// Ensure the callback is callable.
			if (is_callable($callback)) {
				$attrNameConversionMap = $callback((array)$attrNameConversionMap, $attrs);
			}
		}

		$fullAttributePath = str_replace('*', "{$viewport}.{$state}", $attrNameConversionMap);

		if (isset($moduleConversionMap['valueExpansionFunctionMap'][$desktopName])) {
			$valueExpansionFunction = $moduleConversionMap['valueExpansionFunctionMap'][$desktopName];

			// There are two possible ways this value will show up as:
			// 1. its already a callable
			// 2. We need to look in $valueExpansionFunctionMap[$valueExpansionFunction] to get the callable

			if ( ! is_callable( $valueExpansionFunction ) ) {
				if ( !empty($valueExpansionFunctionMap[$valueExpansionFunction]) ) {
					$valueExpansionFunction = $valueExpansionFunctionMap[$valueExpansionFunction];
				}
			}

			// by the time we get here, we better have a real callable, or we throw an exception
			if ( is_callable( $valueExpansionFunction ) ) {
				$expandedValues = $valueExpansionFunction($value, [
					'attrs'       => $attrs,
					'desktopName' => $desktopName,
					'moduleName'  => $moduleName,
					'viewport'    => $viewport,
					'state'       => $state,
				]);

				// If the valueExpansionFunction returns an WP_Error, skip the conversion.
				$is_skip = is_wp_error( $expandedValues );

				if ( ! $is_skip ) {
					if (is_array($expandedValues) || is_object($expandedValues)) {
						foreach ($expandedValues as $expandedAddress => $expandedValue) {
							$generated["{$fullAttributePath}.{$expandedAddress}"] = $expandedValue;
						}

						// D4 can store absolute stop units (for example `6px`) in gradient stops while
						// omitting `*_gradient_unit`. Preserve D4 rendering by inferring D5 gradient length unit.
						if (
							0 === strpos( $desktopName, 'background_color_gradient_stops' ) &&
							str_ends_with( $fullAttributePath, '.gradient.stops' ) &&
							is_string( $value )
						) {
							$gradient_unit_attr_name  = str_replace( '_stops', '_unit', $desktopName );
							$gradient_unit_attr_value = $attrs[ $gradient_unit_attr_name ] ?? '';
							$has_explicit_gradient_unit = is_string( $gradient_unit_attr_value ) && '' !== trim( $gradient_unit_attr_value );

							if ( ! $has_explicit_gradient_unit ) {
								$inferred_gradient_unit = self::_get_gradient_unit_from_stops( $value );
								if ( '' !== $inferred_gradient_unit ) {
									$generated[ str_replace( '.stops', '.length', $fullAttributePath ) ] = '100' . $inferred_gradient_unit;
								}
							}
						}
					} else {
						$generated[$fullAttributePath] = $expandedValues;
					}
				}
			} else {
				throw new \Exception('Value expansion function is not callable. valueExpansionFunction: ' . $valueExpansionFunction);
			}
		}

		// Handle value expansion logic
		// if (isset($moduleConversionMap['valueExpansionFunctionMap'][$desktopName]) && is_callable($moduleConversionMap['valueExpansionFunctionMap'][$desktopName])) {
		//  $expandedValues = $moduleConversionMap['valueExpansionFunctionMap'][$desktopName]($value);

		//  if (is_array($expandedValues) || is_object($expandedValues)) {
		//      foreach ($expandedValues as $expandedAddress => $expandedValue) {
		//          $generated["{$fullAttributePath}.{$expandedAddress}"] = $expandedValue;
		//      }
		//  } else {
		//      $generated[$fullAttributePath] = $expandedValues;
		//  }
		// }

		else if (is_string($moduleConversionMap['attributeMap'][$desktopName] ?? null)) {
			// Keep inferred unitful gradient lengths from stops when D4 unit attr is empty.
			if ( 0 === strpos( $desktopName, 'background_color_gradient_unit' ) && '' === trim( (string) $value ) ) {
				$gradient_stops_attr_name  = str_replace( '_gradient_unit', '_gradient_stops', $attrName );
				$gradient_stops_attr_value = $attrs[ $gradient_stops_attr_name ] ?? '';

				if ( is_string( $gradient_stops_attr_value ) ) {
					$inferred_gradient_unit = self::_get_gradient_unit_from_stops( $gradient_stops_attr_value );
					if ( '' !== $inferred_gradient_unit ) {
						return [];
					}
				}
			}

			$generated[$fullAttributePath] = self::valueSanitization($value, $desktopName, $moduleName);
		} else {
			$generated[$fullAttributePath] = $value;
		}

		return $generated;
	}

	/**
	 * Check if a post is a global layout template for conversion purposes.
	 *
	 * This method is specifically for use during conversion and includes fallback logic
	 * to detect imported D4 global modules that might not have taxonomy terms set yet.
	 *
	 * @since ??
	 *
	 * @param int|null $post_id Post ID to check.
	 *
	 * @return bool True if the post is a global layout template, false otherwise.
	 */
	public static function is_global_layout_template_for_conversion( ?int $post_id ): bool {
		// Try the standard check first (checks for 'global' scope taxonomy).
		if ( GlobalLayout::is_global_layout_template( $post_id ) ) {
			return true;
		}

		// Fallback for imported D4 global modules that don't have scope taxonomy set yet.
		// Return false if no post ID provided.
		if ( empty( $post_id ) ) {
			return false;
		}

		// Check if post exists and is a Divi Library item.
		$post = get_post( $post_id );
		if ( ! $post || ET_BUILDER_LAYOUT_POST_TYPE !== $post->post_type ) {
			return false;
		}

		// Check if this is a module with selective sync metadata (_et_pb_excluded_global_options).
		// Global modules are the only ones that have this meta, so its presence indicates a global module.
		$module_type = get_post_meta( $post_id, '_et_pb_module_type', true );
		if ( ! empty( $module_type ) ) {
			$excluded_options = get_post_meta( $post_id, '_et_pb_excluded_global_options', true );
			if ( ! empty( $excluded_options ) ) {
				return true;
			}
		}

		return false;
	}

	static function encodeAttrs( array $attrs, string $moduleName, ?string $content = null, $originalShortcode = null, $shortcodeName = null, $postId = null, ?array $parentAttrs = null, bool $is_ab_testing_active = false ): string {
		// Convert the attributes to the new format.
		$convertedAttrs = self::convertAttrs($attrs, $moduleName, $content, false, $is_ab_testing_active);

		// Define structural modules that cannot have selective sync.
		$structural_modules = [ 'divi/section', 'divi/row', 'divi/row-inner', 'divi/column', 'divi/column-inner' ];

		// Check if this module is a child of a global module (has globalParent attribute).
		// Child modules should not have selective sync attributes.
		$has_global_parent = isset( $convertedAttrs['globalParent'] );

		// Add selective sync attributes if this is a global layout template.
		// Structural modules can be global, but cannot have selective sync (localAttrsMap/localChildren).
		// Child modules (with globalParent) cannot have selective sync.
		$is_structural_module = in_array( $moduleName, $structural_modules, true );

		if ( self::is_global_layout_template_for_conversion( $postId ) && ! $is_structural_module && ! $has_global_parent ) {
			$excluded_options = get_post_meta( $postId, '_et_pb_excluded_global_options', true );

			if ( ! empty( $excluded_options ) ) {
				$excluded_options_array = json_decode( $excluded_options, true );

				if ( is_array( $excluded_options_array ) && ! empty( $excluded_options_array ) ) {
					// Get the module's conversion map (D4 attr name => D5 path).
					$moduleLibraryConversionMap = apply_filters('divi.conversion.moduleLibrary.conversionMap', []);
					$moduleConversionMap = $moduleLibraryConversionMap[$moduleName] ?? [];
					$attributeMap = $moduleConversionMap['attributeMap'] ?? [];


					$local_attrs_map = [];
					$local_children  = false;
					$processed_paths = []; // Track already processed paths to avoid duplicates.

					foreach ( $excluded_options_array as $d4_attr_name ) {
						// Check for content/children exclusion.
						if ( in_array( $d4_attr_name, [ 'et_pb_content_field', 'content', 'content__hover' ], true ) ) {
							$local_children = true;
							continue;
						}

						// Remove responsive and state suffixes to get base attribute name.
						$base_attr_name = preg_replace( '/_(tablet|phone|last_edited)$/', '', $d4_attr_name );
						$base_attr_name = preg_replace( '/__(hover|hover_enabled|sticky|sticky_enabled)$/', '', $base_attr_name );

					// Look up D5 path in attribute map.
					if ( ! empty( $attributeMap ) && isset( $attributeMap[ $base_attr_name ] ) ) {
							$d5_path_raw = $attributeMap[ $base_attr_name ];
							$d5_path     = $d5_path_raw;

							// Clean up the path for localAttrsMap (remove wildcards and .value suffix).
							// Keep property names after wildcards as they will be split into subName.
							// Example: 'image.innerContent.*.src' → 'image.innerContent.src' → {attrName: 'image.innerContent', subName: 'src'}
							// Example: 'module.decoration.border.*.radius' → 'module.decoration.border.radius' → {attrName: 'module.decoration.border', subName: 'radius'}
							$d5_path = str_replace( '.*.', '.', $d5_path );
							$d5_path = str_replace( '.*', '', $d5_path );
							$d5_path = preg_replace( '/\.value$/', '', $d5_path );


							// Skip if we've already processed this path.
							if ( isset( $processed_paths[ $d5_path ] ) ) {
								continue;
								}
							$processed_paths[ $d5_path ] = true;

							// Determine if we need to split into attrName and subName.
							// For paths with property names after base paths, split the last segment as subName.
							$attr_entry = [];

							// Check if this is a decoration attribute with multiple segments.
							// Example: 'module.decoration.border.radius' → attrName: 'module.decoration.border', subName: 'radius'
							if ( preg_match( '/^(module\.decoration\.[^.]+)\.(.+)$/', $d5_path, $matches ) ) {
								$attr_entry['attrName'] = $matches[1];
								$attr_entry['subName']  = $matches[2];
								}
							// Check if this is a path with property after innerContent.
							// Example: 'image.innerContent.src' → attrName: 'image.innerContent', subName: 'src'
							elseif ( preg_match( '/^(.+\.innerContent)\.(\w+)$/', $d5_path, $matches ) ) {
								$attr_entry['attrName'] = $matches[1];
								$attr_entry['subName']  = $matches[2];
								}
							// Generic handling for wildcard-property paths not covered by the
							// module.decoration.* and innerContent.* patterns above.
							// Example: 'title.decoration.font.font.*.color' -> attrName: 'title.decoration.font.font', subName: 'color'.
							elseif ( str_contains( $d5_path_raw, '.*.' ) && str_contains( $d5_path, '.' ) ) {
								$last_dot_index = strrpos( $d5_path, '.' );
								$attr_entry['attrName'] = substr( $d5_path, 0, $last_dot_index );
								$attr_entry['subName']  = substr( $d5_path, $last_dot_index + 1 );
							}
							// Otherwise, simple attribute with just attrName.
							else {
								$attr_entry['attrName'] = $d5_path;
								}

							$local_attrs_map[] = $attr_entry;
						}
					}

					if ( ! empty( $local_attrs_map ) ) {
						$convertedAttrs['localAttrsMap'] = $local_attrs_map;
					}

					if ( $local_children ) {
						$convertedAttrs['localChildren'] = true;
					}
				}
			}
		}

		// If module contains unknownAttributes add originalShortcode to the content attribute.
		// This is needed for converting modules with unknownAttributes to ShortcodeModule.
		if (isset($convertedAttrs['unknownAttributes'])) {

			// When shortcode is parsed in parseShortcode, we didn't knew that shortcode
			// wont be converted due to unknownAttributes. So we need to add the shortcodeName here.
			$convertedAttrs['shortcodeName'] = $shortcodeName;
			$convertedAttrs['content'] = $originalShortcode;
		}

		// For Slide modules: inherit text_orientation from parent slider if not explicitly set.
		// In D4, slide modules inherit the parent slider's text_orientation value.
		// If the slide doesn't have its own text_orientation, use the parent's value.
		// If neither has a value, use 'center' (D4 default for sliders).
		if ( 'divi/slide' === $moduleName ) {
			$text_orientation_exists = null !== self::get( $convertedAttrs, [ 'module', 'advanced', 'text', 'text', 'desktop', 'value', 'orientation' ] );

			if ( ! $text_orientation_exists ) {
				// Inherit from parent if available, otherwise use D4 default 'center'.
				$parent_text_orientation = isset( $parentAttrs['text_orientation'] ) ? $parentAttrs['text_orientation'] : 'center';
				self::set( $convertedAttrs, [ 'module', 'advanced', 'text', 'text', 'desktop', 'value', 'orientation' ], $parent_text_orientation );
			}
		}

		// For Slider modules: if text_orientation doesn't exist, set it to 'center' (D4 default).
		// D4 Slider modules default to text_orientation='center' but don't store it in the shortcode.
		$slider_parent_modules = [ 'divi/slider', 'divi/fullwidth-slider', 'divi/post-slider', 'divi/fullwidth-post-slider' ];
		if ( in_array( $moduleName, $slider_parent_modules, true ) ) {
			$text_orientation_exists = null !== self::get( $convertedAttrs, [ 'module', 'advanced', 'text', 'text', 'desktop', 'value', 'orientation' ] );

			if ( ! $text_orientation_exists ) {
				// Use D4 default 'center' for slider modules.
				self::set( $convertedAttrs, [ 'module', 'advanced', 'text', 'text', 'desktop', 'value', 'orientation' ], 'center' );
			}
		}

		// Encode the converted attributes.
		$encodedAttrs = serialize_block_attributes($convertedAttrs);

		return $encodedAttrs;
	}

	static function convertShortcodeToGbFormat( $shortcodePart, $gbReset = true, $globalID = null, $postId = null, $isFirstLevel = true, $parentAttrs = null, bool $is_ab_testing_active = false, bool $convert_global_instances_as_regular_modules = false ) {
		static $gbString = '';
		static $convertibleModulesSlug = null;
		static $moduleCollections = null;

		if ($gbReset) {
			$gbString = '';
			// Ensure top-level conversion uses the latest registered modules map.
			$convertibleModulesSlug = null;
			$moduleCollections      = null;
		}

		if (null === $moduleCollections || null === $convertibleModulesSlug) {
			$moduleCollections      = self::getModuleCollections();
			$convertibleModulesSlug = [];

			foreach ($moduleCollections as $convertibleModule) {
				$convertibleModulesSlug[$convertibleModule['d4Shortcode']] = $convertibleModule['name'];
			}
		}

		foreach ($shortcodePart as $element) {
			$nonconvertible = 'no';

			$moduleName = empty($convertibleModulesSlug[$element['name']])
				? 'divi/shortcode-module'
				: $convertibleModulesSlug[$element['name']];

			// If module becomes shortcode-module because it's not convertible, mark it as nonconvertible.
			if ($moduleName === 'divi/shortcode-module') {
				$nonconvertible = 'yes';
			}

			$content = is_string($element['content']) && $element['content'] !== '' ? $element['content'] : null;

			// if $element['attrs'] is empty, set it to an empty array
			// this can occur if the shortcode has 0 attributes, e.g. [et_pb_accordion][et_pb_accordion_item something="blah...
			if ( ! isset( $element['attrs'] ) || '' === $element['attrs'] ) {
				$element['attrs'] = [];
			}

			// Portability import deglobalizes all global references in the inline D4 tree.
			if ( $convert_global_instances_as_regular_modules && isset( $element['attrs']['global_parent'] ) ) {
				unset( $element['attrs']['global_parent'] );
			}

			// phpcs:ignore Universal.Operators.DisallowShortTernary.Found -- Short ternary is appropriate here for fallback to global_module attribute.
			$globalModuleID = $globalID ?: (isset($element['attrs']['global_module']) ? $element['attrs']['global_module'] : null);

			// Handle global module instance conversion.
			$isGlobalModuleInstance = ! empty( $globalModuleID ) && empty( $globalID );

			if ( $isGlobalModuleInstance ) {
				if ( $convert_global_instances_as_regular_modules ) {
					// Portability import: deglobalize and convert the full inline tree as regular modules.
					unset( $element['attrs']['global_module'] );
					unset( $element['attrs']['saved_tabs'] );
					$globalModuleID         = null;
					$isGlobalModuleInstance = false;
				} else {
					// On-site migration: convert to self-closing divi/global-layout placeholder.
					$instanceAttrs = $element['attrs'];
					unset( $instanceAttrs['global_module'] );
					unset( $instanceAttrs['saved_tabs'] ); // Don't convert saved_tabs to instance.
					unset( $instanceAttrs['nonconvertible'] ); // Internal conversion metadata, not a user-set override.
					unset( $instanceAttrs['shortcodeName'] ); // Internal conversion metadata, not a user-set override.

					$convertedAttrs = self::convertAttrs( $instanceAttrs, $moduleName, $content, false, $is_ab_testing_active );

					$placeholderAttrs = [
						'globalModule' => $globalModuleID,
						'blockName'    => $moduleName,
					];

					if ( ! empty( $convertedAttrs ) ) {
						$placeholderAttrs['localAttrs'] = $convertedAttrs;
					}

					$encodedAttrs = serialize_block_attributes( $placeholderAttrs );
					$gbString    .= "<!-- wp:divi/global-layout {$encodedAttrs} --><!-- /wp:divi/global-layout -->";

					continue;
				}
			}

			// Only add global_parent if we're in a global template context (globalID set)
			// AND we're not at the first level (root module shouldn't have globalParent).
			$should_add_global_parent = !empty($globalID) && !$isFirstLevel;
			$attrs = array_merge($element['attrs'], $should_add_global_parent ? [ 'global_parent' => $globalModuleID ] : []);

			$encodedAttrs = self::encodeAttrs(
				$attrs,
				$moduleName,
				$content,
				$element['originalShortcode'] ?? null,
				$element['name'] ?? null,
				$postId,
				$parentAttrs,
				$is_ab_testing_active
			);

			// If module contains unknownAttributes, this means that some attributes
			// may be added by Divi 4 third-party plugin, so we should skip conversion in this case.
			if ( str_contains( $encodedAttrs, 'unknownAttributes' ) && isset( $convertibleModulesSlug[$element['name']] ) ) {
				$nonconvertible = 'yes';
				$moduleName     = 'divi/shortcode-module';

				// Replace element content with the original shortcode.
				$element['content'] = $element['originalShortcode'];
			}

			// if $encodedAttrs is an empty array, set it to ''
			$encodedAttrs = $encodedAttrs === '[]' ? '' : $encodedAttrs;

			if ( is_array( $element['content'] ) ) {
				$gbString .= "<!-- wp:{$moduleName} {$encodedAttrs} -->";

				// Pass parent attrs to children for slider modules so slide children can inherit text_orientation.
				$slider_parent_shortcodes = [ 'et_pb_slider', 'et_pb_fullwidth_slider', 'et_pb_post_slider', 'et_pb_fullwidth_post_slider' ];
				$child_parent_attrs       = in_array( $element['name'], $slider_parent_shortcodes, true ) ? $element['attrs'] : null;

				self::convertShortcodeToGbFormat( $element['content'], false, $globalModuleID, $postId, false, $child_parent_attrs, $is_ab_testing_active, $convert_global_instances_as_regular_modules );
				$gbString .= "<!-- /wp:{$moduleName} -->";
			} elseif ($nonconvertible === 'yes') {
				$gbString .= "<!-- wp:{$moduleName} {$encodedAttrs} -->{$element['content']}<!-- /wp:{$moduleName} -->";
			} else {
				$gbString .= "<!-- wp:{$moduleName} {$encodedAttrs} --><!-- /wp:{$moduleName} -->";
			}
		}

		return $gbString;
	}

	static function getModuleCollections() {
		static $moduleCollections = null;
		static $registered_modules_count = null;

		$current_registered_modules_count = 0;

		// Ensure shortcode framework is initialized so 3rd-party modules
		// are registered in the block registry before the conversion map is built.
		self::initialize_shortcode_framework();

		$all_registered_modules          = \WP_Block_Type_Registry::get_instance()->get_all_registered();
		$current_registered_modules_count = count( $all_registered_modules );

		if ( null !== $moduleCollections && $registered_modules_count === $current_registered_modules_count ) {
			return $moduleCollections;
		}

		// $moduleCollections = [
		//  [
		//      'name' => 'divi/text',
		//      'd4Shortcode' => 'et_pb_text',
		//  ],
		//  [
		//      'name' => 'divi/section',
		//      'd4Shortcode' => 'et_pb_section',
		//  ],
		// ];
		$moduleCollections = [];

		foreach ($all_registered_modules as $module) {
			if ( !empty( $module->d4Shortcode ) ) {

				$_module = [
					'name' => $module->name,
					'd4Shortcode' => $module->d4Shortcode,
				];

				if (isset($module->childrenName)) {
					$_module['childrenName'] = $module->childrenName;
				}

				// [
				//  'name' => $module->name,
				//  'd4Shortcode' => $module->d4Shortcode,
				//  'childrenName' => $module->childrenName ?? null,
				// ];
				$moduleCollections[ $module->name ] = $_module;
			}
		}

		// Start processing the metadata for the modules that are not already in the moduleCollections.
		$all_modules_metadata = ModuleRegistration::get_all_core_modules_metadata();

		foreach ( $all_modules_metadata as $module_metadata ) {
			$module_name = $module_metadata['name'];

			// If the module is already in the moduleCollections, skip it.
			if ( isset( $moduleCollections[ $module_name ] ) ) {
				continue;
			}

			// If the module has no d4Shortcode, skip it.
			if ( ! isset( $module_metadata['d4Shortcode'] ) ) {
				continue;
			}

			$processed = ModuleRegistration::process_conversion_outline( $module_metadata );

			if ( $processed ) {
				$_module = [
					'name'        => $module_name,
					'd4Shortcode' => $module_metadata['d4Shortcode'],
				];

				if ( isset( $module_metadata['childrenName'] ) ) {
					$_module['childrenName'] = $module_metadata['childrenName'];
				}

				$moduleCollections[ $module_name ] = $_module;
			}
		}

		/**
		 * Filters the module collections used for Divi 4 to Divi 5 conversion.
		 *
		 * This filter allows developers to modify the collection of modules that are available
		 * for conversion from Divi 4 shortcodes to Divi 5 blocks. The collection is built from
		 * registered modules and module metadata, and includes modules that have a corresponding
		 * Divi 4 shortcode.
		 *
		 * @since ??
		 *
		 * @param array $moduleCollections Array of module collections. Each module collection
		 *                                 contains:
		 *                                 - 'name' (string): The module name (e.g., 'divi/text')
		 *                                 - 'd4Shortcode' (string): The Divi 4 shortcode (e.g., 'et_pb_text')
		 *                                 - 'childrenName' (string, optional): The children module name if applicable
		 */
		$moduleCollections = apply_filters( 'divi.moduleLibrary.conversion.moduleCollections', $moduleCollections );
		$registered_modules_count = $current_registered_modules_count;

		return $moduleCollections;
	}


	/**
	 * Convert content when required.
	 *
	 * Converts the content from D4 format to GB format.
	 *
	 * @since ??
	 *
	 * @param string $content_raw The content to be converted.
	 * @param bool $run_migration Whether to run the migration.
	 * @param int|null $post_id Optional post ID for context (used for global module selective sync).
	 * @param bool $convert_global_instances_as_regular_modules Whether to convert global module instances as regular modules during portability import.
	 * @return string The converted content.
	 */
	static function maybeConvertContent( $content_raw, $run_migration = true, $post_id = null, bool $convert_global_instances_as_regular_modules = false ) {
		// Maybe convert global colors data.
		GlobalData::maybe_convert_global_colors_data();

		// Handle placeholder-wrapped shortcodes that need conversion.
		// This catches content that has the D5 wrapper but still contains D4 shortcodes inside.
		// Common scenario: Content was wrapped but conversion was skipped or failed, resulting in
		// wrapped but unconverted shortcodes that VB cannot parse (appears empty) while FE works.
		// We unwrap the content here and let the existing conversion workflow handle it properly.
		// The conversion workflow will add placeholder wrapper when needed (e.g., for global templates).
		if ( false !== strpos( $content_raw, '<!-- wp:divi/placeholder -->' ) && false !== strpos( $content_raw, '[et_pb_' ) ) {
			// Extract content between placeholder tags using same pattern as convertShortcodeModulesInD5Content.
			$placeholderPattern = '/^<!-- wp:divi\/placeholder[^>]*? -->(.*?)<!-- \/wp:divi\/placeholder -->$/s';

			if ( preg_match( $placeholderPattern, $content_raw, $matches ) ) {
				// Unwrap the content and let conversion workflow handle it.
				$content_raw = trim( $matches[1] );
			}
		}

		// Normalize leading/trailing whitespace before processing.
		// D4 content saved by some environments (e.g. older Divi versions, certain editors)
		// may have leading CRLF/LF characters before the first [et_pb_section shortcode.
		// The conversion regex '@^\[...@' requires '[' at position 0 — any leading whitespace
		// causes a silent failure that produces an empty placeholder instead of converting.
		$content_migrated = trim( $content_raw );

		if ( $run_migration && ! str_contains( $content_migrated, '<!-- wp:divi/layout' ) ) {
			$content_migrated = ShortcodeMigration::maybe_migrate_legacy_shortcode( $content_migrated );
			$content_migrated = Migration::get_instance()->migrate_content_shortcode( $content_migrated );
		}

		$content   = self::normalizeAbSubjectId( $content_migrated );
		$converted = $content;
		$moduleCollections = self::getModuleCollections();

		// Determine whether split testing is active for this post.
		// When inactive, filterSplitTestAttributes must not overwrite disabled_on.
		$is_ab_testing_active = null !== $post_id && 'on' === get_post_meta( $post_id, '_et_pb_use_ab_testing', true );

		// Define the startsWithShortcodeRegExp equivalent in PHP.
		// See add_shortcode() function in WordPress for reference.
		// Ref: https://regexr.com/84q4v
		$startsWithShortcodeRegExp = '@^\[[^<>&/\[\]\x00-\x20=]+@';

		if (preg_match($startsWithShortcodeRegExp, $content)) {
			// If this is a global layout template, pass post_id as globalID to add globalParent to children.
			$is_global_template     = self::is_global_layout_template_for_conversion( $post_id );
			$global_id_for_children = $is_global_template ? $post_id : null;

			$converted = self::convertShortcodeToGbFormat(
				self::parseShortcode($content, $moduleCollections),
				true,
				$global_id_for_children,
				$post_id,
				true,
				null,
				$is_ab_testing_active,
				$convert_global_instances_as_regular_modules
			);

			// Wrap global layout templates in placeholder block.
			if ( $is_global_template ) {
				$converted = '<!-- wp:divi/placeholder -->' . $converted . '<!-- /wp:divi/placeholder -->';
			}
		} else if ( str_contains( $content, '<!-- wp:divi/layout' ) ) {
			// parse blocks and iteratively convert them or concatenate them
			// as $blockObjects is an array of blocks with their details.
			$blockObjects = parse_blocks($content);  // parse_blocks is a WordPress function to parse blocks
			$converted = '';
			foreach ($blockObjects as $block) {
				if ('divi/layout' === $block['blockName']) {
					$layout_inner_html = trim( (string) ( $block['innerHTML'] ?? '' ) );

					if ( false === strpos( $layout_inner_html, '[et_pb_' ) ) {
						$converted .= serialize_block( $block );
						continue;
					}

					$converted .= self::convertShortcodeToGbFormat(
						self::parseShortcode( $layout_inner_html, $moduleCollections ),
						true,
						null,
						$post_id,
						true,
						null,
						$is_ab_testing_active,
						$convert_global_instances_as_regular_modules
					);
				} else {
					// Preserve non-layout blocks using WordPress serializer to keep valid block syntax.
					// This avoids malformed output such as `<!-- wp:paragraph [] -->`.
					$converted .= serialize_block( $block );
				}
			}
		} else if (strpos($content, 'divi/shortcode-module') !== false) {
			$converted = self::convertShortcodeModulesInD5Content( $content, $moduleCollections, $convert_global_instances_as_regular_modules );
		}

		return $converted;
	}

	/**
	 * Convert shortcode-module blocks in D5 content to proper D5 blocks.
	 * This method specifically targets divi/shortcode-module blocks, extracts their shortcode content,
	 * and uses the existing conversion system to convert them to proper D5 blocks.
	 *
	 * @param string $content D5 content containing shortcode-module blocks
	 * @param array $moduleCollections Available module collections
	 * @param bool $convert_global_instances_as_regular_modules Whether to convert global module instances as regular modules during portability import.
	 * @return string Converted content with shortcode-modules replaced by proper D5 blocks
	 */
	static function convertShortcodeModulesInD5Content( $content, $moduleCollections, bool $convert_global_instances_as_regular_modules = false ) {
		// Initialize shortcode framework to ensure third-party modules are loaded
		self::initialize_shortcode_framework();

		// Step 1: Remove placeholder wrapper if present (simple string replacement)
		$placeholderPattern = '/<!-- wp:divi\/placeholder[^>]*? -->(.*?)<!-- \/wp:divi\/placeholder -->/s';
		$contentToProcess   = $content;
		$placeholderWrapper = '';

		if (preg_match($placeholderPattern, $content, $matches)) {
			$placeholderWrapper = $matches[0]; // Store the full wrapper
			$contentToProcess   = $matches[1]; // Extract inner content
		}

		// Step 2: Parse blocks from the extracted content (without placeholder wrapper)
		$blockObjects = parse_blocks($contentToProcess);

		$converted            = '';
		$shortcodeModuleCount = 0;

		// Step 3: Process the content with nested block traversal
		foreach ($blockObjects as $index => $block) {
			$blockName = $block['blockName'] ?? 'null';

			$processedBlock = self::processBlockRecursively( $block, $moduleCollections, $shortcodeModuleCount, $convert_global_instances_as_regular_modules );
			$converted      .= $processedBlock;
		}

		// Step 4: Wrap converted content back in placeholder wrapper if one was removed
		$finalContent = $converted;

		if ( ! empty($placeholderWrapper)) {
			// Simple string replacement: wrap converted content with placeholder tags
			$finalContent = '<!-- wp:divi/placeholder -->' . $converted . '<!-- /wp:divi/placeholder -->';
		}

		return $finalContent;
	}

	/**
	 * Process blocks recursively to handle nested structures (section -> row -> column -> module)
	 *
	 * @param array $block               Block to process
	 * @param array $moduleCollections   Available module collections
	 * @param int &$shortcodeModuleCount Reference to shortcode module counter
	 * @param bool $convert_global_instances_as_regular_modules Whether to convert global module instances as regular modules during portability import.
	 *
	 * @return string Processed block content
	 */
	static function processBlockRecursively( array $block, array $moduleCollections, int &$shortcodeModuleCount, bool $convert_global_instances_as_regular_modules = false ): string {
		$blockName = $block['blockName'] ?? 'null';

		if ('divi/shortcode-module' === $block['blockName']) {
			// Found a shortcode-module, process it
			++$shortcodeModuleCount;

			return self::processShortcodeModuleBlock( $block, $moduleCollections, $shortcodeModuleCount, $convert_global_instances_as_regular_modules );

		} else if ( ! empty($block['innerBlocks'])) {
			// Block has nested blocks, process them recursively
			$processedInnerContent = '';

			foreach ($block['innerBlocks'] as $innerBlock) {
				$processedInnerContent .= self::processBlockRecursively( $innerBlock, $moduleCollections, $shortcodeModuleCount, $convert_global_instances_as_regular_modules );
			}

			// Reconstruct the parent block with processed inner content
			$attrs           = ! empty($block['attrs']) ? ' ' . serialize_block_attributes($block['attrs']) : '';
			$parentBlockName = str_replace('core/', '', $block['blockName']);

			return "<!-- wp:{$parentBlockName}{$attrs} -->" . $processedInnerContent . "<!-- /wp:{$parentBlockName} -->";

		} else if (null === $block['blockName']) {
			// Plain text content
			return $block['innerHTML'];

		} else {
			// Leaf block with no inner blocks, keep as is
			$blockName    = str_replace('core/', '', $block['blockName']);
			$attrs        = ! empty($block['attrs']) ? ' ' . serialize_block_attributes($block['attrs']) : '';
			$blockContent = "<!-- wp:{$blockName}{$attrs} -->{$block['innerHTML']}<!-- /wp:{$blockName} -->";

			return $blockContent;
		}
	}

	/**
	 * Process a single shortcode-module block
	 */
	static function processShortcodeModuleBlock( $block, $moduleCollections, $count, bool $convert_global_instances_as_regular_modules = false ) {
		static $convertibleShortcodes = null;

		$shortcodeContent = trim($block['innerHTML']);
		$isConvertible    = false;

		if (null === $convertibleShortcodes) {
			$convertibleShortcodes = array_filter(array_column($moduleCollections, 'd4Shortcode'));
		}

		// Extract shortcode name and check if it's convertible using array_column
		if (preg_match('/^\[([^\s\]]+)/', $shortcodeContent, $matches)) {
			$shortcodeName = $matches[1];
			$isConvertible = in_array($shortcodeName, $convertibleShortcodes, true);
		}

		if ($isConvertible) {
			// Use existing conversion system to convert the shortcode
			$parsedShortcode    = self::parseShortcode($shortcodeContent, $moduleCollections);
			$convertedShortcode = self::convertShortcodeToGbFormat( $parsedShortcode, true, null, null, true, null, false, $convert_global_instances_as_regular_modules );

			return $convertedShortcode;

		} else {
			// Keep as shortcode-module if not convertible
			$attrs        = ! empty($block['attrs']) ? ' ' . serialize_block_attributes($block['attrs']) : '';
			$blockContent = "<!-- wp:{$block['blockName']}{$attrs} -->{$block['innerHTML']}<!-- /wp:{$block['blockName']} -->";

			return $blockContent;
		}
	}


	static function parseShortcode($shortcode, $moduleCollections, $parentName = null) {
		$convertibleModules = array_filter($moduleCollections, function ($module) {
			return !empty($module['d4Shortcode']);
		});

		$modules = array_column($convertibleModules, null, 'name');
		$shortcodeModules = array_column($convertibleModules, null, 'd4Shortcode');

		// Extract the d4Shortcode values into an array
		$d4shortcodeTags = array_map(function ($module) {
			return $module['d4Shortcode'];
		}, $convertibleModules);

		$shortcodeTags = array_merge([], $d4shortcodeTags, self::$_woo_modules, self::$_third_party_modules);

		// Build the regex
		$shortcodeTagPattern = get_shortcode_regex($shortcodeTags);

		preg_match_all('/' . $shortcodeTagPattern . '/s', $shortcode, $matches, PREG_SET_ORDER);

		$result = [];

		foreach ($matches as $parsed) {
			$shortcodeName = $parsed[2];
			// Check if module can be converted, and set nonconvertible attribute accordingly.
			$nonconvertible = ($shortcodeName !== 'et_pb_unsupported' && isset($shortcodeModules[$shortcodeName])) ? 'no' : 'yes';

			// For nonconvertible modules, generate special attributes.
			$attributes = $nonconvertible === 'yes'
				? self::generateNonconvertibleAttributes(shortcode_parse_atts($parsed[3]), $shortcodeName, $nonconvertible)
				: \shortcode_parse_atts($parsed[3]);

			$isParentModule = self::hasChildShortcode([
				'nonconvertible' => $nonconvertible,
				'content' => $parsed[5],
				'modules' => $modules,
				'shortcodeModules' => $shortcodeModules,
				'shortcodeName' => $shortcodeName,
			]);

			$nextParentName = $isParentModule ? $shortcodeName : '';

			if ($nonconvertible === 'yes') {
				$content = $parsed[0];
			} elseif ($isParentModule) {
					$content = self::parseShortcode($parsed[5], $moduleCollections, $nextParentName);
				} else {
				$content = $parsed[5];
			}

			$result[] = [
				'name' => $shortcodeName,
				'attrs' => $attributes,
				'parentName' => $parentName ?? '',
				'content' => $content,
				'originalShortcode' => $parsed[0],
			];
		}

		return $result;
	}

	static function generateNonconvertibleAttributes($allAttrs, $shortcodeName, $nonconvertible) {
		// Define allowed attributes
		$allowedAttributes = [ '_builder_version', '_module_preset', 'nonconvertible', 'global_module', 'global_parent' ];

		// Strip all unwanted attributes from unsupported module
		$attributes = array_intersect_key($allAttrs, array_flip($allowedAttributes));

		// Add shortcodeName to attributes
		$attributes['shortcodeName'] = $shortcodeName;
		$attributes['nonconvertible'] = $nonconvertible;

		return $attributes;
	}

	static function hasChildShortcode($params) {
		$nonconvertible = $params['nonconvertible'];
		$content = trim($params['content']);  // Make sure to trim the content as done in the original TypeScript function.
		$modules = $params['modules'];
		$shortcodeModules = $params['shortcodeModules'];
		$shortcodeName = $params['shortcodeName'];

		if ($nonconvertible === 'yes') {
			return false;
		}

		$structureShortcodes = [
			'et_pb_section',
			'et_pb_row',
			'et_pb_row_inner',
			'et_pb_column',
			'et_pb_column_inner',
		];

		if (in_array($shortcodeName, $structureShortcodes, true)) {
			return true;
		}

		if (!isset($shortcodeModules[$shortcodeName]['childrenName'])) {
			return false;
		}

		$childModules = $shortcodeModules[$shortcodeName]['childrenName'];
		$childShortcodes = array_map(function ($childModule) use ($modules) {
			return isset($modules[$childModule]['d4Shortcode']) ? $modules[$childModule]['d4Shortcode'] : null;
		}, $childModules);

		// Filter out any null values that might have been added if a module key didn't exist
		$childShortcodes = array_filter($childShortcodes);

		// Early return if no child shortcodes
		if (empty($childShortcodes)) {
			return false;
		}

		// Escape the shortcodes for use in regex and join them with | to create the pattern
		$childShortcodesPattern = implode('|', array_map(function ($shortcode) {
			return preg_quote($shortcode, '/');
		}, $childShortcodes));

		// Construct the regex pattern to match any of the child shortcodes at the beginning of the content.
		$childShortcodeRegExp = '/^\[(' . $childShortcodesPattern . ')/';

		// Use the regex pattern to test if the content contains any child shortcodes.
		return preg_match($childShortcodeRegExp, $content) === 1;
	}

	/**
	 * Maybe convert presets data.
	 *
	 * @param array $presets The presets data to be converted.
	 * @return array The converted presets data.
	 */
	public static function maybe_convert_presets_data( $presets ) {
		// Bail early if the presets is from D5.
		if ( self::is_global_data_presets_items( $presets ) ) {
			return $presets;
		}

		$output = [ 'module' => [], 'group' => [] ];

		// Bail early if there are no presets.
		if ( empty( $presets ) ) {
			return $output;
		}

		// Get list of the D5 modules.
		$module_collections = self::getModuleCollections();

		$convertible_modules = array_filter(
			$module_collections,
			function ( $module ) {
				return ! empty( $module['d4Shortcode'] );
			}
		);

		$convertible_modules_slug = [];
		$default_presets          = [];

		// It will add Divi 4 shortcode as key and module name as value.
		foreach ( $convertible_modules as $convertible_module ) {
			$convertible_modules_slug[ $convertible_module['d4Shortcode'] ] = $convertible_module['name'];

			// Store default preset for each module.
			if ( isset( $presets[ $convertible_module['d4Shortcode'] ]['default'] ) ) {
				$default_presets[ $convertible_module['name'] ] = $presets[ $convertible_module['d4Shortcode'] ]['default'];
			}
		}

		if ( isset( $presets['et_pb_section_fullwidth']['default'] ) ) {
			$default_presets['divi/fullwidth-section'] = $presets['et_pb_section_fullwidth']['default'];
		}

		if ( isset( $presets['et_pb_section_specialty']['default'] ) ) {
			$default_presets['divi/specialty-section'] = $presets['et_pb_section_specialty']['default'];
		}

		$all_presets = [];
		foreach ( $presets as $d4_shortcode => $value ) {
			if ( isset( $value['presets'] ) && is_array( $value['presets'] ) ) {
				foreach ( $value['presets'] as $preset_id => $preset_value ) {
					$all_presets[] = self::convert_preset( $preset_value, $preset_id, $d4_shortcode );
				}
			}
		}

		foreach ( $all_presets as $converted_preset ) {
			if ( $converted_preset ) {
				$output['module'][ $converted_preset['moduleName'] ]['items'][ $converted_preset['id'] ] = $converted_preset;

				// Add default preset if it's not already added.
				if ( ! isset( $output['module'][ $converted_preset['moduleName'] ]['default'] ) ) {
					$output['module'][ $converted_preset['moduleName'] ]['default'] = $default_presets[ $converted_preset['moduleName'] ] ?? '';
				}
			}
		}

		return $output;
	}

	/**
	 * Convert a D4 preset to a D5 compatible preset.
	 *
	 * @param array  $preset The D4 preset attributes.
	 * @param string $preset_id The preset ID.
	 * @param string $d4_shortcode The D4 module shortcode.
	 * @return array | null The converted preset or null on failure.
	 */
	public static function convert_preset( $preset, $preset_id, $d4_shortcode ) {
		// Get current module version from settings.
		$version = ET_CORE_VERSION;

		// Get list of D5 modules.
		$module_collections = self::getModuleCollections();

		// Filter modules that have a D4 shortcode.
		$convertible_modules = array_filter(
			$module_collections,
			function( $module ) {
				return ! empty( $module['d4Shortcode'] );
			}
		);

		// Map D4 shortcode to module name.
		$convertible_modules_slug = [];
		foreach ( $convertible_modules as $module ) {
			$convertible_modules_slug[ $module['d4Shortcode'] ] = $module['name'];
		}

		// Add support for fullwidth and specialty sections.
		$convertible_modules_slug['et_pb_section_fullwidth'] = 'divi/fullwidth-section';
		$convertible_modules_slug['et_pb_section_specialty'] = 'divi/specialty-section';

		// Add support for fullwidth slides (D4 used separate preset module name for fullwidth slider slides).
		// Both et_pb_slide and et_pb_slide_fullwidth map to divi/slide in D5.
		$convertible_modules_slug['et_pb_slide_fullwidth'] = 'divi/slide';

		// Get D5 module name from D4 shortcode.
		$module_name = $convertible_modules_slug[ $d4_shortcode ] ?? null;

		if ( ! $module_name ) {
			return null; // Return null if no matching module found.
		}

		// Restore preset module name (if needed).
		$restored_module_name = self::maybe_restore_preset_module_name( $module_name );

		// Encode and decode preset attributes (simulating the encodeAttrs function).
		// Pass true for $is_preset_conversion to skip injecting default layout values.
		$converted_preset_attrs = self::convertAttrs( $preset['settings'], $restored_module_name, null, true );

		// Remove converted attributes that match default printed style attributes.
		$default_printed_style_attrs = ModuleRegistration::get_module_default_printed_style_attributes( $module_name );
		if ( ! empty( $default_printed_style_attrs ) ) {
			$converted_preset_attrs = ModuleUtils::remove_matching_values( $converted_preset_attrs, $default_printed_style_attrs );
		}

		// Get preset attributes mapping.
		// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
		// TODO - Get preset attributes mapping.
		$map = self::get_preset_attrs_mapping( $restored_module_name );

		// Construct the converted preset item.
		$converted_preset_item = [
			'id'          => $preset_id,
			'moduleName'  => $module_name,
			'name'        => $preset['name'],
			'attrs'       => $converted_preset_attrs,
			// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
			// TODO use map to get the attrs.
			// 'attrs'       => self::get_preset_attrs( $converted_preset_attrs, [ 'style', 'html', 'script' ], $map ),
			'styleAttrs'  => self::get_preset_attrs( $converted_preset_attrs, [ 'style' ], $map ),
			'renderAttrs' => self::get_preset_attrs( $converted_preset_attrs, [ 'html', 'script' ], $map ),
			'version'     => $preset['version'] ?? $version,
			'type'        => 'module',
		];

		return $converted_preset_item;
	}

	/**
	 * Check if input is of type GlobalData.Presets.Items.
	 *
	 * @param mixed $presets The presets object to be checked.
	 * @return bool A boolean indicating whether the `presets` object is of type GlobalData.Presets.Items.
	 */
	public static function is_global_data_presets_items( $presets ) {
		return isset( $presets['module'] ) || isset( $presets['group'] );
	}

	/**
	 * Maybe restore preset module name.
	 *
	 * @param string $module_name The module name to be checked.
	 * @return string The restored module name.
	 */
	public static function maybe_restore_preset_module_name( string $module_name ): string {
		$preset_modules = [ 'divi/fullwidth-section', 'divi/specialty-section', 'divi/section' ];

		if ( in_array( $module_name, $preset_modules ) ) {
			return 'divi/section';
		}

		return $module_name;
	}

	/**
	 * Checks if the specified keys exist in the array.
	 *
	 * @param array $array The array to check.
	 * @param array $keys The keys to check for.
	 * @return bool True if all keys exist, false otherwise.
	 */
	public static function has( array $array, array $keys ): bool {
		$current = $array;
		foreach ( $keys as $key ) {
			if ( ! is_array( $current ) || ! array_key_exists( $key, $current ) ) {
				return false;
			}
			$current = $current[ $key ];
		}
		return true;
	}

	/**
	 * Retrieves the value at the specified keys in the array.
	 *
	 * @param array $array The array to retrieve the value from.
	 * @param array $keys The keys to retrieve the value for.
	 * @return mixed The value at the specified keys, or null if the keys do not exist.
	 */
	public static function get( array $array, array $keys ) {
		$current = $array;
		foreach ( $keys as $key ) {
			if ( ! is_array( $current ) || ! array_key_exists( $key, $current ) ) {
				return null; // Return null if the key does not exist.
			}
			$current = $current[ $key ];
		}
		return $current;
	}

	/**
	 * Sets the value at the specified keys in the array.
	 *
	 * @param array &$array The array to set the value in.
	 * @param array $keys The keys to set the value for.
	 * @param mixed $value The value to set.
	 * @return void
	 */
	public static function set( array &$array, array $keys, $value ): void {
		$current = &$array;
		foreach ( $keys as $key ) {
			if ( ! isset( $current[ $key ] ) ) {
				$current[ $key ] = []; // Create an empty array if the key does not exist.
			}
			$current = &$current[ $key ];
		}
		$current = $value; // Set the value at the final key.
	}

	/**
	 * Retrieves the preset attributes for a given preset type.
	 *
	 * @param array $preset_type The type of the preset.
	 * @param array $module_attrs The module attributes.
	 * @param array $map The attribute map.
	 * @return array The preset attributes.
	 */
	public static function get_preset_attrs( $module_attrs, $preset_type, $map ) {
		$attrs = [];

		$attr_names = self::get_preset_attrs_names( $preset_type, $module_attrs, $map );

		foreach ( $attr_names as $attr ) {
			$attr_name  = $attr['attrName'];
			$sub_name   = $attr['subName'] ?? null;
			$breakpoint = $attr['breakpoint'] ?? null;
			$state      = $attr['state'] ?? null;

			$attr_name_paths = explode( '.', $attr_name );

			if ( $breakpoint ) {
				$attr_name_paths[] = $breakpoint;
			}

			if ( $state ) {
				$attr_name_paths[] = $state;
			}

			if ( $sub_name ) {
				$sub_name_paths  = explode( '.', $sub_name );
				$attr_name_paths = array_merge( $attr_name_paths, $sub_name_paths );
			}

			if ( self::has( $module_attrs, $attr_name_paths ) ) {
				self::set( $attrs, $attr_name_paths, self::get( $module_attrs, $attr_name_paths ) );
			}
		}

		return $attrs;
	}

	/**
	 * Retrieves the preset attributes names.
	 *
	 * @param array $preset_type The type of the preset.
	 * @param array $module_attrs The module attributes.
	 * @param array $map The attribute map.
	 * @return array The preset attribute names.
	 */
	public static function get_preset_attrs_names( $preset_type, $module_attrs, $map ) {
		$attrs_name = [];

		$is_duplicate = function( $item_to_find ) use ( &$attrs_name ) {
			foreach ( $attrs_name as $item ) {
				if ( $item === $item_to_find ) {
					return true;
				}
			}
			return false;
		};

		$mappings_filtered = array_filter(
			$map,
			function( $mapping ) use ( $preset_type ) {
				if ( ! isset( $mapping['preset'] ) ) {
					return false;
				}

				$preset = $mapping['preset'];
				if ( is_array( $preset ) ) {
					foreach ( $preset as $item ) {
						if ( in_array( $item, $preset_type, true ) ) {
							return true;
						}
					}
					return false;
				}

				return in_array( $preset, $preset_type, true );
			}
		);

		foreach ( $mappings_filtered as $mapping ) {
			$attr_name       = $mapping['attrName'];
			$sub_name        = $mapping['subName'] ?? null;
			$attr_name_paths = explode( '.', $attr_name );

			$current_attr = $module_attrs;
			foreach ( $attr_name_paths as $key ) {
				if ( ! isset( $current_attr[ $key ] ) ) {
					$current_attr = null;
					break;
				}
				$current_attr = $current_attr[ $key ];
			}

			if ( null !== $current_attr ) {
				$breakpoint_states_values = $current_attr;

				if ( $sub_name ) {
					$sub_name_paths = explode( '.', $sub_name );

					foreach ( $breakpoint_states_values as $breakpoint => $states ) {
						foreach ( $states as $state => $state_value ) {
							$item_to_find = [
								'attrName'   => $attr_name,
								'subName'    => $sub_name,
								'breakpoint' => $breakpoint,
								'state'      => $state,
							];

							$sub_value = $state_value;
							foreach ( $sub_name_paths as $sub_key ) {
								if ( ! isset( $sub_value[ $sub_key ] ) ) {
									$sub_value = null;
									break;
								}
								$sub_value = $sub_value[ $sub_key ];
							}

							if ( null !== $sub_value && ! $is_duplicate( $item_to_find ) ) {
								$attrs_name[] = $item_to_find;
							}
						}
					}
				} elseif ( ! $is_duplicate( [ 'attrName' => $attr_name ] ) ) {
					$attrs_name[] = [ 'attrName' => $attr_name ];
				}
			}
		}

		return $attrs_name;
	}

	/**
	 * Restores specific encoded characters in the given value.
	 *
	 * If any of these encoded characters are found, they are replaced with their corresponding
	 * restored characters:
	 * - %22 -> "
	 * - %92 -> \
	 * - %91 -> &#91;
	 * - %93 -> &#93;
	 * - %5c -> \.
	 *
	 * @since ??
	 *
	 * @see /visual-builder/packages/conversion/src/utils/restore-special-chars/index.ts
	 *
	 * @param string $value The value to restore. Can be a string or a number.
	 * @return string The restored value, or the original value if no encoded characters are found.
	 */
	public static function restoreSpecialChars( $value ) {
		$strValue = strval($value);

		// Check if the string contains any of the encoded characters, if not then return original value.
		if (!preg_match('/%91|%93|%22|%92|%5c/', $strValue)) {
			return $value;
		}

		// Perform replacements if encoded characters are found.
		$strValue = str_replace('%22', '"', $strValue);
		$strValue = str_replace('%92', '\\', $strValue);
		$strValue = str_replace('%91', '&#91;', $strValue);
		$strValue = str_replace('%93', '&#93;', $strValue);
		$strValue = str_replace('%5c', '\\', $strValue);

		return $strValue;
	}

	/**
	 * Replaces specific characters in a string to ensure it can be safely embedded in various contexts.
	 *
	 * This function is adapted from the core function `serializeAttributes`.
	 * It replaces characters that might interfere with embedding the result in an HTML comment or other contexts.
	 * Ref: https://github.com/WordPress/gutenberg/blob/release/17.7/packages/blocks/src/api/serializer.js#L263C17-L263C36
	 *
	 * The result is a string with unicode escape sequence substitution for characters which might otherwise interfere with embedding the result.
	 *
	 * @param string $content The string to be processed.
	 * @return string The processed string with replaced characters.
	 */
	public static function maybe_replace_special_characters( $content ) {
		$patterns = [
			'/--/',
			'/</',
			'/>/',
			'/&/',
			'/\\"/',
		];

		$replacements = [
			'\\u002d\\u002d',
			'\\u003c',
			'\\u003e',
			'\\u0026',
			'\\u0022',
		];

		$encoded_string = preg_replace( $patterns, $replacements, $content );

		return $encoded_string;
	}

	public static function get_the_preset_item_map(array $item, string $full_attr_name) {
		$component_type = $item['component']['type'] ?? '';
		$is_field = 'field' === $component_type;
		$is_group = 'group' === $component_type;

		if ($is_field) {
			$attrs_map_item = [
				'attrName' => $full_attr_name,
				'preset' => $item['features']['preset'] ?? [ 'style' ],
			];

			$attrs_map_item_key = $full_attr_name;

			if (isset($item['subName'])) {
				$attrs_map_item['subName'] = $item['subName'];

				$attrs_map_item_key .= '__' . $item['subName'];
			}

			return [
				$attrs_map_item_key => $attrs_map_item,
			];
		} elseif ($is_group) {
			$group_name = $item['component']['name'] ?? '';
			$group_attrs = ModuleOptionsPresetAttrs::get_preset_attrs_from_group($group_name, $full_attr_name);
			return $group_attrs;
		}

		return [];
	}

	/**
	 * Get the preset attributes mapping for a module.
	 *
	 * @since ??
	 *
	 * @param string $module_name The module name.
	 *
	 * @return array The preset attributes map.
	 */
	public static function get_preset_attrs_mapping( $module_name ) {
		// Cache the preset attributes maps.
		if (isset(self::$preset_attrs_maps[$module_name])) {
			return self::$preset_attrs_maps[$module_name];
		}

		$attrs_map = [];

		// Implement logic to retrieve mapping.
		$registry = WP_Block_Type_Registry::get_instance();
		$block    = $registry->get_registered( $module_name );

		// Fallback: when block registry is not populated in this request context (observed on some hosts),
		// retrieve module settings from ModuleRegistration which can source core metadata.
		if ( ! $block ) {
			$block = ModuleRegistration::get_module_settings( $module_name );
		}

		// Bail when no attributes found.
		if ( ! isset( $block->attributes ) ) {
			return $attrs_map;
		}

		$attributes        = $block->attributes;
		$custom_css_fields = $block->customCssFields;

		// Create map for the attributes.
		if (! empty( $attributes ) ) {
			foreach ( $attributes as $attr_name => $attr_data ) {
				$settings = $attr_data['settings'] ?? [];
				$element_type = $attr_data['elementType'] ?? 'element';

				foreach ( $settings as $attrs_type => $setting_items ) {

					if('innerContent' === $attrs_type){
						// Inner content attributes.
						$full_attr_name = "{$attr_name}.{$attrs_type}";

						if (
							is_array($setting_items) &&
							(
								0 === count($setting_items) ||
								in_array($element_type, [ 'content', 'headingLink' ], true)
							)
						) {
							if ('headingLink' === $element_type) {
								$heading_link_inner_content_attrs = [
									"{$full_attr_name}__text" => [
										'attrName' => $full_attr_name,
										'preset' => 'content',
										'subName' => 'text',
									],
									"{$full_attr_name}__url" => [
										'attrName' => $full_attr_name,
										'preset' => 'content',
										'subName' => 'url',
									],
									"{$full_attr_name}__target" => [
										'attrName' => $full_attr_name,
										'preset' => 'content',
										'subName' => 'target',
									],
								];

								$attrs_map = array_merge($attrs_map, $heading_link_inner_content_attrs);
							} elseif ('content' === $element_type) {
								$content_inner_content_attrs = [
									$full_attr_name => [
										'attrName' => $full_attr_name,
										'preset' => 'content',
									],
								];

								$attrs_map = array_merge($attrs_map, $content_inner_content_attrs);
							}
						} elseif (isset($setting_items['groupType']) && 'group-item' === $setting_items['groupType']) {

							$item_attrs_map = self::get_the_preset_item_map($setting_items['item'], $full_attr_name);
							$attrs_map = array_merge($attrs_map, $item_attrs_map);

						} elseif (isset($setting_items['groupType']) && 'group-items' === $setting_items['groupType']) {
							// error_log(print_r($setting_item, true));
							$items = $setting_items['items'] ?? [];

							foreach ($items as $item) {
								$item_attrs_map = self::get_the_preset_item_map($item, $full_attr_name);
								$attrs_map = array_merge($attrs_map, $item_attrs_map);
							}
						}

					} elseif ( ! empty( $setting_items ) ) {
						// Advanced, Decorations, Meta, etc.
						foreach ( $setting_items as $setting_item_key => $setting_item ) {
							$full_attr_name = "{$attr_name}.{$attrs_type}.{$setting_item_key}";
							// error_log($full_attr_name);
							// error_log(print_r($setting_item, true));

							if (is_array($setting_item) && (0 === count($setting_item) || ! isset($setting_item['groupType']))) {
								$args = [];

								if ('decoration' === $attrs_type && 'font' === $setting_item_key && in_array($element_type, [ 'heading', 'headingLink' ], true)) {
									$args['has_heading_level'] = true;
								}

								// If the setting item is an empty array, generate the group name from the key.
								$group_name = ModuleOptionsPresetAttrs::get_the_group_name_by_key($attrs_type, $setting_item_key);

								// error_log(print_r($args, true));


								// If the group name is not empty, get the preset attributes from the group.
								if (!empty($group_name)) {
									$group_attrs = ModuleOptionsPresetAttrs::get_preset_attrs_from_group($group_name, $full_attr_name, $args);
									$attrs_map = array_merge($attrs_map, $group_attrs);
								}
							} elseif (isset($setting_item['groupType']) && 'group-item' === $setting_item['groupType']) {

								$item_attrs_map = self::get_the_preset_item_map($setting_item['item'], $full_attr_name);
								$attrs_map = array_merge($attrs_map, $item_attrs_map);

							} elseif (isset($setting_item['groupType']) && 'group-items' === $setting_item['groupType']) {
								// error_log(print_r($setting_item, true));
								$items = $setting_item['items'] ?? [];

								foreach ($items as $item) {
									$item_attrs_map = self::get_the_preset_item_map($item, $full_attr_name);
									$attrs_map = array_merge($attrs_map, $item_attrs_map);
								}
							} elseif (isset($setting_item['groupType']) && 'group' === $setting_item['groupType']) {
								$group_name = $setting_item['groupName'] ?? '';
								$preset_group_name = $setting_item['component']['props']['presetGroup'] ?? '';

								if ( ! empty( $preset_group_name ) ) {
									$group_name = $preset_group_name;
								}

								if (empty($group_name)) {
									$group_name = ModuleOptionsPresetAttrs::get_the_group_name_by_key($attrs_type, $setting_item_key);
								}

								// Build arguments for group-level preset attrs map (e.g., for font groups with conditional attributes).
								$args = [];
								if ('decoration' === $attrs_type && 'font' === $setting_item_key && in_array($element_type, [ 'heading', 'headingLink' ], true)) {
									$args['has_heading_level'] = true;
								}

								$group_attr_name = $full_attr_name;

								/*
								 * `divi/image` group definitions live under `{attr}.settings.decoration.image`,
								 * but the actual data path is rooted at the group attrName
								 * (e.g. `image` / `portrait`) not `{attr}.decoration.image`.
								 */
								if ( in_array( $group_name, [ 'divi/image', 'image' ], true ) ) {
									$group_attr_name = $setting_item['component']['props']['attrName'] ?? $attr_name;
								}

								// Always get the group-level preset attrs map first to include all default fields (e.g., textShadow).
								$group_attrs = ModuleOptionsPresetAttrs::get_preset_attrs_from_group($group_name, $group_attr_name, $args);
								$attrs_map = array_merge($attrs_map, $group_attrs);

								// Process fields in the group individually if they exist.
								// This ensures explicitly defined fields are also included (they may override defaults).
								$fields = $setting_item['component']['props']['fields'] ?? [];
								if (!empty($fields)) {
									foreach ($fields as $field_key => $field) {
										// Check for attrName in field or in component.props (for nested groups).
										$field_attr_name = $field['attrName'] ?? $field['component']['props']['attrName'] ?? null;
										if (null !== $field_attr_name) {
											$field_attrs_map = self::get_the_preset_item_map($field, $field_attr_name);
											$attrs_map = array_merge($attrs_map, $field_attrs_map);
										}
									}
								}
							} elseif (!isset($setting_item['groupType']) && ! empty($setting_item)) {
								$group_name = ModuleOptionsPresetAttrs::get_the_group_name_by_key($attrs_type, $setting_item_key);
								$group_attrs = ModuleOptionsPresetAttrs::get_preset_attrs_from_group($group_name, $full_attr_name);
								$attrs_map = array_merge($attrs_map, $group_attrs);
							}
						}
					}
				}

				if ( ! empty( $settings['preset'] ) ) {
				}
			}
		}

		$attrs_map = array_merge($attrs_map, [
			'css__before' => [
				'attrName' => 'css',
				'preset' => [ 'style' ],
				'subName' => 'before',
			],
			'css__mainElement' => [
				'attrName' => 'css',
				'preset' => [ 'style' ],
				'subName' => 'mainElement',
			],
			'css__after' => [
				'attrName' => 'css',
				'preset' => [ 'style' ],
				'subName' => 'after',
			],
			'css__freeForm' => [
				'attrName' => 'css',
				'preset' => [ 'style' ],
				'subName' => 'freeForm',
			],
		]);

		if (! empty( $custom_css_fields ) ) {
			foreach ( $custom_css_fields as $attr_name => $attr_data ) {
				// error_log(print_r($attr_data, true));
				$attrs_map_item = [
					'attrName' => 'css',
					'preset' => [ 'style' ],
					'subName' => $attr_data['subName'],
				];

				$attrs_map_item_key = 'css__' . $attr_data['subName'];

				$attrs_map[$attrs_map_item_key] = $attrs_map_item;
			}
		}

		// Add deprecated attributes for modules that still need them for preset conversion.
		if ( DeprecatedAttributeMapping::has_deprecated_attrs( $module_name ) ) {
			$deprecated_attrs = DeprecatedAttributeMapping::get_deprecated_attrs_for_module( $module_name );
			$attrs_map = array_merge( $attrs_map, $deprecated_attrs );
		}

		$attrs_map = apply_filters( 'divi_conversion_presets_attrs_map', $attrs_map, $module_name );

		self::$preset_attrs_maps[$module_name] = $attrs_map;

		return $attrs_map; // Return an empty array for simplicity.
	}

	/**
	 * Converts attributes of a module to a new format.
	 *
	 * @param array       $attrs                The original attributes of the module.
	 * @param string      $moduleName           The name of the module.
	 * @param string|null $content              The content of the module.
	 * @param bool        $is_preset_conversion Whether this conversion is for a preset (default: false).
	 * @param bool        $is_ab_testing_active Whether split testing is active for the post. Default false.
	 * @return array The converted attributes.
	 */
	static function convertAttrs( array $attrs, string $moduleName, ?string $content = null, bool $is_preset_conversion = false, bool $is_ab_testing_active = false ): array {
		$convertedAttrs = [];

		if ( null !== $content ) {
			$attrs['content'] = $content;
		}

		// Set order of attributes.
		$attrs = self::_set_order_attrs( $attrs );

		// Filter split test attributes.
		$attrs = self::filterSplitTestAttributes( $attrs, $is_ab_testing_active );

		$moduleGlobalColors = self::getModuleGlobalColors( $attrs );

		// Restore gcid references from global_colors_info and directly convert them to D5 format.
		foreach ( $moduleGlobalColors as $globalColorId => $attributeNames ) {
			// Get the actual global color value to validate against.
			$globalColorData  = GlobalData::get_global_color_by_id( $globalColorId );
			$globalColorValue = $globalColorData['color'] ?? null;

			// If the color doesn't exist in D5 global data, try to get it from legacy D4 global colors.
			if ( ! $globalColorValue ) {
				$legacy_colors = et_get_option( 'et_global_colors', [] );
				if ( ! empty( $legacy_colors ) && isset( $legacy_colors[ $globalColorId ] ) ) {
					// Skip corrupted entries where the value is not a valid array.
					if ( ! is_array( $legacy_colors[ $globalColorId ] ) ) {
						continue;
					}

					$globalColorValue = $legacy_colors[ $globalColorId ]['color'] ?? null;

					// Convert and save legacy global color to D5 global data option on-demand.
					if ( $globalColorValue ) {
						$converted_color_status = 'yes' === ( $legacy_colors[ $globalColorId ]['active'] ?? 'no' ) ? 'active' : 'inactive';
						self::_convert_global_color_to_global_data(
							$globalColorId,
							$globalColorValue,
							$converted_color_status
						);
					}
				}
			}

			foreach ( $attributeNames as $attrName ) {
				if ( isset( $attrs[ $attrName ] ) && is_string( $attrs[ $attrName ] ) && false === strpos( $attrs[ $attrName ], 'gcid' ) ) {
					// Only replace if the attribute's value matches the global color's value.
					// This prevents distinct colors from being incorrectly converted to the same global color.
					$attrValue   = strtolower( trim( $attrs[ $attrName ] ) );
					$globalValue = $globalColorValue ? strtolower( trim( $globalColorValue ) ) : '';

					if ( $globalValue && $attrValue === $globalValue ) {
						$attrs[ $attrName ] = self::formatDynamicContent( $globalColorId, [], 'color' );
					}
				}
			}
		}


		foreach ( $attrs as $name => $value ) {
			// error_log('name: ' . $name);

			if (
				str_ends_with( $name, '_last_edited' )
				|| str_ends_with( $name, '__hover_enabled' )
				|| str_ends_with( $name, '__focus_enabled' )
				|| str_ends_with( $name, '__checked_enabled' )
				|| str_ends_with( $name, '__active_enabled' )
				|| str_ends_with( $name, '__sticky_enabled' )
			) {
				continue;
			}

			if ( $moduleName === 'divi/section' && in_array( $name, array( 'specialty', 'fullwidth' ) ) && $attrs[ $name ] !== 'on' ) {
				continue;
			}

			if ( $name === 'global_colors_info' ) {
				continue;
			}

			// 3rd party: Pass via filters below object of address(es) and value(s) that correspond to one attribute.
			$attrMap = self::getAttrMap( $attrs, $name, $moduleName );

			// error_log('attrMap: ' . print_r($attrMap, true));

			if ( count( $attrMap ) > 0 ) {
				foreach ( $attrMap as $objectPath => $encodedValue ) {
					if ( count( $moduleGlobalColors ) > 0 ) {
						$encodedValue = self::convertGlobalColor( $encodedValue, $name, $moduleGlobalColors );
					}

					if ( ! is_array( $encodedValue ) && self::isDynamicContent( (string) $encodedValue ) ) {
						$encodedValue = self::convertDynamicContent( (string) $encodedValue );
					}

					// If encoded value is a CSS variable and isn't wrapped in `var()` wrap it here.
					if ( is_string( $encodedValue ) && strpos( $encodedValue, '--' ) === 0 ) {
						// Wrap in var() if it starts with '--'.
						$encodedValue = 'var(' . $encodedValue . ')';
					}

					if ( in_array( $moduleName, array( 'divi/map', 'divi/map-pin', 'divi/fullwidth-map' ) ) ) {
						$encodedValue = self::maybeParseValue( $objectPath, $encodedValue );
					}

					// error_log('objectPath: ' . $objectPath);
					// error_log('encodedValue: ' . print_r($encodedValue, true));
					// error_log('encodedAttrs: ' . print_r($encodedAttrs, true));

					// Set the value in encodedAttrs array using objectPath as the nested keys.
					// This functionality mimics the lodash `set` function behavior.
					$keys    = explode( '.', $objectPath );
					$lastKey = array_pop( $keys );
					$tempArr = &$convertedAttrs;

					foreach ( $keys as $key ) {
						// Ensure that $tempArr[$key] is an array before further assignment
						if ( ! isset( $tempArr[ $key ] ) || ! is_array( $tempArr[ $key ] ) ) {
							$tempArr[ $key ] = [];
						}
						$tempArr = &$tempArr[ $key ];
					}

					$tempArr[ $lastKey ] = $encodedValue;

					if ( $name === 'background_enable_color' && $encodedValue === 'off' ) {
						// This is where it is headed, commented out bc the arrays werent merging like the js set() counterpart
						// $tempArr['module']['decoration']['background']['desktop']['value']['color'] = '';
						$tempArr['color'] = '';
					}

					// For social-media-follow-network, if background_color doesn't exist for the current state,
					// set it to empty to prevent default network colors from showing.
					// State-aware: checks background_color__hover when processing hover, etc.
					if ( 'divi/social-media-follow-network' === $moduleName ) {
						$matches = [];
						// Only process background-related attributes.
						if ( preg_match( '/^background_(?:enable_)?color(.*)$/', $name, $matches ) ) {
							$state_suffix = $matches[1]; // e.g., '__hover', '_tablet', '' (base).
							// Strip '_enabled' suffix if present (e.g., '__hover_enabled' -> '__hover').
							$state_suffix = str_replace( '_enabled', '', $state_suffix );
							$check_attr   = 'background_color' . $state_suffix;

							// Only clear if the corresponding background_color attribute doesn't exist.
							if ( ! isset( $attrs[ $check_attr ] ) ) {
								$tempArr['color'] = '';
							}
						}
					}
				}
			}
		}

		// In D4, when mobile_menu_bg_color is empty, it defaults to the module's background_color.
		// This ensures migrated modules maintain visual parity with their D4 counterparts.
		if ( in_array( $moduleName, [ 'divi/menu', 'divi/fullwidth-menu' ], true ) ) {
			$mobile_menu_bg_color     = $attrs['mobile_menu_bg_color'] ?? '';
			$dropdown_menu_bg_color   = $attrs['dropdown_menu_bg_color'] ?? '';
			$background_color         = $attrs['background_color'] ?? '';
			$module_bg_converted      = $convertedAttrs['module']['decoration']['background']['desktop']['value']['color'] ?? null;
			$mobile_menu_bg_converted = $convertedAttrs['menuMobile']['decoration']['background']['desktop']['value']['color'] ?? null;
			$dropdown_menu_converted  = $convertedAttrs['menuDropdown']['decoration']['background']['desktop']['value']['color'] ?? null;

			// Apply fallback only if mobile_menu_bg_color was empty in D4, background_color has a value,
			// and mobile menu bg hasn't been converted yet (to avoid overriding explicit values).
			if ( empty( $mobile_menu_bg_color ) && ! empty( $background_color ) && is_null( $mobile_menu_bg_converted ) ) {
				if ( ! isset( $convertedAttrs['menuMobile'] ) ) {
					$convertedAttrs['menuMobile'] = [];
				}
				if ( ! isset( $convertedAttrs['menuMobile']['decoration'] ) ) {
					$convertedAttrs['menuMobile']['decoration'] = [];
				}
				if ( ! isset( $convertedAttrs['menuMobile']['decoration']['background'] ) ) {
					$convertedAttrs['menuMobile']['decoration']['background'] = [];
				}
				if ( ! isset( $convertedAttrs['menuMobile']['decoration']['background']['desktop'] ) ) {
					$convertedAttrs['menuMobile']['decoration']['background']['desktop'] = [];
				}
				if ( ! isset( $convertedAttrs['menuMobile']['decoration']['background']['desktop']['value'] ) ) {
					$convertedAttrs['menuMobile']['decoration']['background']['desktop']['value'] = [];
				}

				$convertedAttrs['menuMobile']['decoration']['background']['desktop']['value']['color'] = $background_color;
			}

			// In D4, when dropdown_menu_bg_color is empty, it falls back to background_color.
			// Apply the same behavior during conversion while preserving explicit dropdown values.
			// Use converted module background so global color tokens stay converted.
			if (
				empty( $dropdown_menu_bg_color ) &&
				! empty( $module_bg_converted ) &&
				( is_null( $dropdown_menu_converted ) || '' === $dropdown_menu_converted )
			) {
				if ( ! isset( $convertedAttrs['menuDropdown'] ) ) {
					$convertedAttrs['menuDropdown'] = [];
				}
				if ( ! isset( $convertedAttrs['menuDropdown']['decoration'] ) ) {
					$convertedAttrs['menuDropdown']['decoration'] = [];
				}
				if ( ! isset( $convertedAttrs['menuDropdown']['decoration']['background'] ) ) {
					$convertedAttrs['menuDropdown']['decoration']['background'] = [];
				}
				if ( ! isset( $convertedAttrs['menuDropdown']['decoration']['background']['desktop'] ) ) {
					$convertedAttrs['menuDropdown']['decoration']['background']['desktop'] = [];
				}
				if ( ! isset( $convertedAttrs['menuDropdown']['decoration']['background']['desktop']['value'] ) ) {
					$convertedAttrs['menuDropdown']['decoration']['background']['desktop']['value'] = [];
				}

				$convertedAttrs['menuDropdown']['decoration']['background']['desktop']['value']['color'] = $module_bg_converted;
			}
		}

		if (isset($convertedAttrs['unknownAttributes']) && is_array($convertedAttrs['unknownAttributes'])) {
			$moduleLibraryConversionMap = apply_filters('divi.conversion.moduleLibrary.conversionMap', []);

			// Get list of unknown attributes to process.
			$currentUnknownAttrs = array_keys($convertedAttrs['unknownAttributes']);
			$filteredUnknownAttrs = [];

			// Conditional preservation rules: pattern/attrName => required conditions
			// When a pattern matches:
			//   - If ALL conditions are met: KEEP as unknown (preserve data, prevent conversion)
			//   - If conditions are NOT met: IGNORE (treat as known, allow conversion)
			// Keys can be exact attribute names or regex patterns (patterns must start with '/').
			$conditionalPreservationRules = [
				// Popups For Divi extension adds attributes with da_ prefix.
				// Only preserve them as unknown if popup is actually enabled, otherwise allow conversion.
				'/^da_/' => [ 'da_is_popup' => 'on' ],

				// Divi Carousel Maker extension adds attributes with pac_dcm_ prefix.
				// Only preserve them as unknown if carousel is actually enabled, otherwise allow conversion.
				'/^pac_dcm_/' => [ 'pac_dcm_is_carousel' => 'on' ],
			];

			/**
			 * Allow 3rd party plugins to add additional conditional preservation rules.
			 *
			 * @since ??
			 *
			 * @param array  $conditionalPreservationRules {
			 *     Conditional preservation rules grouped by attribute prefix/pattern.
			 *
			 *     @type array<string, string> $pattern {
			 *         Associative array of `attribute => required value` pairs. Every requirement must be met
			 *         for the unknown attribute to be preserved. `$pattern` may be an exact attribute name or
			 *         a regular-expression string (regex patterns must begin with `/`).
			 *     }
			 * }
			 * @param string $moduleName Module name.
			 *
			 * @return array The filtered conditional preservation rules.
			 *
			 * @example
			 * ```php
			 * add_filter( 'divi.conversion.conditionalPreservationRules', function( $conditionalPreservationRules, $moduleName ) {
			 *     // Add additional conditional preservation rules.
			 *     return array_merge( $conditionalPreservationRules, [
			 *         '/^pac_dcm_/' => ['pac_dcm_is_carousel' => 'on'],
			 *     ] );
			 * }, 10, 2 );
			 * ```
			 */
			$conditionalPreservationRules = apply_filters( 'divi.conversion.conditionalPreservationRules', $conditionalPreservationRules, $moduleName );

			// Check if the unknown attribute is known in other modules.
			// This is needed because sometimes unknown attributes come
			// from copy/pasting the module settings groups from one module to another.
			// In this case, the unknown attributes are just a residue from other modules
			// and we can remove them to avoid confusing them with the actual unknown attributes
			// added by third-party extensions that written for Divi 4.
			foreach ($currentUnknownAttrs as $unknownAttr) {
				$shouldPreserveAsUnknown = true;

				// Check if this is a legacy attribute name from migrations
				if (LegacyAttributeNames::is_legacy_attribute($unknownAttr)) {
					continue; // Skip legacy attribute names (treat as known)
				}

				// Ultimate Membership Pro and similar extensions inject `ihc_*` shortcode attributes; drop them
				// so official Divi modules convert natively (issue #49579).
				if ( 1 === preg_match( '/^ihc_/', $unknownAttr ) ) {
					continue;
				}

				// Divi Booster adds `db_separators` to Menu modules; discard so it does not become unknownAttributes (theme builder layouts).
				if ( 'db_separators' === $unknownAttr ) {
					continue;
				}

				// Rank Math FAQ Schema integration adds `rank_math_faq_schema` to Accordion modules; discard so they convert natively (issue #50441).
				if ( 'rank_math_faq_schema' === $unknownAttr ) {
					continue;
				}

				// AnalyticsWP Divi integration adds `analyticswp_tracking` to CTA modules; discard so they convert natively (issue #50451).
				if ( 'analyticswp_tracking' === $unknownAttr ) {
					continue;
				}

				// Check conditional preservation rules
				foreach ($conditionalPreservationRules as $pattern => $requiredConditions) {
					$patternMatches = false;

					// Check if the pattern is a regex (starts with '/')
					if (strpos($pattern, '/') === 0) {
						$patternMatches = preg_match($pattern, $unknownAttr) === 1;
					} else {
						// Exact string match
						$patternMatches = $pattern === $unknownAttr;
					}

					// If pattern matches, check if required conditions are met
					if ($patternMatches && is_array($requiredConditions)) {
						$allConditionsMet = true;

						foreach ($requiredConditions as $requiredAttr => $requiredValue) {
							$actualValue = null;

							if ( isset( $convertedAttrs[ $requiredAttr ] ) ) {
								$actualValue = $convertedAttrs[ $requiredAttr ];
							} elseif ( isset( $convertedAttrs['unknownAttributes'][ $requiredAttr ] ) ) {
								$actualValue = $convertedAttrs['unknownAttributes'][ $requiredAttr ];
							} elseif ( isset( $attrs[ $requiredAttr ] ) ) {
								$actualValue = $attrs[ $requiredAttr ];
							}

							if ( $actualValue !== $requiredValue ) {
								$allConditionsMet = false;
								break;
							}
						}

						// If pattern matched but conditions NOT met, don't preserve as unknown (allow conversion)
						if (! $allConditionsMet) {
							$shouldPreserveAsUnknown = false;
							break; // Exit the rules loop - we've made our decision
						}
					}
				}

				// If we decided not to preserve this attribute, skip it (treat as known)
				if (! $shouldPreserveAsUnknown) {
					continue;
				}

				// Check if attribute is known in other modules (another reason to not preserve)
				$isKnownInOtherModules = false;
				foreach ($moduleLibraryConversionMap as $otherModuleName => $conversionMap) {
					if ($otherModuleName === $moduleName) {
						continue;
					}

					$knownAttrs = array_keys($conversionMap['attributeMap'] ?? []);
					if (in_array($unknownAttr, $knownAttrs)) {
						$isKnownInOtherModules = true;
						break;
					}
				}

				// If the unknown attribute is not known in other modules, keep it as unknown
				if (!$isKnownInOtherModules) {
					$filteredUnknownAttrs[$unknownAttr] = $convertedAttrs['unknownAttributes'][$unknownAttr];
				}
			}

			// If any unknown attributes remain after filtering, add them back to the converted attributes.
			if (count($filteredUnknownAttrs) > 0) {
				$convertedAttrs['unknownAttributes'] = $filteredUnknownAttrs;
			} else {
				unset($convertedAttrs['unknownAttributes']);
			}
		}

		// Set layout to "block" for modules that support layout when converting from Divi 4.
		// This is needed because Divi 4 never supported flex layouts, so all converted modules
		// should default to "block" layout instead of the Divi 5 default "flex".
		// Person (divi/team-member) is an exception: D4 uses a horizontal image + text layout that
		// maps to flex row in D5 so inner Team Member style declarations run (see #49701).
		// IMPORTANT: Skip this for preset conversion to avoid polluting presets with default values.
		if ( ! $is_preset_conversion ) {
			$modulesWithLayout = [
				'divi/section',
				'divi/row',
				'divi/row-inner',
				'divi/column',
				'divi/column-inner',
				'divi/group',
				'divi/post-nav',
				'divi/pricing-table',
				'divi/social-media-follow',
				'divi/team-member',
				'divi/sidebar',
			];

			// Restore module name for consistent module name handling.
			$restoredModuleName = self::maybe_restore_preset_module_name( $moduleName );

			if ( in_array( $restoredModuleName, $modulesWithLayout, true ) ) {
				if ( 'divi/team-member' === $restoredModuleName ) {
					$convertedAttrs['module']['decoration']['layout']['desktop']['value']['display']       = 'flex';
					$convertedAttrs['module']['decoration']['layout']['desktop']['value']['flexDirection'] = 'row';
				} else {
					// Set the layout value to "block".
					$convertedAttrs['module']['decoration']['layout']['desktop']['value']['display'] = 'block';
				}
			}
		}

		// Convert modulePreset from string to array format.
		// This ensures compatibility with D5 format where modulePreset is always an array.
		if ( isset( $convertedAttrs['modulePreset'] ) && is_string( $convertedAttrs['modulePreset'] ) ) {
			$preset_value = trim( $convertedAttrs['modulePreset'] );
			if ( '' !== $preset_value ) {
				$convertedAttrs['modulePreset'] = [ $preset_value ];
			} else {
				unset( $convertedAttrs['modulePreset'] );
			}
		}

		// For CTA module: if background is disabled and padding is unresolved, set padding to 0px.
		// D4 CTA modules with background disabled have no padding, but D5 has default padding.
		// This ensures migrated modules maintain visual parity with D4.
		if ( 'divi/cta' === $moduleName || 'divi/cta' === self::maybe_restore_preset_module_name($moduleName) ) {
			$current_padding_value = isset( $convertedAttrs['module']['decoration']['spacing']['desktop']['value']['padding'] )
				? $convertedAttrs['module']['decoration']['spacing']['desktop']['value']['padding']
				: null;
			$use_background_color_off = isset( $attrs['use_background_color'] ) && 'off' === $attrs['use_background_color'];
			$padding_state            = 'missing';

			if ( is_array( $current_padding_value ) ) {
				$padding_sides = [
					isset( $current_padding_value['top'] ) ? trim( (string) $current_padding_value['top'] ) : '',
					isset( $current_padding_value['right'] ) ? trim( (string) $current_padding_value['right'] ) : '',
					isset( $current_padding_value['bottom'] ) ? trim( (string) $current_padding_value['bottom'] ) : '',
					isset( $current_padding_value['left'] ) ? trim( (string) $current_padding_value['left'] ) : '',
				];

				$all_empty_sides = true;
				foreach ( $padding_sides as $padding_side ) {
					if ( '' !== $padding_side ) {
						$all_empty_sides = false;
						break;
					}
				}

				$padding_state = $all_empty_sides ? 'empty_sides' : 'has_values';
			}

			// If use_background_color is explicitly 'off' and padding is unresolved, set padding to 0px.
			if ( $use_background_color_off && in_array( $padding_state, [ 'missing', 'empty_sides' ], true ) ) {
				if ( ! isset( $convertedAttrs['module']['decoration']['spacing'] ) ) {
					$convertedAttrs['module']['decoration']['spacing'] = [];
				}
				if ( ! isset( $convertedAttrs['module']['decoration']['spacing']['desktop'] ) ) {
					$convertedAttrs['module']['decoration']['spacing']['desktop'] = [];
				}
				if ( ! isset( $convertedAttrs['module']['decoration']['spacing']['desktop']['value'] ) ) {
					$convertedAttrs['module']['decoration']['spacing']['desktop']['value'] = [];
				}
				$convertedAttrs['module']['decoration']['spacing']['desktop']['value']['padding'] = [
					'top'    => '0px',
					'right'  => '0px',
					'bottom' => '0px',
					'left'   => '0px',
				];
			}
		}

		/**
		 * Filters converted attributes after conversion is complete.
		 *
		 * This filter allows modules to modify converted attributes after the main conversion
		 * process has finished. Useful for adding migration-specific attributes or performing
		 * module-specific post-processing.
		 *
		 * @since ??
		 *
		 * @param array  $convertedAttrs The converted attributes array.
		 * @param string $moduleName      The module name (e.g., 'divi/text').
		 * @param array  $attrs           The original D4 attributes.
		 * @param bool   $is_preset_conversion Whether this is a preset conversion.
		 */
		$convertedAttrs = apply_filters( 'divi.conversion.postConvertAttrs', $convertedAttrs, $moduleName, $attrs, $is_preset_conversion );

		return $convertedAttrs;
	}

	/**
	 * Reorder attributes to ensure specific attributes are processed last.
	 *
	 * This method reorders module attributes so that certain attributes
	 * (e.g., 'use_background_color') and their responsive/state variants
	 * (_tablet, _phone, __hover, __focus, __checked, __active, __sticky) appear at the end of the array.
	 * This ensures proper processing order during module conversion.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attributes to reorder.
	 *
	 * @return array Reordered attributes with last-processed attributes at the end.
	 */
	private static function _set_order_attrs( array $attrs ): array {
		// List of attribute desktop names that should be processed last.
		$names_last_processed = [ 'use_background_color' ];
		$attrs_sorted         = [];
		$attrs_last           = [];

		foreach ( $attrs as $name => $value ) {
			$desktop_name = preg_replace( '/(_tablet|_phone|__hover|__focus|__checked|__active|__sticky)$/', '', $name );

			if ( in_array( $desktop_name, $names_last_processed, true ) ) {
				$attrs_last[ $name ] = $value;
				continue;
			}

			$attrs_sorted[ $name ] = $value;
		}

		// Merge attributes, ensuring last-processed attributes come at the end.
		return array_merge( $attrs_sorted, $attrs_last );
	}
}
