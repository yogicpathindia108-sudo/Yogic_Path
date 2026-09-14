<?php
/**
 * Module: ZIndexPresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options;

use ET\Builder\Packages\Module\Options\Animation\AnimationPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Background\BackgroundPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Border\BorderPresetAttrsMap;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowPresetAttrsMap;
use ET\Builder\Packages\Module\Options\TextShadow\TextShadowPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Font\FontPresetAttrsMap;
use ET\Builder\Packages\Module\Options\FontBodyGroup\FontBodyPresetAttrsMap;
use ET\Builder\Packages\Module\Options\FontHeaderGroup\FontHeaderPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Conditions\ConditionsPresetAttrsMap;
use ET\Builder\Packages\Module\Options\DisabledOn\DisabledOnPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Filters\FiltersPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Icon\IconPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Overflow\OverflowPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Position\PositionPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Sizing\SizingPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Spacing\SpacingPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Sticky\StickyPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Transform\TransformPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Transition\TransitionPresetAttrsMap;
use ET\Builder\Packages\Module\Options\ZIndex\ZIndexPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Scroll\ScrollPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Text\TextPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Link\LinkUtilsPresetAttrsMap;
use ET\Builder\Packages\Module\Options\IdClasses\IdClassesPresetAttrsMap;
use ET\Builder\Packages\Module\Options\AdminLabel\AdminLabelPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Button\ButtonPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Meta\MetaPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Attributes\AttributesPresetAttrsMap;
use ET\Builder\Packages\Module\Options\AttributesRel\AttributesRelPresetAttrsMap;
use ET\Builder\Packages\Module\Options\FormField\FormFieldPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Fit\FitPresetAttrsMap;
use ET\Builder\Packages\Module\Options\ScrollSettings\ScrollSettingsPresetAttrsMap;
use ET\Builder\Packages\Module\Options\PositionSettings\PositionSettingsPresetAttrsMap;
use ET\Builder\Packages\Module\Options\VisibilitySettings\VisibilitySettingsPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Dividers\DividersPresetAttrsMap;
use ET\Builder\Packages\Module\Options\SpamProtection\SpamProtectionPresetAttrsMap;
use ET\Builder\Packages\Module\Options\EmailService\EmailServicePresetAttrsMap;
use ET\Builder\Packages\Module\Options\Gutter\GutterPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Interactions\InteractionsPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Image\ImagePresetAttrsMap;
use ET\Builder\Packages\Module\Options\Layout\LayoutPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Loop\LoopPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Html\HtmlPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * ModuleOptionsPresetAttrs class.
 *
 * This class provides the static map of groups for the preset attributes.
 *
 * @since ??
 */
class ModuleOptionsPresetAttrs {

