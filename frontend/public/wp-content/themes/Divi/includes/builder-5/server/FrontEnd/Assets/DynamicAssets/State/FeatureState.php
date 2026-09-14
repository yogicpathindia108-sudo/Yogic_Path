<?php
/**
 * Feature State for Dynamic Assets.
 *
 * Holds all feature detection flags and feature-related state.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets\DynamicAssets\State;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Feature state container.
 *
 * Holds all properties related to feature detection (icons, colors, gutters, etc.).
 *
 * @since ??
 */
class FeatureState {

	/**
	 * Default Gutter widths found during early detection.
	 *
	 * @var array
	 */
	public array $default_gutters = [];

	/**
	 * Gutter widths found during late detection.
	 *
	 * @var array
	 */
	public array $late_gutter_width = [];

	/**
	 * Preset attributes.
	 *
	 * @var array
	 */
	public array $presets_attributes = [];

	/**
	 * Whether animations are found during late detection.
	 *
	 * @var bool
	 */
	public bool $late_use_animation_style = false;

	/**
	 * Whether link is found during late detection.
	 *
	 * @var bool
	 */
	public bool $late_use_link = false;

	/**
	 * Whether parallax is found during late detection.
	 *
	 * @var bool
	 */
	public bool $late_use_parallax = false;

	/**
	 * Whether specialty sections are found during late detection.
	 *
	 * @var bool
	 */
	public bool $late_use_specialty = false;

	/**
	 * Whether sticky options are found during late detection.
	 *
	 * @var bool
	 */
	public bool $late_use_sticky = false;

	/**
	 * Whether motion effects are found during late detection.
	 *
	 * @var bool
	 */
	public bool $late_use_motion_effect = false;

	/**
	 * Whether custom icons are found during late detection.
	 *
	 * @var bool
	 */
	public bool $late_custom_icon = false;

	/**
	 * Whether social icons are found during late detection.
	 *
	 * @var bool
	 */
	public bool $late_social_icon = false;

	/**
	 * Whether FontAwesome icons are found during late detection.
	 *
	 * @var bool
	 */
	public bool $late_fa_icon = false;

	/**
	 * Whether lightbox use is found during late detection.
	 *
	 * @var bool
	 */
	public bool $late_show_in_lightbox = false;

	/**
	 * Whether blog modules set to 'show content' are found during late detection.
	 *
	 * @var bool
	 */
	public bool $late_show_content = false;

	/**
	 * Whether block mode blog is found during late detection.
	 *
	 * @var bool
	 */
	public bool $late_use_block_mode_blog = false;

	/**
	 * Used global modules.
	 *
	 * @var array
	 */
	public array $global_modules = [];

	/**
	 * Used builder global presets.
	 *
	 * @var array
	 */
	public array $presets_feature_used = [];

	/**
	 * Whether to load resources to print all Divi icons ( icons_all.scss ).
	 *
	 * @var bool
	 */
	public bool $use_divi_icons = false;

	/**
	 * Whether to load resources to print FA icons ( icons_fa_all.scss ).
	 *
	 * @var bool
	 */
	public bool $use_fa_icons = false;

	/**
	 * Whether to load resources to print icons used in Social Follow Module ( icons_base_social.scss ).
	 *
	 * @var bool
	 */
	public bool $use_social_icons = false;

	/**
	 * Global asset list found during early detection.
	 *
	 * @var array
	 */
	public array $early_global_asset_list = [];

	/**
	 * Global asset list found during late detection.
	 *
	 * @var array
	 */
	public array $late_global_asset_list = [];

	/**
	 * Whether to load global colors css.
	 *
	 * @var bool
	 */
	public bool $use_global_colors = false;

	/**
	 * Track global color ids in early detections.
	 *
	 * @var array
	 */
	public array $early_global_color_ids = [];

	/**
	 * Track global color ids in late detections.
	 *
	 * @var array
	 */
	public array $late_global_color_ids = [];
}
