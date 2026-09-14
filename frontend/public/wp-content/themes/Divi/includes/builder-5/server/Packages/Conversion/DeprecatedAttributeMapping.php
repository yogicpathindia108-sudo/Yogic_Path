<?php
/**
 * Deprecated Attribute Mapping for Preset Conversion.
 *
 * This class handles attribute mappings for deprecated settings that have been
 * removed from module UIs but still need to be included in preset conversion
 * for backwards compatibility.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Conversion;

/**
 * Class DeprecatedAttributeMapping
 *
 * Manages deprecated attribute mappings for modules that need them for preset conversion.
 */
class DeprecatedAttributeMapping {

	/**
	 * Get deprecated attribute mappings for a specific module.
	 *
	 * @since ??
	 *
	 * @param string $module_name The module name (e.g., 'divi/sidebar').
	 *
	 * @return array Array of deprecated attribute mappings for the module.
	 */
	public static function get_deprecated_attrs_for_module( $module_name ) {
		$module_attribute_types = self::_get_module_attribute_types();
		$attribute_definitions  = self::_get_attribute_definitions();

		$deprecated_attrs = [];

		// Get the deprecated attribute types for this module.
		$attribute_types = $module_attribute_types[ $module_name ] ?? [];

		// Build the deprecated attributes array from the attribute type definitions.
		foreach ( $attribute_types as $attribute_type ) {
			if ( isset( $attribute_definitions[ $attribute_type ] ) ) {
				$deprecated_attrs = array_merge( $deprecated_attrs, $attribute_definitions[ $attribute_type ] );
			}
		}

		return $deprecated_attrs;
	}

	/**
	 * Check if a module has deprecated attributes.
	 *
	 * @since ??
	 *
	 * @param string $module_name The module name (e.g., 'divi/sidebar').
	 *
	 * @return bool True if the module has deprecated attributes, false otherwise.
	 */
	public static function has_deprecated_attrs( $module_name ) {
		$module_attribute_types = self::_get_module_attribute_types();

		return isset( $module_attribute_types[ $module_name ] );
	}

