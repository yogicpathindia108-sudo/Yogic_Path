<?php
/**
 * Module Library: Group Carousel Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\GroupCarousel;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\Module\ScriptData;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\GroupCarousel\GroupCarouselPresetAttrsMap;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleUtils\TimeUtils;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * GroupCarouselModule class.
 *
 * This class implements the functionality of a group carousel component in a
 * frontend application. It provides functions for rendering the carousel,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class GroupCarouselModule implements DependencyInterface {

	/**
	 * Render callback for the Group Carousel module.
	 *
	 * This function is responsible for the module's server-side HTML rendering on the frontend.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string The HTML rendered output of the Group Carousel module.
	 */
	public static function render_callback( array $attrs, $content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ) {
		$children_ids = $block->parsed_block['innerBlocks'] ? array_map(
			function ( $inner_block ) {
				return $inner_block['id'];
			},
			$block->parsed_block['innerBlocks']
		) : [];

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		return Module::render(
			[
				// FE only.
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'                    => $attrs,
				'elements'                 => $elements,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'id'                       => $block->parsed_block['id'],
				'childrenIds'              => $children_ids,
				'name'                     => $block->block_type->name,
				'parentAttrs'              => isset( $parent->attrs ) ? $parent->attrs : [],
				'parentId'                 => isset( $parent->id ) ? $parent->id : '',
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'moduleCategory'           => $block->block_type->category,
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'children'                 => [
					$elements->style_components(
						[
							'attrName' => 'module',
						]
					),
					self::_render_carousel_content( $attrs, $block, $elements ),
				],
			]
		);
	}

	/**
	 * Generate classnames for the module.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type object $classnamesInstance Module classnames instance.
	 *     @type array  $attrs              Block attributes data for rendering the module.
	 * }
	 *
	 * @return void
	 */
	public static function module_classnames( array $args ) {
		$attrs               = $args['attrs'];
		$classnames_instance = $args['classnamesInstance'];

		// Base carousel class.
		$classnames_instance->add( 'et_pb_group_carousel' );

		// Add group class to enable child module hovering like the Group module.
		$classnames_instance->add( 'et_pb_group' );

		// Add module class like the Group module.
		$classnames_instance->add( 'et_pb_module' );

		// Add center mode class.
		$center_mode = 'on' === ( $attrs['module']['advanced']['centerMode']['desktop']['value'] ?? 'off' );
		if ( $center_mode ) {
			$classnames_instance->add( 'et_pb_group_carousel_center_mode' );
		}

		// Add arrow position classes.
		$arrows_attr    = $attrs['arrows']['advanced']['showArrows'] ?? [];
		$show_arrows    = empty( $arrows_attr ) ? true : ModuleUtils::has_value(
			$arrows_attr,
			[
				'valueResolver' => function ( $value ) {
					return 'on' === ( $value ?? 'on' );
				},
			]
		);
		$arrow_position = $attrs['arrows']['advanced']['position']['desktop']['value'] ?? 'inside';
		if ( $show_arrows ) {
			if ( 'outside' === $arrow_position ) {
				$classnames_instance->add( 'et_pb_group_carousel_arrows_outside' );
			} elseif ( 'center' === $arrow_position ) {
				$classnames_instance->add( 'et_pb_group_carousel_arrows_center' );
			} else {
				$classnames_instance->add( 'et_pb_group_carousel_arrows_inside' );
			}
		}

		// Define show_dots variable for dot-related classes.
		$dots_attr = $attrs['dotNav']['advanced']['showDots'] ?? [];
		$show_dots = self::_is_dot_navigation_enabled( $dots_attr );

		// Add dot alignment classes.
		$dot_alignment = $attrs['dotNav']['advanced']['alignment']['desktop']['value'] ?? 'center';
		if ( $show_dots ) {
			if ( 'left' === $dot_alignment ) {
				$classnames_instance->add( 'et_pb_group_carousel_dots_left' );
			} elseif ( 'right' === $dot_alignment ) {
				$classnames_instance->add( 'et_pb_group_carousel_dots_right' );
			} else {
				$classnames_instance->add( 'et_pb_group_carousel_dots_center' );
			}
		}

		// Add dot position classes.
		$dot_position = $attrs['dotNav']['advanced']['position']['desktop']['value'] ?? 'below';
		if ( $show_dots ) {
			if ( 'above' === $dot_position ) {
				$classnames_instance->add( 'et_pb_group_carousel_dots_above' );
			} elseif ( 'overlay' === $dot_position ) {
				$classnames_instance->add( 'et_pb_group_carousel_dots_overlay' );
			} else {
				$classnames_instance->add( 'et_pb_group_carousel_dots_below' );
			}
		} else {
			// Add disabled class when dot navigation is not enabled.
			$classnames_instance->add( 'et_pb_group_carousel_dots_disabled' );
		}

		$slides_to_show = (int) ( $attrs['module']['advanced']['slidesToShow']['desktop']['value'] ?? '1' );
		if ( 1 < $slides_to_show ) {
			$classnames_instance->add( 'et_pb_group_carousel_slides_' . $slides_to_show );
		}

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => array_merge(
						isset( $attrs['module']['decoration'] ) ? $attrs['module']['decoration'] : [],
						[
							'link' => isset( $attrs['module']['advanced']['link'] ) ? $attrs['module']['advanced']['link'] : [],
						]
					),
				]
			)
		);
	}

	/**
	 * Group Carousel module script data.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of arguments for setting the module script data.
	 *
	 *     @type ModuleElements $elements           Elements instance.
	 *     @type string         $selector           Module selector.
	 *     @type array          $attrs              Module attributes.
	 *     @type string         $id                 Module ID.
	 *     @type int|null       $storeInstance      Store instance ID.
	 * }
	 *
	 * @return void
	 */
	public static function module_script_data( array $args ) {
		$elements       = $args['elements'];
		$selector       = isset( $args['selector'] ) ? $args['selector'] : '';
		$attrs          = isset( $args['attrs'] ) ? $args['attrs'] : [];
		$id             = isset( $args['id'] ) ? $args['id'] : '';
		$name           = isset( $args['name'] ) ? $args['name'] : '';
		$store_instance = isset( $args['storeInstance'] ) ? $args['storeInstance'] : null;

		$elements->script_data( [ 'attrName' => 'module' ] );

		// Set responsive class names for arrow positioning.
		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'hoverSelector' => $selector,
				'setClassName'  => [
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_group_carousel_arrows_outside' => $attrs['arrows']['advanced']['position'] ?? [],
							'et_pb_group_carousel_arrows_center'  => $attrs['arrows']['advanced']['position'] ?? [],
							'et_pb_group_carousel_arrows_inside'  => $attrs['arrows']['advanced']['position'] ?? [],
						],
						'valueResolver' => function ( $value, $resolver_args ) {
							$class_name = $resolver_args['className'] ?? '';

							// Only add the class if the position matches.
							if ( 'et_pb_group_carousel_arrows_outside' === $class_name ) {
								return 'outside' === $value ? 'add' : 'remove';
							} elseif ( 'et_pb_group_carousel_arrows_center' === $class_name ) {
								return 'center' === $value ? 'add' : 'remove';
							} elseif ( 'et_pb_group_carousel_arrows_inside' === $class_name ) {
								return 'inside' === $value ? 'add' : 'remove';
							}

							return 'remove';
						},
					],
				],
			]
		);

		// Set module specific front-end data using MultiView system.
		self::set_front_end_data(
			[
				'selector'      => $selector,
				'id'            => $id,
				'attrs'         => $attrs,
				'storeInstance' => $store_instance,
			]
		);
	}

	/**
	 * Determine whether dot navigation should be considered enabled for any responsive breakpoint.
	 *
	 * This method resolves inherited values per breakpoint so frontend rendering remains aligned
	 * with responsive settings when non-desktop values are inherited rather than explicitly serialized.
	 *
	 * @param array $attrs Dot navigation showDots attributes.
	 *
	 * @return bool
	 */
	private static function _is_dot_navigation_enabled( array $attrs ): bool {
		if ( empty( $attrs ) ) {
			return true;
		}

		foreach ( Breakpoint::get_enabled_breakpoint_names() as $breakpoint ) {
			$value = ModuleUtils::use_attr_value(
				[
					'attr'       => $attrs,
					'breakpoint' => $breakpoint,
					'state'      => 'value',
					'mode'       => 'getAndInheritAll',
				]
			);

			if ( 'on' === ( $value ?? 'on' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract auto rotate data with responsive support.
	 *
	 * @param array $attrs Auto rotate attributes.
	 * @return array Responsive auto rotate data.
	 */
	private static function _data_auto_rotate( array $attrs ): array {
		$attr_value = [];

		foreach ( $attrs as $breakpoint => $states ) {
			foreach ( array_keys( $states ) as $state ) {
				$value                               = ModuleUtils::use_attr_value(
					[
						'attr'       => $attrs,
						'breakpoint' => $breakpoint,
						'state'      => $state,
						'mode'       => 'getAndInheritAll',
					]
				);
				$attr_value[ $breakpoint ][ $state ] = 'on' === $value;
			}
		}

		return $attr_value;
	}

	/**
	 * Extract speed data with responsive support.
	 *
	 * @param array $attrs Speed attributes.
	 * @return array Responsive speed data.
	 */
	private static function _data_speed( array $attrs ): array {
		$attr_value = [];

		foreach ( $attrs as $breakpoint => $states ) {
			foreach ( array_keys( $states ) as $state ) {
				$value                               = ModuleUtils::use_attr_value(
					[
						'attr'       => $attrs,
						'breakpoint' => $breakpoint,
						'state'      => $state,
						'mode'       => 'getAndInheritAll',
					]
				);
				$attr_value[ $breakpoint ][ $state ] = TimeUtils::value_to_ms( $value ?? '2000ms' );
			}
		}

		return $attr_value;
	}

	/**
	 * Extract transition speed data with responsive support.
	 *
	 * @param array $attrs Transition speed attributes.
	 * @return array Responsive transition speed data.
	 */
	private static function _data_transition_speed( array $attrs ): array {
		$attr_value = [];

		foreach ( $attrs as $breakpoint => $states ) {
			foreach ( array_keys( $states ) as $state ) {
				$value                               = ModuleUtils::use_attr_value(
					[
						'attr'       => $attrs,
						'breakpoint' => $breakpoint,
						'state'      => $state,
						'mode'       => 'getAndInheritAll',
					]
				);
				$attr_value[ $breakpoint ][ $state ] = TimeUtils::value_to_ms( $value ?? '200ms' );
			}
		}

		return $attr_value;
	}


	/**
	 * Extract pause on hover data with responsive support.
	 *
	 * @param array $attrs Pause on hover attributes.
	 * @return array Responsive pause on hover data.
	 */
	private static function _data_pause_on_hover( array $attrs ): array {
		$attr_value = [];

		foreach ( $attrs as $breakpoint => $states ) {
			foreach ( array_keys( $states ) as $state ) {
				$value                               = ModuleUtils::use_attr_value(
					[
						'attr'       => $attrs,
						'breakpoint' => $breakpoint,
						'state'      => $state,
						'mode'       => 'getAndInheritAll',
					]
				);
				$attr_value[ $breakpoint ][ $state ] = 'on' === ( $value ?? 'on' );
			}
		}

		return $attr_value;
	}

	/**
	 * Extract center mode data with responsive support.
	 *
	 * @param array $attrs Center mode attributes.
	 * @return array Responsive center mode data.
	 */
	private static function _data_center_mode( array $attrs ): array {
		$attr_value = [];

		foreach ( $attrs as $breakpoint => $states ) {
			foreach ( array_keys( $states ) as $state ) {
				$value                               = ModuleUtils::use_attr_value(
					[
						'attr'       => $attrs,
						'breakpoint' => $breakpoint,
						'state'      => $state,
						'mode'       => 'getAndInheritAll',
					]
				);
				$attr_value[ $breakpoint ][ $state ] = 'on' === $value;
			}
		}

		return $attr_value;
	}

	/**
	 * Extract slides to show data with responsive support.
	 *
	 * @param array $attrs Slides to show attributes.
	 * @return array Responsive slides to show data.
	 */
	private static function _data_slides_to_show( array $attrs ): array {
		$attr_value = [];

		foreach ( $attrs as $breakpoint => $states ) {
			foreach ( array_keys( $states ) as $state ) {
				$value                               = ModuleUtils::use_attr_value(
					[
						'attr'       => $attrs,
						'breakpoint' => $breakpoint,
						'state'      => $state,
						'mode'       => 'getAndInheritAll',
					]
				);
				$attr_value[ $breakpoint ][ $state ] = (int) ( $value ?? '1' );
			}
		}

		return $attr_value;
	}

	/**
	 * Extract slides to scroll data with responsive support.
	 *
	 * @param array $attrs Slides to scroll attributes.
	 * @return array Responsive slides to scroll data.
	 */
	private static function _data_slides_to_scroll( array $attrs ): array {
		$attr_value = [];

		foreach ( $attrs as $breakpoint => $states ) {
			foreach ( array_keys( $states ) as $state ) {
				$value                               = ModuleUtils::use_attr_value(
					[
						'attr'       => $attrs,
						'breakpoint' => $breakpoint,
						'state'      => $state,
						'mode'       => 'getAndInheritAll',
					]
				);
				$attr_value[ $breakpoint ][ $state ] = (int) ( $value ?? '1' );
			}
		}

		return $attr_value;
	}

	/**
	 * Extract arrow position data with responsive support.
	 *
	 * @param array $attrs Arrow position attributes.
	 * @return array Responsive arrow position data.
	 */
	private static function _data_arrow_position( array $attrs ): array {
		$attr_value = [];

		foreach ( $attrs as $breakpoint => $states ) {
			foreach ( array_keys( $states ) as $state ) {
				$value                               = ModuleUtils::use_attr_value(
					[
						'attr'       => $attrs,
						'breakpoint' => $breakpoint,
						'state'      => $state,
						'mode'       => 'getAndInheritAll',
					]
				);
				$attr_value[ $breakpoint ][ $state ] = $value ?? 'center';
			}
		}

		return $attr_value;
	}

	/**
	 * Extract dot alignment data with responsive support.
	 *
	 * @param array $attrs Dot alignment attributes.
	 * @return array Responsive dot alignment data.
	 */
	private static function _data_dot_alignment( array $attrs ): array {
		$attr_value = [];

		foreach ( $attrs as $breakpoint => $states ) {
			foreach ( array_keys( $states ) as $state ) {
				$value                               = ModuleUtils::use_attr_value(
					[
						'attr'       => $attrs,
						'breakpoint' => $breakpoint,
						'state'      => $state,
						'mode'       => 'getAndInheritAll',
					]
				);
				$attr_value[ $breakpoint ][ $state ] = $value ?? 'center';
			}
		}

		return $attr_value;
	}

	/**
	 * Set the module specific front-end data.
	 *
	 * @param array $args {
	 *     An array of arguments for setting the front-end script data.
	 *
	 *     @type string   $selector      The module selector.
	 *     @type string   $id            The module ID.
	 *     @type array    $attrs         The module attributes.
	 *     @type int|null $storeInstance The store instance ID.
	 * }
	 * @return void
	 */
	public static function set_front_end_data( array $args ) {
		// Script data is not needed in VB.
		if ( Conditions::is_vb_enabled() ) {
			return;
		}

		$selector       = isset( $args['selector'] ) ? $args['selector'] : '';
		$id             = isset( $args['id'] ) ? $args['id'] : '';
		$attrs          = isset( $args['attrs'] ) ? $args['attrs'] : [];
		$store_instance = isset( $args['storeInstance'] ) ? $args['storeInstance'] : null;

		if ( empty( $attrs ) || empty( $selector ) || empty( $id ) ) {
			return;
		}

		// Generate responsive carousel data using MultiView system.
		self::_set_responsive_carousel_data(
			[
				'id'            => $id,
				'selector'      => $selector,
				'attrs'         => $attrs,
				'storeInstance' => $store_instance,
			]
		);

		// Set MultiView content for arrow icons.
		foreach (
			[
				'prev' => 'leftIcon',
				'next' => 'rightIcon',
			] as $type => $icon_key
		) {
			self::_set_icon_content(
				[
					'id'            => $id,
					'selector'      => $selector,
					'storeInstance' => $store_instance,
					'iconAttr'      => $attrs['arrows']['advanced'][ $icon_key ] ?? [],
					'arrowType'     => $type,
				]
			);
		}

		// Register desktop carousel settings in script data.
		ScriptData::add_data_item(
			[
				'data_name'    => 'group_carousel',
				'data_item_id' => null,
				'data_item'    => [
					'selector'   => $selector,
					'data'       => self::_get_desktop_carousel_data( $attrs ),
					'responsive' => self::_get_responsive_carousel_settings_by_breakpoint( $attrs ),
				],
			]
		);
	}

	/**
	 * Get desktop carousel data for script output.
	 *
	 * @param array $attrs Module attributes.
	 * @return array Desktop carousel settings.
	 */
	private static function _get_desktop_carousel_data( array $attrs ): array {
		return [
			'auto'            => 'on' === ( $attrs['module']['advanced']['auto']['desktop']['value'] ?? 'off' ),
			'speed'           => TimeUtils::value_to_ms( $attrs['module']['advanced']['speed']['desktop']['value'] ?? '2000ms' ),
			'transitionSpeed' => TimeUtils::value_to_ms( $attrs['module']['advanced']['transitionSpeed']['desktop']['value'] ?? '200ms' ),
			'pauseOnHover'    => 'on' === ( $attrs['module']['advanced']['pauseOnHover']['desktop']['value'] ?? 'on' ),
			'centerMode'      => 'on' === ( $attrs['module']['advanced']['centerMode']['desktop']['value'] ?? 'off' ),
			'slidesToShow'    => (int) ( $attrs['module']['advanced']['slidesToShow']['desktop']['value'] ?? '1' ),
			'slidesToScroll'  => (int) ( $attrs['module']['advanced']['slidesToScroll']['desktop']['value'] ?? '1' ),
			'showArrows'      => 'on' === ( $attrs['arrows']['advanced']['showArrows']['desktop']['value'] ?? 'on' ),
			'showDots'        => 'on' === ( $attrs['dotNav']['advanced']['showDots']['desktop']['value'] ?? 'on' ),
			'arrowPosition'   => $attrs['arrows']['advanced']['position']['desktop']['value'] ?? 'center',
			'dotAlignment'    => $attrs['dotNav']['advanced']['alignment']['desktop']['value'] ?? 'center',
			'dotPosition'     => $attrs['dotNav']['advanced']['position']['desktop']['value'] ?? 'below',
		];
	}

	/**
	 * Resolved carousel settings per enabled breakpoint (same inheritance as {@see _set_responsive_carousel_data}).
	 *
	 * Exposed in localized script so front-end JS does not depend on MultiView `unique_data` omitting
	 * breakpoint rows that match a wider row — per-key multiview resolution can then jump to the wrong
	 * row (e.g. tablet) for narrow breakpoints.
	 *
	 * @param array $attrs Module attributes.
	 * @return array<string, array<string, int|bool|string>> Map of breakpoint name to settings (same shape as {@see _get_desktop_carousel_data}).
	 */
	private static function _get_responsive_carousel_settings_by_breakpoint( array $attrs ): array {
		$desktop = self::_get_desktop_carousel_data( $attrs );
		$by_breakpoint = [];

		foreach ( Breakpoint::get_enabled_breakpoint_names() as $breakpoint ) {
			$get = function ( array $attr_path ) use ( $attrs, $breakpoint ) {
				if ( empty( $attr_path ) ) {
					return null;
				}
				return ModuleUtils::use_attr_value(
					[
						'attr'       => $attr_path,
						'breakpoint' => $breakpoint,
						'state'      => 'value',
						'mode'       => 'getAndInheritAll',
					]
				);
			};

			$auto_v     = $get( $attrs['module']['advanced']['auto'] ?? [] );
			$speed_v    = $get( $attrs['module']['advanced']['speed'] ?? [] );
			$tspeed_v   = $get( $attrs['module']['advanced']['transitionSpeed'] ?? [] );
			$pause_v    = $get( $attrs['module']['advanced']['pauseOnHover'] ?? [] );
			$center_v   = $get( $attrs['module']['advanced']['centerMode'] ?? [] );
			$sts_v      = $get( $attrs['module']['advanced']['slidesToShow'] ?? [] );
			$stsc_v     = $get( $attrs['module']['advanced']['slidesToScroll'] ?? [] );
			$sarr_v     = $get( $attrs['arrows']['advanced']['showArrows'] ?? [] );
			$sdots_v    = $get( $attrs['dotNav']['advanced']['showDots'] ?? [] );
			$ap_v       = $get( $attrs['arrows']['advanced']['position'] ?? [] );
			$da_v       = $get( $attrs['dotNav']['advanced']['alignment'] ?? [] );
			$dp_v       = $get( $attrs['dotNav']['advanced']['position'] ?? [] );

			$by_breakpoint[ $breakpoint ] = [
				'auto'            => 'on' === ( null !== $auto_v ? $auto_v : ( $attrs['module']['advanced']['auto']['desktop']['value'] ?? 'off' ) ),
				'speed'           => TimeUtils::value_to_ms( null !== $speed_v ? $speed_v : ( $attrs['module']['advanced']['speed']['desktop']['value'] ?? '2000ms' ) ),
				'transitionSpeed' => TimeUtils::value_to_ms( null !== $tspeed_v ? $tspeed_v : ( $attrs['module']['advanced']['transitionSpeed']['desktop']['value'] ?? '200ms' ) ),
				'pauseOnHover'    => 'on' === ( null !== $pause_v ? $pause_v : ( $attrs['module']['advanced']['pauseOnHover']['desktop']['value'] ?? 'on' ) ),
				'centerMode'      => 'on' === ( null !== $center_v ? $center_v : ( $attrs['module']['advanced']['centerMode']['desktop']['value'] ?? 'off' ) ),
				'slidesToShow'    => (int) ( null !== $sts_v ? $sts_v : ( $attrs['module']['advanced']['slidesToShow']['desktop']['value'] ?? '1' ) ),
				'slidesToScroll'  => (int) ( null !== $stsc_v ? $stsc_v : ( $attrs['module']['advanced']['slidesToScroll']['desktop']['value'] ?? '1' ) ),
				'showArrows'      => 'on' === ( null !== $sarr_v ? $sarr_v : ( $attrs['arrows']['advanced']['showArrows']['desktop']['value'] ?? 'on' ) ),
				'showDots'        => 'on' === ( null !== $sdots_v ? $sdots_v : ( $attrs['dotNav']['advanced']['showDots']['desktop']['value'] ?? 'on' ) ),
				'arrowPosition'   => null !== $ap_v ? (string) $ap_v : $desktop['arrowPosition'],
				'dotAlignment'    => null !== $da_v ? (string) $da_v : $desktop['dotAlignment'],
				'dotPosition'     => null !== $dp_v ? (string) $dp_v : $desktop['dotPosition'],
			];
		}

		return $by_breakpoint;
	}

	/**
	 * Set responsive carousel data using MultiView system.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments for setting the responsive carousel data.
	 *
	 *     @type string   $id The module ID.
	 *     @type string   $selector The module selector.
	 *     @type array    $attrs The module attributes for all breakpoints.
	 *     @type int|null $storeInstance The store instance ID.
	 * }
	 * @return void
	 */
	private static function _set_responsive_carousel_data( array $args ) {
		$id             = isset( $args['id'] ) ? $args['id'] : '';
		$selector       = isset( $args['selector'] ) ? $args['selector'] : '';
		$attrs          = isset( $args['attrs'] ) ? $args['attrs'] : [];
		$store_instance = isset( $args['storeInstance'] ) ? $args['storeInstance'] : null;

		if ( empty( $attrs ) || empty( $selector ) || empty( $id ) ) {
			return;
		}

		// Prepare data for MultiView script data.
		$carousel_data = [];

		// Map of carousel settings and their attribute paths.
		$carousel_settings = [
			'data-carousel-auto'             => $attrs['module']['advanced']['auto'] ?? [],
			'data-carousel-speed'            => $attrs['module']['advanced']['speed'] ?? [],
			'data-carousel-transition-speed' => $attrs['module']['advanced']['transitionSpeed'] ?? [],
			'data-carousel-pause-on-hover'   => $attrs['module']['advanced']['pauseOnHover'] ?? [],
			'data-carousel-center-mode'      => $attrs['module']['advanced']['centerMode'] ?? [],
			'data-carousel-slides-to-show'   => $attrs['module']['advanced']['slidesToShow'] ?? [],
			'data-carousel-slides-to-scroll' => $attrs['module']['advanced']['slidesToScroll'] ?? [],
			'data-carousel-show-arrows'      => $attrs['arrows']['advanced']['showArrows'] ?? [],
			'data-carousel-show-dots'        => $attrs['dotNav']['advanced']['showDots'] ?? [],
			'data-carousel-arrow-position'   => $attrs['arrows']['advanced']['position'] ?? [],
			'data-carousel-dot-alignment'    => $attrs['dotNav']['advanced']['alignment'] ?? [],
			'data-carousel-dot-position'     => $attrs['dotNav']['advanced']['position'] ?? [],
		];

		// Process each carousel setting that should be responsive.
		foreach ( $carousel_settings as $data_attr => $setting_attrs ) {
			if ( empty( $setting_attrs ) ) {
				continue;
			}

			$setting_data = [];

			// Extract values for each breakpoint and state with full responsive inheritance.
			foreach ( Breakpoint::get_enabled_breakpoint_names() as $breakpoint ) {
				// Process each state: value, hover, sticky.
				foreach ( [ 'value', 'hover', 'sticky' ] as $state ) {
					$value = ModuleUtils::use_attr_value(
						[
							'attr'       => $setting_attrs,
							'breakpoint' => $breakpoint,
							'state'      => $state,
							'mode'       => 'getAndInheritAll',
						]
					);

					// Only process if we have a value (explicit or inherited).
					if ( null !== $value ) {
						// Convert specific values to appropriate formats.
						if ( in_array( $data_attr, [ 'data-carousel-auto', 'data-carousel-pause-on-hover', 'data-carousel-center-mode', 'data-carousel-show-arrows', 'data-carousel-show-dots' ], true ) ) {
							// Boolean settings.
							$value = 'on' === $value ? 'true' : 'false';
						} elseif ( in_array( $data_attr, [ 'data-carousel-speed', 'data-carousel-transition-speed' ], true ) ) {
							// Speed settings - convert to milliseconds.
							$value = (string) TimeUtils::value_to_ms( $value );
						} else {
							// Ensure all other values are strings.
							$value = (string) $value;
						}

						$setting_data[ $breakpoint ][ $state ] = $value;
					}
				}
			}

			// Only add to carousel data if we have at least desktop data.
			if ( ! empty( $setting_data ) ) {
				$carousel_data[ $data_attr ] = $setting_data;
			}
		}

		// Set MultiView attributes if we have responsive data.
		if ( ! empty( $carousel_data ) ) {
			MultiViewScriptData::set_attrs(
				[
					'id'            => $id,
					'name'          => 'divi/group-carousel',
					'selector'      => $selector,
					'hoverSelector' => $selector,
					'data'          => $carousel_data,
					'storeInstance' => $store_instance,
				]
			);
		}
	}

	/**
	 * Set MultiView content for an arrow icon.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments for setting the icon content.
	 *
	 *     @type string   $id            The module ID.
	 *     @type string   $selector      The module selector.
	 *     @type int|null $storeInstance The store instance ID.
	 *     @type array    $iconAttr      The icon attribute data.
	 *     @type string   $arrowType     The arrow type ('prev' or 'next').
	 * }
	 * @return void
	 */
	private static function _set_icon_content( array $args ) {
		$id             = isset( $args['id'] ) ? $args['id'] : '';
		$selector       = isset( $args['selector'] ) ? $args['selector'] : '';
		$store_instance = isset( $args['storeInstance'] ) ? $args['storeInstance'] : null;
		$icon_attr      = isset( $args['iconAttr'] ) ? $args['iconAttr'] : [];
		$arrow_type     = isset( $args['arrowType'] ) ? $args['arrowType'] : 'prev';

		if ( empty( $icon_attr ) || empty( $selector ) || empty( $id ) ) {
			return;
		}

		$arrow_class = 'prev' === $arrow_type ? 'et_pb_group_carousel_arrow_prev' : 'et_pb_group_carousel_arrow_next';

		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => 'divi/group-carousel',
				'storeInstance' => $store_instance,
				'hoverSelector' => $selector,
				'setContent'    => [
					[
						'selector'      => "{$selector} .{$arrow_class} .et-pb-icon",
						'data'          => $icon_attr,
						'sanitizer'     => 'et_core_esc_previously',
						'valueResolver' => function ( $value ) {
							if ( is_array( $value ) && isset( $value['unicode'] ) ) {
								$icon_html = Utils::process_font_icon( $value );
								return $icon_html ?? '';
							}
							return '';
						},
					],
				],
			]
		);
	}

	/**
	 * Get the custom CSS fields for the Group Carousel module.
	 *
	 * This function retrieves the custom CSS fields defined for the Group Carousel module.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Group Carousel module.
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/group-carousel' )->customCssFields;
	}

	/**
	 * Handle module styles.
	 *
	 * @param array $args Arguments.
	 */
	public static function module_styles( array $args ) {
		$attrs    = isset( $args['attrs'] ) ? $args['attrs'] : [];
		$elements = $args['elements'];
		$settings = isset( $args['settings'] ) ? $args['settings'] : [];

		$default_printed_style_attrs = isset( $args['defaultPrintedStyleAttrs'] ) ? $args['defaultPrintedStyleAttrs'] : [];
		$order_class                 = $args['orderClass'] ?? '';

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					// Module.
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'disabledOn'               => [
									'disabledModuleVisibility' => isset( $settings['disabledModuleVisibility'] ) ? $settings['disabledModuleVisibility'] : null,
								],
								'defaultPrintedStyleAttrs' => isset( $default_printed_style_attrs['module']['decoration'] ) ? $default_printed_style_attrs['module']['decoration'] : [],
								'advancedStyles'           => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $order_class . ' .et_pb_group_carousel_slide > .et_pb_group',
											'attr'     => $attrs['module']['advanced']['transitionSpeed'] ?? null,
											'declarationFunction' => function ( $props ) {
												$speed = $props['attrValue'] ?? '200ms';
												return "transition: all {$speed} ease-in-out";
											},
										],
									],
								],
							],
						]
					),

					// Arrows.
					$elements->style(
						[
							'attrName'   => 'arrows',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $order_class . ' .et_pb_group_carousel_arrow .et-pb-icon',
											'attr'     => $attrs['arrows']['advanced']['color'] ?? null,
											'property' => 'color',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $order_class . ' .et_pb_group_carousel_arrow .et-pb-icon',
											'attr'     => $attrs['arrows']['advanced']['size'] ?? null,
											'property' => 'font-size',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $order_class . ' .et_pb_group_carousel_arrow_prev .et-pb-icon',
											'attr'     => $attrs['arrows']['advanced']['leftIcon'] ?? [],
											'declarationFunction' => [ self::class, 'icon_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $order_class . ' .et_pb_group_carousel_arrow_next .et-pb-icon',
											'attr'     => $attrs['arrows']['advanced']['rightIcon'] ?? [],
											'declarationFunction' => [ self::class, 'icon_style_declaration' ],
										],
									],
								],
							],
						]
					),

					// Dot Navigation.
					$elements->style(
						[
							'attrName'   => 'dotNav',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $order_class . ' .et_pb_group_carousel_dot',
											'attr'     => $attrs['dotNav']['advanced']['size'] ?? null,
											'property' => 'width',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $order_class . ' .et_pb_group_carousel_dot',
											'attr'     => $attrs['dotNav']['advanced']['size'] ?? null,
											'property' => 'height',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $order_class . ' .et_pb_group_carousel_dots',
											'attr'     => $attrs['dotNav']['advanced']['alignment'] ?? null,
											'property' => 'text-align',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $order_class . ' .et_pb_group_carousel_dot',
											'attr'     => $attrs['dotNav']['advanced']['color'] ?? null,
											'property' => 'background-color',
										],
									],
								],
							],
						]
					),

					// Children/Slides.
					$elements->style(
						[
							'attrName' => 'children',
						]
					),

					// Active Groups.
					$elements->style(
						[
							'attrName' => 'activeGroups',
						]
					),

					// Module - Only for Custom CSS.
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
	 * Render carousel content with slides.
	 *
	 * @param array          $attrs    Module attributes.
	 * @param WP_Block       $block    Block object.
	 * @param ModuleElements $elements An instance of the ModuleElements class.
	 *
	 * @return string
	 */
	private static function _render_carousel_content( array $attrs, WP_Block $block, ModuleElements $elements ) {
		$arrows_attr = $attrs['arrows']['advanced']['showArrows'] ?? [];
		$show_arrows = empty( $arrows_attr ) ? true : ModuleUtils::has_value(
			$arrows_attr,
			[
				'valueResolver' => function ( $value ) {
					return 'on' === ( $value ?? 'on' );
				},
			]
		);

		$dots_attr = $attrs['dotNav']['advanced']['showDots'] ?? [];
		$show_dots = self::_is_dot_navigation_enabled( $dots_attr );

		// Render inner blocks without manual slide wrappers.
		// Child Groups will automatically wrap themselves in carousel slide containers
		// when they detect their parent is a Group Carousel. This allows looping to work
		// correctly - each looped instance gets its own slide wrapper.
		$slides_html = '';
		if ( ! empty( $block->parsed_block['innerBlocks'] ) ) {
			foreach ( $block->parsed_block['innerBlocks'] as $inner_block ) {
				// Create a WP_Block instance for the inner block.
				$inner_wp_block = new WP_Block( $inner_block );

				// Render the inner block (Groups will self-wrap if needed).
				$slides_html .= $inner_wp_block->render();
			}
		}

		// Build carousel track with slides.
		$track_content = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'et_pb_group_carousel_track',
				],
				'children'          => $slides_html,
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		// Build arrows HTML (inside container).
		$arrows_html = '';
		if ( $show_arrows ) {
			// Get custom arrow icons.
			$left_icon  = $attrs['arrows']['advanced']['leftIcon']['desktop']['value'] ?? null;
			$right_icon = $attrs['arrows']['advanced']['rightIcon']['desktop']['value'] ?? null;

			// Process icons using Utils::process_font_icon.
			$left_icon_html  = $left_icon ? Utils::process_font_icon( $left_icon ) : '';
			$right_icon_html = $right_icon ? Utils::process_font_icon( $right_icon ) : '';

			// Render previous arrow using elements->render for custom attributes support.
			$prev_arrow = $elements->render(
				[
					'attrName'          => 'arrows',
					'tagName'           => 'span',
					'attributes'        => [
						'class'      => 'et_pb_group_carousel_arrow et_pb_group_carousel_arrow_prev',
						'role'       => 'button',
						'tabindex'   => '0',
						'aria-label' => 'Previous slide',
					],
					'skipAttrChildren'  => true,
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => '<span class="et-pb-icon">' . $left_icon_html . '</span>',
				]
			);

			// Render next arrow using elements->render for custom attributes support.
			$next_arrow = $elements->render(
				[
					'attrName'          => 'arrows',
					'tagName'           => 'span',
					'attributes'        => [
						'class'      => 'et_pb_group_carousel_arrow et_pb_group_carousel_arrow_next',
						'role'       => 'button',
						'tabindex'   => '0',
						'aria-label' => 'Next slide',
					],
					'skipAttrChildren'  => true,
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => '<span class="et-pb-icon">' . $right_icon_html . '</span>',
				]
			);

			$arrows_html = $prev_arrow . $next_arrow;
		}

		// Build carousel container with track only (arrows moved outside).
		$carousel_container = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [ 'class' => 'et_pb_group_carousel_container' ],
				'children'          => $track_content,
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		// Build dots HTML (outside container).
		$dots_attributes = [
			'class' => 'et_pb_group_carousel_dots',
		];

		if ( ! $show_dots ) {
			$dots_attributes['style'] = 'display: none;';
		}

		// Always render dots container so frontend runtime can update visibility per breakpoint.
		$dots_content = $elements->render(
			[
				'attrName'          => 'dotNav',
				'tagName'           => 'div',
				'attributes'        => $dots_attributes,
				'skipAttrChildren'  => true,
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => '', // Dots are created and managed entirely by JavaScript.
			]
		);

		// Return container, arrows, and dots together (arrows between container and dots).
		return $carousel_container . $arrows_html . $dots_content;
	}

	/**
	 * Icon style declaration for Group Carousel icons.
	 *
	 * This function generates CSS for icons with proper font-family, font-weight
	 * and content based on icon type (FontAwesome vs ETmodules).
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The icon attribute value with desktop/tablet/phone breakpoints.
	 * }
	 *
	 * @return string The CSS for icon style.
	 */
	public static function icon_style_declaration( array $params ): string {
		$icon_attr = $params['attrValue'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'font-family' => true,
				],
			]
		);

		if ( isset( $icon_attr['type'] ) ) {
			$font_family = 'fa' === $icon_attr['type'] ? 'FontAwesome' : 'ETmodules';
			$style_declarations->add( 'font-family', $font_family );
		}

		if ( ! empty( $icon_attr['weight'] ) ) {
			$style_declarations->add( 'font-weight', $icon_attr['weight'] );
		}

		if ( ! empty( $icon_attr['unicode'] ) ) {
			$font_icon = Utils::escape_font_icon( Utils::process_font_icon( $icon_attr ) );
			$style_declarations->add( 'content', "'" . $font_icon . "'" );
		}

		return $style_declarations->value();
	}

	/**
	 * Initial setup for the dependency.
	 */
	public function load() {
		$module_json_folder_path = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/visual-builder/packages/module-library/src/components/group-carousel/';

		add_filter( 'divi_conversion_presets_attrs_map', [ GroupCarouselPresetAttrsMap::class, 'get_map' ], 10, 2 );

		// Ensure that all filters and actions applied during module registration are registered before calling `ModuleRegistration::register_module()`.
		// However, for consistency, register all module-specific filters and actions prior to invoking `ModuleRegistration::register_module()`.
		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
