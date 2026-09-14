<?php
/**
 * Module Element Class
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Packages\Module\Layout\Components\ModuleElements;

// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames -- Reserved keywords used intentionally for clarity in utility functions.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\Module\Options\Attributes\AttributeUtils;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowClassnames;
use WP_Block_Type;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewElement;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewElementValue;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\Module\Options\Button\ButtonComponent;
use ET\Builder\Packages\Module\Options\Animation\AnimationUtils;
use ET\Builder\Packages\Module\Options\Animation\AnimationScriptData;
use ET\Builder\Packages\Module\Options\Background\BackgroundClassnames;
use ET\Builder\Packages\Module\Options\Background\BackgroundParallaxScriptData;
use ET\Builder\Packages\Module\Options\Background\BackgroundVideoScriptData;
use ET\Builder\Packages\Module\Options\Element\ElementStyle;
use ET\Builder\Packages\Module\Options\Element\ElementScriptData;
use ET\Builder\Packages\GlobalData\GlobalPresetItem;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Button\Button;
use ET\Builder\Packages\Shortcode\ShortcodeUtils;
use ET\Builder\Packages\StyleLibrary\Utils\Utils as StyleUtils;


/**
 * Module related helper class.
 *
 * @since ??
 */
class ModuleElements {


	/**
	 * The current preset priority being rendered.
	 * This is used as a temporary storage for preset priority
	 * so Style::add() can access it without modules needing to pass it explicitly.
	 *
	 * @since ??
	 *
	 * @var int|null
	 */
	private static $_current_preset_priority = null;

	/**
	 * Module ID
	 *
	 * @since ??
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Module name
	 *
	 * @since ??
	 *
	 * @var string
	 */
	public $name;

	/**
	 * A key-value pair of module attributes data where the key is the module attribute name and the value is the formatted attribute array.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	public $module_attrs = [];

	/**
	 * Runtime module attributes used for classnames/script-data/render decisions.
	 *
	 * This can differ from `$module_attrs` when style-only attr cleanup is applied.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	public $runtime_module_attrs = [];

	/**
	 * Module attributes original data.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private $_module_attrs_original;

	/**
	 * A key-value pair of selectors where the key is the module attribute name and the value is the selector.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	public $selectors = [];

	/**
	 * Key-value pair of module metadata (module.json config file).
	 *
	 * @since ??
	 *
	 * @var WP_Block_Type
	 */
	public $module_metadata;

	/**
	 * Base order classname.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	public $base_order_class = '';

	/**
	 * The selector class name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	public $order_class = '';

	/**
	 * Base wrapper order classname.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	public $base_wrapper_order_class = '';

	/**
	 * The selector class name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	public $wrapper_order_class = '';

	/**
	 * Module name class name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	public $module_name_class = '';

	/**
	 * Module order ID.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	public $order_id = '';

	/**
	 * Module order index.
	 *
	 * @since ??
	 *
	 * @var mixed|null
	 */
	public $order_index;

	/**
	 * Module store instance.
	 *
	 * @since ??
	 *
	 * @var int|null
	 */
	public $store_instance;

	/**
	 * The group of the style where it will be added.
	 *
	 * @var string
	 */
	private $_style_group = 'module';

	/**
	 * The preset priority for CSS rendering order.
	 * Higher priority presets are rendered last for proper CSS cascade.
	 *
	 * @since ??
	 *
	 * @var int|null
	 */
	private $_preset_priority = null;

	/**
	 * Whether current post type is custom post type or not
	 *
	 * @since ??
	 *
	 * @var boolean
	 */
	private $_is_custom_post_type = false;

	/**
	 * Whether current module is inside another sticky module or not.
	 *
	 * @since ??
	 *
	 * @var boolean
	 */
	private $_is_inside_sticky_module = false;

	/**
	 * Sticky parent order class name (e.g., '.et_pb_row_2_tb_header').
	 *
	 * @since ??
	 *
	 * @var string|null
	 */
	private $_sticky_parent_order_class = null;

	/**
	 * Sticky parent ID (for later conversion).
	 *
	 * @since ??
	 *
	 * @var string|null
	 */
	private $_sticky_parent_id = null;

	/**
	 * Whether current module is nested or not.
	 *
	 * @since ??
	 *
	 * @var boolean
	 */
	private $_is_nested_module = false;

	/**
	 * Default printed styles.
	 *
	 * @var array
	 */
	public $default_printed_style_attrs = [];

	/**
	 * Preset printed styles.
	 *
	 * @var array
	 */
	public $preset_printed_style_attrs = [];

	/**
	 * Whether the module has a default render background.
	 *
	 * @since ??
	 *
	 * @var bool
	 */
	public $has_default_background = false;

	/**
	 * Placeholder for merged attributes.
	 *
	 * @var array
	 */
	private $_merged_attrs;

	/**
	 * Whether parent module is flex or not.
	 *
	 * @since ??
	 *
	 * @var boolean
	 */
	private $_is_parent_flex_layout;

	/**
	 * Whether parent module is grid or not.
	 *
	 * @since ??
	 *
	 * @var boolean
	 */
	private $_is_parent_grid_layout;

	/**
	 * Targeted custom attributes for sub-elements.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private $_targeted_attributes = [];

	/**
	 * Tracks attr names whose style components were rendered manually before render().
	 *
	 * @since ??
	 *
	 * @var array<string,int>
	 */
	private $_pre_rendered_style_components = [];

	/**
	 * Create an instance of ModuleElements class.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *                    Optional. An array of arguments. Default `[]`.
	 *
	 * @type string $id                The Module unique ID.
	 * @type string $name              The Module name.
	 * @type array  $moduleAttrs       A key-value pair of module attributes data where the key is
	 *                                     the module attribute name and the value is the formatted attribute array.
	 * @type array  $runtimeModuleAttrs Optional. Runtime module attributes for classnames/script-data/render decisions.
	 *                                     Defaults to `moduleAttrs`.
	 * @type array  $selectors         Optional. A key-value pair of selectors where the key is the module attribute
	 *                                     name and the value is the selector. If not provided, the selectors will be
	 *                                     retrieved from the module.json config file.
	 *                                     Default `ModuleRegistration::get_selectors( $this->name )`.
	 * @type int    $storeInstance     Optional. The ID of instance where the module object is stored in BlockParserStore.
	 *                                     Default `null`.
	 * @type int    $orderIndex        Optional. The order index of the module. Default `null`.
	 * @type WP_Block_Type|array $moduleMetadata Optional. The module metadata. Could be an instance of WP_Block_Type or an array to be converted into WP_Block_Type instance.
	 * @type boolean $is_custom_post_type Optional. Whether current post type is custom post type or not. Default `false`.
	 * @type string|null $stickyParentId Optional. Sticky parent ID. Default `null`.
	 * @type boolean $hasDefaultBackground Optional. Whether the module has a default render background. Default `false`.
	 * @type boolean $is_parent_flex_layout Optional. Whether parent module is flex or not. Default `false`.
	 * @type array  $targetedAttributes Optional. Custom attributes separated by target element. Default `[]`.
	 * }
	 */
	public function __construct( array $args = [] ) {
		$this->id                   = $args['id'] ?? '';
		$this->name                 = $args['name'] ?? '';
		$this->module_attrs         = $args['moduleAttrs'] ?? [];
		$this->runtime_module_attrs = $args['runtimeModuleAttrs'] ?? $this->module_attrs;
		$this->module_metadata      = $args['moduleMetadata'] ?? null;

		$this->default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];
		$this->_targeted_attributes        = $args['targetedAttributes'] ?? [];
		$this->preset_printed_style_attrs  = $args['presetPrintedStyleAttrs'] ?? [];
		$this->has_default_background      = $args['hasDefaultBackground'] ?? false;

		// If targeted attributes not provided, separate them from module attributes.
		if ( empty( $this->_targeted_attributes ) && ! empty( $this->module_attrs['module']['decoration']['attributes'] ) ) {
			$this->_targeted_attributes = AttributeUtils::separate_attributes_by_target_element(
				$this->module_attrs['module']['decoration']['attributes']
			);
		}

		// Normalize module metadata.
		if ( is_array( $this->module_metadata ) ) {
			$block_type_args = $this->module_metadata;

			// Replacing certain keys in the array with new keys based on a predefined mapping.
			// @see https://github.com/WordPress/wordpress-develop/blob/d065eedd0d88215637f3468c49a76057f4ca731f/src/wp-includes/blocks.php#L412C29-L412C29.
			$property_mappings = [
				'apiVersion'      => 'api_version',
				'providesContext' => 'provides_context',
				'usesContext'     => 'uses_context',
			];

			foreach ( $property_mappings as $old_key => $new_key ) {
				if ( isset( $block_type_args[ $old_key ] ) ) {
					// Insert the new key-value pair.
					$block_type_args[ $new_key ] = $block_type_args[ $old_key ];

					// Remove the old key-value pair.
					unset( $block_type_args[ $old_key ] );
				}
			}

			$this->module_metadata = new WP_Block_Type( $block_type_args['name'] ?? $this->name, $block_type_args );
		}

		// Override the module name if the module metadata is an instance of WP_Block_Type.
		if ( $this->module_metadata instanceof WP_Block_Type ) {
			$this->name = $this->module_metadata->name ?? '';
		}

		$this->selectors      = $args['selectors'] ?? ModuleRegistration::get_selectors( $this->name );
		$this->order_index    = $args['orderIndex'] ?? null;
		$this->store_instance = $args['storeInstance'] ?? null;

		// Set $is_custom_post_type property.
		if ( isset( $args['is_custom_post_type'] ) ) {
			$this->_is_custom_post_type = $args['is_custom_post_type'];
		}

		// Set sticky parent information.
		$sticky_parent_id = $args['stickyParentId'] ?? null;

		if ( is_string( $sticky_parent_id ) ) {
			$this->_sticky_parent_id        = $sticky_parent_id;
			$this->_is_inside_sticky_module = true;
		} else {
			$this->_is_inside_sticky_module = (bool) $sticky_parent_id;
		}

		if ( $this->_is_inside_sticky_module && $this->_sticky_parent_id ) {
			$this->_sticky_parent_order_class = ModuleUtils::get_module_order_class_name( $this->_sticky_parent_id, $this->store_instance );
		}

		// Set $_is_nested_module property.
		if ( isset( $args['is_nested_module'] ) ) {
			$this->_is_nested_module = $args['is_nested_module'];
		}

		// Set $_is_parent_flex_layout property.
		if ( isset( $args['is_parent_flex_layout'] ) ) {
			$this->_is_parent_flex_layout = $args['is_parent_flex_layout'];
		}