	/**
	 * Get mapping of modules to their deprecated attribute types.
	 *
	 * @since ??
	 *
	 * @return array Array mapping module names to their deprecated attribute types.
	 */
	private static function _get_module_attribute_types() {
		return [
			'divi/accordion'                            => [ 'htmlAttributes' ],
			'divi/audio'                                => [ 'htmlAttributes' ],
			'divi/blog'                                 => [ 'htmlAttributes', 'blogLayout', 'blogGridFlexType' ],
			'divi/blurb'                                => [ 'htmlAttributes', 'imageIconAlt', 'imageIconAnimation', 'imageIconWidth', 'imageIconAlignment', 'imageIconBackground' ],
			'divi/button'                               => [ 'htmlAttributes', 'buttonRel', 'buttonEnable', 'buttonAlignment' ],
			'divi/circle-counter'                       => [ 'htmlAttributes' ],
			'divi/code'                                 => [ 'htmlAttributes' ],
			'divi/column'                               => [ 'htmlAttributes' ],
			'divi/column-inner'                         => [ 'htmlAttributes' ],
			'divi/comments'                             => [ 'htmlAttributes', 'buttonEnable', 'buttonAlignment', 'focusFieldLegacyColors', 'focusFieldLegacyBorder', 'fieldLegacyPlaceholderFont' ],
			'divi/contact-field'                        => [ 'htmlAttributes', 'contactFieldFullwidth', 'focusFieldLegacyColors', 'focusFieldLegacyBorder', 'fieldLegacyPlaceholderFont' ],
			'divi/contact-form'                         => [ 'htmlAttributes', 'buttonEnable', 'buttonAlignment', 'focusFieldLegacyColors', 'fieldLegacyPlaceholderFont' ],
			'divi/countdown-timer'                      => [ 'htmlAttributes' ],
			'divi/counters'                             => [ 'htmlAttributes' ],
			'divi/cta'                                  => [ 'htmlAttributes', 'buttonRel', 'buttonEnable', 'buttonAlignment' ],
			'divi/divider'                              => [ 'htmlAttributes' ],
			'divi/filterable-portfolio'                 => [ 'htmlAttributes', 'portfolioLayout', 'portfolioGridFlexType' ],
			'divi/fullwidth-code'                       => [ 'htmlAttributes' ],
			'divi/fullwidth-header'                     => [ 'htmlAttributes', 'logoAlt', 'imageAlt', 'buttonOneRel', 'buttonTwoRel', 'buttonOneEnable', 'buttonTwoEnable', 'imageTitle', 'logoTitle' ],
			'divi/fullwidth-image'                      => [ 'htmlAttributes', 'imageAlt', 'imageTitleText' ],
			'divi/fullwidth-map'                        => [ 'htmlAttributes' ],
			'divi/fullwidth-menu'                       => [ 'htmlAttributes', 'logoAlt' ],
			'divi/fullwidth-portfolio'                  => [ 'htmlAttributes', 'portfolioGridFlexType' ],
			'divi/fullwidth-post-content'               => [ 'htmlAttributes' ],
			'divi/fullwidth-post-slider'                => [ 'htmlAttributes', 'buttonRel', 'buttonEnable', 'buttonAlignment' ],
			'divi/fullwidth-post-title'                 => [ 'htmlAttributes' ],
			'divi/fullwidth-slider'                     => [ 'htmlAttributes', 'childrenButtonRel', 'childrenButtonEnable' ],
			'divi/gallery'                              => [ 'htmlAttributes', 'galleryGridFlexType' ],
			'divi/group'                                => [ 'htmlAttributes' ],
			'divi/group-carousel'                       => [ 'htmlAttributes' ],
			'divi/heading'                              => [ 'htmlAttributes' ],
			'divi/icon'                                 => [ 'htmlAttributes', 'iconTitle' ],
			'divi/icon-list'                            => [ 'htmlAttributes' ],
			'divi/icon-list-item'                       => [ 'htmlAttributes', 'iconTitle' ],
			'divi/image'                                => [ 'htmlAttributes', 'imageAlt', 'imageTitleText', 'imageRel' ],
			'divi/login'                                => [ 'htmlAttributes', 'buttonEnable', 'buttonAlignment', 'focusFieldLegacyColors', 'focusFieldLegacyBorder', 'fieldLegacyPlaceholderFont' ],
			'divi/lottie'                               => [ 'htmlAttributes' ],
			'divi/map'                                  => [ 'htmlAttributes' ],
			'divi/menu'                                 => [ 'htmlAttributes', 'logoAlt' ],
			'divi/number-counter'                       => [ 'htmlAttributes' ],
			'divi/portfolio'                            => [ 'htmlAttributes', 'portfolioLayout', 'portfolioGridFlexType' ],
			'divi/post-content'                         => [ 'htmlAttributes' ],
			'divi/post-nav'                             => [ 'htmlAttributes' ],
			'divi/post-slider'                          => [ 'htmlAttributes', 'buttonRel', 'buttonEnable', 'buttonAlignment' ],
			'divi/post-title'                           => [ 'htmlAttributes', 'postTitleFeaturedImageSizing' ],
			'divi/pricing-table'                        => [ 'buttonRel', 'buttonEnable', 'buttonAlignment' ],
			'divi/pricing-tables'                       => [ 'htmlAttributes', 'childrenButtonRel', 'childrenButtonEnable' ],
			'divi/pricing-tables-item'                  => [ 'htmlAttributes', 'buttonEnable', 'buttonAlignment' ],
			'divi/row'                                  => [ 'htmlAttributes' ],
			'divi/row-inner'                            => [ 'htmlAttributes' ],
			'divi/search'                               => [ 'htmlAttributes', 'focusFieldLegacyColors', 'fieldLegacyPlaceholderFont' ],
			'divi/section'                              => [ 'htmlAttributes' ],
			'divi/shop'                                 => [ 'htmlAttributes' ],
			'divi/sidebar'                              => [ 'htmlAttributes' ],
			'divi/signup'                               => [ 'htmlAttributes', 'buttonRel', 'buttonEnable', 'buttonAlignment', 'formLayout', 'focusFieldLegacyColors', 'focusFieldLegacyBorder', 'fieldLegacyPlaceholderFont' ],
			'divi/signup-custom-field'                  => [ 'focusFieldLegacyColors', 'focusFieldLegacyBorder', 'fieldLegacyPlaceholderFont' ],
			'divi/slide'                                => [ 'imageAlt', 'buttonRel', 'buttonEnable', 'buttonAlignment' ],
			'divi/slider'                               => [ 'htmlAttributes', 'childrenButtonRel', 'childrenButtonEnable' ],
			'divi/social-media-follow'                  => [ 'htmlAttributes', 'buttonEnable', 'buttonAlignment' ],
			'divi/tabs'                                 => [ 'htmlAttributes', 'inactiveTabBackground' ],
			'divi/team-member'                          => [ 'htmlAttributes' ],
			'divi/testimonial'                          => [ 'htmlAttributes' ],
			'divi/text'                                 => [ 'htmlAttributes' ],
			'divi/toggle'                               => [ 'htmlAttributes' ],
			'divi/video'                                => [ 'htmlAttributes' ],
			'divi/video-slider'                         => [ 'htmlAttributes' ],
			'divi/woocommerce-breadcrumb'               => [ 'htmlAttributes' ],
			'divi/woocommerce-cart-products'            => [ 'htmlAttributes', 'focusFieldLegacyColors', 'focusFieldLegacyBorder', 'fieldLegacyPlaceholderFont' ],
			'divi/woocommerce-cart-notice'              => [ 'htmlAttributes', 'fieldLegacyFieldLabelsFont', 'fieldLegacyRequiredFieldIndicatorColor', 'focusFieldLegacyColors', 'focusFieldLegacyBorder', 'fieldLegacyPlaceholderFont' ],
			'divi/woocommerce-cart-totals'              => [ 'htmlAttributes', 'focusFieldLegacyColors', 'focusFieldLegacyBorder', 'fieldLegacyPlaceholderFont' ],
			'divi/woocommerce-checkout-additional-info' => [ 'htmlAttributes', 'focusFieldLegacyColors', 'focusFieldLegacyBorder', 'fieldLegacyPlaceholderFont' ],
			'divi/woocommerce-checkout-billing'         => [ 'htmlAttributes', 'fieldLegacyFieldLabelsFont', 'fieldLegacyRequiredFieldIndicatorColor', 'focusFieldLegacyColors', 'focusFieldLegacyBorder', 'fieldLegacyPlaceholderFont' ],
			'divi/woocommerce-checkout-information'     => [ 'htmlAttributes', 'fieldLegacyFieldLabelsFont', 'focusFieldLegacyColors', 'focusFieldLegacyBorder', 'fieldLegacyPlaceholderFont' ],
			'divi/woocommerce-checkout-payment-info'    => [ 'htmlAttributes', 'focusTooltipLegacyBorder' ],
			'divi/woocommerce-checkout-shipping'        => [ 'htmlAttributes', 'fieldLegacyFieldLabelsFont', 'fieldLegacyRequiredFieldIndicatorColor', 'focusFieldLegacyColors', 'focusFieldLegacyBorder', 'fieldLegacyPlaceholderFont' ],
			'divi/woocommerce-product-add-to-cart'      => [ 'htmlAttributes', 'fieldLegacyFieldLabelsFont', 'focusFieldLegacyColors', 'focusDropdownMenusLegacyColors', 'focusDropdownMenusLegacyBorder', 'fieldLegacyPlaceholderFont' ],
			'divi/woocommerce-product-additional-info'  => [ 'htmlAttributes' ],
			'divi/woocommerce-product-description'      => [ 'htmlAttributes' ],
			'divi/woocommerce-product-gallery'          => [ 'htmlAttributes' ],
			'divi/woocommerce-product-images'           => [ 'htmlAttributes', 'imageForceFullwidth' ],
			'divi/woocommerce-product-meta'             => [ 'htmlAttributes' ],
			'divi/woocommerce-product-price'            => [ 'htmlAttributes' ],
			'divi/woocommerce-product-rating'           => [ 'htmlAttributes' ],
			'divi/woocommerce-product-reviews'          => [ 'htmlAttributes', 'focusFieldLegacyColors', 'focusFieldLegacyBorder', 'fieldLegacyPlaceholderFont' ],
			'divi/woocommerce-product-stock'            => [ 'htmlAttributes' ],
			'divi/woocommerce-product-tabs'             => [ 'htmlAttributes' ],
			'divi/woocommerce-product-title'            => [ 'htmlAttributes' ],
			'divi/woocommerce-product-upsell'           => [ 'htmlAttributes' ],
			'divi/woocommerce-related-products'         => [ 'htmlAttributes' ],
		];
	}