	/**
	 * Get the preset attributes map for the given group name.
	 *
	 * @since ??
	 *
	 * @param string $group_name The group name.
	 * @param string $attr_name The attribute name.
	 * @param array  $args The arguments.
	 *
	 * @return array The preset attributes map.
	 */
	public static function get_preset_attrs_from_group( string $group_name, string $attr_name, array $args = [] ) {
		switch ( $group_name ) {
			case 'divi/animation':
				return AnimationPresetAttrsMap::get_map( $attr_name );
			case 'divi/background':
				return BackgroundPresetAttrsMap::get_map( $attr_name );
			case 'divi/border':
				return BorderPresetAttrsMap::get_map( $attr_name );
			case 'divi/box-shadow':
				return BoxShadowPresetAttrsMap::get_map( $attr_name );
			case 'divi/text-shadow':
				return TextShadowPresetAttrsMap::get_map( $attr_name );
			case 'divi/font':
				return FontPresetAttrsMap::get_map( $attr_name, $args );
			case 'divi/font-chart-js':
				// The Chart.js font group shares the same attribute shape as `divi/font`.
				return FontPresetAttrsMap::get_map( $attr_name, $args );
			case 'divi/font-body':
				return FontBodyPresetAttrsMap::get_map( $attr_name );
			case 'divi/font-header':
				return FontHeaderPresetAttrsMap::get_map( $attr_name );
			case 'divi/conditions':
				return ConditionsPresetAttrsMap::get_map( $attr_name );
			case 'divi/disabled-on':
				return DisabledOnPresetAttrsMap::get_map( $attr_name );
			case 'divi/filters':
				return FiltersPresetAttrsMap::get_map( $attr_name );
			case 'divi/fit':
				return FitPresetAttrsMap::get_map( $attr_name );
			case 'divi/icon':
				return IconPresetAttrsMap::get_map( $attr_name );
			case 'divi/overflow':
				return OverflowPresetAttrsMap::get_map( $attr_name );
			case 'divi/position':
				return PositionPresetAttrsMap::get_map( $attr_name );
			case 'divi/scroll':
				return ScrollPresetAttrsMap::get_map( $attr_name );
			case 'divi/sizing':
				return SizingPresetAttrsMap::get_map( $attr_name );
			case 'divi/spacing':
				return SpacingPresetAttrsMap::get_map( $attr_name );
			case 'divi/sticky':
				return StickyPresetAttrsMap::get_map( $attr_name );
			case 'divi/transform':
				return TransformPresetAttrsMap::get_map( $attr_name );
			case 'divi/transition':
				return TransitionPresetAttrsMap::get_map( $attr_name );
			case 'divi/z-index':
				return ZIndexPresetAttrsMap::get_map( $attr_name );
			case 'divi/text':
				return TextPresetAttrsMap::get_map( $attr_name );
			case 'divi/link':
				return LinkUtilsPresetAttrsMap::get_map( $attr_name );
			case 'divi/id-classes':
				return IdClassesPresetAttrsMap::get_map( $attr_name );
			case 'divi/admin-label':
				return AdminLabelPresetAttrsMap::get_map( $attr_name );
			case 'divi/meta':
				return MetaPresetAttrsMap::get_map( $attr_name );
			case 'divi/button':
				return ButtonPresetAttrsMap::get_map( $attr_name );
			case 'divi/attributes':
				return AttributesPresetAttrsMap::get_map( $attr_name );
			case 'divi/attributes-rel':
				return AttributesRelPresetAttrsMap::get_map( $attr_name );
			case 'divi/form-field':
				return FormFieldPresetAttrsMap::get_map( $attr_name );
			case 'divi/image':
				return ImagePresetAttrsMap::get_map( $attr_name );
			case 'divi/scroll-settings':
				return ScrollSettingsPresetAttrsMap::get_map( $attr_name );
			case 'divi/position-settings':
				return PositionSettingsPresetAttrsMap::get_map( $attr_name );
			case 'divi/visibility':
				return VisibilitySettingsPresetAttrsMap::get_map( $attr_name );
			case 'divi/dividers':
				return DividersPresetAttrsMap::get_map( $attr_name );
			case 'divi/spam-protection':
				return SpamProtectionPresetAttrsMap::get_map( $attr_name );
			case 'divi/email-service':
				return EmailServicePresetAttrsMap::get_map( $attr_name );
			case 'divi/gutter':
				return GutterPresetAttrsMap::get_map( $attr_name );
			case 'divi/interactions':
				return InteractionsPresetAttrsMap::get_map( $attr_name );
			case 'divi/layout':
				return LayoutPresetAttrsMap::get_map( $attr_name );
			case 'divi/loop':
				return LoopPresetAttrsMap::get_map( $attr_name );
			case 'divi/html':
				return HtmlPresetAttrsMap::get_map( $attr_name );
			case 'divi/elements':
				return Elements\ElementsPresetAttrsMap::get_map( $attr_name );
			case 'image':
				return ImagePresetAttrsMap::get_map( $attr_name );
			default:
				return [];
		}
	}

	/**
	 * Get the group name by key.
	 *
	 * @since ??
	 *
	 * @param string $type The type.
	 * @param string $key The key.
	 *
	 * @return string The group name.
	 */
	public static function get_the_group_name_by_key( string $type, string $key ) {
		if ( 'decoration' === $type ) {
			switch ( $key ) {
				case 'animation':
					return 'divi/animation';
				case 'attributes':
					return 'divi/attributes';
				case 'background':
					return 'divi/background';
				case 'bodyFont':
					return 'divi/font-body';
				case 'border':
					return 'divi/border';
				case 'boxShadow':
					return 'divi/box-shadow';
				case 'button':
					return 'divi/button';
				case 'conditions':
					return 'divi/conditions';
				case 'disabledOn':
					return 'divi/disabled-on';
				case 'filters':
					return 'divi/filters';
				case 'fit':
					return 'divi/fit';
				case 'image':
					return 'divi/image';
				case 'interactions':
					return 'divi/interactions';
				case 'layout':
					return 'divi/layout';
				case 'font':
					return 'divi/font';
				case 'headingFont':
					return 'divi/font-header';
				case 'icon':
					return 'divi/icon';
				case 'inlineFont':
					return '';
				case 'overflow':
					return 'divi/overflow';
				case 'position':
					return 'divi/position';
				case 'scroll':
					return 'divi/scroll';
				case 'sizing':
					return 'divi/sizing';
				case 'spacing':
					return 'divi/spacing';
				case 'sticky':
					return 'divi/sticky';
				case 'transform':
					return 'divi/transform';
				case 'transition':
					return 'divi/transition';
				case 'zIndex':
					return 'divi/z-index';
			}
		} elseif ( 'advanced' === $type ) {
			switch ( $key ) {
				case 'elements':
					return 'divi/elements';
				case 'htmlAttributes':
					return 'divi/id-classes';
				case 'gutter':
					return 'divi/gutter';
				case 'link':
					return 'divi/link';
				case 'text':
					return 'divi/text';
				case 'loop':
					return 'divi/loop';
				case 'html':
					return 'divi/html';
			}
		} elseif ( 'meta' === $type ) {
			switch ( $key ) {
				case 'adminLabel':
					return 'divi/admin-label';
				case 'meta':
					return 'divi/meta';
			}
		}
	}
}
