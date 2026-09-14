<?php
/**
 * Module: MultiViewElement class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\MultiView;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\VisualBuilder\Saving\SavingUtility;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewElementValue;
use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
/**
 * Module: MultiViewElement class.
 *
 * This class is used to render a module elements that support MultiView functionality.
 *
 * it utilizes MultiViewScriptData class to populate data for the element.
 *
 * @since ??
 */
class MultiViewElement {

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
	 * Module selector
	 *
	 * @since ??
	 *
	 * @var string
	 */
	public $selector;

	/**
	 * Module hover selector
	 *
	 * @since ??
	 *
	 * @var string
	 */
	public $hover_selector;

	/**
	 * The ID of instance where this block stored in BlockParserStore class.
	 *
	 * @since ??
	 *
	 * @var int|null
	 */
	public $store_instance;

	/**
	 * A key-value pair array of custom sanitizers that will be used to override the default sanitizer.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private $_attributes_sanitizers = [];

	/**
	 * The function that will be invoked to sanitize/escape the children element. Default is `esc_html`.
	 *
	 * @since ??
	 *
	 * @var callable
	 */
	private $_children_sanitizer = 'esc_html';

	/**
	 * The hidden on load breakpoints.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private $_hidden_on_load_breakpoints = [];

	/**
	 * The enabled breakpoint names.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private $_enabled_breakpoint_names = [];

	/**
	 * Create an instance of the MultiViewElement class.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $id            Optional. The Module unique ID. Default empty string.
	 *     @type string $name          Optional. The Module name. Default empty string.
	 *     @type string $selector      Optional. The selector of element to be updated. Default empty string.
	 *     @type string $hoverSelector Optional. The selector to trigger hover event. Default `null`.
	 *     @type string $storeInstance Optional. The ID of instance where this block stored in BlockParserStore. Default `null`.
	 * }
	 */
	public function __construct( array $args ) {
		$this->id             = $args['id'] ?? '';
		$this->name           = $args['name'] ?? '';
		$this->selector       = $args['selector'] ?? '';
		$this->hover_selector = $args['hoverSelector'] ?? null;
		$this->store_instance = $args['storeInstance'] ?? null;
	}

	/**
	 * Create a new instance of the MultiViewElement class with the given arguments.
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $id            The Module unique ID.
	 *     @type string $name          The Module name.
	 *     @type string $selector      The selector of element to be updated.
	 *     @type string $hoverSelector Optional. The selector to trigger hover event. Default `null`.
	 * }
	 *
	 * @return MultiViewElement A new instance of the MultiViewElement class.
	 */
	public static function create( array $args ): MultiViewElement {
		return new MultiViewElement( $args );
	}

	/**
	 * Populates a class name array with data and returns the processed array.
	 *
	 * @since ??
	 *
	 * @param array $class_name_data An array of data where the keys are class names and the values can be either a
	 *                               boolean or an instance of MultiViewElementValue class.
	 *
	 * @return arrayAn array of processed class data.
	 */
	private function _populate_class_name( array $class_name_data ): array {
		$processed = [];
		$sanitizer = $this->_attributes_sanitizers['class'] ?? null;

		if ( ! is_callable( $sanitizer ) ) {
			$sanitizer = 'esc_attr';
		}

		foreach ( $class_name_data as $class_name => $value ) {
			if ( $value instanceof MultiViewElementValue ) {
				$populated = MultiViewScriptData::set_class_name(
					[
						'id'            => $this->id,
						'name'          => $this->name,
						'data'          => [
							$class_name => $value->get_data(),
						],
						'subName'       => $value->get_sub_name(),
						'valueResolver' => $value->get_value_resolver(),
						'sanitizer'     => $sanitizer,
						'selector'      => $value->get_selector() ?? $this->selector,
						'hoverSelector' => $value->get_hover_selector() ?? $this->hover_selector,
						'storeInstance' => $this->store_instance,
						'switchOnLoad'  => false,
					]
				);

				if ( 'et_multi_view_hidden' === $class_name ) {
					foreach ( $this->_get_enabled_breakpoint_names() as $breakpoint ) {
						if ( ! $this->_is_hidden_on_load_breakpoint( $breakpoint ) && in_array( $class_name, ( $populated[ $breakpoint ]['add'] ?? [] ), true ) ) {
							$this->_set_hidden_on_load_breakpoint( $breakpoint );
						}
					}
				}

				// Processed and sanitized class name array for the desktop breakpoint.
				$processed[ $class_name ] = in_array( $class_name, ( $populated['desktop']['add'] ?? [] ), true );
				continue;
			}

			if ( preg_match( '/[a-z]/i', $class_name ) ) { // Array key is the class name.
				$class_name = call_user_func( $sanitizer, $class_name );
			} elseif ( is_string( $value ) && preg_match( '/[a-z]/i', $value ) ) { // Array value is the class name.
				$value = call_user_func( $sanitizer, $value );
			}

			$processed[ $class_name ] = $value;
		}

		// At this point, we have all the class has been sanitized, so we can safely skip the sanitization process in the HTMLUtility::render().
		$this->_attributes_sanitizers['class'] = 'et_core_esc_previously';

		return $processed;
	}