	/**
	 * Get attribute definitions organized by attribute type.
	 *
	 * @since ??
	 *
	 * @return array Array of attribute definitions organized by attribute type.
	 */
	private static function _get_attribute_definitions() {
		return [
			'htmlAttributes'                         => self::_get_html_attributes_definition(),
			'imageIconAlt'                           => self::_get_image_icon_alt_definition(),
			'imageAlt'                               => self::_get_image_alt_definition(),
			'imageTitleText'                         => self::_get_image_title_text_definition(),
			'logoAlt'                                => self::_get_logo_alt_definition(),
			'buttonRel'                              => self::_get_button_rel_definition(),
			'buttonEnable'                           => self::_get_button_enable_definition(),
			'buttonAlignment'                        => self::_get_button_alignment_definition(),
			'iconTitle'                              => self::_get_icon_title_definition(),
			'buttonOneRel'                           => self::_get_button_one_rel_definition(),
			'buttonOneEnable'                        => self::_get_button_one_enable_definition(),
			'buttonTwoRel'                           => self::_get_button_two_rel_definition(),
			'buttonTwoEnable'                        => self::_get_button_two_enable_definition(),
			'imageTitle'                             => self::_get_image_title_definition(),
			'logoTitle'                              => self::_get_logo_title_definition(),
			'childrenButtonRel'                      => self::_get_children_button_rel_definition(),
			'childrenButtonEnable'                   => self::_get_children_button_enable_definition(),
			'imageRel'                               => self::_get_image_rel_definition(),
			'imageIconAnimation'                     => self::_get_image_icon_animation_definition(),
			'imageIconWidth'                         => self::_get_image_icon_width_definition(),
			'imageIconAlignment'                     => self::_get_image_icon_alignment_definition(),
			'imageIconBackground'                    => self::_get_image_icon_background_definition(),
			'inactiveTabBackground'                  => self::_get_inactive_tab_background_definition(),
			'formLayout'                             => self::_get_form_layout_definition(),
			'portfolioLayout'                        => self::_get_portfolio_layout_definition(),
			'blogLayout'                             => self::_get_blog_layout_definition(),
			'portfolioGridFlexType'                  => self::_get_portfolio_grid_flex_type_definition(),
			'blogGridFlexType'                       => self::_get_blog_grid_flex_type_definition(),
			'galleryGridFlexType'                    => self::_get_gallery_grid_flex_type_definition(),
			'contactFieldFullwidth'                  => self::_get_contact_field_fullwidth_definition(),
			'focusFieldLegacyColors'                 => self::_get_focus_field_legacy_colors_definition(),
			'focusFieldLegacyBorder'                 => self::_get_focus_field_legacy_border_definition(),
			'fieldLegacyPlaceholderFont'             => self::_get_field_legacy_placeholder_font_definition(),
			'fieldLegacyFieldLabelsFont'             => self::_get_field_legacy_field_labels_font_definition(),
			'fieldLegacyRequiredFieldIndicatorColor' => self::_get_field_legacy_required_field_indicator_color_definition(),
			'focusDropdownMenusLegacyColors'         => self::_get_focus_dropdown_menus_legacy_colors_definition(),
			'focusDropdownMenusLegacyBorder'         => self::_get_focus_dropdown_menus_legacy_border_definition(),
			'focusTooltipLegacyBorder'               => self::_get_focus_tooltip_legacy_border_definition(),
			'imageForceFullwidth'                    => self::_get_image_force_fullwidth_definition(),
			'postTitleFeaturedImageSizing'           => self::_get_post_title_featured_image_sizing_definition(),
		];
	}

