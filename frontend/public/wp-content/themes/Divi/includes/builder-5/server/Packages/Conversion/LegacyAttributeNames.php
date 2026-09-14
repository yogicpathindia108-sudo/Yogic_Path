<?php
/**
 * Legacy Attribute Names Handler
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Conversion;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET_Builder_Module_Settings_Migration;

/**
 * Handles Legacy Attribute Names
 *
 * @since ??
 */
class LegacyAttributeNames {

	/**
	 * Option name for storing legacy attribute names
	 */
	const OPTION_NAME = 'et_divi_legacy_attribute_names';

	/**
	 * Legacy attribute names
	 *
	 * @var array
	 */
	public static $attributes = [
		'background_url',
		'bb_built',
		'fb_built',
		'max_width',
		'title_font_color',
		'content_font_color',
		'subhead_font_color',
		'button_one_text_size_hover',
		'button_two_text_size_hover',
		'button_one_text_color_hover',
		'button_two_text_color_hover',
		'button_one_border_width_hover',
		'button_two_border_width_hover',
		'button_one_border_color_hover',
		'button_two_border_color_hover',
		'button_one_border_radius_hover',
		'button_two_border_radius_hover',
		'button_one_letter_spacing_hover',
		'button_two_letter_spacing_hover',
		'button_one_bg_color_hover',
		'button_two_bg_color_hover',
		'animation',
		'sticky',
		'use_border_color',
		'border_color',
		'border_width',
		'border_style',
		'always_center_on_mobile',
		'transparent_background',
		'transparent_background_fb',
		'padding',
		'padding_bottom',
		'padding_left',
		'padding_right',
		'padding_top',
		'padding_mobile',
		'make_fullwidth',
		'use_custom_width',
		'width_unit',
		'custom_width_px',
		'custom_width_px__hover',
		'custom_width_px__hover_enabled',
		'custom_width_percent',
		'custom_width_percent__hover',
		'custom_width_percent__hover_enabled',
		'parallax_effect',
		'module_bg_color',
		'icon_placement',
		'image_max_width',
		'icon_font_size',
		'icon_placement_tablet',
		'image_max_width_tablet',
		'icon_font_size_tablet',
		'icon_placement_phone',
		'image_max_width_phone',
		'icon_font_size_phone',
		'image_max_width__hover',
		'icon_font_size__hover',
		'image_max_width__hover_enabled',
		'icon_font_size__hover_enabled',
		'icon_placement_last_edited',
		'image_max_width_last_edited',
		'icon_font_size_last_edited',
		'header_2_2_font_size',
		'header_2_2_font_size_last_edited',
		'header_3_2_font_size',
		'header_3_2_font_size_last_edited',
		'header_4_2_font_size',
		'header_4_2_font_size_last_edited',
		'header_5_2_font_size',
		'header_5_2_font_size_last_edited',
		'header_6_2_font_size',
		'header_6_2_font_size_last_edited',
		'header_2_3_font_size',
		'header_2_3_font_size_last_edited',
		'header_2_3_font_size_tablet',
		'header_2_3_font_size_phone',
		'header_3_3_font_size',
		'header_3_3_font_size_last_edited',
		'header_3_3_font_size_tablet',
		'header_3_3_font_size_phone',
		'header_4_3_font_size',
		'header_4_3_font_size_last_edited',
		'header_4_3_font_size_tablet',
		'header_4_3_font_size_phone',
		'header_5_3_font_size',
		'header_5_3_font_size_last_edited',
		'header_5_3_font_size_tablet',
		'header_5_3_font_size_phone',
		'header_6_3_font_size',
		'header_6_3_font_size_last_edited',
		'header_6_3_font_size_tablet',
		'header_6_3_font_size_phone',

		// Issue #48687 — historical double-index header keys (mirror `header_*_*_font_size` matrix). Corrupt names such as `null__hover` are discarded earlier in `Conversion::getAttrMap()`; see GitHub #48821.
		'header_2_2_text_color',
		'header_2_2_text_color_last_edited',
		'header_3_2_text_color',
		'header_3_2_text_color_last_edited',
		'header_4_2_text_color',
		'header_4_2_text_color_last_edited',
		'header_5_2_text_color',
		'header_5_2_text_color_last_edited',
		'header_6_2_text_color',
		'header_6_2_text_color_last_edited',
		'header_2_3_text_color',
		'header_2_3_text_color_last_edited',
		'header_2_3_text_color_tablet',
		'header_2_3_text_color_phone',
		'header_3_3_text_color',
		'header_3_3_text_color_last_edited',
		'header_3_3_text_color_tablet',
		'header_3_3_text_color_phone',
		'header_4_3_text_color',
		'header_4_3_text_color_last_edited',
		'header_4_3_text_color_tablet',
		'header_4_3_text_color_phone',
		'header_5_3_text_color',
		'header_5_3_text_color_last_edited',
		'header_5_3_text_color_tablet',
		'header_5_3_text_color_phone',
		'header_6_3_text_color',
		'header_6_3_text_color_last_edited',
		'header_6_3_text_color_tablet',
		'header_6_3_text_color_phone',
		'header_2_2_line_height',
		'header_2_2_line_height_last_edited',
		'header_3_2_line_height',
		'header_3_2_line_height_last_edited',
		'header_4_2_line_height',
		'header_4_2_line_height_last_edited',
		'header_5_2_line_height',
		'header_5_2_line_height_last_edited',
		'header_6_2_line_height',
		'header_6_2_line_height_last_edited',
		'header_2_3_line_height',
		'header_2_3_line_height_last_edited',
		'header_2_3_line_height_tablet',
		'header_2_3_line_height_phone',
		'header_3_3_line_height',
		'header_3_3_line_height_last_edited',
		'header_3_3_line_height_tablet',
		'header_3_3_line_height_phone',
		'header_4_3_line_height',
		'header_4_3_line_height_last_edited',
		'header_4_3_line_height_tablet',
		'header_4_3_line_height_phone',
		'header_5_3_line_height',
		'header_5_3_line_height_last_edited',
		'header_5_3_line_height_tablet',
		'header_5_3_line_height_phone',
		'header_6_3_line_height',
		'header_6_3_line_height_last_edited',
		'header_6_3_line_height_tablet',
		'header_6_3_line_height_phone',
		'header_2_4_text_color',
		'header_2_4_text_color_last_edited',
		'header_2_4_text_color__hover',
		'header_2_4_text_color__hover_enabled',
		'header_3_4_text_color',
		'header_3_4_text_color_last_edited',
		'header_3_4_text_color__hover',
		'header_3_4_text_color__hover_enabled',
		'header_4_4_text_color',
		'header_4_4_text_color_last_edited',
		'header_4_4_text_color__hover',
		'header_4_4_text_color__hover_enabled',
		'header_5_4_text_color',
		'header_5_4_text_color_last_edited',
		'header_5_4_text_color__hover',
		'header_5_4_text_color__hover_enabled',
		'header_6_4_text_color',
		'header_6_4_text_color_last_edited',
		'header_6_4_text_color__hover',
		'header_6_4_text_color__hover_enabled',
		'image_max_width__sticky_enabled',
		'icon_font_size__sticky_enabled',
		'image_max_width__sticky',
		'icon_font_size__sticky',
		'use_circle',
		'use_circle_border',
		'circle_border_color',
		'circle_border_color_tablet',
		'circle_border_color_phone',
		'circle_border_color__hover',
		'circle_border_color__hover_enabled',
		'circle_border_color_last_edited',
		'circle_border_color__sticky_enabled',
		'circle_border_color__sticky',
		'circle_color',
		'circle_color_tablet',
		'circle_color_phone',
		'circle_color__hover',
		'circle_color__hover_enabled',
		'circle_color_last_edited',
		'circle_color__sticky_enabled',
		'circle_color__sticky',
		'text_orientation',
		'text_text_align',
		'field_bg',
		'hide_button',
		'button_text_size_hover',
		'button_text_color_hover',
		'button_border_width_hover',
		'button_border_color_hover',
		'button_border_radius_hover',
		'button_letter_spacing_hover',
		'button_bg_color_hover',
		'input_text_color',
		'input_text_color__hover_enabled',
		'input_text_color__hover',
		'input_font',
		'input_text_align',
		'input_font_size',
		'input_font_size_last_edited',
		'input_font_size_tablet',
		'input_font_size_phone',
		'input_font_size__hover_enabled',
		'input_font_size__hover',
		'input_letter_spacing',
		'input_letter_spacing_last_edited',
		'input_letter_spacing_tablet',
		'input_letter_spacing_phone',
		'input_letter_spacing__hover_enabled',
		'input_letter_spacing__hover',
		'input_line_height',
		'input_line_height_last_edited',
		'input_line_height_tablet',
		'input_line_height_phone',
		'input_line_height__hover_enabled',
		'input_line_height__hover',
		'input_text_shadow_horizontal_length',
		'input_text_shadow_horizontal_length__hover_enabled',
		'input_text_shadow_horizontal_length__hover',
		'input_text_shadow_vertical_length',
		'input_text_shadow_vertical_length__hover_enabled',
		'input_text_shadow_vertical_length__hover',
		'input_text_shadow_blur_strength',
		'input_text_shadow_blur_strength__hover_enabled',
		'input_text_shadow_blur_strength__hover',
		'input_text_shadow_color',
		'input_text_shadow_color__hover_enabled',
		'input_text_shadow_color__hover',
		'input_text_shadow_style',
		'bg_color',
		'bar_top_padding',
		'bar_bottom_padding',
		'bar_top_padding_tablet',
		'bar_bottom_padding_tablet',
		'bar_top_padding_phone',
		'bar_bottom_padding_phone',
		'bar_top_padding_last_edited',
		'bar_bottom_padding_last_edited',
		'border_radius',
		'label_color',
		'percentage_color',
		'top_padding',
		'bottom_padding',
		'top_padding_tablet',
		'bottom_padding_tablet',
		'top_padding_phone',
		'bottom_padding_phone',
		'top_padding_last_edited',
		'bottom_padding_last_edited',
		'remove_inner_shadow',
		'hide_content_on_mobile',
		'hide_cta_on_mobile',
		'box_shadow_style',
		'show_inner_shadow',
		'video_bg_mp4',
		'video_bg_webm',
		'video_bg_width',
		'video_bg_height',
		'hide_on_mobile',
		'hide_prev',
		'hide_next',
		'remove_featured_drop_shadow',
		'center_list_items',
		'remove_border',
		'use_dropshadow',
		'input_border_radius',
		'form_background_color',
		'form_background_color__hover_enabled',
		'form_background_color__hover',
		'field_background_color',
		'field_background_color__hover_enabled',
		'field_background_color__hover',
		'use_focus_border_color',
		'focus_border_color',
		'content',
		'focus_background_color',
		'focus_text_color',
		'fields_text_shadow_horizontal_length',
		'fields_text_shadow_horizontal_length__hover_enabled',
		'fields_text_shadow_horizontal_length__hover',
		'fields_text_shadow_vertical_length',
		'fields_text_shadow_vertical_length__hover_enabled',
		'fields_text_shadow_vertical_length__hover',
		'fields_text_shadow_blur_strength',
		'fields_text_shadow_blur_strength__hover_enabled',
		'fields_text_shadow_blur_strength__hover',
		'fields_text_shadow_color',
		'fields_text_shadow_color__hover_enabled',
		'fields_text_shadow_color__hover',
		'fields_text_shadow_style',
		'focus_background_color__hover_enabled',
		'focus_background_color__hover',
		'focus_text_color__hover_enabled',
		'focus_text_color__hover',
		'icon_hover_color',
		'portrait_border_radius',
		'use_icon_font_size',
		'icon_color',
		'icon_color_tablet',
		'icon_color_phone',
		'icon_color__hover',
		'icon_color__hover_enabled',
		'icon_color_last_edited',
		'link_shape',
		'grayscale_filter_amount',
		'pricing_item_excluded_color',
		'pricing_item_excluded_color__hover_enabled',
		'pricing_item_excluded_color__hover',
		'body_font',
		'body_font_last_edited',
		'body_font_tablet',
		'body_font_phone',
		'body_text_color',
		'body_text_color_last_edited',
		'body_text_color_tablet',
		'body_text_color_phone',
		'body_text_color__hover_enabled',
		'body_text_color__hover',
		'body_quote_border_color',
		'body_quote_border_color_tablet',
		'body_quote_border_color_phone',
		'body_quote_border_color_last_edited',
		'body_quote_border_weight',
		'body_quote_border_weight_tablet',
		'body_quote_border_weight_phone',
		'body_quote_border_weight_last_edited',

		'body_font_size',
		'body_font_size_last_edited',
		'body_font_size_tablet',
		'body_font_size_phone',
		'body_font_size__hover_enabled',
		'body_font_size__hover',
		'body_letter_spacing',
		'body_letter_spacing_last_edited',
		'body_letter_spacing_tablet',
		'body_letter_spacing_phone',
		'body_letter_spacing__hover_enabled',
		'body_letter_spacing__hover',
		'body_line_height',
		'body_line_height_last_edited',
		'body_line_height_tablet',
		'body_line_height_phone',
		'body_line_height__hover_enabled',
		'body_line_height__hover',
		'body_link_text_align',
		'body_text_shadow_style',
		'body_text_shadow_horizontal_length',
		'body_text_shadow_horizontal_length_last_edited',
		'body_text_shadow_horizontal_length_tablet',
		'body_text_shadow_horizontal_length_phone',
		'body_text_shadow_horizontal_length__hover_enabled',
		'body_text_shadow_horizontal_length__hover',
		'body_text_shadow_vertical_length',
		'body_text_shadow_vertical_length_last_edited',
		'body_text_shadow_vertical_length_tablet',
		'body_text_shadow_vertical_length_phone',
		'body_text_shadow_vertical_length__hover_enabled',
		'body_text_shadow_vertical_length__hover',
		'body_text_shadow_blur_strength',
		'body_text_shadow_blur_strength_last_edited',
		'body_text_shadow_blur_strength_tablet',
		'body_text_shadow_blur_strength_phone',
		'body_text_shadow_blur_strength__hover_enabled',
		'body_text_shadow_blur_strength__hover',
		'body_text_shadow_color',
		'body_text_shadow_color_last_edited',
		'body_text_shadow_color_tablet',
		'body_text_shadow_color_phone',
		'body_text_shadow_color__hover_enabled',
		'body_text_shadow_color__hover',
		'body_ul_text_align',
		'body_ul_position',
		'use_background_color',
		'saved_tabs',
		'_unique_id',
		'x',
		'y',
		'transform_styles',
		'form_field_text_align',
		'collapsed',
		'disabled',

		// Button attributes.
		'button_bg_video_pause_outside_viewport',
		'button_bg_enable_video_mp4',
		'button_bg_enable_video_webm',
		'button_bg_allow_player_pause',
		'button_icon_default',

		// Background Gradient Attributes (from BackgroundGradientStops.php and BackgroundGradientOverlaysImage.php).
		'background_color_gradient_start',
		'background_color_gradient_start_position',
		'background_color_gradient_end',
		'background_color_gradient_end_position',
		'use_background_color_gradient',
		'background_color_gradient_overlays_image',
		'background_color_gradient_type',
		'background_color_gradient_direction',
		'background_color_gradient_direction_radial',
		'background_color_gradient_stops',
		'background_color_gradient_unit',

		// Responsive variations of background gradient attributes.
		'background_color_gradient_start_tablet',
		'background_color_gradient_start_phone',
		'background_color_gradient_start__hover',
		'background_color_gradient_start__sticky',
		'background_color_gradient_end_tablet',
		'background_color_gradient_end_phone',
		'background_color_gradient_end__hover',
		'background_color_gradient_end__sticky',
		'background_color_gradient_type_tablet',
		'background_color_gradient_type_phone',
		'background_color_gradient_type__hover',
		'background_color_gradient_type__sticky',
		'background_color_gradient_direction_tablet',
		'background_color_gradient_direction_phone',
		'background_color_gradient_direction__hover',
		'background_color_gradient_direction__sticky',
		'background_color_gradient_direction_radial_tablet',
		'background_color_gradient_direction_radial_phone',
		'background_color_gradient_direction_radial__hover',
		'background_color_gradient_direction_radial__sticky',
		'background_color_gradient_start_position_tablet',
		'background_color_gradient_start_position_phone',
		'background_color_gradient_start_position__hover',
		'background_color_gradient_start_position__sticky',
		'background_color_gradient_end_position_tablet',
		'background_color_gradient_end_position_phone',
		'background_color_gradient_end_position__hover',
		'background_color_gradient_end_position__sticky',
		'use_background_color_gradient_tablet',
		'use_background_color_gradient_phone',
		'use_background_color_gradient__hover',
		'use_background_color_gradient__sticky',
		'background_color_gradient_overlays_image_tablet',
		'background_color_gradient_overlays_image_phone',
		'background_color_gradient_overlays_image__hover',
		'background_color_gradient_overlays_image__sticky',
		'background_color_gradient_stops_tablet',
		'background_color_gradient_stops_phone',
		'background_color_gradient_stops__hover',
		'background_color_gradient_stops__sticky',
		'background_color_gradient_unit_tablet',
		'background_color_gradient_unit_phone',
		'background_color_gradient_unit__hover',
		'background_color_gradient_unit__sticky',

		// Button gradient attributes and variations.
		'button_bg_color_gradient_start',
		'button_bg_color_gradient_start_position',
		'button_bg_color_gradient_end',
		'button_bg_color_gradient_end_position',
		'button_bg_color_gradient_type',
		'button_bg_color_gradient_direction',
		'button_bg_color_gradient_direction_radial',
		'button_bg_color_gradient_stops',
		'button_bg_color_gradient_unit',
		'button_bg_color_gradient_overlays_image',
		'button_bg_color_gradient_start_tablet',
		'button_bg_color_gradient_start_phone',
		'button_bg_color_gradient_start__hover',
		'button_bg_color_gradient_start__sticky',
		'button_bg_color_gradient_end_tablet',
		'button_bg_color_gradient_end_phone',
		'button_bg_color_gradient_end__hover',
		'button_bg_color_gradient_end__sticky',
		'button_bg_color_gradient_type_tablet',
		'button_bg_color_gradient_type_phone',
		'button_bg_color_gradient_type__hover',
		'button_bg_color_gradient_type__sticky',
		'button_bg_color_gradient_direction_tablet',
		'button_bg_color_gradient_direction_phone',
		'button_bg_color_gradient_direction__hover',
		'button_bg_color_gradient_direction__sticky',
		'button_bg_color_gradient_direction_radial_tablet',
		'button_bg_color_gradient_direction_radial_phone',
		'button_bg_color_gradient_direction_radial__hover',
		'button_bg_color_gradient_direction_radial__sticky',
		'button_bg_color_gradient_start_position_tablet',
		'button_bg_color_gradient_start_position_phone',
		'button_bg_color_gradient_start_position__hover',
		'button_bg_color_gradient_start_position__sticky',
		'button_bg_color_gradient_end_position_tablet',
		'button_bg_color_gradient_end_position_phone',
		'button_bg_color_gradient_end_position__hover',
		'button_bg_color_gradient_end_position__sticky',
		'use_button_bg_color_gradient',
		'use_button_bg_color_gradient_tablet',
		'use_button_bg_color_gradient_phone',
		'use_button_bg_color_gradient__hover',
		'use_button_bg_color_gradient__sticky',

		// Button One gradient attributes (for fullwidth header).
		'button_one_bg_color_gradient_start',
		'button_one_bg_color_gradient_start_position',
		'button_one_bg_color_gradient_end',
		'button_one_bg_color_gradient_end_position',
		'button_one_bg_color_gradient_type',
		'button_one_bg_color_gradient_direction',
		'button_one_bg_color_gradient_direction_radial',
		'button_one_bg_color_gradient_stops',
		'button_one_bg_color_gradient_unit',
		'button_one_bg_color_gradient_overlays_image',
		'button_one_bg_color_gradient_start_tablet',
		'button_one_bg_color_gradient_start_phone',
		'button_one_bg_color_gradient_start__hover',
		'button_one_bg_color_gradient_start__sticky',
		'button_one_bg_color_gradient_end_tablet',
		'button_one_bg_color_gradient_end_phone',
		'button_one_bg_color_gradient_end__hover',
		'button_one_bg_color_gradient_end__sticky',
		'button_one_bg_color_gradient_type_tablet',
		'button_one_bg_color_gradient_type_phone',
		'button_one_bg_color_gradient_type__hover',
		'button_one_bg_color_gradient_type__sticky',
		'button_one_bg_color_gradient_direction_tablet',
		'button_one_bg_color_gradient_direction_phone',
		'button_one_bg_color_gradient_direction__hover',
		'button_one_bg_color_gradient_direction__sticky',
		'button_one_bg_color_gradient_direction_radial_tablet',
		'button_one_bg_color_gradient_direction_radial_phone',
		'button_one_bg_color_gradient_direction_radial__hover',
		'button_one_bg_color_gradient_direction_radial__sticky',
		'button_one_bg_color_gradient_start_position_tablet',
		'button_one_bg_color_gradient_start_position_phone',
		'button_one_bg_color_gradient_start_position__hover',
		'button_one_bg_color_gradient_start_position__sticky',
		'button_one_bg_color_gradient_end_position_tablet',
		'button_one_bg_color_gradient_end_position_phone',
		'button_one_bg_color_gradient_end_position__hover',
		'button_one_bg_color_gradient_end_position__sticky',
		'use_button_one_bg_color_gradient',
		'use_button_one_bg_color_gradient_tablet',
		'use_button_one_bg_color_gradient_phone',
		'use_button_one_bg_color_gradient__hover',
		'use_button_one_bg_color_gradient__sticky',

		// Button Two gradient attributes (for fullwidth header).
		'button_two_bg_color_gradient_start',
		'button_two_bg_color_gradient_start_position',
		'button_two_bg_color_gradient_end',
		'button_two_bg_color_gradient_end_position',
		'button_two_bg_color_gradient_type',
		'button_two_bg_color_gradient_direction',
		'button_two_bg_color_gradient_direction_radial',
		'button_two_bg_color_gradient_stops',
		'button_two_bg_color_gradient_unit',
		'button_two_bg_color_gradient_overlays_image',
		'button_two_bg_color_gradient_start_tablet',
		'button_two_bg_color_gradient_start_phone',
		'button_two_bg_color_gradient_start__hover',
		'button_two_bg_color_gradient_start__sticky',
		'button_two_bg_color_gradient_end_tablet',
		'button_two_bg_color_gradient_end_phone',
		'button_two_bg_color_gradient_end__hover',
		'button_two_bg_color_gradient_end__sticky',
		'button_two_bg_color_gradient_type_tablet',
		'button_two_bg_color_gradient_type_phone',
		'button_two_bg_color_gradient_type__hover',
		'button_two_bg_color_gradient_type__sticky',
		'button_two_bg_color_gradient_direction_tablet',
		'button_two_bg_color_gradient_direction_phone',
		'button_two_bg_color_gradient_direction__hover',
		'button_two_bg_color_gradient_direction__sticky',
		'button_two_bg_color_gradient_direction_radial_tablet',
		'button_two_bg_color_gradient_direction_radial_phone',
		'button_two_bg_color_gradient_direction_radial__hover',
		'button_two_bg_color_gradient_direction_radial__sticky',
		'button_two_bg_color_gradient_start_position_tablet',
		'button_two_bg_color_gradient_start_position_phone',
		'button_two_bg_color_gradient_start_position__hover',
		'button_two_bg_color_gradient_start_position__sticky',
		'button_two_bg_color_gradient_end_position_tablet',
		'button_two_bg_color_gradient_end_position_phone',
		'button_two_bg_color_gradient_end_position__hover',
		'button_two_bg_color_gradient_end_position__sticky',
		'use_button_two_bg_color_gradient',
		'use_button_two_bg_color_gradient_tablet',
		'use_button_two_bg_color_gradient_phone',
		'use_button_two_bg_color_gradient__hover',
		'use_button_two_bg_color_gradient__sticky',

		// Background color gradient with _1, _2, _3 suffixes.
		'background_color_gradient_type_1',
		'background_color_gradient_type_2',
		'background_color_gradient_type_3',
		'background_color_gradient_type_tablet_1',
		'background_color_gradient_type_tablet_2',
		'background_color_gradient_type_tablet_3',
		'background_color_gradient_type_phone_1',
		'background_color_gradient_type_phone_2',
		'background_color_gradient_type_phone_3',
		'background_color_gradient_type__hover_1',
		'background_color_gradient_type__hover_2',
		'background_color_gradient_type__hover_3',
		'background_color_gradient_type__sticky_1',
		'background_color_gradient_type__sticky_2',
		'background_color_gradient_type__sticky_3',
		'background_color_gradient_stops_1',
		'background_color_gradient_stops_2',
		'background_color_gradient_stops_3',
		'background_color_gradient_stops_tablet_1',
		'background_color_gradient_stops_tablet_2',
		'background_color_gradient_stops_tablet_3',
		'background_color_gradient_stops_phone_1',
		'background_color_gradient_stops_phone_2',
		'background_color_gradient_stops_phone_3',
		'background_color_gradient_stops__hover_1',
		'background_color_gradient_stops__hover_2',
		'background_color_gradient_stops__hover_3',
		'background_color_gradient_stops__sticky_1',
		'background_color_gradient_stops__sticky_2',
		'background_color_gradient_stops__sticky_3',

		// Background.
		'background_color_default',
		'background_enable_color_default',

		// Divi AI attributes.
		'template_type',

		// Additional legacy attributes from other migration files.
		'bg_img',
		'image_icon_width',
		'image_icon_custom_padding',
		'border_width_all_image',
		'orderby',
		'header_font_select',
		'body_font_select',
		'button_font_select',

		// Video.
		'allow_player_pause_default',

		// Paralax.
		'parallax_method_1',
		'parallax_method_2',
		'parallax_method_3',
		'parallax_method_4',
		'parallax_method_5',
		'parallax_method_6',
		'parallax_default',
		'parallax_method_default',
		'parallax_4',

		// Misc (Typos, etc).
		'dmin_label',

		// Body UL Font Attributes.
		'body_ul_position',
		'body_ul_item_indent',
		'body_orientation',
		'body_orientation_tablet',
		'body_orientation_phone',
		'body_orientation_last_edited',
		'body_ul_font',
		'body_ul_text_color',
		'body_ul_font_size',
		'body_ul_line_height',
	];