		// Set $_is_parent_grid_layout property.
		if ( isset( $args['is_parent_grid_layout'] ) ) {
			$this->_is_parent_grid_layout = $args['is_parent_grid_layout'];
		}
	}

	/**
	 * Create a new instance of the ModuleElements class with the given arguments.
	 *
	 * @param array $args {
	 *                    An array of arguments.
	 *
	 * @type string $id          The Module unique ID.
	 * @type string $name        The Module name.
	 * @type string|null $stickyParentId Optional. Sticky parent ID. Default `null`.
	 * @type array  $moduleAttrs A key-value pair of module attributes data where the key is the module attribute name
	 *                               and the value is the formatted attribute array.
	 * @type array  $selectors   Optional. A key-value pair of selectors where the key is the module attribute name and
	 *                               the value is the selector.
	 *                               If not provided, the selectors will be retrieved from the module.json config file.
	 *                               Default `ModuleRegistration::get_selectors( $this->name )`.
	 * }
	 *
	 * @return ModuleElements A new instance of the ModuleElements class.
	 */
	public static function create( array $args ): ModuleElements {
		return new ModuleElements( $args );
	}

	/**
	 * Retrieve a selector from the given ModuleElementsAttr instance.
	 *
	 * @since ??
	 *
	 * @param ModuleElementsAttr $instance The instance of ModuleElementsAttr class.
	 *
	 * @return string
	 */
	private function _resolve_selector( ModuleElementsAttr $instance ): string {
		$selector = $instance->get_selector();

		if ( is_string( $selector ) ) {
			return $selector;
		}

		$attr_name = $instance->get_attr_name();

		if ( $attr_name ) {
			return $this->selectors[ $attr_name ] ?? '';
		}

		return '';
	}

	/**
	 * Retrieve module formatted attribute array based from the given ModuleElementsAttr instance.
	 *
	 * @since ??
	 *
	 * @param ModuleElementsAttr $instance The instance of ModuleElementsAttr class.
	 *
	 * @return array
	 */
	private function _resolve_attr( ModuleElementsAttr $instance ): array {
		$attr_name = $instance->get_attr_name();

		if ( $attr_name ) {
			// Element-based attribute structure enforces element content to be located inside `innerContent`
			// property. Therefore automatically retrieve the `innerContent` property if the attribute name is given.
			$attr = $this->module_attrs[ $attr_name ]['innerContent'] ?? $this->module_attrs[ $attr_name ] ?? [];

			// If $attr is not an array, or decoration, advanced, meta is presents in $attr, return an empty array.
			if ( ! is_array( $attr ) || isset( $attr['decoration'] ) || isset( $attr['advanced'] ) || isset( $attr['meta'] ) ) {
				return [];
			}

			return StyleUtils::resolve_dynamic_variables_recursive( $attr );
		}

		$attr = $instance->get_attr();

		if ( is_array( $attr ) ) {
			return StyleUtils::resolve_dynamic_variables_recursive( $attr );
		}

		return [];
	}

	/**
	 * Checks if an array has either 'attrName' or 'attr' keys.
	 *
	 * @since ??
	 *
	 * @param array $array The array to check.
	 *
	 * @return bool
	 */
	private function _is_attr_array( array $array ): bool {
		return isset( $array['attrName'] ) || isset( $array['attr'] );
	}

	/**
	 * Check if the HTML markup for self-closing tags should be rendered or not.
	 *
	 * This is achieved by checking the required attributes values.
	 *
	 * This function is only applicable if the required attributes value is instance of ModuleElementsAttr class.
	 *
	 * @since ??
	 *
	 * @param array  $attributes A key-value pair array of attributes data to check.
	 * @param string $tag        HTML Element tag to check.
	 * @param string $parent_tag Optional. The parent HTML Element tag where this element will be rendered.
	 *                           This is used to compute the required attributes for certain self-closing
	 *                           tags like `source` which needs to know the parent tag to compute the
	 *                           required attributes list. Default empty string.
	 *
	 * @return bool
	 */
	private function _is_render_self_closing_tag( array $attributes, string $tag, string $parent_tag = '' ): bool {
		$is_render = true;
		$required  = HTMLUtility::get_self_closing_tag_required_attrs( $tag, $parent_tag );

		if ( $required ) {
			$required_all        = $required['requiredAll'];
			$required_attributes = $required['attributes'];
			$required_count      = count( $required_attributes );

			foreach ( $required_attributes as $index => $required_attribute ) {
				$populated_attribute = $attributes[ $required_attribute ] ?? null;

				if ( ! $populated_attribute instanceof MultiViewElementValue ) {
					continue;
				}

				$has_value = $populated_attribute->has_value();

				if ( ! $required_all ) {
					if ( $has_value ) {
						break;
					}

					if ( ( $required_count - 1 ) === $index ) {
						$is_render = $has_value;
					}
				}

				if ( $required_all && ! $has_value ) {
					$is_render = false;
					break;
				}
			}
		}

		return $is_render;
	}

	/**
	 * Check if the HTML markup for paired tags should be rendered or not.
	 *
	 * This is achieved by checking the children.
	 *
	 * The function is only applicable if the children is an instance of ModuleElementsAttr class.
	 *
	 * @since ??
	 *
	 * @param string|array|ModuleElementsAttr $children The children element to check.
	 *
	 * @return bool
	 */
	private function _is_render_paired_tag( $children ): bool {
		return $children instanceof MultiViewElementValue ? $children->has_value() : true;
	}

	/**
	 * Populate and convert passed class name data to an instance of MultiViewElementValue class if needed.
	 *
	 * Array of ModuleElementsAttr constructor arguments will be converted to an instance of ModuleElementsAttr class.
	 *
	 * Instance of ModuleElementsAttr will be converted to an instance of MultiViewElementValue class.
	 *
	 * Other values will be returned as is.
	 *
	 * @since ??
	 *
	 * @param array $class_name_data A key-value array of attributes data where the keys are class name and the values
	 *                               can be either a scalar, instance of ModuleElementsAttr or array of
	 *                               ModuleElementsAttr constructor arguments.
	 *
	 * @return array
	 */
	private function _populate_class_name( array $class_name_data ): array {
		$processed = [];

		foreach ( $class_name_data as $class_name => $value ) {
			// Convert class name data to an instance of ModuleElementsAttr class if it's an array and has `attr` key.
			if ( is_array( $value ) && $this->_is_attr_array( $value ) ) {
				$value = ModuleElementsAttr::create(
					[
						'attrName'      => $value['attrName'] ?? null,
						'attr'          => $value['attr'] ?? null,
						'subName'       => $value['subName'] ?? null,
						'valueResolver' => $value['valueResolver'] ?? null,
						'selector'      => $value['selector'] ?? null,
						'hoverSelector' => $value['hoverSelector'] ?? null,
					]
				);
			}

			if ( $value instanceof ModuleElementsAttr ) {
				$processed[ $class_name ] = new MultiViewElementValue(
					[
						'data'          => $this->_resolve_attr( $value ),
						'subName'       => $value->get_sub_name(),
						'valueResolver' => $value->get_value_resolver(),
						'selector'      => $this->_resolve_selector( $value ),
						'hoverSelector' => $value->get_hover_selector(),
					]
				);

				continue;
			}

			$processed[ $class_name ] = $value;
		}

		return $processed;
	}

	/**
	 * Populate and convert passed styles data to an instance of MultiViewElementValue class if needed.
	 *
	 * Array of ModuleElementsAttr constructor arguments will be converted to an instance of ModuleElementsAttr class.
	 *
	 * Instance of ModuleElementsAttr will be converted to an instance of MultiViewElementValue class.
	 *
	 * Other values will be returned as is.
	 *
	 * @since ??
	 *
	 * @param array $style_data A key-value array of attributes data where the keys are style properties and the values can be
	 *                          either a scalar, instance of ModuleElementsAttr or array of ModuleElementsAttr constructor arguments.
	 *
	 * @return array An array of processed style data.
	 */
	private function _populate_style( array $style_data ): array {
		$processed = [];

		foreach ( $style_data as $property => $value ) {
			// Convert style data to an instance of ModuleElementsAttr class if it's an array and has `attr` key.
			if ( is_array( $value ) && $this->_is_attr_array( $value ) ) {
				$value = ModuleElementsAttr::create(
					[
						'attrName'      => $value['attrName'] ?? null,
						'attr'          => $value['attr'] ?? null,
						'subName'       => $value['subName'] ?? null,
						'valueResolver' => $value['valueResolver'] ?? null,
						'selector'      => $value['selector'] ?? null,
						'hoverSelector' => $value['hoverSelector'] ?? null,
					]
				);
			}

			if ( $value instanceof ModuleElementsAttr ) {
				$processed[ $property ] = new MultiViewElementValue(
					[
						'data'          => $this->_resolve_attr( $value ),
						'subName'       => $value->get_sub_name(),
						'valueResolver' => $value->get_value_resolver(),
						'selector'      => $this->_resolve_selector( $value ),
						'hoverSelector' => $value->get_hover_selector(),
					]
				);

				continue;
			}

			$processed[ $property ] = $value;
		}

		return $processed;
	}

	/**
	 * Populate and convert passed attributes data to an instance of MultiViewElementValue class if needed.
	 *
	 * Array of ModuleElementsAttr constructor arguments will be converted to an instance of ModuleElementsAttr class.
	 *
	 * Instance of ModuleElementsAttr will be converted to an instance of MultiViewElementValue class.
	 *
	 * Other values will be returned as is.
	 *
	 * @since ??
	 *
	 * @param array  $attributes_data A key-value array of attributes data where the keys are HTML attribute names and the values can be
	 *                                either a scalar, instance of ModuleElementsAttr or array of ModuleElementsAttr constructor arguments.
	 * @param string $target_element  The target element to get custom attributes for.
	 *
	 * @return array An array of processed attributes.
	 */
	private function _populate_attributes( array $attributes_data, string $target_element = '' ): array {
		$processed = [];

		// Merge targeted custom attributes if specified.
		if ( ! empty( $target_element ) ) {
			$custom_attributes = $this->_get_targeted_custom_attributes( $target_element );

			// Properly merge attributes by checking for collisions and merging values appropriately.
			foreach ( $custom_attributes as $custom_attr_name => $custom_value ) {
				if ( ! isset( $attributes_data[ $custom_attr_name ] ) ) {
					$attributes_data[ $custom_attr_name ] = $custom_value;
					continue;
				}

				$existing_value = $attributes_data[ $custom_attr_name ];

				if ( is_scalar( $existing_value ) && is_scalar( $custom_value ) ) {
					// Attribute collision detected, merge values appropriately.
					$attributes_data[ $custom_attr_name ] = AttributeUtils::merge_attribute_values( $custom_attr_name, $existing_value, $custom_value );
				} elseif ( 'class' === $custom_attr_name && is_array( $existing_value ) && is_scalar( $custom_value ) ) {
					// Merge scalar custom class into array-valued class (e.g. hiddenIfFalsy MultiView classes).
					$custom_classes = array_filter( preg_split( '/\s+/', (string) $custom_value ), 'strlen' );

					foreach ( $custom_classes as $custom_class ) {
						if ( ! isset( $existing_value[ $custom_class ] ) && ! in_array( $custom_class, $existing_value, true ) ) {
							$existing_value[ $custom_class ] = true;
						}
					}

					$attributes_data[ $custom_attr_name ] = $existing_value;
				} else {
					// No collision, add normally.
					$attributes_data[ $custom_attr_name ] = $custom_value;
				}
			}
		}

		foreach ( $attributes_data as $attr_name => $value ) {
			if ( null === $value || is_scalar( $value ) ) {
				$processed[ $attr_name ] = $value;
				continue;
			}

			if ( 'class' === $attr_name ) {
				if ( is_array( $value ) ) {
					$processed[ $attr_name ] = $this->_populate_class_name( $value );
				}
				continue;
			}

			if ( 'style' === $attr_name ) {
				if ( is_array( $value ) ) {
					$processed[ $attr_name ] = $this->_populate_style( $value );
				}
				continue;
			}

			// Convert attribute data to an instance of ModuleElementsAttr class if it's an array and has `attr` key.
			if ( is_array( $value ) && $this->_is_attr_array( $value ) ) {
				$value = ModuleElementsAttr::create(
					[
						'attrName'      => $value['attrName'] ?? null,
						'attr'          => $value['attr'] ?? null,
						'subName'       => $value['subName'] ?? null,
						'valueResolver' => $value['valueResolver'] ?? null,
						'selector'      => $value['selector'] ?? null,
						'hoverSelector' => $value['hoverSelector'] ?? null,
					]
				);
			}

			if ( $value instanceof ModuleElementsAttr ) {
				$processed[ $attr_name ] = new MultiViewElementValue(
					[
						'data'          => $this->_resolve_attr( $value ),
						'subName'       => $value->get_sub_name(),
						'valueResolver' => $value->get_value_resolver(),
						'selector'      => $this->_resolve_selector( $value ),
						'hoverSelector' => $value->get_hover_selector(),
					]
				);
			}
		}

		return $processed;
	}

	/**
	 * Populate children elements and returns a MultiViewElementValue object.
	 *
	 * If the children are not of type ModuleElementsAttr or array of ModuleElementsAttr constructor arguments,
	 * the children are returned as is.
	 *
	 * @since ??
	 *
	 * @param string|array|ModuleElementsAttr $children The children to be populated.
	 *
	 * @return string|array|MultiViewElementValue
	 */
	private function _populate_children( $children ) {
		// Convert children param into an instance of ModuleElementsAttr if the children param is an array and has attr key.
		if ( is_array( $children ) && $this->_is_attr_array( $children ) ) {
			$children = ModuleElementsAttr::create(
				[
					'attrName'      => $children['attrName'] ?? null,
					'attr'          => $children['attr'] ?? null,
					'subName'       => $children['subName'] ?? null,
					'valueResolver' => $children['valueResolver'] ?? null,
					'selector'      => $children['selector'] ?? null,
					'hoverSelector' => $children['hoverSelector'] ?? null,
				]
			);
		}

		if ( $children instanceof ModuleElementsAttr ) {
			return new MultiViewElementValue(
				[
					'data'          => $this->_resolve_attr( $children ),
					'subName'       => $children->get_sub_name(),
					'valueResolver' => $children->get_value_resolver(),
					'selector'      => $this->_resolve_selector( $children ),
					'hoverSelector' => $children->get_hover_selector(),
				]
			);
		}

		return $children;
	}

	/**
	 * Get inside sticky module status.
	 *
	 * @since ??
	 *
	 * @return boolean Whether current module is inside another sticky module or not.
	 */
	public function get_is_inside_sticky_module() {
		return $this->_is_inside_sticky_module;
	}

	/**
	 * Get a sticky parent order class name.
	 *
	 * @since ??
	 *
	 * @return string|null Sticky parent order class name (e.g., 'et_pb_row_2_tb_header') or null if not inside sticky module.
	 */
	public function get_sticky_parent_order_class(): ?string {
		return $this->_sticky_parent_order_class;
	}

	/**
	 * Recalculate sticky parent order class if store instance is now available.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function recalculate_sticky_parent_order_class(): void {
		if ( $this->_sticky_parent_id && ! $this->_sticky_parent_order_class ) {
			$this->_sticky_parent_order_class = ModuleUtils::get_module_order_class_name( $this->_sticky_parent_id, $this->store_instance );
		}
	}

	/**
	 * Get parent layout flex status.
	 *
	 * @since ??
	 *
	 * @return boolean Whether current module is parent layout flex or not.
	 */
	public function get_is_parent_flex_layout() {
		return $this->_is_parent_flex_layout;
	}

	/**
	 * Get parent layout grid status.
	 *
	 * @since ??
	 *
	 * @return boolean Whether current module is parent layout grid or not.
	 */
	public function get_is_parent_grid_layout() {
		return $this->_is_parent_grid_layout;
	}

	/**
	 * Render HTML code with specified attributes and children.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *                    An array of arguments.
	 *
	 * @type string                           $tagName              Optional. HTML Element tag. Default `div`.
	 * @type string                           $parentTag            Optional. The parent HTML Element tag where this element will be rendered. Default empty string.
	 *                                                                  This is used to compute the required attributes for certain self-closing tags like `source` which
	 *                                                                  needs to know the parent tag to compute the required attributes list.
	 * @type array                            $attributes           Optional. A key-value pair array of attributes data. Default `[]`.
	 *                                                                    - The array item key must be a string.
	 *                                                                    - For boolean attributes, the array item value must be a `true`.
	 *                                                                    - For key-value pair attributes, the array item value must be a MultiViewElementValue object,
	 *                                                                      array of ModuleElementsAttr constructor arguments, int, float, string, boolean, array or null.
	 *                                                                    - `ModuleElementsAttr` or array of ModuleElementsAttr constructor arguments value will be
	 *                                                                       computed with multi view data.
	 *                                                                    - `boolean` value will be stringified to avoid `true` get printed as `1` and `false` get
	 *                                                                       printed as `0`.
	 *                                                                    - `array` value only applicable for `style` attribute.
	 *                                                                    - `null` value will skip the attribute to be rendered.
	 * @type string|array|ModuleElementsAttr $children              Optional. The children element. Default `null`.
	 *                                                                    - Pass instance of ModuleElementsAttr or array of ModuleElementsAttr constructor arguments to
	 *                                                                      compute multi view data.
	 *                                                                    - Pass string for single children element.
	 *                                                                    - Pass array for multiple children elements and nested children elements.
	 *                                                                    - Only applicable for non self-closing tags.
	 * @type callable                         $childrenSanitizer    Optional. The function that will be invoked to sanitize/escape the children element. Default `esc_html`.
	 * @type array                            $attributesSanitizers Optional. A key-value pair array of custom sanitizers that will be used to override the default sanitizer.
	 *                                                                  Default `[]`.
	 * @type string                           $attrName             Optional. The Module attribute name. Default empty string.
	 * @type array                            $attr                 Optional. The Module formatted attribute array. Default `[]`.
	 * @type string                           $attrSubName          Optional. The attribute sub name that will be queried. Default `null`.
	 * @type callable                         $valueResolver        Optional. A function that will be invoked to resolve the value. Default `null`.
	 * @type string                           $selector             Optional. The selector of element to be updated. Default `null`.
	 * @type string                           $hoverSelector        Optional. The selector to trigger hover event. Default `null`.
	 * @type bool                             $forceRender          Optional. Flag to keep render the HTML code even if the children element is empty
	 *                                                                  or the required attributes in certain self-closing tags are not provided, or the module attribute that
	 *                                                                  passed into the `hiddenIfFalsy` param has no value across all breakpoints and states is empty.
	 *                                                                  Default `false`.
	 * @type array|ModuleElementsAttr         $hiddenIfFalsy        Optional. Parameter that will be computed to determine if the element should be hidden if
	 *                                                                  certain module attribute value is falsy. Default ``.
	 *                                                                     - Array of ModuleElementsAttr constructor arguments.
	 *                                                                     - Instance of ModuleElementsAttr.
	 * @type string                             $elementType        Optional. The element type. Default `element`.
	 * @type array                              $elementProps       Optional. The element props. Default `[]`.
	 * @type bool                               $skipAttrChildren   Optional. When true, prevents automatic content generation
	 *                                                                  from module attributes and uses explicitly provided children
	 *                                                                  instead. Useful for self-closing tags (input, img) or elements
	 *                                                                  with custom pre-processed content. Default false.
	 *
	 * }
	 *
	 * @return string The rendered HTML code.
	 */
	public function render( array $args ): string {
		// Attribute name. Attribute settings from metadata and module attributes are retrieved from this.
		$attr_name = $args['attrName'] ?? '';

		// Attribute subName.
     // phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
		// TODO feat(D5, Refactor) rename `subName` into `attrSubName`.
		$attr_sub_name = $args['attrSubName'] ?? null;

		// Element attributes.
		$element_attr = $args['elementAttr'] ?? $this->module_attrs[ $attr_name ] ?? [];

		// Resolve dynamic variables in element attributes.
		$element_attr = StyleUtils::resolve_dynamic_variables_recursive( $element_attr );

		// Element settings retrieved from metadata (module.json).
		$element_settings = $this->module_metadata->attributes[ $attr_name ] ?? [];

		// Element type. Some arguments and rendered output are adjusted by this.
		$element_type = $args['elementType'] ?? $element_settings['elementType'] ?? 'element';

		// Element props.
		$element_props = $args['elementProps'] ?? $element_settings['elementProps'] ?? [];

		// Element tag name.
		$tag_name = tag_escape( ( $args['tagName'] ?? $element_settings['tagName'] ?? 'div' ) );

		// Element children's sanitizer.
		switch ( $element_type ) {
			case 'wrapper':
			case 'content':
				$children_sanitizer = $args['childrenSanitizer'] ?? $element_settings['childrenSanitizer'] ?? 'et_core_esc_previously';
				break;

			default:
				// Element children's sanitizer.
				$children_sanitizer = $args['childrenSanitizer'] ?? $element_settings['childrenSanitizer'] ?? 'esc_html';
				break;
		}

		// Check element's type and adjust element property accordingly.
		switch ( $element_type ) {
			case 'heading':
				// `heading` tagName changes based on the selected heading level on `decoration.font.font` attribute.
				// Use runtime attrs for HTML structure; style attrs may have headingLevel stripped for CSS (#48678).
				$runtime_element_attr = $this->runtime_module_attrs[ $attr_name ] ?? [];
				$tag_name             = tag_escape(
					$runtime_element_attr['decoration']['font']['font']['desktop']['value']['headingLevel']
					?? $element_attr['decoration']['font']['font']['desktop']['value']['headingLevel']
					?? $tag_name
				);
				break;

			case 'headingLink':
				// `headingLink` tagName changes based on the selected heading level on `decoration.font.font` attribute.
				// Use runtime attrs for HTML structure; style attrs may have headingLevel stripped for CSS (#48678).
				$runtime_element_attr = $this->runtime_module_attrs[ $attr_name ] ?? [];
				$tag_name             = tag_escape(
					$runtime_element_attr['decoration']['font']['font']['desktop']['value']['headingLevel']
					?? $element_attr['decoration']['font']['font']['desktop']['value']['headingLevel']
					?? $tag_name
				);

				// $attr_sub_name is automatically set for `headingLink` type element.
				$attr_sub_name = 'text';
				break;

			case 'button':
				// $attr_sub_name is automatically set for `button` type element.
				$attr_sub_name = 'text';
				break;

			default:
				break;
		}

		if ( ! is_string( $tag_name ) || ! $tag_name ) {
			return '';
		}

		$is_self_closing_tag   = HTMLUtility::is_self_closing_tag( $tag_name );
		$attributes_sanitizers = $args['attributesSanitizers'] ?? [];
		// Merge metadata attributes with custom attributes (custom attributes take precedence).
		$metadata_attributes = $element_settings['attributes'] ?? [];
		$custom_attributes   = $args['attributes'] ?? [];
		$attributes          = array_merge( $metadata_attributes, $custom_attributes );
		$children            = $args['children'] ?? null;
		$hidden_if_falsy     = $args['hiddenIfFalsy'] ?? [];
		$force_render        = $args['forceRender'] ?? false;
		$parent_tag          = $args['parentTag'] ?? '';
		$allow_empty_value   = $args['allowEmptyValue'] ?? false;
		$skip_attr_children  = $args['skipAttrChildren'] ?? false;
		// Prefer style components that were pre-rendered during module style generation.
		// If none are available for this attr, render them on demand for this element only,
		// and skip usage tracking because this is a fallback render path.
		$has_pre_rendered_style_components = $this->_consume_pre_rendered_style_components( $attr_name );
		$auto_style_components             = '';

		if ( ! $has_pre_rendered_style_components && $this->_should_auto_render_style_components( $attr_name, $element_attr, $element_settings ) ) {
			$auto_style_components = $this->style_components(
				[
					'attrName'   => $attr_name,
					'trackUsage' => false,
				]
			);
		}

		// Ensure element animation class is added for any attr with enabled animation settings.
		// This is required by animation style selectors (e.g. `.et_animated{{selector}}`) and
		// keeps dynamic subgroup-hosted sub-elements behavior consistent with module wrappers.
		$animation_classname   = AnimationUtils::classnames( $element_attr['decoration']['animation'] ?? [] );
		$background_classname  = BackgroundClassnames::classnames( $element_attr['decoration']['background'] ?? [] );
		$layout_classname      = $this->_get_layout_module_classname( $element_attr, $element_settings );
		$additional_classnames = HTMLUtility::classnames(
			$animation_classname,
			$background_classname,
			$layout_classname
		);

		$has_attributes_class = array_key_exists( 'class', $attributes );
		$attributes_class     = $attributes['class'] ?? '';

		if ( is_array( $attributes_class ) ) {
			$additional_classname_parts = array_filter(
				explode( ' ', $additional_classnames ),
				static function ( $class_name ) {
					return '' !== $class_name;
				}
			);

			$attributes['class'] = array_merge(
				$attributes_class,
				array_fill_keys( $additional_classname_parts, true )
			);
		} else {
			$merged_classname = HTMLUtility::classnames(
				$attributes_class,
				$additional_classnames
			);

			if ( '' === $merged_classname && ! $has_attributes_class ) {
				unset( $attributes['class'] );
			} else {
				$attributes['class'] = $merged_classname;
			}
		}

		// Prepare element to be rendered.
		$element = '';

		// Check element's type and adjust rendered element accordingly.
		switch ( $element_type ) {
			case 'button':
				$button_attr = ModuleElementsAttr::create(
					[
						'attrName'      => $args['attrName'] ?? null,
						'attr'          => $args['attr'] ?? null,
						'subName'       => $args['attrSubName'] ?? $attr_sub_name,
						'valueResolver' => function ( $value, $resolver_args ) use ( $args ) {
							$value_resolver = $args['valueResolver'] ?? null;

							if ( null !== $value_resolver ) {
								$value = call_user_func( $value_resolver, $value, $resolver_args );
							}

							// Check if the button link text is plain text or wrapped in a HTML tag.
							// If the text is wrapped in a HTML tag, extract the text title from the tag.
							// Test regex: https://regex101.com/r/E5rBze/3.
							if ( ( preg_match( '/<[^<]+?>/', $value ) ) ) {
								// Extract the title text from the link.
								$value = ModuleUtils::extract_link_title( $value );
							}

								return $value;
						},
						'selector'      => $args['selector'] ?? null,
						'hoverSelector' => $args['hoverSelector'] ?? null,
					]
				);

				$inner_content = $element_props['innerContent'] ?? $element_attr['innerContent'] ?? [];

				$is_force_render = ModuleUtils::has_value(
					$inner_content,
					[
						'valueResolver' => function ( $value, array $resolver_args ) use ( $element_props ) {
							$breakpoint = $resolver_args['breakpoint'] ?? 'desktop';
							$state      = $resolver_args['state'] ?? 'value';

							if ( 'desktop' === $breakpoint && 'value' === $state ) {
									return false;
							}

							return ButtonComponent::is_render(
								array_merge(
									[
										'text'    => $value['text'] ?? '',
										'linkUrl' => $value['linkUrl'] ?? '',
									],
									$element_props
								)
							);
						},
					]
				);

				// Get button classnames.
				$button_classnames = HTMLUtility::classnames(
					$attributes['class'] ?? '',
					MultiViewUtils::hidden_on_load_class_name(
						$inner_content,
						[
							'valueResolver' => function ( $value ) use ( $element_props ) {
								$is_render_args = array_merge(
									[
										'text'    => $value['text'] ?? '',
										'linkUrl' => $value['linkUrl'] ?? '',
									],
									$element_props
								);

								return ButtonComponent::is_render(
									$is_render_args
								) ? 'visible' : 'hidden';
							},
						]
					)
				);

					// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
				// TODO: feat(D5, Improvement) Make `et_pb_newsletter_button_text` class name to be more generic and configurable.
				$text_wrapper_class_name         = 'et_pb_newsletter_button_text';
				$has_text_wrapper                = $element_props['hasTextWrapper'] ?? false;
				$multi_view_set_content_selector = $this->_resolve_selector( $button_attr );

				if ( $has_text_wrapper && $text_wrapper_class_name ) {

					// Split the selector by comma. Append the text wrapper class name to each selector
					// if the selector doesn't contain the text wrapper class name.
					$multi_view_set_content_selector_parts = explode( ',', $multi_view_set_content_selector );

					$multi_view_set_content_selector_parts = array_map(
						function ( $selector ) use ( $text_wrapper_class_name ) {
							if ( ! str_contains( $selector, $text_wrapper_class_name ) ) {
								return $selector . ' .' . $text_wrapper_class_name;
							}

							return $selector;
						},
						$multi_view_set_content_selector_parts
					);

					$multi_view_set_content_selector = implode( ',', $multi_view_set_content_selector_parts );
				}

				$resolved_data = $this->_resolve_attr( $button_attr );

				// If the innerContent key is present into $resolved_data array then only
				// assign that value to $resolved_data otherwise keep it as it is.
				if ( is_array( $resolved_data ) && isset( $resolved_data['innerContent'] ) ) {
					$resolved_data = $resolved_data['innerContent'] ?? [];
				}

				$hover_selector = $button_attr->get_hover_selector() ?? '{{selector}}';

				// MultiView script data configuration for button element.
				MultiViewScriptData::set(
					[
						'id'            => $this->id,
						'name'          => $this->name,
						'storeInstance' => $this->store_instance,
						'setContent'    => [
							[
								'data'          => $resolved_data,
								'selector'      => $multi_view_set_content_selector,
								'hoverSelector' => $hover_selector,
								'subName'       => $button_attr->get_sub_name(),
								'valueResolver' => $button_attr->get_value_resolver(),
							],
						],
						'setVisibility' => [
							[
								'data'          => $resolved_data,
								'selector'      => $this->_resolve_selector( $button_attr ),
								'hoverSelector' => $hover_selector,
								'subName'       => $button_attr->get_sub_name(),
								'valueResolver' => $button_attr->get_value_resolver(),
							],
						],
					]
				);

				// Get custom attributes for the button element and merge with defaults.
				$target_element    = $this->_map_attr_name_to_target_element( $attr_name );
				$merged_attributes = $this->_populate_attributes( $attributes, $target_element );

				// Render button element.
				$element = ButtonComponent::component(
					array_merge(
						[
							'className'    => $button_classnames,
							'innerContent' => $element_attr['innerContent'] ?? [],
							'buttonAttr'   => $element_attr['decoration']['button'] ?? [],
							'forceRender'  => $is_force_render,
							'attributes'   => $merged_attributes,
						],
						$element_props
					)
				);
				break;

			case 'image':
				// Populate image attributes and auto-populate alt/title from featured image metadata when using post_featured_image dynamic content.
				$inner_content = ModuleElementsUtils::populate_image_element_attrs_with_featured_image_alt_title(
					$element_attr['innerContent'] ?? [],
					'alt',
					'title'
				);

				$has_values        = [];
				$attrs_to_populate = [];
				$attr_keys_mapping = [
					'src'    => 'src',
					'alt'    => 'alt',
					'title'  => 'title',
					'width'  => 'width',
					'height' => 'height',
				];

				if ( et_is_responsive_images_enabled() ) {
					$attr_keys_mapping['srcset'] = 'srcset';
					$attr_keys_mapping['sizes']  = 'sizes';
				}

				foreach ( $attr_keys_mapping as $attr_key => $populate_sub_name ) {
					$has_value = ModuleUtils::has_value(
						$inner_content,
						[
							'subName' => $populate_sub_name,
						]
					);

					if ( $has_value ) {
						$attrs_to_populate[ $attr_key ] = [
							'attr'          => $inner_content,
							'selector'      => $args['selector'] ?? $this->selectors[ $attr_name ] ?? '',
							'subName'       => $populate_sub_name,
							'hoverSelector' => $args['hoverSelector'] ?? null,
							'valueResolver' => $args['valueResolver'] ?? null,
						];
					}

					$has_values[ $attr_key ] = $has_value;
				}

				if ( $has_values['src'] || $allow_empty_value ) {
					$wp_image_classes = [];

					foreach ( $inner_content as $breakpoint => $states ) {
						foreach ( $states as $state => $state_value ) {
							$attachment_id = $state_value['id'] ?? 0;

							if ( $attachment_id ) {
								$wp_image_classes[ 'wp-image-' . $attachment_id ] = [
									'attr'          => $inner_content,
									'selector'      => $args['selector'] ?? $this->selectors[ $attr_name ] ?? '',
									'hoverSelector' => $args['hoverSelector'] ?? null,
									'valueResolver' => function ( $value, array $resolver_args ) use ( $breakpoint, $state ) {
										if ( $resolver_args['breakpoint'] === $breakpoint && $resolver_args['state'] === $state ) {
											return 'add';
										}

										return 'remove';
									},
								];
							}
						}
					}

					// Merge custom class names passed from the attributes.
					if ( isset( $attributes['class'] ) ) {
						$wp_image_classes = is_string( $attributes['class'] ) ? array_merge(
							[
								$attributes['class'] => true,
							],
							$wp_image_classes
						) : array_merge( $attributes['class'], $wp_image_classes );

						unset( $attributes['class'] );
					}

					if ( ! empty( $wp_image_classes ) ) {
						// Convert complex class array to string for proper custom attributes merging.
						$attrs_to_populate['class'] = HTMLUtility::classnames( $wp_image_classes );
					}

					$target_element   = $this->_map_attr_name_to_target_element( $attr_name );
					$image_attributes = $this->_populate_attributes( $attrs_to_populate, $target_element );
					$image_attributes = array_merge( $attributes, $image_attributes );

					$element = MultiViewElement::create(
						[
							'id'            => $this->id,
							'name'          => $this->name,
							'storeInstance' => $this->store_instance,
						]
					)->render(
						[
							'tag'                  => 'img',
							'tagEscaped'           => true,
							'attributes'           => $image_attributes,
							'children'             => null,
							'attributesSanitizers' => [],
							'childrenSanitizer'    => null,
						]
					);
				}
				break;

			case 'imageLink':
				// Populate image attributes and auto-populate alt/title from featured image metadata when using post_featured_image dynamic content.
				$inner_content = ModuleElementsUtils::populate_image_element_attrs_with_featured_image_alt_title(
					$element_attr['innerContent'] ?? [],
					'alt',
					'titleText'
				);

				$has_values        = [];
				$attrs_to_populate = [];
				$attr_keys_mapping = [
					'src'    => 'src',
					'alt'    => 'alt',
					'title'  => 'titleText',
					'width'  => 'width',
					'height' => 'height',
				];

				if ( et_is_responsive_images_enabled() ) {
					$attr_keys_mapping['srcset'] = 'srcset';
					$attr_keys_mapping['sizes']  = 'sizes';
				}

				$image_selector = $args['selector'] ?? $this->selectors[ $attr_name ] ?? '';

				foreach ( $attr_keys_mapping as $attr_key => $populate_sub_name ) {
					$has_value = ModuleUtils::has_value(
						$inner_content,
						[
							'subName' => $populate_sub_name,
						]
					);

					if ( $has_value ) {
						$attrs_to_populate[ $attr_key ] = [
							'attr'          => $inner_content,
							'selector'      => $image_selector,
							'subName'       => $populate_sub_name,
							'hoverSelector' => $args['hoverSelector'] ?? null,
							'valueResolver' => $args['valueResolver'] ?? null,
						];
					}

					$has_values[ $attr_key ] = $has_value;
				}

				if ( $has_values['src'] || $allow_empty_value ) {
					$wp_image_classes = [];

					foreach ( $inner_content as $breakpoint => $states ) {
						foreach ( $states as $state => $state_value ) {
							$attachment_id = $state_value['id'] ?? 0;

							if ( $attachment_id ) {
								$wp_image_classes[ 'wp-image-' . $attachment_id ] = [
									'attr'          => $inner_content,
									'selector'      => $image_selector,
									'hoverSelector' => $args['hoverSelector'] ?? null,
									'valueResolver' => function ( $value, array $resolver_args ) use ( $breakpoint, $state ) {
										if ( $resolver_args['breakpoint'] === $breakpoint && $resolver_args['state'] === $state ) {
											return 'add';
										}

										return 'remove';
									},
								];
							}
						}
					}

					// Merge custom class names passed from the attributes.
					if ( isset( $attributes['class'] ) ) {
						$wp_image_classes = is_string( $attributes['class'] ) ? array_merge(
							[
								$attributes['class'] => true,
							],
							$wp_image_classes
						) : array_merge( $attributes['class'], $wp_image_classes );

						unset( $attributes['class'] );
					}

					if ( ! empty( $wp_image_classes ) ) {
						// Convert complex class array to string for proper custom attributes merging.
						$attrs_to_populate['class'] = HTMLUtility::classnames( $wp_image_classes );
					}

					$target_element   = $this->_map_attr_name_to_target_element( $attr_name );
					$image_attributes = $this->_populate_attributes( $attrs_to_populate, $target_element );
					$image_attributes = array_merge( $attributes, $image_attributes );

					$image = MultiViewElement::create(
						[
							'id'            => $this->id,
							'name'          => $this->name,
							'storeInstance' => $this->store_instance,
						]
					)->render(
						[
							'tag'               => 'img',
							'tagEscaped'        => true,
							'attributes'        => $image_attributes,
							'children'          => null,
							'childrenSanitizer' => null,
						]
					);

					$url              = $element_attr['innerContent']['desktop']['value']['linkUrl'] ?? '';
					$url_target       = $element_attr['innerContent']['desktop']['value']['linkTarget'] ?? null;
					$rendered_rel     = $element_attr['innerContent']['desktop']['value']['rel'] ?? [];
					$show_in_lightbox = $element_attr['advanced']['lightbox']['desktop']['value'] ?? 'off';
					$use_overlay      = $element_attr['advanced']['overlay']['desktop']['value']['use'] ?? 'off';
					$is_lightbox      = 'on' === $show_in_lightbox;
					$is_overlay       = 'on' === $use_overlay && ( $is_lightbox || ( ! $is_lightbox && '' !== $url ) );

					// Overlay.
					$hover_icon        = Utils::process_font_icon( $element_attr['advanced']['overlayIcon']['desktop']['value']['hoverIcon'] ?? null );
					$hover_icon_sticky = Utils::process_font_icon( $element_attr['advanced']['overlayIcon']['desktop']['sticky']['hoverIcon'] ?? null );
					$hover_icon_tablet = Utils::process_font_icon( $element_attr['advanced']['overlayIcon']['tablet']['value']['hoverIcon'] ?? null );
					$hover_icon_phone  = Utils::process_font_icon( $element_attr['advanced']['overlayIcon']['phone']['value']['hoverIcon'] ?? null );
					$overlay           = $is_overlay ? HTMLUtility::render(
						[
							'tag'        => 'span',
							'attributes' => [
								'class'            => HTMLUtility::classnames(
									[
										'et_overlay' => true,
										'et_pb_inline_icon' => ! empty( $hover_icon ),
										'et_pb_inline_icon_tablet' => ! empty( $hover_icon_tablet ),
										'et_pb_inline_icon_phone' => ! empty( $hover_icon_phone ),
										'et_pb_inline_icon_sticky' => ! empty( $hover_icon_sticky ),
									]
								),
								'data-icon'        => $hover_icon,
								'data-icon-sticky' => $hover_icon_sticky,
								'data-icon-tablet' => $hover_icon_tablet,
								'data-icon-phone'  => $hover_icon_phone,
							],
						]
					) : '';

					$box_shadow_classname = BoxShadowClassnames::has_overlay( $element_attr['decoration']['boxShadow'] ?? [] );

					$image_wrap = $has_values['src'] && ! empty( $args['imageWrapperClassName'] ) ? HTMLUtility::render(
						[
							'tag'               => 'span',
							'attributes'        => [
								'class' => HTMLUtility::classnames(
									[
										'et_pb_image_wrap' => true,
									],
									$box_shadow_classname
								),
							],
							'childrenSanitizer' => 'et_core_esc_previously',
							'children'          => [
								ElementComponents::component(
									[
										'attrs'         => $element_attr['decoration'] ?? [],
										'id'            => $this->id,
										'background'    => false,
										'boxShadow'     => [
											'settings' => [
												'overlay' => true,
											],
										],
										'orderIndex'    => $this->order_index,
										'storeInstance' => $this->store_instance,
									]
								),
								$image,
								$overlay,
							],
						]
					) : $image . $overlay;

					if ( $is_lightbox ) {
							$link_selector  = '{{selector}} a.et_pb_lightbox_image';
							$target_element = $this->_map_attr_name_to_target_element( $attr_name );
						$link_attributes    = $this->_populate_attributes(
							[
								'href'  => [
									'attr'     => $inner_content,
									'selector' => $link_selector,
									'subName'  => 'src',
								],
								'title' => [
									'attr'     => $inner_content,
									'selector' => $link_selector,
									'subName'  => 'alt',
								],
								'class' => 'et_pb_lightbox_image',
							],
							$target_element
						);

						if ( ! $has_values['alt'] ) {
									unset( $link_attributes['title'] );
						}

						$element = MultiViewElement::create(
							[
								'id'            => $this->id,
								'name'          => $this->name,
								'storeInstance' => $this->store_instance,
							]
						)->render(
							[
								'tag'                  => 'a',
								'tagEscaped'           => true,
								'attributes'           => $link_attributes,
								'childrenSanitizer'    => 'et_core_esc_previously',
								'children'             => $image_wrap,
								'attributesSanitizers' => [
									'href' => [
										'ET\Builder\Framework\Utility\SanitizerUtility',
										'sanitize_image_src',
									],
								],
							]
						);
					} elseif ( ! empty( $url ) ) {
						// Get custom attributes for 'image' target element.
						$target_element  = $this->_map_attr_name_to_target_element( $attr_name );
						$link_attributes = $this->_populate_attributes(
							[
								'href'   => $url,
								'target' => 'on' === $url_target ? '_blank' : null,
								'rel'    => empty( $rendered_rel ) ? null : esc_attr( implode( ' ', $rendered_rel ) ),
							],
							$target_element
						);

						// Keep custom attributes like rel/data/aria on the anchor but avoid duplicating title there.
						// Also make sure we don't print invalid attributes on the anchor.
						unset( $link_attributes['title'], $link_attributes['alt'], $link_attributes['src'], $link_attributes['width'], $link_attributes['height'] );

						$element = HTMLUtility::render(
							[
								'tag'               => 'a',
								'tagEscaped'        => true,
								'attributes'        => $link_attributes,
								'childrenSanitizer' => 'et_core_esc_previously',
								'children'          => $image_wrap,
							]
						);
					} else {
						$element = $image_wrap;
					}
				}

				break;

			case 'wrapper':
				$target_element       = $this->_map_attr_name_to_target_element( $attr_name );
				$populated_attributes = $this->_populate_attributes( $attributes, $target_element );
				$populated_children   = $this->_populate_children( $args['children'] ?? '' );

				$element = HTMLUtility::render(
					[
						'tag'                  => $tag_name,
						'attributes'           => $populated_attributes,
						'children'             => $populated_children,
						'attributesSanitizers' => $attributes_sanitizers,
						'childrenSanitizer'    => $children_sanitizer,
					]
				);
				break;

			case 'content':
				$children          = $args['children'] ?? null;
				$has_value_content = ModuleUtils::has_value(
					$element_attr['innerContent'] ?? [],
					[
						'subName' => $attr_sub_name,
					]
				);

				// Render if: content has value, OR empty values are allowed, OR children are provided.
				$nested_children_content             = is_string( $children ) ? $children : '';
				$has_children                        = ! empty( $children );
				$render_nested_children_with_payload = '' !== $nested_children_content;

				if ( $has_value_content || $allow_empty_value || $has_children ) {
					$target_element       = $this->_map_attr_name_to_target_element( $attr_name );
					$populated_attributes = $this->_populate_attributes( $attributes, $target_element );

					// When skipAttrChildren is true, use explicitly provided children instead of,
					// auto-generating content from module attributes. This prevents unwanted content,
					// conversion for self-closing tags or elements with custom pre-processed children.
					if ( ! $skip_attr_children && $this->_is_attr_array( $args ) ) {
						// Get selector from args, element_settings, or selectors array.
						$content_selector = $args['selector'] ?? $element_settings['selector'] ?? $this->selectors[ $attr_name ] ?? null;

						$populated_children = $this->_populate_children(
							ModuleElementsAttr::create(
								[
									'attrName'      => $args['attrName'] ?? null,
									'attr'          => $args['attr'] ?? null,
									'subName'       => $attr_sub_name,
									'valueResolver' => function ( $value, $resolver_args ) use ( $args, $element_settings, $nested_children_content, $render_nested_children_with_payload ) {
										$value_resolver = $args['valueResolver'] ?? null;

										if ( null !== $value_resolver ) {
											$value = call_user_func( $value_resolver, $value, $resolver_args );
										}

										$allow_shortcodes = $args['allowShortcodes'] ?? $element_settings['allowShortcodes'] ?? true;
										$apply_wpautop    = $args['applyWpautop'] ?? $element_settings['applyWpautop'] ?? true;

										// When wpautop is disabled (e.g., Code module), run shortcodes only and return early.
										if ( ! $apply_wpautop ) {
											if ( $allow_shortcodes ) {
												// Process [embed] before do_shortcode; core registers embed as a noop until WP_Embed::run_shortcode().
												$value = ShortcodeUtils::get_processed_embed_shortcode( (string) $value );
												// Set global post context before shortcode execution when in loop context.
												// This ensures WordPress template tags like `get_the_ID()` work correctly in shortcodes within Loop Builder.
												// Also checks ancestor blocks so loops on parent elements (e.g. column) are handled correctly.
												$loop_post_id = DynamicContentUtils::get_loop_post_id( $this->module_attrs, $this->id, $this->store_instance );
												if ( $loop_post_id > 0 ) {
													$value = DynamicContentUtils::with_loop_post_context(
														$loop_post_id,
														function () use ( $value ) {
															return do_shortcode( $value );
														}
													);
												} else {
													$value = do_shortcode( $value );
												}
											}
											return $value;
										}

										// Preprocess empty paragraphs to match VB behavior and D4 pattern.
										// This ensures empty paragraphs render with consistent height between VB and FE.
										// VB-side applies similar preprocessing in getEditableContent() and processContent() functions.
										// REF: D4 builder.js line 23122 - similar preprocessing for TinyMCE content.
										// REF: Issue:- https://github.com/elegantthemes/Divi/issues/45543 - TinyMCE empty paragraphs height difference VB vs FE.
										// Regex101 link: https://regex101.com/r/InP4zq/1.
										$value = preg_replace( '/<p>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>/i', '<p>&nbsp;</p>', $value );

										// Apply wpautop to wrap content in <p> tags (including shortcode syntax).
										$value = wpautop( $value );

										// Process shortcodes using D4's correct order: wpautop → shortcode_unautop → do_shortcode.
										// REF: D4 includes/builder/core.php lines 310-314.
										// 1. wpautop wraps content including [shortcode] syntax in <p> tags.
										// 2. shortcode_unautop removes <p> tags around [shortcode] syntax.
										// 3. do_shortcode executes shortcodes (now outside of any <p> tag).
										// This prevents shortcode OUTPUT from being wrapped in <p> tags.
										if ( $allow_shortcodes ) {
											$value = shortcode_unautop( $value );
											// Process [embed] before do_shortcode; core registers embed as a noop until WP_Embed::run_shortcode().
											$value = ShortcodeUtils::get_processed_embed_shortcode( (string) $value );
											// Set global post context before shortcode execution when in loop context.
											// This ensures WordPress template tags like `get_the_ID()` work correctly in shortcodes within Loop Builder.
											// Also checks ancestor blocks so loops on parent elements (e.g. column) are handled correctly.
											$loop_post_id = DynamicContentUtils::get_loop_post_id( $this->module_attrs, $this->id, $this->store_instance );
											if ( $loop_post_id > 0 ) {
												$value = DynamicContentUtils::with_loop_post_context(
													$loop_post_id,
													function () use ( $value ) {
														return do_shortcode( $value );
													}
												);
											} else {
												$value = do_shortcode( $value );
											}
										}

										if ( $render_nested_children_with_payload ) {
											return $value . $nested_children_content;
										}

										return $value;
									},
									'selector'      => $content_selector,
									'hoverSelector' => $args['hoverSelector'] ?? null,
								]
							)
						);

					} else {
						$populated_children = $this->_populate_children( $args['children'] ?? '' );
					}

					$element = MultiViewElement::create(
						[
							'id'            => $this->id,
							'name'          => $this->name,
							'storeInstance' => $this->store_instance,
						]
					)->render(
						[
							'tag'                  => $tag_name,
							'tagEscaped'           => true,
							'attributes'           => $populated_attributes,
							'children'             => $populated_children,
							'attributesSanitizers' => $attributes_sanitizers,
							'childrenSanitizer'    => $children_sanitizer,
						]
					);

					// Append nested child modules after MultiView processing.
					//
					// CONTEXT: For 'content' elementType, we need to combine two types of content..
					// 1. Attribute-based content (from module settings) - already processed by MultiViewElement above.
					// 2. Nested child modules (from WordPress innerBlocks) - passed via $args['children'].
					//
					// SECURITY: $args['children'] contains PRE-SANITIZED HTML from WordPress block rendering.
					// Each child module was rendered through.
					// WordPress render_block() → Module render_callback() → HTMLUtility::render()
					// which applies tag_escape(), attribute sanitizers, and childrenSanitizer.
					// DO NOT pass unsanitized user input to $args['children'].
					//
					// IMPLEMENTATION: We use regex to insert child modules before the closing tag because.
					// MultiViewElement has already generated a complete HTML element with a closing tag.
					// The regex pattern:
					// - Uses preg_quote() to safely escape the tag name.
					// - Matches only the LAST closing tag with $ anchor.
					// - Captures the closing tag in group 1 and preserves it with $1.
					//
					// EXAMPLE.
					// Before: <div class="content">User typed content</div>.
					// After:  <div class="content">User typed content<div class="child">Child module</div></div>.
					//
					// TEST: See ModuleElementsChildrenSanitizationTest.php for security validation.
					// REGEX PROOF: https://regex101.com/r/2ILQCp/1.
					if (
						! empty( $children ) &&
						! $skip_attr_children &&
						$this->_is_attr_array( $args )
					) {
						// When content attr data resolved to [] (absent attr or decoration-only),
						// valueResolver was never invoked and nested children were not appended.
						// Allow preg_replace fallback to run in that case, as it did pre-5.4.
						$children_data_is_empty = empty( $populated_children->get_data() );

						if ( ! $render_nested_children_with_payload || $children_data_is_empty ) {
							$element = preg_replace(
								'/(<\/' . preg_quote( $tag_name, '/' ) . '>)$/',
								str_replace( '$', '\\$', $children ) . '$1',
								$element
							);
						}
					}
				}
				break;

			case 'headingLink':
				$heading_link        = $element_attr['innerContent']['desktop']['value']['url'] ?? '';
				$heading_link_target = $element_attr['innerContent']['desktop']['value']['target'] ?? '';

				// Convert attrName or attr param into an instance of ModuleElementsAttr and override the children param.
				// The attrName or attr param is prioritized over the children param.
				// Skip this automatic override if skipAttrChildren is true (useful for container elements).
				if ( ! $skip_attr_children && $this->_is_attr_array( $args ) ) {
					$children = ModuleElementsAttr::create(
						[
							'attrName'      => $args['attrName'] ?? null,
							'attr'          => $args['attr'] ?? null,
							'subName'       => $attr_sub_name,
							'valueResolver' => $args['valueResolver'] ?? null,
							'selector'      => $args['selector'] ?? null,
							'hoverSelector' => $args['hoverSelector'] ?? null,
						]
					);
				}

				if ( $heading_link && $children instanceof ModuleElementsAttr ) {
					$multi_view_set_content_selector = $this->_resolve_selector( $children );

					$multi_view_set_content_selector_parts = explode( ',', $multi_view_set_content_selector );

					$multi_view_set_content_selector_parts = array_map(
						function ( $selector ) {
							$selector = trim( $selector );

							if ( ! str_ends_with( $selector, ' a' ) ) {
								return $selector . ' a';
							}

							return $selector;
						},
						$multi_view_set_content_selector_parts
					);

					$multi_view_set_content_selector = implode( ',', $multi_view_set_content_selector_parts );

					$children = $children->set(
						[
							'selector' => $multi_view_set_content_selector,
						],
						false
					);

					$children = MultiViewElement::create(
						[
							'id'            => $this->id,
							'name'          => $this->name,
							'storeInstance' => $this->store_instance,
						]
					)->render(
						[
							'tag'               => 'a',
							'tagEscaped'        => true,
							'attributes'        => [
								'href'   => $heading_link,
								'target' => 'on' === $heading_link_target ? '_blank' : null,
							],
							'children'          => $this->_populate_children( $children ),
							'childrenSanitizer' => $children_sanitizer,
						]
					);
				} elseif ( $heading_link ) {
					$heading_text = $element_attr['innerContent']['desktop']['value']['text'] ?? '';

					$children = HTMLUtility::render(
						[
							'tag'               => 'a',
							'attributes'        => [
								'href'   => $heading_link,
								'target' => 'on' === $heading_link_target ? '_blank' : null,
							],
							'childrenSanitizer' => 'et_core_esc_previously',
							'children'          => $heading_text,
						]
					);
				}

				$target_element       = $this->_map_attr_name_to_target_element( $attr_name );
				$populated_attributes = $this->_populate_attributes( $attributes, $target_element );
				$populated_children   = $this->_populate_children( $children );

				$element = MultiViewElement::create(
					[
						'id'            => $this->id,
						'name'          => $this->name,
						'storeInstance' => $this->store_instance,
					]
				)->render(
					[
						'tag'                  => $tag_name,
						'tagEscaped'           => true,
						'attributes'           => $populated_attributes,
						'children'             => $populated_children,
						'attributesSanitizers' => $attributes_sanitizers,
						'childrenSanitizer'    => $children_sanitizer,
					]
				);
				break;

			default:
				if ( $hidden_if_falsy ) {
					if ( is_array( $hidden_if_falsy ) && $this->_is_attr_array( $hidden_if_falsy ) ) {
						$hidden_if_falsy = ModuleElementsAttr::create(
							[
								'attrName'      => $hidden_if_falsy['attrName'] ?? null,
								'attr'          => $hidden_if_falsy['attr'] ?? null,
								'subName'       => $hidden_if_falsy['subName'] ?? null,
								'valueResolver' => $hidden_if_falsy['valueResolver'] ?? null,
								'selector'      => $hidden_if_falsy['selector'] ?? null,
								'hoverSelector' => $hidden_if_falsy['hoverSelector'] ?? null,
							]
						);
					}

					if ( $hidden_if_falsy instanceof ModuleElementsAttr ) {
						if ( ! $force_render ) {
							$hidden_if_falsy_has_value = ModuleUtils::has_value(
								$this->_resolve_attr( $hidden_if_falsy ),
								[
									'subName'       => $hidden_if_falsy->get_sub_name(),
									'valueResolver' => $hidden_if_falsy->get_value_resolver(),
								]
							);

								// Bail early if the `hiddenIfFalsy` module attribute has no value.
							if ( ! $hidden_if_falsy_has_value ) {
								return '';
							}
						}

						$attributes_class = $attributes['class'] ?? [];

						if ( ! is_array( $attributes_class ) ) {
							$attributes_class = [ $attributes_class ];
						}

						$attributes['class'] = array_merge(
							$attributes_class,
							[
								'et_multi_view_hidden' => $hidden_if_falsy->set(
									[
										// Override the valueResolver param to check if the value is falsy.
										'valueResolver' => function ( $value, array $resolver_args ) use ( $hidden_if_falsy ) {
											$value_resolver_original = $hidden_if_falsy->get_value_resolver();

											if ( is_callable( $value_resolver_original ) ) {
												$value = call_user_func( $value_resolver_original, $value, $resolver_args );
											}

											return empty( $value ) ? 'add' : 'remove'; // Add class `et_multi_view_hidden` if the value is falsy.
										},
									]
								),
							]
						);
					}
				}

				if ( $is_self_closing_tag ) {
					$children = null;
				 // phpcs:ignore Universal.ControlStructures.DisallowLonelyIf.Found -- Intentional else-if pattern for readability.
				} else {
					// Convert attrName or attr param into an instance of ModuleElementsAttr and override the children param.
					// The attrName or attr param is prioritized over the children param.
					// Skip this automatic override if skipAttrChildren is true (useful for container elements).
					if ( ! $skip_attr_children && $this->_is_attr_array( $args ) ) {
						$children = ModuleElementsAttr::create(
							[
								'attrName'      => $args['attrName'] ?? null,
								'attr'          => $args['attr'] ?? null,
								'subName'       => $attr_sub_name,
								'valueResolver' => $args['valueResolver'] ?? null,
								'selector'      => $args['selector'] ?? null,
								'hoverSelector' => $args['hoverSelector'] ?? null,
							]
						);
					}
				}

				$target_element       = $this->_map_attr_name_to_target_element( $attr_name );
				$populated_attributes = $this->_populate_attributes( $attributes, $target_element );
				$populated_children   = $this->_populate_children( $children );

				if ( ! $force_render ) {
					if ( $is_self_closing_tag ) {
						$is_render = $this->_is_render_self_closing_tag( $populated_attributes, $tag_name, $parent_tag );
					} else {
						$is_render = $this->_is_render_paired_tag( $populated_children );
					}

					// Bail early if the children element is empty or the attributes that required by certain tag is empty.
					if ( ! $is_render ) {
						return '';
					}
				}
				$element = MultiViewElement::create(
					[
						'id'            => $this->id,
						'name'          => $this->name,
						'storeInstance' => $this->store_instance,
					]
				)->render(
					[
						'tag'                  => $tag_name,
						'tagEscaped'           => true,
						'attributes'           => $populated_attributes,
						'children'             => $populated_children,
						'attributesSanitizers' => $attributes_sanitizers,
						'childrenSanitizer'    => $children_sanitizer,
					]
				);
				break;
		}

		/**
		 * Filter the element before rendered module element.
		 *
		 * @since ??
		 *
		 * @param string $before_element The element before rendered module element.
		 * @param array  $args           Module element parameters.
		 * @param object $this           The ModuleElements instance.
		 */
		$before_element = apply_filters( 'divi_module_elements_before_render', '', $args, $this );

		/**
		 * Filter the rendered module element.
		 *
		 * @since ??
		 *
		 * @param string $element The rendered module element.
		 * @param array  $args    Module element parameters.
		 * @param object $this    The ModuleElements instance.
		 */
		$element = apply_filters( 'divi_module_elements_render', $element, $args, $this );

		$should_wrap_parallax_content = '' !== $background_classname
			&& (
				str_contains( $background_classname, 'et_pb_section_parallax' )
				|| str_contains( $background_classname, 'et-pb-has-background-video' )
			)
			&& '' !== $auto_style_components;

		// Inject auto-generated style components as the first child of the rendered element.
		// This enables parallax/video/pattern/mask background structure for sub-elements that
		// rely on dynamic subgroups without requiring every module to call style_components() manually.
		$element = $this->_inject_style_components( $element, $auto_style_components, $should_wrap_parallax_content );

		/**
		 * Filter the element after rendered module element.
		 *
		 * @since ??
		 *
		 * @param string $after_element The element after rendered module element.
		 * @param array  $args          Module element parameters.
		 * @param object $this          The ModuleElements instance.
		 */
		$after_element = apply_filters( 'divi_module_elements_after_render', '', $args, $this );

		return $before_element . $element . $after_element;
	}

	/**
	 * Set base order class.
	 *
	 * @since ??
	 *
	 * @param string $base_order_class The base order class.
	 */
	public function set_base_order_class( string $base_order_class ): void {
		$this->base_order_class = $base_order_class;
	}

	/**
	 * Set the order class.
	 *
	 * @since ??
	 *
	 * @param string $order_class The order class.
	 *
	 * @return void
	 */
	public function set_order_class( string $order_class ): void {
		$this->order_class = $order_class;
	}

	/**
	 * Set base wrapper order class.
	 *
	 * @since ??
	 *
	 * @param string $base_wrapper_order_class The base wrapper order class.
	 */
	public function set_base_wrapper_order_class( string $base_wrapper_order_class ): void {
		$this->base_wrapper_order_class = $base_wrapper_order_class;
	}

	/**
	 * Set the wrapper order class.
	 *
	 * @since ??
	 *
	 * @param string $wrapper_order_class The order class.
	 *
	 * @return void
	 */
	public function set_wrapper_order_class( string $wrapper_order_class ): void {
		$this->wrapper_order_class = $wrapper_order_class;
	}

	/**
	 * Set module name class.
	 *
	 * @since ??
	 *
	 * @param string $module_name_class The module name class.
	 *
	 * @return void
	 */
	public function set_module_name_class( string $module_name_class ): void {
		$this->module_name_class = $module_name_class;
	}

	/**
	 * Set the module order ID.
	 *
	 * @since ??
	 *
	 * @param string $order_id The order ID.
	 *
	 * @return void
	 */
	public function set_order_id( string $order_id ): void {
		$this->order_id = $order_id;
	}

	/**
	 * Set module script data.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *                    An array of arguments.
	 *
	 * @type string   $attrName        Optional. The attribute name declared in module.json config file. Default empty string.
	 * @type array    $scriptDataProps Optional. A key-value pair array of script data props. Default `[]`.
	 * @type callable $attrsResolver   Optional. A function that will be called to filter/resolve the attributes data. Default `null`.
	 * }
	 *
	 * @return void
	 */
	public function script_data( array $args ): void {
		$attr_name         = $args['attrName'] ?? '';
		$script_data_props = $args['scriptDataProps'] ?? [];
		$attrs_resolver    = $args['attrsResolver'] ?? null;
		$style_group       = $args['group'] ?? $this->_style_group;

		$merged_attrs    = $this->runtime_module_attrs;
		$decoration_attr = $merged_attrs[ $attr_name ]['decoration'] ?? [];
		$settings        = $this->module_metadata->attributes[ $attr_name ] ?? [];

		$settings_script_data_props = $settings['scriptDataProps'] ?? [];

		$element_selector = $this->_get_dynamic_subgroup_selector( $settings, $style_group );

     // phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
		// TODO feat(D5, Module Attribute Refactor) Once link is merged as part of options property, remove this.
		if ( 'module' === $attr_name && ! isset( $decoration_attr['link'] ) ) {
			$link_attr = $this->module_attrs['module']['advanced']['link'] ?? [];

			if ( ! empty( $link_attr ) ) {
				$decoration_attr['link'] = $link_attr;
			}
		}

		$script_data_params = array_merge(
			[
				'id'            => $this->id,
				'selector'      => $element_selector,
				'attrs'         => $decoration_attr,

				// FE only.
				'storeInstance' => $this->store_instance,
			],
			// From module.json.
			$settings_script_data_props,
			// Overridden.
			$script_data_props
		);

		// If attrsResolver is provided, call it to filter/resolve the attributes.
		if ( is_callable( $attrs_resolver ) ) {
			$script_data_params['attrs'] = call_user_func( $attrs_resolver, $script_data_params['attrs'] ?? [] );
		}

		ElementScriptData::set( $script_data_params );

		// Dynamic subgroup script-data can be enabled on sub-elements even when module_script_data()
		// only calls script_data() for the `module` attr.
		if ( 'module' === $attr_name ) {
			$this->_set_dynamic_subgroup_script_data( $style_group );
		}
	}

	/**
	 * Register script data for dynamic-subgroup-hosted sub-elements.
	 *
	 * @since ??
	 *
	 * @param string $style_group Current style group.
	 *
	 * @return void
	 */
	private function _set_dynamic_subgroup_script_data( string $style_group ): void {
		$merged_attrs = $this->runtime_module_attrs;

		foreach ( $merged_attrs as $sub_attr_name => $sub_attr ) {
			if ( ! is_string( $sub_attr_name ) || 'module' === $sub_attr_name || ! is_array( $sub_attr ) ) {
				continue;
			}

			$sub_attr_settings = $this->module_metadata->attributes[ $sub_attr_name ] ?? [];

			$has_dynamic_subgroup_host = $this->_has_dynamic_subgroup_host( $sub_attr_settings )
				|| $this->_has_dynamic_subgroup_host_from_group_settings( $sub_attr_name );

			if ( ! $has_dynamic_subgroup_host ) {
				continue;
			}

			$animation_attr  = $sub_attr['decoration']['animation'] ?? [];
			$background_attr = $sub_attr['decoration']['background'] ?? [];

			if ( empty( $animation_attr ) && empty( $background_attr ) ) {
				continue;
			}

			$default_selector   = $this->_get_dynamic_subgroup_selector( $sub_attr_settings, $style_group );
			$animation_selector = $this->_get_dynamic_subgroup_selector( $sub_attr_settings, $style_group, 'animation' );

			if ( '' === $default_selector && '' === $animation_selector ) {
				continue;
			}

			if ( ! empty( $animation_attr ) && '' !== $animation_selector ) {
				AnimationScriptData::set(
					[
						'id'            => $this->id,
						'selector'      => $animation_selector,
						'attr'          => $animation_attr,
						'moduleAttrs'   => $sub_attr['decoration'] ?? [],
						'storeInstance' => $this->store_instance,
					]
				);
			}

			if ( ! empty( $background_attr ) && '' !== $default_selector ) {
				BackgroundParallaxScriptData::set(
					[
						'id'            => $this->id,
						'selector'      => $default_selector,
						'attr'          => $background_attr,
						'storeInstance' => $this->store_instance,
					]
				);

				BackgroundVideoScriptData::set(
					[
						'id'            => $this->id,
						'selector'      => $default_selector,
						'attr'          => $background_attr,
						'storeInstance' => $this->store_instance,
					]
				);
			}
		}
	}

	/**
	 * Build selector used by dynamic subgroup script-data registrations.
	 *
	 * @since ??
	 *
	 * @param array  $sub_attr_settings Sub-element settings from module metadata.
	 * @param string $style_group       Current style group.
	 * @param string $script_data_type  Optional script-data type key.
	 *
	 * @return string
	 */
	private function _get_dynamic_subgroup_selector( array $sub_attr_settings, string $style_group, string $script_data_type = '' ): string {
		$script_data_selector = '';
		$style_props_selector = ModuleElementsUtils::get_first_nested_string_by_key(
			$sub_attr_settings['styleProps'] ?? [],
			'selector'
		);

		if ( '' !== $script_data_type ) {
			$script_data_selector = $sub_attr_settings['scriptDataProps'][ $script_data_type ]['selector'] ?? '';
		}

		// For preset style group, always use original order class.
		if ( 'preset' === $style_group ) {
			$setting_selector = $this->order_class;
		} elseif ( is_string( $script_data_selector ) && '' !== $script_data_selector ) {
				$setting_selector = $script_data_selector;
		} else {
			$setting_selector = $this->_is_custom_post_type && isset( $sub_attr_settings['customPostTypeSelector'] )
				? $sub_attr_settings['customPostTypeSelector']
				: ( $sub_attr_settings['selector'] ?? $style_props_selector );
		}

		$element_selector = isset( $setting_selector ) ? ModuleElementsUtils::interpolate_selector(
			[
				'selectorTemplate' => $setting_selector,
				'value'            => $this->order_class,
			]
		) : '';

		if ( isset( $this->order_id ) ) {
			$element_selector = ModuleElementsUtils::interpolate_selector(
				[
					'selectorTemplate' => $element_selector,
					'value'            => $this->order_id,
					'placeholder'      => '{{orderId}}',
				]
			);
		}

		if ( $this->base_order_class ) {
			$element_selector = ModuleElementsUtils::interpolate_selector(
				[
					'selectorTemplate' => $element_selector,
					'value'            => $this->base_order_class,
					'placeholder'      => '{{baseSelector}}',
				]
			);
		}

		// Regex test: https://regex101.com/r/0wBd8m/1.
		return preg_replace( '/\{\{[^}]+\}\}/', '', $element_selector );
	}

	/**
	 * Check whether element settings include dynamic subgroup host support.
	 *
	 * @since ??
	 *
	 * @param array $settings Element settings from module metadata.
	 *
	 * @return bool
	 */
	private function _has_dynamic_subgroup_host( array $settings ): bool {
		if ( isset( $settings['dynamicSubgroupHost'] ) ) {
			return true === $settings['dynamicSubgroupHost'];
		}

		foreach ( $settings as $value ) {
			if ( is_array( $value ) && $this->_has_dynamic_subgroup_host( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether settings.groups marks the given attribute group as dynamic subgroup host.
	 *
	 * @since ??
	 *
	 * @param string $attr_name Module attribute name.
	 *
	 * @return bool
	 */
	private function _has_dynamic_subgroup_host_from_group_settings( string $attr_name ): bool {
		$settings               = $this->module_metadata->settings ?? [];
		$groups                 = is_array( $settings ) ? ( $settings['groups'] ?? [] ) : [];
		$attr_settings          = $this->module_metadata->attributes[ $attr_name ] ?? [];
		$referenced_group_slugs = [];

		if ( ! is_array( $groups ) ) {
			return false;
		}

		$collect_group_slugs = static function ( $value ) use ( &$collect_group_slugs, &$referenced_group_slugs ): void {
			if ( ! is_array( $value ) ) {
				return;
			}

			$is_group_item = isset( $value['groupType'] ) && 'group-item' === $value['groupType'];
			$group_slug    = $value['item']['groupSlug'] ?? '';

			if ( $is_group_item && is_string( $group_slug ) && '' !== $group_slug ) {
				$referenced_group_slugs[ $group_slug ] = true;
			}

			foreach ( $value as $nested_value ) {
				$collect_group_slugs( $nested_value );
			}
		};

		$collect_group_slugs( $attr_settings['settings'] ?? [] );

		foreach ( $groups as $group_key => $group_settings ) {
			if ( ! is_array( $group_settings ) ) {
				continue;
			}

			$group_name         = $group_settings['groupName'] ?? '';
			$matches_group_name = $attr_name === $group_name;
			$matches_group_slug = is_string( $group_key ) && isset( $referenced_group_slugs[ $group_key ] );

			if ( ! $matches_group_name && ! $matches_group_slug ) {
				continue;
			}

			$is_dynamic_host = $group_settings['component']['props']['dynamicSubgroupHost'] ?? false;

			if ( true === $is_dynamic_host ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Set the style group which will be used to calculate the attributes data that will be used to render the style.
	 *
	 * @since ??
	 *
	 * @param string $group The style group.
	 *
	 * @return void
	 */
	public function set_style_group( string $group ) {
		$this->_style_group = $group;
	}

	/**
	 * Set the preset priority for CSS rendering order.
	 *
	 * @since ??
	 *
	 * @param int $priority The preset priority.
	 *
	 * @return void
	 */
	public function set_preset_priority( int $priority ) {
		$this->_preset_priority         = $priority;
		self::$_current_preset_priority = $priority;
	}

	/**
	 * Get the preset priority for CSS rendering order.
	 *
	 * @since ??
	 *
	 * @return int|null The preset priority, or null if not set.
	 */
	public function get_preset_priority() {
		return $this->_preset_priority;
	}

	/**
	 * Get the current preset priority being rendered (static accessor).
	 *
	 * @since ??
	 *
	 * @return int|null The current preset priority, or null if not set.
	 */
	public static function get_current_preset_priority() {
		return self::$_current_preset_priority;
	}

	/**
	 * Clear the current preset priority (called after preset rendering is complete).
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function clear_current_preset_priority() {
		self::$_current_preset_priority = null;
	}

	/**
	 * Render style declaration.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *                    An array of arguments.
	 *
	 * @type string $attrName   Optional. The attribute name declared in module.json config file. Default empty string.
	 * @type array  $styleProps Optional. A key-value pair array of style props. Default `[]`.
	 * @type bool   $isMergeRecursiveProps Optional. Whether to merge style properties recursively. Default `false`.
	 * @type string $group      Optional. The style group. This group will be used to calculate the attributes data that will be used to render the style. Default `module`.
	 * }
	 *
	 * @return string|array|null
	 */
	public function style( array $args ) {
		$attr_name   = $args['attrName'] ?? '';
		$style_props = $args['styleProps'] ?? [];
		$style_group = $args['group'] ?? $this->_style_group;

		$is_merge_recursive_props = $args['isMergeRecursiveProps'] ?? false;

		// Merge the decoration attributes to ensure transitions are included.
		// The 'transition' attribute is critical for applying CSS transition properties
		// (e.g., duration, timing-function) defined in the module's decoration. By merging
		// it here, we ensure that transitions are consistently applied across modules that contains buttons.
		$decoration_attr = array_merge(
			$this->module_attrs[ $attr_name ]['decoration'] ?? [],
			[
				'transition' => $this->module_attrs['module']['decoration']['transition'] ?? [],
			]
		);
		$settings        = $this->module_metadata->attributes[ $attr_name ] ?? [];

		$is_preset_style_group       = 'preset' === $style_group;
		$is_preset_group_style_group = 'presetGroup' === $style_group;
		$style_props_selector        = ModuleElementsUtils::get_first_nested_string_by_key(
			$settings['styleProps'] ?? [],
			'selector'
		);
		$base_settings_selector      = isset( $settings['selector'] ) && is_string( $settings['selector'] )
			? $settings['selector']
			: '';
		$resolved_settings_selector  = '' !== $base_settings_selector ? $base_settings_selector : $style_props_selector;
		$has_custom_post_type_selector = $this->_is_custom_post_type && isset( $settings['customPostTypeSelector'] );

		if ( $is_preset_style_group || $is_preset_group_style_group ) {
			// For preset style groups, use customPostTypeSelector in CPT context when provided;
			// otherwise, use settings selector (with styleProps fallback) or original order class.
			if ( $has_custom_post_type_selector ) {
				$setting_selector = $settings['customPostTypeSelector'];
			} elseif ( '' !== $resolved_settings_selector ) {
				$setting_selector = $resolved_settings_selector;
			} else {
				$setting_selector = $this->order_class;
			}
		} else {
			// Get settings selector. Custom post type can have its own selector if the auto-prefixed selector is not
			// suitable compared to the default selector (eg. button module).
			if ( $has_custom_post_type_selector ) {
				$setting_selector = $settings['customPostTypeSelector'];
			} else {
				$setting_selector = $resolved_settings_selector;
			}
		}

		$element_selector = isset( $setting_selector ) ? ModuleElementsUtils::interpolate_selector(
			[
				'selectorTemplate' => $setting_selector,
				'value'            => $this->order_class,
			]
		) : '';

		if ( $this->base_order_class ) {
			$element_selector = ModuleElementsUtils::interpolate_selector(
				[
					'selectorTemplate' => $element_selector,
					'value'            => $this->base_order_class,
					'placeholder'      => '{{baseSelector}}',
				]
			);
		}

		if ( $this->base_wrapper_order_class ) {
			$element_selector = ModuleElementsUtils::interpolate_selector(
				[
					'selectorTemplate' => $element_selector,
					'value'            => $this->base_wrapper_order_class,
					'placeholder'      => '{{baseWrapperSelector}}',
				]
			);
		}

		if ( $this->wrapper_order_class ) {
			$element_selector = ModuleElementsUtils::interpolate_selector(
				[
					'selectorTemplate' => $element_selector,
					'value'            => $this->wrapper_order_class,
					'placeholder'      => '{{wrapperSelector}}',
				]
			);
		}

		// Always interpolate nestedModuleNameSelector placeholder, even if module_name_class is not set.
		// This prevents the placeholder from appearing in the final CSS output.
		$element_selector = ModuleElementsUtils::interpolate_selector(
			[
				'selectorTemplate' => $element_selector,
				'value'            => ( $this->module_name_class && $this->_is_nested_module ) ? " .{$this->module_name_class} " : '',
				'placeholder'      => '{{nestedModuleNameSelector}}',
			]
		);

		// Only apply selectorPrefix when using default selector; skip when customPostTypeSelector is used.
		$using_custom_selector = $this->_is_custom_post_type && ! empty( $settings['customPostTypeSelector'] );

		if ( $this->_is_custom_post_type && ! $using_custom_selector ) {
			$element_selector = ModuleElementsUtils::interpolate_selector(
				[
					'selectorTemplate' => $element_selector,
					'value'            => '.et-db #et-boc .et-l ',
					'placeholder'      => '{{selectorPrefix}}',
				]
			);
		} else {
			$element_selector = ModuleElementsUtils::interpolate_selector(
				[
					'selectorTemplate' => $element_selector,
					'value'            => '',
					'placeholder'      => '{{selectorPrefix}}',
				]
			);
		}

		if ( isset( $this->order_id ) ) {
			$element_selector = ModuleElementsUtils::interpolate_selector(
				[
					'selectorTemplate' => $element_selector,
					'value'            => $this->order_id,
					'placeholder'      => '{{orderId}}',
				]
			);
		}

		// Use customPostTypeSelector in styleProps if available.
		$style_props_settings = $settings['styleProps'] ?? null;

		if ( $this->_is_custom_post_type && is_array( $style_props_settings ) && ! empty( $style_props_settings['customPostTypeSelector'] ) ) {
			$style_props_settings['selector'] = $style_props_settings['customPostTypeSelector'];
		}

		$settings_style_props = isset( $style_props_settings ) ? ModuleElementsUtils::interpolate_selector(
			[
				'selectorTemplate' => $style_props_settings,
				'value'            => $this->order_class,
			]
		) : [];

		if ( $this->base_order_class ) {
			$settings_style_props = ModuleElementsUtils::interpolate_selector(
				[
					'selectorTemplate' => $settings_style_props,
					'value'            => $this->base_order_class,
					'placeholder'      => '{{baseSelector}}',
				]
			);
		}

		if ( $this->base_wrapper_order_class ) {
			$settings_style_props = ModuleElementsUtils::interpolate_selector(
				[
					'selectorTemplate' => $settings_style_props,
					'value'            => $this->base_wrapper_order_class,
					'placeholder'      => '{{baseWrapperSelector}}',
				]
			);
		}

		// Always interpolate {{wrapperSelector}}. When wrapper is absent (e.g. Link with enableWhenChildren, no children),
		// construct the wrapper selector so we output both module and wrapper. Unmatched wrapper selectors are harmless.
		$wrapper_selector_value = $this->wrapper_order_class;
		if ( ! $wrapper_selector_value && $this->base_order_class ) {
			$wrapper_selector_value = str_replace( $this->base_order_class, $this->base_order_class . '_wrapper', $this->order_class );
		}
		$settings_style_props = ModuleElementsUtils::interpolate_selector(
			[
				'selectorTemplate' => $settings_style_props,
				'value'            => $wrapper_selector_value,
				'placeholder'      => '{{wrapperSelector}}',
			]
		);

		// Always interpolate nestedModuleNameSelector placeholder, even if module_name_class is not set.
		// This prevents the placeholder from appearing in the final CSS output.
		$settings_style_props = ModuleElementsUtils::interpolate_selector(
			[
				'selectorTemplate' => $settings_style_props,
				'value'            => ( $this->module_name_class && $this->_is_nested_module ) ? " .{$this->module_name_class} " : '',
				'placeholder'      => '{{nestedModuleNameSelector}}',
			]
		);

		// Only apply selectorPrefix for styleProps when not using customPostTypeSelector.
		$using_custom_style_props = $this->_is_custom_post_type && is_array( $style_props_settings ) && ! empty( $style_props_settings['customPostTypeSelector'] );

		if ( $this->_is_custom_post_type && ! $using_custom_style_props ) {
			$settings_style_props = ModuleElementsUtils::interpolate_selector(
				[
					'selectorTemplate' => $settings_style_props,
					'value'            => '.et-db #et-boc .et-l ',
					'placeholder'      => '{{selectorPrefix}}',
				]
			);
		} else {
			$settings_style_props = ModuleElementsUtils::interpolate_selector(
				[
					'selectorTemplate' => $settings_style_props,
					'value'            => '',
					'placeholder'      => '{{selectorPrefix}}',
				]
			);
		}

		if ( isset( $this->order_id ) ) {
			$settings_style_props = ModuleElementsUtils::interpolate_selector(
				[
					'selectorTemplate' => $settings_style_props,
					'value'            => $this->order_id,
					'placeholder'      => '{{orderId}}',
				]
			);
		}

		$settings_element_type                   = $settings['elementType'] ?? null;
		$button_sizing_alignment_advanced_styles = [];

		switch ( $settings_element_type ) {
			case 'element':
			case 'button':
				$settings_style_props['type'] = $settings_element_type;

				if ( 'button' === $settings_element_type ) {
					$button_sizing_alignment_advanced_styles = [
						[
							'componentName' => 'divi/common',
							'props'         => [
								'selector'            => ! empty( $this->wrapper_order_class )
									? $this->wrapper_order_class
									: $this->_get_button_wrapper_selector( $element_selector ),
								'attr'                => $decoration_attr['sizing'] ?? [],
								'declarationFunction' => [ Button::class, 'style_declaration' ],
							],
						],
					];
				}
				break;

			default:
				// Do nothing.
				break;
		}

		// Extract default printed style attributes for this specific attribute.
		$default_printed_style_attr = $this->default_printed_style_attrs[ $attr_name ]['decoration'] ?? [];

		// Detect if presets are actively applied.
		$preset_printed_style_attr = $this->preset_printed_style_attrs[ $attr_name ]['decoration'] ?? [];
		$has_background_presets    = isset( $preset_printed_style_attr['background'] ) && 'module' === $this->_style_group;

		// Use the flag set during module registration to detect default render backgrounds.
		$has_default_background = $this->has_default_background && 'module' === $this->_style_group;

		if ( 'module' === $this->_style_group ) {
			// Extract preset printed style attributes for this specific attribute.
			$default_printed_style_attr = array_replace_recursive( $default_printed_style_attr, $preset_printed_style_attr );
		}

		$merged_style_props = $is_merge_recursive_props ? array_replace_recursive( $settings_style_props, $style_props ) : array_merge( $settings_style_props, $style_props );

		if (
			isset( $merged_style_props['attrs'] ) &&
			is_array( $merged_style_props['attrs'] ) &&
			! array_key_exists( 'transition', $merged_style_props['attrs'] )
		) {
			$merged_style_props['attrs']['transition'] = $decoration_attr['transition'] ?? [];
		}

		if ( ! empty( $button_sizing_alignment_advanced_styles ) ) {
			$merged_style_props['advancedStyles'] = array_merge(
				$merged_style_props['advancedStyles'] ?? [],
				$button_sizing_alignment_advanced_styles
			);
		}

		// Extract defaultPrintedStyleAttrs from merged_style_props to prevent overwrite during final merge.
		// $merged_style_props['defaultPrintedStyleAttrs'] contains $style_props['defaultPrintedStyleAttrs'] (from caller).
		// We'll merge it separately using array_replace_recursive so nested values (like boxShadow) merge correctly.
		$merged_style_props_default_printed_style_attrs = $merged_style_props['defaultPrintedStyleAttrs'] ?? [];

		// Remove defaultPrintedStyleAttrs from merged_style_props so it doesn't overwrite our merged value.
		unset( $merged_style_props['defaultPrintedStyleAttrs'] );

		// Merge defaultPrintedStyleAttrs separately using array_replace_recursive.
		// $default_printed_style_attr has the merged preset from ModuleElements (preset data).
		// Preset data (second arg) takes precedence for detection purposes only.
		// Note: This preserves preset DATA for detection, NOT to override user's local selection.
		// User's local module values ($element_attrs) always win over preset values (Divi standard).
		// This matches Visual Builder pattern: merge({}, styleProps?.defaultPrintedStyleAttrs, defaultPrintedStyleAttr).
		$final_default_printed_style_attrs = array_replace_recursive(
			$merged_style_props_default_printed_style_attrs,
			$default_printed_style_attr
		);

		return ElementStyle::style(
			array_merge(
				[
					'attrs'                    => $decoration_attr,
					'defaultPrintedStyleAttrs' => $final_default_printed_style_attrs,
					'orderClass'               => $this->order_class, // Module orderClass.
					'selector'                 => $element_selector,
					'isInsideStickyModule'     => $this->_is_inside_sticky_module,
					'stickyParentOrderClass'   => $this->get_sticky_parent_order_class(),
					'isParentFlexLayout'       => $this->_is_parent_flex_layout,
					'isParentGridLayout'       => $this->_is_parent_grid_layout,
					'hasBackgroundPresets'     => $has_background_presets,
					'hasDefaultBackground'     => $has_default_background,

					// We need to set `returnType` as `array` so that `Style::render` can reduce style-outputs by
					// combining styles based on declaration.
					'returnType'               => 'array',
				],
				$merged_style_props
			)
		);
	}

	/**
	 * Convert a button selector into its wrapper selector.
	 *
	 * @since ??
	 *
	 * @param string $selector Button selector.
	 *
	 * @return string
	 */
	private function _get_button_wrapper_selector( string $selector ): string {
		$selectors = array_filter(
			array_map( 'trim', explode( ',', $selector ) )
		);

		$converted_selectors = [];

		// Regex test: https://regex101.com/r/sq9grX/1.
		foreach ( $selectors as $selector_item ) {
			$converted_selector = preg_replace(
				'/\s+[^\s]*\.et_pb_button(?:\.[^\s]*)*$/',
				' .et_pb_button_wrapper',
				$selector_item
			);

			if ( is_string( $converted_selector ) && $converted_selector !== $selector_item ) {
				$converted_selectors[] = $converted_selector;
			}
		}

		if ( empty( $converted_selectors ) ) {
			return "{$this->order_class} .et_pb_button_wrapper";
		}

		return implode( ', ', $converted_selectors );
	}

	/**
	 * Set custom module attributes.
	 *
	 * This method is used to set custom module attributes that will be used in the current module instance.
	 *
	 * @param  array $attrs An array of custom module attributes.
	 * @return void
	 */
	public function use_custom_module_attrs( array $attrs ) {
		$this->_module_attrs_original = $this->module_attrs;
		$this->module_attrs           = $attrs;
	}

	/**
	 * Clear custom module attributes.
	 *
	 * This method is used to clear custom module attributes that have been set using `use_custom_module_attrs` method.
	 *
	 * @return void
	 */
	public function clear_custom_attributes() {
		$this->module_attrs           = $this->_module_attrs_original;
		$this->_module_attrs_original = null;
	}

	/**
	 * Render element style components.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *                    An array of arguments.
	 *
	 * @type string $attrName             Optional. The attribute name declared in module.json config file. Default empty string.
	 * @type array  $styleComponentsProps Optional. A key-value pair array of component props. Default `[]`.
	 * }
	 *
	 * @return string|null
	 */
	public function style_components( array $args ) {
		$attr_name       = $args['attrName'] ?? '';
		$component_props = $args['styleComponentsProps'] ?? [];
		$track_usage     = $args['trackUsage'] ?? true;

		if ( $track_usage && is_string( $attr_name ) && '' !== $attr_name ) {
			$tracked_count                                      = $this->_pre_rendered_style_components[ $attr_name ] ?? 0;
			$this->_pre_rendered_style_components[ $attr_name ] = $tracked_count + 1;
		}

		$merged_attrs    = $this->get_merged_attrs();
		$decoration_attr = $merged_attrs[ $attr_name ]['decoration'] ?? [];
		$settings        = $this->module_metadata->attributes[ $attr_name ] ?? [];

		$attr_resolver = $component_props['attrsResolver'] ?? null;

		if ( is_callable( $attr_resolver ) ) {
			$decoration_attr = call_user_func( $attr_resolver, $decoration_attr, $args );
		}

		$settings_component_props = $settings['styleComponentsProps'] ?? [];

		return ElementComponents::component(
			array_merge(
				[
					'id'            => $this->id,
					'attrs'         => $decoration_attr,

					// FE Only.
					'orderIndex'    => $this->order_index,
					'storeInstance' => $this->store_instance,
				],
				// From module.json.
				$settings_component_props,
				// Overridden.
				$component_props
			)
		);
	}

	/**
	 * Merges module attributes with preset and group preset attributes.
	 *
	 * This method retrieves and merges attributes from a specified module,
	 * its selected preset, and any applicable group presets.
	 *
	 * @since ??
	 *
	 * @return array The merged attributes array.
	 */
	public function get_merged_attrs(): array {
		if ( is_array( $this->_merged_attrs ) ) {
			return $this->_merged_attrs;
		}

		// ModuleElements receives already-merged attributes from ModuleRegistration (including renderAttrs from option group presets).
		// Return them directly instead of re-merging, which could cause issues with renderAttrs.
		$this->_merged_attrs = $this->module_attrs;

		return $this->_merged_attrs;
	}

	/**
	 * Get targeted custom attributes.
	 *
	 * @since ??
	 *
	 * @param string $target_element The target element name.
	 *
	 * @return array Array of custom attributes for the target element.
	 */
	private function _get_targeted_custom_attributes( string $target_element ): array {
		return $this->_targeted_attributes[ $target_element ] ?? [];
	}

	/**
	 * Map attribute name to target element using direct name matching.
	 *
	 * Since we use attribute names as direct identifiers, this is a simple lookup.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The attribute name being rendered.
	 *
	 * @return string The target element name, or empty string if no mapping found.
	 */
	private function _map_attr_name_to_target_element( string $attr_name ): string {
		// Get module attributes from metadata.
		$module_attributes = $this->module_metadata->attributes ?? [];

		// If no attributes defined, no mapping possible.
		if ( empty( $module_attributes ) ) {
			return '';
		}

		// If attr_name directly matches an attribute name, use that.
		if ( isset( $module_attributes[ $attr_name ] ) ) {
			return $attr_name;
		}

		// If no direct match, no mapping is possible.
		return '';
	}

	/**
	 * Check whether style components should be auto-rendered for the provided attr.
	 *
	 * @since ??
	 *
	 * @param string $attr_name      Attribute name being rendered.
	 * @param array  $element_attr   Resolved element attrs.
	 * @param array  $element_settings Element settings from metadata.
	 *
	 * @return bool
	 */
	private function _should_auto_render_style_components( string $attr_name, array $element_attr = [], array $element_settings = [] ): bool {
		if ( '' === $attr_name || 'module' === $attr_name ) {
			return false;
		}

		$resolved_settings = ! empty( $element_settings )
			? $element_settings
			: ( $this->module_metadata->attributes[ $attr_name ] ?? [] );

		// Auto-injection exists to support dynamic subgroup-hosted sub-elements.
		if ( ! $this->_has_dynamic_subgroup_host( $resolved_settings ) ) {
			return false;
		}

		$decoration_attr = $element_attr['decoration'] ?? [];

		// Background and box-shadow options output additional markup through style_components().
		$has_background_attr = ! empty( $decoration_attr['background'] ?? [] );
		$has_box_shadow_attr = ! empty( $decoration_attr['boxShadow'] ?? [] );

		return $has_background_attr || $has_box_shadow_attr;
	}

	/**
	 * Consume one tracked manual style-components usage for an attr.
	 *
	 * @since ??
	 *
	 * @param string $attr_name Attribute name.
	 *
	 * @return bool True when a manual style-components usage was consumed.
	 */
	private function _consume_pre_rendered_style_components( string $attr_name ): bool {
		$tracked_count = (int) ( $this->_pre_rendered_style_components[ $attr_name ] ?? 0 );

		if ( 0 >= $tracked_count ) {
			return false;
		}

		$this->_pre_rendered_style_components[ $attr_name ] = $tracked_count - 1;

		return true;
	}

	/**
	 * Inject style components as first child of rendered paired element markup.
	 *
	 * @since ??
	 *
	 * @param string $element          Rendered element markup.
	 * @param string $style_components             Rendered style components markup.
	 * @param bool   $should_wrap_parallax_content Whether plain-text children should be wrapped for parallax stacking.
	 *
	 * @return string
	 */
	private function _inject_style_components( string $element, string $style_components, bool $should_wrap_parallax_content = false ): string {
		if ( '' === $element || '' === $style_components ) {
			return $element;
		}

		// Only inject into paired tags. Self-closing tags (e.g. img/input) cannot host children.
		// Regex test: https://regex101.com/r/0caYdY/2.
		$has_paired_tag = preg_match( '/^\s*<([a-zA-Z][a-zA-Z0-9:_-]*)\b[^>]*>.*<\/\1>\s*$/s', $element );

		if ( ! $has_paired_tag ) {
			return $element;
		}

		// Parallax relies on child element stacking; plain text nodes cannot be layered with z-index.
		// Wrap plain text children so parallax-enabled sub-elements can keep text above background layers.
		if ( $should_wrap_parallax_content ) {
			$element = $this->_wrap_plain_text_content_for_parallax( $element );
		}

		// Regex test: https://regex101.com/r/ChCeYM/1.
		$injected_element = preg_replace( '/(<[a-zA-Z][^>]*>)/', '$1' . $style_components, $element, 1 );

		return is_string( $injected_element ) ? $injected_element : $element;
	}

	/**
	 * Wrap plain text content in a span so it can be layered above parallax backgrounds.
	 *
	 * @since ??
	 *
	 * @param string $element Rendered paired element markup.
	 *
	 * @return string
	 */
	private function _wrap_plain_text_content_for_parallax( string $element ): string {
		$matches = [];
		// Regex text: https://regex101.com/r/dBEtwh/2.
		$paired_element_match = preg_match(
			'/^\s*(<[a-zA-Z][a-zA-Z0-9:_-]*\b[^>]*>)(.*)(<\/[a-zA-Z][a-zA-Z0-9:_-]*>)\s*$/s',
			$element,
			$matches
		);

		if ( ! $paired_element_match ) {
			return $element;
		}

		$opening_tag = $matches[1] ?? '';
		$content     = $matches[2] ?? '';
		$closing_tag = $matches[3] ?? '';

		// Only wrap direct plain-text content; preserve existing structured children.
		if ( '' === trim( $content ) || str_contains( $content, '<' ) ) {
			return $element;
		}

		return $opening_tag . '<span class="et-pb-parallax-content">' . $content . '</span>' . $closing_tag;
	}

	/**
	 * Get module layout classname for element attrs.
	 *
	 * @since ??
	 *
	 * @param array $element_attr     Element attrs.
	 * @param array $element_settings Element settings metadata.
	 *
	 * @return string
	 */
	private function _get_layout_module_classname( array $element_attr, array $element_settings = [] ): string {
		if ( ! $this->_has_dynamic_subgroup_host( $element_settings ) ) {
			return '';
		}

		$layout_attr = $element_attr['decoration']['layout'] ?? [];

		if ( empty( $layout_attr ) ) {
			return '';
		}

		$layout_display_value = $layout_attr['desktop']['value']['display'] ?? 'flex';

		if ( 'grid' === $layout_display_value ) {
			return 'et_grid_module';
		}

		if ( 'flex' === $layout_display_value ) {
			return 'et_flex_module';
		}

		return 'et_block_module';
	}
}