	/**
	 * Get htmlAttributes deprecated attribute definition.
	 *
	 * Many modules have deprecated htmlAttributes from the UI but still
	 * need them for preset conversion.
	 *
	 * @since ??
	 *
	 * @return array Array of htmlAttributes deprecated attribute mappings.
	 */
	private static function _get_html_attributes_definition() {
		return [
			'module.advanced.htmlAttributes__id'    => [
				'attrName' => 'module.advanced.htmlAttributes',
				'preset'   => 'content',
				'subName'  => 'id',
			],
			'module.advanced.htmlAttributes__class' => [
				'attrName' => 'module.advanced.htmlAttributes',
				'preset'   => [ 'html' ],
				'subName'  => 'class',
			],
		];
	}

	/**
	 * Get imageIconAlt deprecated attribute definition.
	 *
	 * Used by Blurb module for imageIcon.innerContent__alt attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of imageIconAlt deprecated attribute mappings.
	 */
	private static function _get_image_icon_alt_definition() {
		return [
			'imageIcon.innerContent__alt' => [
				'attrName' => 'imageIcon.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'alt',
			],
		];
	}

	/**
	 * Get imageAlt deprecated attribute definition.
	 *
	 * Used by modules with image elements for image.innerContent__alt attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of imageAlt deprecated attribute mappings.
	 */
	private static function _get_image_alt_definition() {
		return [
			'image.innerContent__alt' => [
				'attrName' => 'image.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'alt',
			],
		];
	}

	/**
	 * Get imageTitleText deprecated attribute definition.
	 *
	 * Used by modules with image elements for image.innerContent__titleText attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of imageTitleText deprecated attribute mappings.
	 */
	private static function _get_image_title_text_definition() {
		return [
			'image.innerContent__titleText' => [
				'attrName' => 'image.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'titleText',
			],
		];
	}

	/**
	 * Get logoAlt deprecated attribute definition.
	 *
	 * Used by modules with logo elements for logo.innerContent__alt attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of logoAlt deprecated attribute mappings.
	 */
	private static function _get_logo_alt_definition() {
		return [
			'logo.innerContent__alt' => [
				'attrName' => 'logo.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'alt',
			],
		];
	}

	/**
	 * Get formLayout deprecated attribute definition.
	 *
	 * Used by Signup module for module.advanced.layout attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of formLayout deprecated attribute mappings.
	 */
	private static function _get_form_layout_definition() {
		return [
			'module.advanced.layout' => [
				'attrName' => 'module.advanced.layout',
				'preset'   => [ 'html' ],
			],
		];
	}

	/**
	 * Get portfolioLayout deprecated attribute definition.
	 *
	 * Used by Portfolio and Filterable Portfolio modules for portfolio.advanced.layout attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of portfolioLayout deprecated attribute mappings.
	 */
	private static function _get_portfolio_layout_definition() {
		return [
			'portfolio.advanced.layout' => [
				'attrName' => 'portfolio.advanced.layout',
				'preset'   => [ 'html' ],
			],
		];
	}

	/**
	 * Get blogLayout deprecated attribute definition.
	 *
	 * Used by Blog module for fullwidth.advanced.enable attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of blogLayout deprecated attribute mappings.
	 */
	private static function _get_blog_layout_definition() {
		return [
			'fullwidth.advanced.enable' => [
				'attrName' => 'fullwidth.advanced.enable',
				'preset'   => [ 'html' ],
			],
		];
	}

	/**
	 * Get portfolioGridFlexType deprecated attribute definition.
	 *
	 * Used by Portfolio and Filterable Portfolio modules for portfolioGrid.advanced.flexType attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of portfolioGridFlexType deprecated attribute mappings.
	 */
	private static function _get_portfolio_grid_flex_type_definition() {
		return [
			'portfolioGrid.advanced.flexType' => [
				'attrName' => 'portfolioGrid.advanced.flexType',
				'preset'   => [ 'html' ],
			],
		];
	}

	/**
	 * Get blogGridFlexType deprecated attribute definition.
	 *
	 * Used by Blog module for blogGrid.advanced.flexType attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of blogGridFlexType deprecated attribute mappings.
	 */
	private static function _get_blog_grid_flex_type_definition() {
		return [
			'blogGrid.advanced.flexType' => [
				'attrName' => 'blogGrid.advanced.flexType',
				'preset'   => [ 'html' ],
			],
		];
	}

