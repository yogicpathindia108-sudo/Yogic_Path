<?php
/**
 * Detection State for Dynamic Assets.
 *
 * Holds all detection-related properties for early and late detection.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets\DynamicAssets\State;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Detection state container.
 *
 * Holds all properties related to module/block/shortcode detection.
 *
 * @since ??
 */
class DetectionState {

	/**
	 * List of early modules.
	 *
	 * @var array
	 */
	public array $early_modules = [];

	/**
	 * Cached list of blocks saved in post meta.
	 *
	 * @var array
	 */
	public array $early_blocks = [];

	/**
	 * Cached list of shortcodes saved in post meta.
	 *
	 * @var array
	 */
	public array $early_shortcodes = [];

	/**
	 * Cached combined shortcodes (early + late detection).
	 *
	 * @var array|null
	 */
	public ?array $all_shortcodes = null;

	/**
	 * Missed modules detected by late detection.
	 *
	 * @var array
	 */
	public array $missed_modules = [];

	/**
	 * Cached list of attributes saved in post meta.
	 *
	 * @var array
	 */
	public array $early_attributes = [];

	/**
	 * Whether early_attributes was loaded from postmeta cache at the start of the request.
	 * This distinguishes between cache-loaded attributes vs attributes populated during detection.
	 *
	 * @var bool
	 */
	public bool $early_attributes_from_cache = false;

	/**
	 * Missed blocks detected by late detection.
	 *
	 * @var array
	 */
	public array $missed_blocks = [];

	/**
	 * Missed shortcodes detected by late detection.
	 *
	 * @var array
	 */
	public array $missed_shortcodes = [];

	/**
	 * All blocks detected during late detection (regardless of whether they were found early).
	 *
	 * @var array
	 */
	public array $late_blocks = [];

	/**
	 * Track all modules used in the page.
	 *
	 * @var array
	 */
	public array $all_modules = [];

	/**
	 * List of modules to process for data collection.
	 *
	 * @var array
	 */
	public array $processed_modules = [];

	/**
	 * Check whether to use late detection mechanism in other areas.
	 *
	 * @var bool
	 */
	public bool $need_late_generation = false;

	/**
	 * Flag to track whether early detection phase is complete.
	 * Used to determine when to start populating late_block_content.
	 *
	 * @var bool
	 */
	public bool $early_detection_complete = false;

	/**
	 * Keep track of processed files.
	 *
	 * @var array
	 */
	public array $processed_files = [];

	/**
	 * Block/Module Used.
	 *
	 * @var array
	 */
	public array $blocks_used = [];

	/**
	 * Module Attr Values Used.
	 *
	 * @var array
	 */
	public array $block_feature_used = [];

	/**
	 * Valid Block Names.
	 *
	 * @var array
	 */
	public array $verified_blocks = [];

	/**
	 * Valid Shortcode Slugs.
	 *
	 * @var array
	 */
	public array $verified_shortcodes = [];

	/**
	 * Keep track of shortcode use in the page.
	 *
	 * @var array
	 */
	public array $shortcode_used = [];

	/**
	 * Keep track of interested attributes for late detection.
	 *
	 * @var array
	 */
	public array $interested_attrs = [];

	/**
	 * Keep track of attribute use in the page.
	 *
	 * @var string
	 */
	public string $attribute_used = '';

	/**
	 * Keep track of late block content for preset detection.
	 *
	 * @var string
	 */
	public string $late_block_content = '';

	/**
	 * Keep track of late shortcode content for preset detection.
	 *
	 * @var string
	 */
	public string $late_shortcode_content = '';

	/**
	 * Option for feature detections.
	 *
	 * @var array
	 */
	public array $options = [
		'has_block'     => false,
		'has_shortcode' => false,
	];
}