	/**
	 * Populates an array with processed data, including styles, from a given array of data.
	 *
	 * @since ??
	 *
	 * @param array $style_data A key-value array of style data where the keys are style properties and the values can
	 *                          be either a scalar or an instance of MultiViewElementValue class.
	 *
	 * @return array An array of processed style data.
	 */
	private function _populate_style( array $style_data ): array {
		$processed = [];
		$sanitizer = $this->_attributes_sanitizers['style'] ?? null;

		if ( ! is_callable( $sanitizer ) ) {
			$sanitizer = [ SavingUtility::class, 'sanitize_css_properties' ];
		}

		foreach ( $style_data as $property => $value ) {
			if ( $value instanceof MultiViewElementValue ) {
				$populated = MultiViewScriptData::set_style(
					[
						'id'            => $this->id,
						'name'          => $this->name,
						'data'          => [
							$property => $value->get_data(),
						],
						'subName'       => $value->get_sub_name(),
						'valueResolver' => $value->get_value_resolver(),
						'sanitizer'     => $sanitizer,
						'selector'      => $value->get_selector() ?? $this->selector,
						'hoverSelector' => $value->get_hover_selector() ?? $this->hover_selector,
						'storeInstance' => $this->store_instance,
						'switchOnLoad'  => false,
					]
				);

				// Processed and sanitized style for the desktop breakpoint.
				$processed[ $property ] = $populated['desktop'][ $property ] ?? '';
				continue;
			}

			$sanitized = call_user_func( $sanitizer, $property . ':' . $value );

			if ( $sanitized ) {
				$sanitized = substr_replace( $sanitized, '', 0, strlen( $property . ':' ) );
			}

			$processed[ $property ] = $value;
		}

		// At this point, we have all the style has been sanitized, so we can safely skip the sanitization process in the HTMLUtility::render().
		$this->_attributes_sanitizers['style'] = 'et_core_esc_previously';

		return $processed;
	}

