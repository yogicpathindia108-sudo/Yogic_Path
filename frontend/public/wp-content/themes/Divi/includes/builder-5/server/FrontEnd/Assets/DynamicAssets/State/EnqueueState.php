<?php
/**
 * Enqueue State for Dynamic Assets.
 *
 * Holds all boolean flags for determining what scripts/styles to enqueue.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets\DynamicAssets\State;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Enqueue state container.
 *
 * Holds all boolean flags for determining what scripts/styles to enqueue.
 *
 * @since ??
 */
class EnqueueState {

	/**
	 * Whether fitvids should be enqueued.
	 *
	 * @var bool
	 */
	public bool $fitvids = false;

	/**
	 * Whether comments should be enqueued.
	 *
	 * @var bool
	 */
	public bool $comments = false;

	/**
	 * Whether jquery mobile should be enqueued.
	 *
	 * @var bool
	 */
	public bool $jquery_mobile = false;

	/**
	 * Whether magnific popup should be enqueued.
	 *
	 * @var bool
	 */
	public bool $magnific_popup = false;

	/**
	 * Whether easy pie chart should be enqueued.
	 *
	 * @var bool
	 */
	public bool $easypiechart = false;

	/**
	 * Whether toggle script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $toggle = false;

	/**
	 * Whether tooltip module script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $tooltip = false;

	/**
	 * Whether audio script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $audio = false;

	/**
	 * Whether video overlay script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $video_overlay = false;

	/**
	 * Whether video lazy load script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $video_lazy_load = false;

	/**
	 * Whether map lazy load script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $map_lazy_load = false;

	/**
	 * Whether search script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $search = false;

	/**
	 * Whether woo script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $woo = false;

	/**
	 * Whether fullwidth header script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $fullwidth_header = false;

	/**
	 * Whether blog script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $blog = false;

	/**
	 * Whether pagination script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $pagination = false;

	/**
	 * Whether fullscreen section script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $fullscreen_section = false;

	/**
	 * Whether section divider script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $section_dividers = false;

	/**
	 * Whether link script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $link = false;

	/**
	 * Whether slider script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $slider = false;

	/**
	 * Whether map script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $map = false;

	/**
	 * Whether sidebar script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $sidebar = false;

	/**
	 * Whether testimonial script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $testimonial = false;

	/**
	 * Whether tabs script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $tabs = false;

	/**
	 * Whether table of contents script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $table_of_contents = false;

	/**
	 * Whether fullwidth portfolio script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $fullwidth_portfolio = false;

	/**
	 * Whether filterable portfolio script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $filterable_portfolio = false;

	/**
	 * Whether video slider script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $video_slider = false;

	/**
	 * Whether signup script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $signup = false;

	/**
	 * Whether countdown timer script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $countdown_timer = false;

	/**
	 * Whether bar counter script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $bar_counter = false;

	/**
	 * Whether before after image script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $before_after_image = false;

	/**
	 * Whether circle counter script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $circle_counter = false;

	/**
	 * Whether number counter script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $number_counter = false;

	/**
	 * Whether charts script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $charts = false;

	/**
	 * Whether contact form script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $contact_form = false;

	/**
	 * Whether post filter item script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $post_filter_item = false;

	/**
	 * Whether post filter script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $post_filter = false;

	/**
	 * Whether dropdown script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $dropdown = false;

	/**
	 * Whether WooCommerce cart scripts should be enqueued.
	 *
	 * @var bool
	 */
	public bool $woocommerce_cart_scripts = false;

	/**
	 * Whether WooCommerce cart totals script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $woocommerce_cart_totals = false;

	/**
	 * Whether form conditions script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $form_conditions = false;

	/**
	 * Whether menu module script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $menu = false;

	/**
	 * Whether animation module script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $animation = false;

	/**
	 * Whether interactions module script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $interactions = false;

	/**
	 * Whether gallery module script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $gallery = false;

	/**
	 * Whether lottie script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $lottie = false;

	/**
	 * Whether group carousel script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $group_carousel = false;

	/**
	 * Whether logged in script should be enqueued.
	 *
	 * @var bool
	 */
	public bool $logged_in = false;

	/**
	 * Whether salvattore should be enqueued.
	 *
	 * @var bool
	 */
	public bool $salvattore = false;

	/**
	 * Whether split testing scripts should be enqueued.
	 *
	 * @var bool
	 */
	public bool $split_testing = false;

	/**
	 * Whether Google Maps should be enqueued.
	 *
	 * @var bool
	 */
	public bool $google_maps = false;

	/**
	 * Whether motion effects scripts should be enqueued.
	 *
	 * @var bool
	 */
	public bool $motion_effects = false;

	/**
	 * Whether sticky scripts should be enqueued.
	 *
	 * @var bool
	 */
	public bool $sticky = false;

	/**
	 * Check if a specific asset should be enqueued.
	 *
	 * @since ??
	 *
	 * @param string $asset Asset name.
	 *
	 * @return bool
	 */
	public function should_enqueue( string $asset ): bool {
		return $this->{$asset} ?? false;
	}

	/**
	 * Mark an asset for enqueuing.
	 *
	 * @since ??
	 *
	 * @param string $asset Asset name.
	 *
	 * @return void
	 */
	public function mark_for_enqueue( string $asset ): void {
		if ( property_exists( $this, $asset ) ) {
			$this->{$asset} = true;
		}
	}
}