	/**
	 * Get galleryGridFlexType deprecated attribute definition.
	 *
	 * Used by Gallery module for galleryGrid.advanced.flexType attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of galleryGridFlexType deprecated attribute mappings.
	 */
	private static function _get_gallery_grid_flex_type_definition() {
		return [
			'galleryGrid.advanced.flexType' => [
				'attrName' => 'galleryGrid.advanced.flexType',
				'preset'   => [ 'html' ],
			],
		];
	}

	/**
	 * Get contactFieldFullwidth deprecated attribute definition.
	 *
	 * Used by Contact Field module for fieldItem.advanced.fullwidth attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of contactFieldFullwidth deprecated attribute mappings.
	 */
	private static function _get_contact_field_fullwidth_definition() {
		return [
			'fieldItem.advanced.fullwidth' => [
				'attrName' => 'fieldItem.advanced.fullwidth',
				'preset'   => [ 'html' ],
			],
		];
	}

	/**
	 * Get focusFieldLegacyColors deprecated attribute definition.
	 *
	 * Used by modules with form fields for legacy focus color keys that are
	 * migrated to decoration focus states.
	 *
	 * @since ??
	 *
	 * @return array Array of focusFieldLegacyColors deprecated attribute mappings.
	 */
	private static function _get_focus_field_legacy_colors_definition() {
		return [
			'field.advanced.focus.background__color'       => [
				'attrName' => 'field.advanced.focus.background',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'field.advanced.focus.background__backgroundColor' => [
				'attrName' => 'field.advanced.focus.background',
				'preset'   => [ 'style' ],
				'subName'  => 'backgroundColor',
			],
			'field.advanced.focus.font.font__color'        => [
				'attrName' => 'field.advanced.focus.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'field.advanced.focus.font.font__textColor'    => [
				'attrName' => 'field.advanced.focus.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textColor',
			],
			'field.decoration.background__color'           => [
				'attrName' => 'field.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'field.decoration.background__backgroundColor' => [
				'attrName' => 'field.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'backgroundColor',
			],
		];
	}

	/**
	 * Get focusFieldLegacyBorder deprecated attribute definition.
	 *
	 * Used by modules with form fields for legacy focus border keys and
	 * use-focus-border toggle that are migrated to decoration focus states.
	 *
	 * @since ??
	 *
	 * @return array Array of focusFieldLegacyBorder deprecated attribute mappings.
	 */
	private static function _get_focus_field_legacy_border_definition() {
		return [
			'field.advanced.focusUseBorder'       => [
				'attrName' => 'field.advanced.focusUseBorder',
				'preset'   => [ 'style' ],
			],
			'field.advanced.focus.border__radius' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'radius',
			],
			'field.advanced.focus.border__styles' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles',
			],
			'field.advanced.focus.border__styles.all.width' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.width',
			],
			'field.advanced.focus.border__styles.top.width' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.width',
			],
			'field.advanced.focus.border__styles.right.width' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.width',
			],
			'field.advanced.focus.border__styles.bottom.width' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.width',
			],
			'field.advanced.focus.border__styles.left.width' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.width',
			],
			'field.advanced.focus.border__styles.all.color' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.color',
			],
			'field.advanced.focus.border__styles.top.color' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.color',
			],
			'field.advanced.focus.border__styles.right.color' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.color',
			],
			'field.advanced.focus.border__styles.bottom.color' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.color',
			],
			'field.advanced.focus.border__styles.left.color' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.color',
			],
			'field.advanced.focus.border__styles.all.style' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.style',
			],
			'field.advanced.focus.border__styles.top.style' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.style',
			],
			'field.advanced.focus.border__styles.right.style' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.style',
			],
			'field.advanced.focus.border__styles.bottom.style' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.style',
			],
			'field.advanced.focus.border__styles.left.style' => [
				'attrName' => 'field.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.style',
			],
		];
	}

	/**
	 * Get fieldLegacyPlaceholderFont deprecated attribute definition.
	 *
	 * Used by modules with form fields for legacy placeholder font keys that are
	 * migrated to decoration placeholder-font state.
	 *
	 * @since ??
	 *
	 * @return array Array of fieldLegacyPlaceholderFont deprecated attribute mappings.
	 */
	private static function _get_field_legacy_placeholder_font_definition() {
		return [
			'field.advanced.placeholder.font.font__color' => [
				'attrName' => 'field.advanced.placeholder.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'field.advanced.placeholder.font.font__textColor' => [
				'attrName' => 'field.advanced.placeholder.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textColor',
			],
			'field.advanced.placeholder.font.font__size'  => [
				'attrName' => 'field.advanced.placeholder.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'field.advanced.placeholder.font.font__letterSpacing' => [
				'attrName' => 'field.advanced.placeholder.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'field.advanced.placeholder.font.font__lineHeight' => [
				'attrName' => 'field.advanced.placeholder.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
		];
	}

	/**
	 * Get fieldLegacyFieldLabelsFont deprecated attribute definition.
	 *
	 * Used by modules with legacy fieldLabels font attrs that are migrated to
	 * field.decoration.labelFont.
	 *
	 * @since ??
	 *
	 * @return array Array of fieldLegacyFieldLabelsFont deprecated attribute mappings.
	 */
	private static function _get_field_legacy_field_labels_font_definition() {
		return [
			'fieldLabels.decoration.font' => [
				'attrName' => 'fieldLabels.decoration.font',
				'preset'   => [ 'style' ],
			],
		];
	}

	/**
	 * Get fieldLegacyRequiredFieldIndicatorColor deprecated attribute definition.
	 *
	 * Used by Woo modules where required indicator color moved from fieldLabels to
	 * field.advanced.requiredFieldIndicatorColor.
	 *
	 * @since ??
	 *
	 * @return array Array of fieldLegacyRequiredFieldIndicatorColor deprecated attribute mappings.
	 */
	private static function _get_field_legacy_required_field_indicator_color_definition() {
		return [
			'fieldLabels.advanced.requiredFieldIndicatorColor' => [
				'attrName' => 'fieldLabels.advanced.requiredFieldIndicatorColor',
				'preset'   => [ 'style' ],
			],
		];
	}

	/**
	 * Get focusDropdownMenusLegacyBorder deprecated attribute definition.
	 *
	 * Used by modules with dropdown menus for legacy focus border keys and
	 * use-focus-border toggle that are migrated to decoration focus states.
	 *
	 * @since ??
	 *
	 * @return array Array of focusDropdownMenusLegacyBorder deprecated attribute mappings.
	 */
	private static function _get_focus_dropdown_menus_legacy_border_definition() {
		return [
			'dropdownMenus.advanced.focusUseBorder'       => [
				'attrName' => 'dropdownMenus.advanced.focusUseBorder',
				'preset'   => [ 'style' ],
			],
			'dropdownMenus.advanced.focus.border__radius' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'radius',
			],
			'dropdownMenus.advanced.focus.border__styles' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles',
			],
			'dropdownMenus.advanced.focus.border__styles.all.width' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.width',
			],
			'dropdownMenus.advanced.focus.border__styles.top.width' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.width',
			],
			'dropdownMenus.advanced.focus.border__styles.right.width' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.width',
			],
			'dropdownMenus.advanced.focus.border__styles.bottom.width' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.width',
			],
			'dropdownMenus.advanced.focus.border__styles.left.width' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.width',
			],
			'dropdownMenus.advanced.focus.border__styles.all.color' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.color',
			],
			'dropdownMenus.advanced.focus.border__styles.top.color' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.color',
			],
			'dropdownMenus.advanced.focus.border__styles.right.color' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.color',
			],
			'dropdownMenus.advanced.focus.border__styles.bottom.color' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.color',
			],
			'dropdownMenus.advanced.focus.border__styles.left.color' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.color',
			],
			'dropdownMenus.advanced.focus.border__styles.all.style' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.style',
			],
			'dropdownMenus.advanced.focus.border__styles.top.style' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.style',
			],
			'dropdownMenus.advanced.focus.border__styles.right.style' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.style',
			],
			'dropdownMenus.advanced.focus.border__styles.bottom.style' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.style',
			],
			'dropdownMenus.advanced.focus.border__styles.left.style' => [
				'attrName' => 'dropdownMenus.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.style',
			],
		];
	}

	/**
	 * Get focusTooltipLegacyBorder deprecated attribute definition.
	 *
	 * Used by modules with tooltip fields for legacy focus border keys and
	 * use-focus-border toggle that are migrated to decoration focus states.
	 *
	 * @since ??
	 *
	 * @return array Array of focusTooltipLegacyBorder deprecated attribute mappings.
	 */
	private static function _get_focus_tooltip_legacy_border_definition() {
		return [
			'tooltip.advanced.focusUseBorder'       => [
				'attrName' => 'tooltip.advanced.focusUseBorder',
				'preset'   => [ 'style' ],
			],
			'tooltip.advanced.focus.border__radius' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'radius',
			],
			'tooltip.advanced.focus.border__styles' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles',
			],
			'tooltip.advanced.focus.border__styles.all.width' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.width',
			],
			'tooltip.advanced.focus.border__styles.top.width' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.width',
			],
			'tooltip.advanced.focus.border__styles.right.width' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.width',
			],
			'tooltip.advanced.focus.border__styles.bottom.width' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.width',
			],
			'tooltip.advanced.focus.border__styles.left.width' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.width',
			],
			'tooltip.advanced.focus.border__styles.all.color' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.color',
			],
			'tooltip.advanced.focus.border__styles.top.color' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.color',
			],
			'tooltip.advanced.focus.border__styles.right.color' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.color',
			],
			'tooltip.advanced.focus.border__styles.bottom.color' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.color',
			],
			'tooltip.advanced.focus.border__styles.left.color' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.color',
			],
			'tooltip.advanced.focus.border__styles.all.style' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.style',
			],
			'tooltip.advanced.focus.border__styles.top.style' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.style',
			],
			'tooltip.advanced.focus.border__styles.right.style' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.style',
			],
			'tooltip.advanced.focus.border__styles.bottom.style' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.style',
			],
			'tooltip.advanced.focus.border__styles.left.style' => [
				'attrName' => 'tooltip.advanced.focus.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.style',
			],
		];
	}

	/**
	 * Get focusDropdownMenusLegacyColors deprecated attribute definition.
	 *
	 * Used by modules with dropdown menus for legacy focus color keys that are
	 * migrated to decoration focus states.
	 *
	 * @since ??
	 *
	 * @return array Array of focusDropdownMenusLegacyColors deprecated attribute mappings.
	 */
	private static function _get_focus_dropdown_menus_legacy_colors_definition() {
		return [
			'dropdownMenus.advanced.focus.background__color' => [
				'attrName' => 'dropdownMenus.advanced.focus.background',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'dropdownMenus.advanced.focus.background__backgroundColor' => [
				'attrName' => 'dropdownMenus.advanced.focus.background',
				'preset'   => [ 'style' ],
				'subName'  => 'backgroundColor',
			],
			'dropdownMenus.advanced.focus.font.font__color' => [
				'attrName' => 'dropdownMenus.advanced.focus.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'dropdownMenus.advanced.focus.font.font__textColor' => [
				'attrName' => 'dropdownMenus.advanced.focus.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textColor',
			],
		];
	}

	/**
	 * Get buttonRel deprecated attribute definition.
	 *
	 * Used by Button, CTA, and other modules for button.innerContent__rel attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of buttonRel deprecated attribute mappings.
	 */
	private static function _get_button_rel_definition() {
		return [
			'button.innerContent__rel' => [
				'attrName' => 'button.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'rel',
			],
		];
	}

	/**
	 * Get buttonEnable deprecated attribute definition.
	 *
	 * Preserves legacy custom button toggle for preset conversion.
	 *
	 * @since ??
	 *
	 * @return array Array of buttonEnable deprecated attribute mappings.
	 */
	private static function _get_button_enable_definition() {
		return [
			'button.decoration.button__enable' => [
				'attrName' => 'button.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'enable',
			],
		];
	}

	/**
	 * Get buttonAlignment deprecated attribute definition.
	 *
	 * Preserves legacy button alignment before composible migration moves it.
	 *
	 * @since ??
	 *
	 * @return array Array of buttonAlignment deprecated attribute mappings.
	 */
	private static function _get_button_alignment_definition() {
		return [
			'button.decoration.button__alignment' => [
				'attrName' => 'button.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'alignment',
			],
		];
	}

	/**
	 * Get iconTitle deprecated attribute definition.
	 *
	 * Used by Icon module for icon.innerContent__title attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of iconTitle deprecated attribute mappings.
	 */
	private static function _get_icon_title_definition() {
		return [
			'icon.innerContent__title' => [
				'attrName' => 'icon.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'title',
			],
		];
	}

	/**
	 * Get buttonOneRel deprecated attribute definition.
	 *
	 * Used by Fullwidth Header module for buttonOne.innerContent__rel attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of buttonOneRel deprecated attribute mappings.
	 */
	private static function _get_button_one_rel_definition() {
		return [
			'buttonOne.innerContent__rel' => [
				'attrName' => 'buttonOne.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'rel',
			],
		];
	}

	/**
	 * Get buttonOneEnable deprecated attribute definition.
	 *
	 * Preserves Fullwidth Header legacy button one custom style toggle.
	 *
	 * @since ??
	 *
	 * @return array Array of buttonOneEnable deprecated attribute mappings.
	 */
	private static function _get_button_one_enable_definition() {
		return [
			'buttonOne.decoration.button__enable' => [
				'attrName' => 'buttonOne.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'enable',
			],
		];
	}

	/**
	 * Get buttonTwoRel deprecated attribute definition.
	 *
	 * Used by Fullwidth Header module for buttonTwo.innerContent__rel attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of buttonTwoRel deprecated attribute mappings.
	 */
	private static function _get_button_two_rel_definition() {
		return [
			'buttonTwo.innerContent__rel' => [
				'attrName' => 'buttonTwo.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'rel',
			],
		];
	}

	/**
	 * Get buttonTwoEnable deprecated attribute definition.
	 *
	 * Preserves Fullwidth Header legacy button two custom style toggle.
	 *
	 * @since ??
	 *
	 * @return array Array of buttonTwoEnable deprecated attribute mappings.
	 */
	private static function _get_button_two_enable_definition() {
		return [
			'buttonTwo.decoration.button__enable' => [
				'attrName' => 'buttonTwo.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'enable',
			],
		];
	}

	/**
	 * Get imageTitle deprecated attribute definition.
	 *
	 * Used by Fullwidth Header module for image.innerContent__title attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of imageTitle deprecated attribute mappings.
	 */
	private static function _get_image_title_definition() {
		return [
			'image.innerContent__title' => [
				'attrName' => 'image.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'title',
			],
		];
	}

	/**
	 * Get logoTitle deprecated attribute definition.
	 *
	 * Used by Fullwidth Header module for logo.innerContent__title attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of logoTitle deprecated attribute mappings.
	 */
	private static function _get_logo_title_definition() {
		return [
			'logo.innerContent__title' => [
				'attrName' => 'logo.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'title',
			],
		];
	}

	/**
	 * Get childrenButtonRel deprecated attribute definition.
	 *
	 * Used by Slider and Pricing Tables modules for children button rel attributes.
	 *
	 * @since ??
	 *
	 * @return array Array of childrenButtonRel deprecated attribute mappings.
	 */
	private static function _get_children_button_rel_definition() {
		return [
			'children.button.innerContent__rel' => [
				'attrName' => 'children.button.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'rel',
			],
		];
	}

	/**
	 * Get childrenButtonEnable deprecated attribute definition.
	 *
	 * Preserves legacy children button custom style toggle for slider-like modules.
	 *
	 * @since ??
	 *
	 * @return array Array of childrenButtonEnable deprecated attribute mappings.
	 */
	private static function _get_children_button_enable_definition() {
		return [
			'children.button.decoration.button__enable' => [
				'attrName' => 'children.button.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'enable',
			],
		];
	}

	/**
	 * Get imageRel deprecated attribute definition.
	 *
	 * Used by Image module for image.innerContent__rel attribute.
	 *
	 * @since ??
	 *
	 * @return array Array of imageRel deprecated attribute mappings.
	 */
	private static function _get_image_rel_definition() {
		return [
			'image.innerContent__rel' => [
				'attrName' => 'image.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'rel',
			],
		];
	}

	/**
	 * Get image force fullwidth deprecated attribute definition.
	 *
	 * Preserves legacy Woo Product Images fullwidth toggle for migration.
	 *
	 * @since ??
	 *
	 * @return array Array of imageForceFullwidth deprecated attribute mappings.
	 */
	private static function _get_image_force_fullwidth_definition() {
		return [
			'image.advanced.forceFullwidth' => [
				'attrName' => 'image.advanced.forceFullwidth',
				'preset'   => [ 'style' ],
			],
		];
	}

	/**
	 * Get Post Title featured image sizing deprecated attribute definition.
	 *
	 * Preserves legacy Post Title featured image sizing and fullwidth attrs for migration.
	 *
	 * @since ??
	 *
	 * @return array Array of postTitleFeaturedImageSizing deprecated attribute mappings.
	 */
	private static function _get_post_title_featured_image_sizing_definition() {
		return [
			'featuredImage.advanced.forceFullwidth'      => [
				'attrName' => 'featuredImage.advanced.forceFullwidth',
				'preset'   => [ 'style' ],
			],
			'featuredImage.decoration.sizing__width'     => [
				'attrName' => 'image.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'width',
			],
			'featuredImage.decoration.sizing__maxWidth'  => [
				'attrName' => 'image.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'maxWidth',
			],
			'featuredImage.decoration.sizing__height'    => [
				'attrName' => 'image.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'height',
			],
			'featuredImage.decoration.sizing__maxHeight' => [
				'attrName' => 'image.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'maxHeight',
			],
			'featuredImage.decoration.sizing__alignment' => [
				'attrName' => 'image.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'alignment',
			],
		];
	}

	/**
	 * Get imageIconAnimation deprecated attribute definition.
	 *
	 * Preserves legacy Blurb image/icon animation before composible migration.
	 *
	 * @since ??
	 *
	 * @return array Array of imageIconAnimation deprecated attribute mappings.
	 */
	private static function _get_image_icon_animation_definition() {
		return [
			'imageIcon.innerContent__animation' => [
				'attrName' => 'imageIcon.innerContent',
				'preset'   => [ 'style' ],
				'subName'  => 'animation',
			],
		];
	}

	/**
	 * Get imageIconWidth deprecated attribute definition.
	 *
	 * Preserves legacy Blurb image/icon width before composible migration.
	 *
	 * @since ??
	 *
	 * @return array Array of imageIconWidth deprecated attribute mappings.
	 */
	private static function _get_image_icon_width_definition() {
		return [
			'imageIcon.advanced.width__image' => [
				'attrName' => 'imageIcon.advanced.width',
				'preset'   => [ 'style' ],
				'subName'  => 'image',
			],
			'imageIcon.advanced.width__icon'  => [
				'attrName' => 'imageIcon.advanced.width',
				'preset'   => [ 'style' ],
				'subName'  => 'icon',
			],
		];
	}

	/**
	 * Get imageIconAlignment deprecated attribute definition.
	 *
	 * Preserves legacy Blurb image/icon alignment before composible migration.
	 *
	 * @since ??
	 *
	 * @return array Array of imageIconAlignment deprecated attribute mappings.
	 */
	private static function _get_image_icon_alignment_definition() {
		return [
			'imageIcon.advanced.alignment' => [
				'attrName' => 'imageIcon.advanced.alignment',
				'preset'   => [ 'style' ],
			],
		];
	}

	/**
	 * Get imageIconBackground deprecated attribute definition.
	 *
	 * Preserves legacy Blurb image/icon background value shape before composible migration.
	 *
	 * @since ??
	 *
	 * @return array Array of imageIconBackground deprecated attribute mappings.
	 */
	private static function _get_image_icon_background_definition() {
		return [
			'imageIcon.decoration.background' => [
				'attrName' => 'imageIcon.decoration.background',
				'preset'   => [ 'style' ],
			],
		];
	}

	/**
	 * Get inactiveTabBackground deprecated attribute definition.
	 *
	 * Preserves legacy Tabs inactiveTab background before composible migration.
	 *
	 * @since ??
	 *
	 * @return array Array of inactiveTabBackground deprecated attribute mappings.
	 */
	private static function _get_inactive_tab_background_definition() {
		return [
			'inactiveTab.decoration.background' => [
				'attrName' => 'inactiveTab.decoration.background',
				'preset'   => [ 'style' ],
			],
		];
	}
}