	/**
	 * When `<img>` has a MultiView `src`, add `et_multi_view_hidden_image` while `src` is empty so visibility matches breakpoint state.
	 *
	 * Skips if `class` already defines `et_multi_view_hidden_image` (e.g. ModuleElements `hiddenIfFalsy`).
	 *
	 * @since ??
	 *
	 * @param array  $attributes_data Attributes passed to {@see MultiViewElement::render()}.
	 * @param string $tag             HTML tag name.
	 *
	 * @return array Possibly updated attributes (merged `class`).
	 */
	private function _maybe_merge_empty_img_src_hidden_class( array $attributes_data, string $tag ): array {
		if ( 'img' !== $tag || ! $this->_is_empty_img_src_on_desktop( $attributes_data ) ) {
			return $attributes_data;
		}

		$src_value = $attributes_data['src'] ?? null;

		// Set the `src` attribute to a transparent 1x1 pixel image to avoid showing invalid image element when src is empty or not set on desktop.
		$attributes_data['src'] = $src_value->set(
			[
				'valueResolver' => function ( $value, array $resolver_args ) use ( $src_value ) {
					$breakpoint = $resolver_args['breakpoint'] ?? 'desktop';
					$state      = $resolver_args['state'] ?? 'value';

					if ( 'desktop' === $breakpoint && 'value' === $state && '' === ( $value ?? '' ) ) {
						return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
					}

					$value_resolver_original = $src_value->get_value_resolver();

					if ( is_callable( $value_resolver_original ) ) {
						$value = call_user_func( $value_resolver_original, $value, $resolver_args );
					}

					return $value;
				},
			],
			true
		);

		$class_data = $attributes_data['class'] ?? [];

		if ( $class_data && is_string( $class_data ) ) {
			$class_data = [
				$class_data => true,
			];
		}

		if ( ! is_array( $class_data ) ) {
			$class_data = [];
		}

		// Add `et_multi_view_hidden_image` class when `src` is empty.
		if ( ! isset( $class_data['et_multi_view_hidden_image'] ) ) {
			$attributes_data['class'] = array_merge(
				$class_data,
				[
					'et_multi_view_hidden_image' => $src_value->set(
						[
							'valueResolver' => function ( $value, array $resolver_args ) use ( $src_value ) {
								$value_resolver_original = $src_value->get_value_resolver();

								if ( is_callable( $value_resolver_original ) ) {
									$value = call_user_func( $value_resolver_original, $value, $resolver_args );
								}

								return empty( $value ) ? 'add' : 'remove';
							},
						],
						true
					),
				]
			);
		}

		return $attributes_data;
	}

	/**
	 * Checks whether the image source is empty on desktop.
	 *
	 * @since ??
	 *
	 * @param array $attributes_data A key-value array of attributes data.
	 *
	 * @return bool Whether the image source is empty on desktop.
	 */
	private function _is_empty_img_src_on_desktop( array $attributes_data ): bool {
		$src_value = $attributes_data['src'] ?? null;

		if ( null === $src_value || ! $src_value instanceof MultiViewElementValue ) {
			return false;
		}

		$is_empty_img_src_on_desktop = ModuleUtils::has_value( $src_value->get_data(), [
			'valueResolver' => function ( $value, array $resolver_args ) use ( $src_value ) {
				$breakpoint = $resolver_args['breakpoint'] ?? 'desktop';
				$state      = $resolver_args['state'] ?? 'value';

				if ( 'desktop' === $breakpoint && 'value' === $state ) {
					return false;
				}

				return $src_value->has_value( [ 'breakpoint' => $breakpoint, 'state' => $state ] );
			},
		] );

		return $is_empty_img_src_on_desktop;
	}

	/**
	 * Populates and processes attributes for a given tag in an array format.
	 *
	 * @since ??
	 *
	 * @param array  $attributes_data A key-value array of attributes data where the keys are attribute names and the
	 *                                values can be either a scalar, array or an instance of MultiViewElementValue class.
	 *                                A scalar value will be used as the attribute value.
	 *                                An array value is only applicable for `class` and style attributes.
	 *                                An instance of MultiViewElementValue class will be used to populate the multi-view data.
	 * @param string $tag             The HTML tag for on which the attributes are used.
	 *
	 * @return array An array of processed attributes.
	 */
	private function _populate_attributes( array $attributes_data, string $tag ): array {
		$attributes_data = $this->_maybe_merge_empty_img_src_hidden_class( $attributes_data, $tag );
		$processed       = [];

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

			if ( $value instanceof MultiViewElementValue ) {
				$populated = MultiViewScriptData::set_attrs(
					[
						'id'            => $this->id,
						'name'          => $this->name,
						'data'          => [
							$attr_name => $value->get_data(),
						],
						'subName'       => $value->get_sub_name(),
						'valueResolver' => $value->get_value_resolver(),
						'sanitizers'    => [
							$attr_name => $this->_attributes_sanitizers[ $attr_name ] ?? null,
						],
						'selector'      => $value->get_selector() ?? $this->selector,
						'hoverSelector' => $value->get_hover_selector() ?? $this->hover_selector,
						'tag'           => $tag,
						'storeInstance' => $this->store_instance,
						'switchOnLoad'  => false,
					]
				);

				// At this point, we have all the attribute value has been sanitized, so we can safely skip the sanitization process in the HTMLUtility::render()
				// for the attributes that have been sanitized by MultiViewScriptData::set_attrs().
				$this->_attributes_sanitizers[ $attr_name ] = 'et_core_esc_previously';

				// Processed and sanitized attribute for the desktop breakpoint.
				$processed[ $attr_name ] = $populated['desktop'][ $attr_name ] ?? '';
			}
		}