	/**
	 * Get all legacy attribute names from migration classes
	 *
	 * @return array Array of legacy attribute names
	 */
	public static function get_legacy_attribute_names(): array {
		if ( self::$attributes ) {
			/**
			 * Filters the list of legacy attribute names during Divi 4 to Divi 5 conversion.
			 *
			 * This filter allows third-party plugins and extensions to register their own legacy
			 * attributes that should be ignored during the D4 to D5 migration process. When attributes
			 * are added to this list, they will be silently skipped during conversion instead of being
			 * marked as "unknown attributes" which would prevent module conversion.
			 *
			 * This is especially useful for plugin developers who have added custom attributes to Divi
			 * modules and need to ensure those legacy attributes don't interfere with the migration process.
			 * Importantly, this filter is intended specifically for cases where third-party plugins have leftover
			 * legacy attributes that are technically no longer in use—but may still appear in module shortcodes
			 * due to historical usage. Developers should only add such obsolete attributes to this list.
			 * Attributes that are still actively used by the plugin must not be included, as doing
			 * so would cause them to be dropped during conversion, potentially breaking functionality for users.
			 *
			 * @since ??
			 *
			 * @param array $legacy_attribute_names Array of legacy attribute names to ignore during conversion.
			 *                                      Each attribute name should be a string matching the exact
			 *                                      attribute name used in Divi 4 shortcodes.
			 *
			 * @example
			 * ```php
			 * add_filter( 'divi.conversion.legacyAttributeNames', function( $attributes ) {
			 *     // Add custom plugin attributes
			 *     // Note: Only base names are needed - suffix variations (_tablet, _phone, __hover, etc.)
			 *     // are automatically handled by the conversion system.
			 *     $plugin_attributes = [
			 *         'my_plugin_custom_attr', // Base name handles all suffix variations automatically
			 *     ];
			 *     return array_merge( $attributes, $plugin_attributes );
			 * } );
			 * ```
			 */
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores,WordPress.NamingConventions.ValidHookName.NotLowercase -- Hook name uses dot notation for namespace consistency.
			return apply_filters( 'divi.conversion.legacyAttributeNames', self::$attributes );
		}

		// Try to get from option first.
		$legacy_attribute_names = et_get_option( self::OPTION_NAME, [] );

		// If we already have the data, return it.
		if ( ! empty( $legacy_attribute_names ) ) {
			return $legacy_attribute_names;
		}

		// Make sure the migration class is loaded.
		if ( ! class_exists( 'ET_Builder_Module_Settings_Migration' ) ) {
			require_once ET_BUILDER_DIR . 'module/settings/Migration.php';
		}

		// Initialize migrations to populate field_name_migrations.
		ET_Builder_Module_Settings_Migration::init();

		// Get all migrations.
		$migrations = ET_Builder_Module_Settings_Migration::get_migrations( 'all' );

		// Process each migration to populate field_name_migrations.
		foreach ( $migrations as $migration ) {
			$fields  = $migration->get_fields();
			$modules = $migration->get_modules();

			foreach ( $modules as $module_slug ) {
				$migration->handle_field_name_migrations( [], $module_slug );
			}
		}

		// Get all legacy attribute names from field_name_migrations.
		$legacy_attribute_names = [];

		if ( ! empty( ET_Builder_Module_Settings_Migration::$field_name_migrations ) ) {
			foreach ( ET_Builder_Module_Settings_Migration::$field_name_migrations as $module_slug => $field_mappings ) {
				foreach ( $field_mappings as $new_name => $old_names ) {
					foreach ( $old_names as $old_name ) {
						$legacy_attribute_names[] = $old_name;
					}
				}
			}
		}

		// Add known legacy attribute names that might not be captured by field_name_migrations.
		$additional_legacy_attributes = [
			'use_background_color',
			'saved_tabs',
			'_unique_id',
			'x',
			'y',
			'transform_styles',
			'form_field_text_align',
		];

		$legacy_attribute_names = array_merge( $legacy_attribute_names, $additional_legacy_attributes );

		// Remove duplicates.
		$legacy_attribute_names = array_values( array_unique( $legacy_attribute_names ) );

		// Store in option for future use.
		et_update_option( self::OPTION_NAME, $legacy_attribute_names );

		return $legacy_attribute_names;
	}