		return $processed;
	}

	/**
	 * Remove invalid empty image metadata attributes.
	 *
	 * @since ??
	 *
	 * @param array $attributes A key-value pair array of rendered attributes.
	 *
	 * @return array Sanitized attributes without empty image metadata values.
	 */
	private function _omit_empty_image_metadata_attributes( array $attributes ): array {
		$image_metadata_attrs = [
			'width',
			'height',
			'srcset',
			'sizes',
		];

		foreach ( $image_metadata_attrs as $attr_name ) {
			if ( isset( $attributes[ $attr_name ] ) && '' === $attributes[ $attr_name ] ) {
				unset( $attributes[ $attr_name ] );
			}
		}

		return $attributes;
	}

	/**
	 * Populates children data for a multi-view element.
	 *
	 * @since ??
	 *
	 * @param string|array|MultiViewElementValue $children_data The children data to be populated.
	 *
	 * @return string|array The populated children data.
	 */
	private function _populate_children( $children_data ) {
		if ( $children_data instanceof MultiViewElementValue ) {
			$populated = MultiViewScriptData::set_content(
				[
					'id'            => $this->id,
					'name'          => $this->name,
					'data'          => $children_data->get_data(),
					'subName'       => $children_data->get_sub_name(),
					'valueResolver' => $children_data->get_value_resolver(),
					'sanitizer'     => $this->_children_sanitizer,
					'selector'      => $children_data->get_selector() ?? $this->selector,
					'hoverSelector' => $children_data->get_hover_selector() ?? $this->hover_selector,
					'storeInstance' => $this->store_instance,
					'switchOnLoad'  => false,
				]
			);

			// At this point, we have the children has been sanitized, we can safely skip the sanitization process in the HTMLUtility::render().
			$this->_children_sanitizer = 'et_core_esc_previously';

			foreach ( $this->_get_enabled_breakpoint_names() as $breakpoint ) {
				if ( ! $this->_is_hidden_on_load_breakpoint( $breakpoint ) && isset( $populated[ $breakpoint ] ) ) {
					$this->_set_hidden_on_load_breakpoint( $breakpoint );
				}
			}

			// Processed and sanitized children for the desktop breakpoint.
			return $populated['desktop'] ?? '';
		}

		return $children_data;
	}

	/**
	 * Renders HTML code with specified attributes and children that support multi-view.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string                             $tag                  Optional. HTML Element tag. Default `div`.
	 *     @type bool                               $tagEscaped           Optional. Whether the tag name has been escaped or not. Default `false`.
	 *     @type array                              $attributes           Optional. A key-value pair array of attributes data. Default `[]`.
	 *                                                                       - The array item key must be a string.
	 *                                                                       - For boolean attributes, the array item value must be a `true`.
	 *                                                                       - For key-value pair attributes, the array item value must be a
	 *                                                                         MultiViewElementValue object, int, float, string, boolean, array or null.
	 *                                                                          -- `MultiViewElementValue` value will be populated with multi view data.
	 *                                                                          -- `boolean` value will be stringified to avoid `true` get printed as `1`
	 *                                                                              and `false` get printed as `0`.
	 *                                                                          -- `array` value only applicable for `style` attribute.
	 *                                                                          -- `null` value will skip the attribute to be rendered.
	 *     @type string|array|MultiViewElementValue $children             Optional. The children element. Default `null`.
	 *                                                                       - Pass instance of MultiViewElementValue object for multi view children element.
	 *                                                                       - Pass string for single children element.
	 *                                                                       - Pass array for multiple children elements and nested children elements.
	 *                                                                       - Only applicable for non self-closing tags.
	 *     @type callable                           $childrenSanitizer    Optional. The function that will be invoked to sanitize/escape the children element.
	 *                                                                    Default `esc_html`.
	 *     @type array                              $attributesSanitizers Optional. A key-value pair array of custom sanitizers that will be used to override
	 *                                                                    the default sanitizer. Default `[]`.
	 * }
	 *
	 * @return string The rendered HTML code.
	 */
	public function render( array $args ): string {
		$tag         = $args['tag'] ?? 'div';
		$tag_escaped = $args['tagEscaped'] ?? false;

		if ( ! $tag_escaped ) {
			$tag = tag_escape( $tag );
		}

		if ( ! is_string( $tag ) || ! $tag ) {
			return '';
		}

		$attributes_raw               = $args['attributes'] ?? [];
		$children                     = $args['children'] ?? null;
		$this->_attributes_sanitizers = $args['attributesSanitizers'] ?? [];
		$this->_children_sanitizer    = $args['childrenSanitizer'] ?? 'esc_html';

		// Reset hidden on load flags.
		$this->_reset_hidden_on_load_breakpoints();

		// Populate attributes and children.
		$attributes = $this->_populate_attributes( $attributes_raw, $tag );

		// If the tag is `img`, unset the `srcset`, `sizes`, `width`, `height` and `alt` attributes if they are empty.
		if  ( 'img' === $tag && $this->_is_empty_img_src_on_desktop( $attributes_raw ) ) {
			$attrs_to_update = ['srcset', 'sizes', 'width', 'height', 'alt'];

			foreach ( $attrs_to_update as $attr_to_unset ) {
				$attr_to_unset_value = $attributes[ $attr_to_unset ] ?? '';

				if ( '' === $attr_to_unset_value ) {
					unset( $attributes[ $attr_to_unset ] );
				}
			}
		}

		if ( 'img' === $tag ) {
			$attributes = $this->_omit_empty_image_metadata_attributes( $attributes );
		}

		if ( $children ) {
			$children = HTMLUtility::is_self_closing_tag( $tag ) ? null : $this->_populate_children( $children );
		}

		foreach ( $this->_get_hidden_on_load_breakpoints() as $breakpoint => $hidden ) {
			$attributes[ 'data-et-mv-hidden-' . strtolower( $breakpoint ) ] = $hidden;
		}

		return HTMLUtility::render(
			[
				'tag'                  => $tag,
				'tagEscaped'           => true,
				'attributes'           => $attributes,
				'children'             => $children,
				'attributesSanitizers' => $this->_attributes_sanitizers,
				'childrenSanitizer'    => $this->_children_sanitizer,
			]
		);
	}

	/**
	 * Resets the hidden on load breakpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private function _reset_hidden_on_load_breakpoints(): void {
		$this->_hidden_on_load_breakpoints = [];
	}

	/**
	 * Sets the hidden on load breakpoint.
	 *
	 * @since ??
	 *
	 * @param string $breakpoint The breakpoint to set.
	 *
	 * @return void
	 */
	private function _set_hidden_on_load_breakpoint( string $breakpoint ): void {
		$this->_hidden_on_load_breakpoints[ $breakpoint ] = true;
	}

	/**
	 * Checks if the breakpoint is hidden on load.
	 *
	 * @since ??
	 *
	 * @param string $breakpoint The breakpoint to check.
	 *
	 * @return bool Whether the breakpoint is hidden on load.
	 */
	private function _is_hidden_on_load_breakpoint( string $breakpoint ): bool {
		return $this->_hidden_on_load_breakpoints[ $breakpoint ] ?? false;
	}

	/**
	 * Gets the hidden on load breakpoints.
	 *
	 * @since ??
	 *
	 * @return array The hidden on load breakpoints.
	 */
	private function _get_hidden_on_load_breakpoints(): array {
		return $this->_hidden_on_load_breakpoints;
	}

	/**
	 * Gets the enabled breakpoint names.
	 *
	 * @since ??
	 *
	 * @return array The enabled breakpoint names.
	 */
	private function _get_enabled_breakpoint_names(): array {
		if ( ! $this->_enabled_breakpoint_names ) {
			$enabled_breakpoints = Breakpoint::get_enabled_breakpoints();

			foreach ( $enabled_breakpoints as $breakpoint ) {
				$is_base_device = $breakpoint['baseDevice'] ?? false;

				if ( $is_base_device ) {
					continue;
				}

				$this->_enabled_breakpoint_names[] = $breakpoint['name'];
			}
		}

		return $this->_enabled_breakpoint_names;
	}
}