	/**
	 * Check if an attribute name is a legacy attribute name
	 *
	 * This function checks if an attribute name (with or without responsive/state suffixes)
	 * matches any legacy attribute in the list. It handles both unprefixed and module-prefixed
	 * attribute names.
	 *
	 * @param string $attribute_name The attribute name to check.
	 * @return bool True if it's a legacy attribute name, false otherwise.
	 */
	public static function is_legacy_attribute( string $attribute_name ): bool {
		$legacy_names = self::get_legacy_attribute_names();

		// 1. Check exact match first.
		if ( in_array( $attribute_name, $legacy_names, true ) ) {
			return true;
		}

		// 2. Strip responsive/state suffixes and check base name match.
		$base_name = preg_replace( '/(_tablet|_phone|_last_edited|__hover|__focus|__checked|__active|__sticky|__hover_enabled|__focus_enabled|__checked_enabled|__active_enabled|__sticky_enabled)$/', '', $attribute_name );
		if ( $base_name !== $attribute_name ) {
			// Check if base name matches exactly.
			if ( in_array( $base_name, $legacy_names, true ) ) {
				return true;
			}

			// Check if any legacy attribute ends with the base name (handles module prefixes).
			// Example: attribute_name="image_title_background" should match legacy="dvmd_image_box_image_title_background".
			foreach ( $legacy_names as $legacy_name ) {
				// Strip suffix from legacy name too.
				$legacy_base = preg_replace( '/(_tablet|_phone|_last_edited|__hover|__focus|__checked|__active|__sticky|__hover_enabled|__focus_enabled|__checked_enabled|__active_enabled|__sticky_enabled)$/', '', $legacy_name );

				// Check if legacy name ends with base_name (handles module prefixes).
				// Must match exactly or end with underscore + base_name to avoid substring matches.
				if ( $base_name === $legacy_base || str_ends_with( $legacy_base, '_' . $base_name ) ) {
					return true;
				}
			}
		}

		// 3. Check if any legacy attribute ends with the full attribute name (handles module prefixes without suffixes).
		// Example: attribute_name="image_title_background" should match legacy="dvmd_image_box_image_title_background".
		foreach ( $legacy_names as $legacy_name ) {
			// Strip suffix from legacy name.
			$legacy_base = preg_replace( '/(_tablet|_phone|_last_edited|__hover|__focus|__checked|__active|__sticky|__hover_enabled|__focus_enabled|__checked_enabled|__active_enabled|__sticky_enabled)$/', '', $legacy_name );

			// Check if legacy name ends with attribute_name (handles module prefixes).
			// Must match exactly or end with underscore + attribute_name to avoid substring matches.
			if ( $attribute_name === $legacy_base || str_ends_with( $legacy_base, '_' . $attribute_name ) ) {
				return true;
			}
		}

		return false;
	}
}
