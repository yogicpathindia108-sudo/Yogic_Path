<?php
/**
 * Generate Dynamic Assets.
 *
 * This file combines the logic from the following Divi 4 files:
 * - includes/builder/feature/dynamic-assets/class-dynamic-assets.php
 * - includes/functions/dynamic-assets.php
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\Assets\DynamicAssets\DynamicAssetsCache;
use ET\Builder\FrontEnd\Assets\DynamicAssets\DynamicAssetsContent;
use ET\Builder\FrontEnd\Assets\DynamicAssets\DynamicAssetsDependencyChecker;
use ET\Builder\FrontEnd\Assets\DynamicAssets\DynamicAssetsDetection;
use ET\Builder\FrontEnd\Assets\DynamicAssets\DynamicAssetsEnqueue;
use ET\Builder\FrontEnd\Assets\DynamicAssets\DynamicAssetsListBuilder;
use ET\Builder\FrontEnd\Assets\DynamicAssets\DynamicAssetsStore;
use ET\Builder\FrontEnd\Assets\DynamicAssets\IntegrationAssetsProviderCollection;
use ET\Builder\FrontEnd\Assets\DynamicAssets\IntegrationAssetsProviderRegistry;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\CacheState;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\DetectionState;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\EnqueueState;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\FeatureState;
use ET\Builder\FrontEnd\FrontEnd;
use ET\Builder\FrontEnd\Module\ScriptData;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\VisualBuilder\OffCanvas\OffCanvasHooks;
use ET_Builder_Dynamic_Assets_Feature;
use ET_Core_Cache_Directory;
use ET_Core_PageResource;
use ET_Post_Stack;
use Feature\ContentRetriever\ET_Builder_Content_Retriever;
use InvalidArgumentException;


/**
 * Dynamic Assets class.
 *
 * Perform content analysis that marks that content should considered `above the fold` or `below the fold`.
 *
 * @since ??
 */
class DynamicAssets implements DependencyInterface {

	/**
	 * Is the current request cachable.
	 *
	 * @var null|bool
	 */
	private static $_is_cachable_request = null;

	/**
	 * Dynamic Assets Store.
	 *
	 * Holds all state objects and provides typed accessors.
	 *
	 * @var DynamicAssetsStore
	 */
	private DynamicAssetsStore $_store;

	/**
	 * Cache handler instance.
	 *
	 * Handles cache directory operations, file generation, and metadata management.
	 *
	 * @var DynamicAssetsCache
	 */
	private DynamicAssetsCache $_cache;

	/**
	 * Dependency checker instance.
	 *
	 * Handles dependency checking logic for determining when assets should be enqueued.
	 *
	 * @var DynamicAssetsDependencyChecker
	 */
	private DynamicAssetsDependencyChecker $_dependency_checker;

	/**
	 * Detection handler instance.
	 *
	 * Handles feature detection logic for early and late detection.
	 *
	 * @var DynamicAssetsDetection
	 */
	private DynamicAssetsDetection $_detection;

	/**
	 * Content handler instance.
	 *
	 * Handles content retrieval and manipulation for dynamic assets processing.
	 *
	 * @var DynamicAssetsContent
	 */
	private DynamicAssetsContent $_content;

	/**
	 * List builder instance.
	 *
	 * Handles building asset lists for dynamic assets processing.
	 *
	 * @var DynamicAssetsListBuilder
	 */
	private DynamicAssetsListBuilder $_list_builder;

	/**
	 * Enqueue handler instance.
	 *
	 * Handles enqueuing scripts and styles for dynamic assets processing.
	 *
	 * @var DynamicAssetsEnqueue
	 */
	private DynamicAssetsEnqueue $_enqueue;

	/**
	 * Request-scoped integration asset provider registry.
	 *
	 * @var IntegrationAssetsProviderRegistry
	 */
	private IntegrationAssetsProviderRegistry $_integration_assets_provider_registry;

	/**
	 * DA-internal integration asset provider lifecycle state.
	 *
	 * @var IntegrationAssetsProviderCollection
	 */
	private IntegrationAssetsProviderCollection $_integration_assets_provider_collection;

	/**
	 * Class instance.
	 *
	 * @since ??
	 *
	 * @var DynamicAssets Dynamic Assets Class instance.
	 */
	protected static $_instance = null;

	/**
	 * Reset class instance and cachable request flag.
	 *
	 * @since ??
	 */
	public static function reset(): void {
		self::$_instance            = null;
		self::$_is_cachable_request = null;
	}

	/**
	 * Constructor - Initialize state objects and handlers.
	 *
	 * Integration provider infrastructure is constructed here. Providers register later through the
	 * public registration action during eligible frontend `pre_initial_setup()`.
	 *
	 * @since ??
	 */
	public function __construct() {
		// Create state objects directly (no factory needed).
		$cache_state     = new CacheState();
		$detection_state = new DetectionState();
		$enqueue_state   = new EnqueueState();
		$feature_state   = new FeatureState();

		// Create store instance with all state objects.
		$this->_store = new DynamicAssetsStore(
			$cache_state,
			$detection_state,
			$enqueue_state,
			$feature_state
		);

		$this->_cache              = new DynamicAssetsCache( $this->_store );
		$this->_dependency_checker = new DynamicAssetsDependencyChecker( $cache_state );
		$this->_content            = new DynamicAssetsContent( $cache_state, $feature_state );
		$this->_integration_assets_provider_collection = new IntegrationAssetsProviderCollection();
		$this->_integration_assets_provider_registry   = new IntegrationAssetsProviderRegistry( $this->_integration_assets_provider_collection );
		$this->_detection          = new DynamicAssetsDetection(
			$cache_state,
			$detection_state,
			$feature_state,
			$this->_cache,
			function () {
				return $this->_content->get_all_content();
			},
			function () {
				return $this->_content->get_theme_builder_template_content();
			}
		);
		$this->_list_builder       = new DynamicAssetsListBuilder(
			$cache_state,
			$detection_state,
			$feature_state,
			$this->_content,
			$this->_detection,
			$this->_dependency_checker
		);
		$this->_enqueue            = new DynamicAssetsEnqueue(
			$cache_state,
			$detection_state,
			$enqueue_state,
			$feature_state,
			$this->_content,
			$this->_detection,
			$this->_dependency_checker,
			$this->_integration_assets_provider_collection
		);
	}

	/**
	 * Load Critical CSS class.
	 *
	 * @since ??
	 */
	public function load() {
		global $shortname;

		DynamicAssetsUtils::ensure_cache_directory_exists();

		add_action( 'wp', [ $this, 'pre_initial_setup' ], 0 );
		add_action( 'wp', [ $this, 'initial_setup' ], 999 );

		// Enqueue early assets.
		add_action( 'wp_enqueue_scripts', [ $this->_enqueue, 'enqueue_dynamic_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this->_enqueue, 'enqueue_dynamic_scripts_early' ] );

		// If this is the Divi theme, add the divi filter to the global assets list.
		if ( 'divi' === $shortname ) {
			add_filter( 'divi_frontend_assets_dynamic_assets_global_assets_list', [ $this->_list_builder, 'divi_get_global_assets_list' ] );
		}

		// Detect Module/Block use.
		add_filter( 'render_block_data', [ $this->_detection, 'log_block_used' ], 99, 3 );
		add_filter( 'pre_do_shortcode_tag', [ $this->_detection, 'log_shortcode_used' ], 99, 4 );

		// Track when main content rendering completes.
		add_filter( 'the_content', [ $this, 'mark_main_content_complete' ], 999999 );

		// Enqueue scripts and generate assets if late blocks or attributes are detected.
		add_action( 'wp_footer', [ $this, 'process_late_detection_and_output' ] );
		add_action( 'wp_footer', [ $this->_enqueue, 'enqueue_dynamic_scripts_late' ] );

		// Add script that loads fallback .css during blog module ajax pagination.
		add_action( 'wp_footer', [ $this->_enqueue, 'maybe_inject_fallback_dynamic_assets' ] );
		// If a late file was generated, we grab it in the footer and then inject it into the header.
		add_action( 'divi_frontend_assets_dynamic_assets_utils_late_assets_generated', [ $this->_enqueue, 'maybe_inject_late_dynamic_assets' ], 0 );

		// Prepare list of modules based on assets list.
		self::_setup_verified_modules();
		add_action( 'et_builder_ready', [ $this, 'late_setup_verified_modules' ] );

		// Save the instance.
		self::$_instance = $this;
	}

	/**
	 * Open the integration asset provider registration window, then seal the collection.
	 *
	 * Infrastructure objects are constructed in `__construct()`. Divi-owned integrations attach
	 * always-eager callbacks before `wp`, independently of lazy module loading. Third-party
	 * integrations must also attach the public action before `wp`. The collection seals after the
	 * action completes.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private function _register_integration_asset_providers(): void {
		if ( $this->_integration_assets_provider_collection->is_sealed() ) {
			return;
		}

		/**
		 * Fires when Dynamic Assets integration asset providers can be registered.
		 *
		 * Providers consume DA-owned request content and call their integration's supported public
		 * enqueue API. This action does not expose or replace Dynamic Assets content gathering.
		 * Divi-owned integrations use reserved `divi/*` IDs and attach at the earliest priority.
		 * Third parties should register from plugin bootstrap or `init`, before `wp`; registrations
		 * from lazily loaded module classes can be absent on warm-cache requests. Provider IDs must be
		 * unique, lowercase namespaced slugs. Extensions supporting older Divi versions must guard or
		 * defer loading classes that implement IntegrationAssetsProviderInterface. Supplied request
		 * content must not be persisted, logged, or transmitted. Visual Builder and REST preview
		 * assets remain the responsibility of each integration and are outside this frontend action.
		 *
		 * @since ??
		 *
		 * @param IntegrationAssetsProviderRegistry $registry Request-scoped provider registry.
		 */
		do_action(
			'divi_frontend_assets_dynamic_assets_register_integration_asset_providers',
			$this->_integration_assets_provider_registry
		);

		$this->_integration_assets_provider_collection->seal();
	}

	/**
	 * Get valid shortcodes slugs.
	 *
	 * @since ??
	 */
	public function _setup_verified_modules() {
		// Value for the filter.
		$additional_valid_names = [
			'core/gallery',
		];

		/**
		 * The "core/gallery" block is not part of the Divi modules but is used for enqueuing MagnificPopup
		 * when Divi Gallery is enabled under Theme Options > Enable Divi Gallery, so we need to include
		 * it in late detection for edge cases such as shortcodes hardcoded into child themes.
		 *
		 * @since ??
		 *
		 * @param array $additional_valid_names Additional Block Names.
		 */
		$additional_valid_names = apply_filters(
			'divi_frontend_assets_dynamic_assets_valid_blocks',
			$additional_valid_names
		);

		$this->_store->detection()->verified_blocks = array_unique( array_merge( DynamicAssetsUtils::get_divi_block_names(), $additional_valid_names ) );

		// Value for the filter.
		$additional_valid_shortcodes = [
			'gallery',
		];

		/**
		 * The "gallery" shortcode is not part of the Divi modules but is used for enqueuing MagnificPopup
		 * when Divi Gallery is enabled under Theme Options > Enable Divi Gallery, so we need to include
		 * it in late detection for edge cases such as shortcodes hardcoded into child themes.
		 *
		 * This filter is the replacement of Divi 4 filter `et_builder_valid_module_slugs`.
		 *
		 * @since ??
		 *
		 * @param array $additional_valid_shortcodes Additional Shortcode Tags.
		 */
		$additional_valid_shortcodes = apply_filters(
			'divi_frontend_assets_dynamic_assets_valid_module_slugs',
			$additional_valid_shortcodes
		);

		$this->_store->detection()->verified_shortcodes = array_unique( array_merge( DynamicAssetsUtils::get_divi_shortcode_slugs(), $additional_valid_shortcodes ) );

		// Value for the filter.
		$interested_attrs_and_values = [
			'gutter_width',
			'animation_style',
			'sticky_position',
			'specialty',
			'use_custom_gutter',
			'font_icon',
			'button_icon',
			'hover_icon',
			'scroll_down_icon',
			'social_network',
			'show_in_lightbox',
			'fullwidth',
			'scroll_vertical_motion_enable',
			'scroll_horizontal_motion_enable',
			'scroll_fade_enable',
			'scroll_scaling_enable',
			'scroll_rotating_enable',
			'scroll_blur_enable',
			'show_content',
		];

		/**
		 * Filters interested shortcode attributes to detect feature use.
		 *
		 * This filter is the replacement of Divi 4 filter `et_builder_module_attrs_values_used`.
		 *
		 * @since ??
		 *
		 * @param array $interested_attrs_and_values List of shortcode attribute name.
		 */
		$this->_store->detection()->interested_attrs = apply_filters(
			'divi_frontend_assets_dynamic_assets_module_attribute_used',
			$interested_attrs_and_values
		);
	}

	/**
	 * Late setup verified modules.
	 *
	 * During `_setup_verified_modules` and `pre_initial_setup` calls, it's not possible to set third party modules for
	 * `_verified_shortcodes` and `_early_shortcodes` properties because the shortcode framework is not loaded yet. So,
	 * there are no D4 modules initialized yet. This causes D4 third party modules to be missed in the early detection
	 * process and also not logged in `_shortcode_used` property that is used for the late detection process. It doesn't
	 * happen to D4 official modules because we define them in `DynamicAssetsUtils::get_divi_shortcode_slugs()`.
	 *
	 * Hence, to make sure D4 third party modules work with Dynamic Assets, we need to add them to `_verified_shortcodes`
	 * property for the late detection process once the D4 module shortcodes are initialized on `et_builder_ready` action.
	 *
	 * @see ET\Builder\VisualBuilder\SettingsData\SettingsDataCallbacks::shortcode_module_definitions()
	 *
	 * @since ??
	 */
	public function late_setup_verified_modules() {
		$third_party_modules      = \ET_Builder_Element::get_third_party_modules();
		$third_party_module_slugs = array_keys( $third_party_modules );

		$this->_store->detection()->verified_shortcodes = array_merge(
			$this->_store->detection()->verified_shortcodes,
			$third_party_module_slugs
		);
	}

	/**
	 * Pre initial setup.
	 *
	 * Initially, it's part of the `initial_setup` method. However, there are some processes that we need to run earlier
	 * due to `initial_setup` method runs too late on `wp` action with order `999`. Hence the `pre_initial_setup` method
	 * is created to run the process earlier on `wp` action with order `0`.
	 *
	 * NOTE: Please use `initial_setup` method for the main process and late detection. Only use `pre_initial_setup` for
	 * the process that needs to run earlier.
	 *
	 * @since ??
	 */
	public function pre_initial_setup() {
		global $post;

		// Skip processing for non-content requests (CSS maps, well-known files, etc.).
		// These are separate HTTP requests that don't need feature detection.
		// Use DynamicAssetsUtils::is_dynamic_front_end_request() which already has
		// comprehensive checks for valid frontend requests.
		if ( ! DynamicAssetsUtils::is_dynamic_front_end_request() ) {
			return;
		}

		$this->_register_integration_asset_providers();

		$content_retriever = ET_Builder_Content_Retriever::init();
		$current_post_id   = is_singular() && $post ? $post->ID : DynamicAssetsUtils::get_current_post_id();
		$current_post      = get_post( $current_post_id );

		// Set post ID early so metadata methods work.
		$this->_store->cache()->post_id = ! empty( $current_post ) ? intval( $current_post_id ) : - 1;

		// Set object_id and folder_name early so cache lookup works for non-singular pages.
		// For non-singular pages (taxonomy, author, date archives, etc.), cache uses folder_name as the key.
		if ( is_singular() && $post ) {
			$this->_store->cache()->object_id = $post->ID;
		} elseif ( is_search() || DynamicAssetsUtils::is_virtual_page() ) {
			// Search and virtual pages use -1 as object_id.
			$this->_store->cache()->object_id = - 1;
		} else {
			// For all other non-singular pages (taxonomy, author, date archives, post type archives, etc.),
			// use get_queried_object_id() which returns term_id for taxonomy, author ID for author pages,
			// and 0 for date/post type archives (which is handled appropriately by get_cache_folder_name).
			$this->_store->cache()->object_id = get_queried_object_id();
		}

		// Set folder_name now so cache lookup works for taxonomy pages.
		$this->_store->cache()->folder_name = $this->_cache->get_folder_name();

		// Store original post content to avoid redundant database queries.
		$this->_store->cache()->original_post_content = $current_post ? $current_post->post_content : '';

		// Set some Dynamic Assets class base properties.
		$_page_content = $content_retriever->get_entire_page_content( $current_post );
		$_page_content = $this->_content->maybe_add_global_modules_content( $_page_content );
		$_page_content = $this->_content->maybe_add_library_modules_content( $_page_content );
		$_page_content = $this->_content->maybe_add_appended_canvas_content( $_page_content, $current_post_id );
		$this->_content->set_all_content( $_page_content );

		// Respect the top-level generation gate before any detection or cache state is populated.
		if ( ! DynamicAssetsUtils::should_generate_dynamic_assets() ) {
			$this->_maybe_prepare_shortcode_rendering_support( $this->_content->get_all_content() );
			return;
		}

		// SIMPLIFICATION: Load cached features early if the cache exists.
		// This allows script enqueuing to use cached data without running detection.
		if ( empty( $this->_store->detection()->early_attributes ) && $this->_cache->metadata_exists( '_divi_dynamic_assets_cached_feature_used' ) ) {
			$this->_store->detection()->early_attributes            = $this->_cache->metadata_get( '_divi_dynamic_assets_cached_feature_used' );
			$this->_store->detection()->early_attributes_from_cache = true;
		}

		// When Dynamic Assets are disabled.
		if ( ! DynamicAssetsUtils::should_initiate_dynamic_assets() ) {
			$this->_maybe_prepare_shortcode_rendering_support( $this->_content->get_all_content() );

			// Bail early.
			return;
		}

		// If cached blocks exist, grab them from the post meta.
		if ( $this->_cache->metadata_exists( '_divi_dynamic_assets_cached_modules' ) ) {
			$used_modules = $this->_cache->metadata_get( '_divi_dynamic_assets_cached_modules' );

			$this->_store->detection()->early_blocks     = $used_modules['blocks'] ?? [];
			$this->_store->detection()->early_shortcodes = $used_modules['shortcodes'] ?? [];
		} elseif ( DynamicAssetsUtils::should_run_detection() ) {
			// If there are no cached modules, parse the post content to retrieve used blocks.
			$used_modules = $this->_detection->get_early_modules( $this->_content->get_all_content() );

			$this->_store->detection()->early_blocks     = $used_modules['blocks'] ?? [];
			$this->_store->detection()->early_shortcodes = $used_modules['shortcodes'] ?? [];

			// Cache the early blocks/shortcodes to the meta.
			$this->_cache->metadata_set(
				'_divi_dynamic_assets_cached_modules',
				[
					'blocks'     => $this->_store->detection()->early_blocks,
					'shortcodes' => $this->_store->detection()->early_shortcodes,
				]
			);
		}

		if ( ! empty( $this->_store->detection()->early_shortcodes ) ) {
			$this->_maybe_prepare_shortcode_rendering_support( $this->_content->get_all_content(), true );
		}

		// Update _early_modules.
		$this->_store->detection()->early_modules = $this->_build_early_modules();

		// Track block/shortcode use.
		$this->_store->detection()->options['has_block']     = ! empty( $this->_store->detection()->early_blocks );
		$this->_store->detection()->options['has_shortcode'] = ! empty( $this->_store->detection()->early_shortcodes );

		// Cache has_excerpt_content_on early so StaticCSS can use it.
		// Check cached features first to avoid unnecessary detection when cached.
		// The result is automatically cached in detection_state->early_attributes by get_cached_or_detect_feature().
		if ( DynamicAssetsUtils::should_run_detection() ) {
			$this->_detection->get_cached_or_detect_feature(
				'excerpt_content_on',
				[ DetectFeature::class, 'has_excerpt_content_on' ],
				[ $this->_content->get_all_content(), $this->_store->detection()->options ]
			);
		}
	}

	/**
	 * Initial setup.
	 */
	public function initial_setup() {
		// Don't do anything if it's not needed.
		if ( ! DynamicAssetsUtils::should_initiate_dynamic_assets() ) {
			return;
		}

		global $shortname;

		// object_id and folder_name are already set in pre_initial_setup() for cache lookup.

		// Don't process Dynamic CSS logic if it's not needed or can't be processed.
		if ( ! $this->is_cachable_request() ) {
			return;
		}

		$this->_set_cache_owner( $shortname );
		$this->_initialize_cache_state();

		// Create asset directory, if it does not exist.
		$ds       = DIRECTORY_SEPARATOR;
		$file_dir = "{$this->_store->cache()->cache_dir_path}{$ds}{$this->_store->cache()->folder_name}{$ds}";

		et_()->ensure_directory_exists( $file_dir );

		// If cache exists and files are not stale, skip all detection and generation.
		if ( $this->_should_skip_generation() ) {
			return;
		}

		// Proceed with detection and generation.
		$this->generate_dynamic_assets();
	}

	/**
	 * Get class instance.
	 *
	 * @since ??
	 *
	 * @return DynamicAssets Dynamic Assets Class instance.
	 */
	public static function get_instance(): ?DynamicAssets {
		return self::$_instance;
	}

	/**
	 * Get a list of blocks used in current page.
	 *
	 * @return array
	 */
	public function get_saved_page_blocks(): array {
		$used_modules = $this->_cache->metadata_get( '_divi_dynamic_assets_cached_modules' );

		if ( empty( $used_modules ) ) {
			return [];
		}

		if ( empty( $used_modules['shortcodes'] ) ) {
			return $used_modules['blocks'];
		}

		// Convert shortcode names to block names.
		return array_unique(
			array_merge(
				$used_modules['blocks'],
				array_map( [ DynamicAssetsUtils::class, 'get_block_name_from_shortcode' ], $used_modules['shortcodes'] )
			)
		);
	}


	/**
	 * Check to see if Dynamic Assets ia applicable to current page request.
	 *
	 * @since  ??
	 * @return bool.
	 */
	public function is_cachable_request(): bool {
		if ( is_null( self::$_is_cachable_request ) ) {
			self::$_is_cachable_request = true;

			// Bail if this is not a front-end page request.
			if ( ! DynamicAssetsUtils::should_generate_dynamic_assets() ) {
				self::$_is_cachable_request = false;
			}

			// Bail if Dynamic CSS is disabled.
			if ( self::$_is_cachable_request && ! DynamicAssetsUtils::use_dynamic_assets() ) {
				self::$_is_cachable_request = false;
			}

			// Bail if the page has no designated cache folder and is not cachable.
			if ( self::$_is_cachable_request && ! $this->_store->cache()->folder_name ) {
				self::$_is_cachable_request = false;
			}
		}

		return self::$_is_cachable_request;
	}

	/**
	 * Merges global assets and blocks assets and
	 * sends the list to generate_dynamic_assets_files() for file generation.
	 *
	 * @since ??
	 * @return void
	 */
	public function generate_dynamic_assets() {
		if ( ! $this->is_cachable_request() ) {
			return;
		}

		/**
		 * Fires before dynamic assets generation starts.
		 * This allows plugins to perform actions before assets are generated.
		 *
		 * @since ??
		 */
		do_action( 'divi_frontend_assets_dynamic_assets_before_generate' );

		$split_global_data = [];
		$atf_blocks        = [];

		if ( $this->_store->detection()->need_late_generation ) {
			$this->_store->detection()->processed_modules = $this->_store->detection()->missed_modules;
			$global_assets_list                           = DynamicAssetsUtils::get_new_array_values( $this->_list_builder->get_late_global_assets_list(), $this->_store->feature()->early_global_asset_list );
		} else {
			$this->_store->feature()->presets_feature_used = $this->_detection->presets_feature_used( $this->_content->get_all_content() );

			// Store preset features as early attributes if they exist.
			$this->_merge_preset_features_to_early_attributes();

			// Run feature detection map during early detection to populate early_attributes.
			// This ensures all features in the map are detected and cached before get_global_assets_list() runs.
			$early_content = $this->_content->get_all_content();
			if ( ! empty( $early_content ) ) {
				$feature_detection_map = DynamicAssetsUtils::get_feature_detection_map( $this->_store->detection()->options );
				$this->_detection->process_feature_detection_map_with_cache( $feature_detection_map, $early_content );
			}

			$this->_store->detection()->processed_modules = $this->_store->detection()->early_modules;
			$global_assets_list                           = $this->_list_builder->get_global_assets_list();

			// Value for the `divi_frontend_assets_dynamic_assets_modules_atf` filter.
			$content = $this->_content->get_all_content();

			/**
			 * Filters the Above The Fold blocks.
			 *
			 * This filter is the replacement of Divi 4 filter `et_dynamic_assets_modules_atf`.
			 *
			 * @since ??
			 *
			 * @param array  $atf_blocks Above The Fold blocks.
			 * @param string $content    Theme Builder Content / Post Content.
			 */
			$atf_blocks = apply_filters( 'divi_frontend_assets_dynamic_assets_modules_atf', $atf_blocks, $content );

			// Initial value for the `et_dynamic_assets_content` filter.
			$split_content = false;

			/**
			 * Filters whether Content can be split in Above The Fold / Below The Fold.
			 *
			 * This filter is the replacement of Divi 4 filter `et_dynamic_assets_content`.
			 *
			 * @since ??
			 *
			 * @param bool|object $split_content Builder Post Content.
			 */
			$split_content = apply_filters( 'divi_frontend_assets_dynamic_assets_content', $split_content );

			if ( 'object' === gettype( $split_content ) ) {
				$split_global_data = $this->_list_builder->split_global_assets_data( $split_content, $global_assets_list );
			}
		}

		$block_assets_list = $this->_list_builder->get_block_assets_list();

		if ( empty( $split_global_data ) ) {
			$this->_generate_unsplit_assets( $global_assets_list, $block_assets_list );
		} else {
			$this->_generate_split_assets( $split_global_data, $global_assets_list, $block_assets_list, $atf_blocks );
		}
	}

	/**
	 * Mark that main content rendering has completed.
	 *
	 * This filter runs at the END of the_content processing (very late priority).
	 * Any blocks rendered after this point are from Theme Builder templates, widgets, footer, or other late sources.
	 *
	 * @since ??
	 *
	 * @param string $content The post content.
	 * @return string Unchanged content.
	 */
	public function mark_main_content_complete( string $content ): string {
		// Only set the flag once (the_content can be called multiple times).
		if ( ! $this->_store->detection()->early_detection_complete ) {
			$this->_store->detection()->early_detection_complete = true;
		}

		return $content;
	}

	/**
	 * Generate late assets if needed.
	 *
	 * @since 4.10.0
	 */
	public function process_late_detection_and_output() {
		// Skip processing for non-content requests (static files, etc.).
		if ( ! DynamicAssetsUtils::is_dynamic_front_end_request() ) {
			return;
		}

		// Respect the top-level generation gate before any late detection runs.
		if ( ! DynamicAssetsUtils::should_generate_dynamic_assets() ) {
			return;
		}

		if ( ! DynamicAssetsUtils::should_run_detection() ) {
			return;
		}

		// SIMPLIFICATION: If cache exists, skip all late detection.
		// Script enqueuing will use the cached data.
		// File generation will be skipped by generate_dynamic_assets_files() if files are not stale.
		if ( $this->_store->detection()->early_attributes_from_cache ) {
			return;
		}

		// Late detection.
		$this->_detection->get_late_blocks();
		$this->_detection->get_late_attributes();

		// Late assets determination.
		// Note: generate_dynamic_assets_files() already checks if files exist and are not stale.
		if ( $this->_store->detection()->need_late_generation ) {
			$this->generate_dynamic_assets();

			/**
			 * Fires after late detected assets are generated.
			 *
			 * @since 4.10.0
			 */
			do_action( 'divi_frontend_assets_dynamic_assets_utils_late_assets_generated' );
		}
	}

	/**
	 * Either load the framework early or not.
	 *
	 * @since ??
	 *
	 * @param string $content The post content.
	 *
	 * @return void
	 */
	public function maybe_load_early_framework( string $content = '' ) {
		// Check if we need to load WooCommerce framework early.
		if ( DetectFeature::has_woocommerce_module_shortcode( $content ) ) {
			et_load_woocommerce_framework();
		}
	}

	/**
	 * Prepare shortcode rendering support without forcing Dynamic Assets detection.
	 *
	 * @since ??
	 *
	 * @param string $content       The post content.
	 * @param bool   $has_shortcode Whether shortcode presence is already known.
	 *
	 * @return void
	 */
	private function _maybe_prepare_shortcode_rendering_support( string $content, bool $has_shortcode = false ): void {
		if ( ! $has_shortcode && ! DetectFeature::get_shortcode_names( $content ) ) {
			return;
		}

		// Add filters to get rid of random p tags.
		add_filter( 'the_content', [ HTMLUtility::class, 'fix_builder_shortcodes' ] );
		add_filter( 'et_builder_render_layout', [ HTMLUtility::class, 'fix_builder_shortcodes' ] );
		add_filter( 'the_content', 'et_pb_the_content_prep_code_module_for_wpautop', 0 );
		add_filter( 'et_builder_render_layout', 'et_pb_the_content_prep_code_module_for_wpautop', 0 );

		// Check if we need to load WooCommerce framework early.
		$this->maybe_load_early_framework( $content );
	}

	/**
	 * Set cache owner based on theme/plugin.
	 *
	 * @since ??
	 *
	 * @param string $shortname Theme shortname.
	 *
	 * @return void
	 */
	private function _set_cache_owner( string $shortname ): void {
		if ( 'divi' === $shortname ) {
			$this->_store->cache()->owner = 'divi';
		} elseif ( 'extra' === $shortname ) {
			$this->_store->cache()->owner = 'extra';
		} elseif ( et_is_builder_plugin_active() ) {
			$this->_store->cache()->owner = 'divi-builder';
		}
	}

	/**
	 * Initialize cache state properties.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private function _initialize_cache_state(): void {
		$cache_dir                                = ET_Core_Cache_Directory::instance();
		$this->_store->cache()->tb_template_ids   = DynamicAssetsUtils::get_theme_builder_template_ids();
		$this->_store->cache()->cache_dir_path    = $cache_dir->path;
		$this->_store->cache()->cache_dir_url     = $cache_dir->url;
		$this->_store->cache()->product_dir       = et_is_builder_plugin_active() ? \ET_BUILDER_PLUGIN_URI : get_template_directory_uri();
		$this->_store->cache()->cpt_suffix        = et_builder_should_wrap_styles() && ! et_is_builder_plugin_active() ? '_cpt' : '';
		$this->_store->cache()->is_rtl            = is_rtl();
		$this->_store->cache()->rtl_suffix        = $this->_store->cache()->is_rtl ? '_rtl' : '';
		$this->_store->cache()->page_builder_used = is_singular() && et_pb_is_pagebuilder_used( $this->_store->cache()->post_id );
		$this->_store->cache()->tb_prefix         = $this->_store->cache()->tb_template_ids ? '-tb' : '';
	}

	/**
	 * Check if cache exists and files are not stale, indicating we should skip generation.
	 *
	 * @since ??
	 *
	 * @return bool True if generation should be skipped, false otherwise.
	 */
	private function _should_skip_generation(): bool {
		$cache_exists = $this->_cache->metadata_exists( '_divi_dynamic_assets_cached_feature_used' );
		if ( ! $cache_exists ) {
			return false;
		}

		// Load cached attributes for script enqueuing.
		if ( empty( $this->_store->detection()->early_attributes ) ) {
			$this->_store->detection()->early_attributes            = $this->_cache->metadata_get( '_divi_dynamic_assets_cached_feature_used' );
			$this->_store->detection()->early_attributes_from_cache = true;
		}

		// Check if CSS files exist and are not stale.
		return $this->_has_non_stale_files();
	}

	/**
	 * Check if any dynamic asset files exist and are not stale.
	 *
	 * @since ??
	 *
	 * @return bool True if non-stale files exist, false otherwise.
	 */
	private function _has_non_stale_files(): bool {
		$files             = (array) glob( "{$this->_store->cache()->cache_dir_path}/{$this->_store->cache()->folder_name}/et*-dynamic*{$this->_store->cache()->tb_prefix}*" );
		$has_dynamic_files = false;

		foreach ( $files as $file ) {
			if ( str_ends_with( $file, '.stale' ) ) {
				continue;
			}

			$has_dynamic_files = true;

			// If any matching dynamic file is stale, generation must not be skipped.
			if ( ET_Core_PageResource::is_file_stale( $file ) ) {
				return false;
			}
		}

		// Skip generation only when at least one dynamic file exists and none are stale.
		return $has_dynamic_files;
	}

	/**
	 * Generate assets without splitting (standard case).
	 *
	 * @since ??
	 *
	 * @param array $global_assets_list Global assets list.
	 * @param array $block_assets_list   Block assets list.
	 *
	 * @return void
	 */
	private function _generate_unsplit_assets( array $global_assets_list, array $block_assets_list ): void {
		$assets_data = $this->_list_builder->get_assets_data( array_merge( $global_assets_list, $block_assets_list ) );
		$this->_cache->generate_dynamic_assets_files( $assets_data );
	}

	/**
	 * Generate assets split into above-the-fold and below-the-fold.
	 *
	 * @since ??
	 *
	 * @param array $split_global_data  Split global assets data.
	 * @param array $global_assets_list Global assets list.
	 * @param array $block_assets_list  Block assets list.
	 * @param array $atf_blocks         Above-the-fold blocks.
	 *
	 * @return void
	 */
	private function _generate_split_assets( array $split_global_data, array $global_assets_list, array $block_assets_list, array $atf_blocks ): void {
		list( $atf_block_assets_list, $btf_block_assets_list ) = $this->_split_block_assets( $block_assets_list, $atf_blocks );

		$atf_assets_data = $this->_list_builder->get_assets_data( array_merge( $split_global_data['atf'], $atf_block_assets_list ) );

		// Reset processed files so get_assets_data returns the correct set for BTF.
		$this->_store->detection()->processed_files = [];
		$btf_assets_data                            = $this->_list_builder->get_assets_data( array_merge( $split_global_data['btf'], $btf_block_assets_list ) );

		$this->_cache->generate_dynamic_assets_files( $atf_assets_data, 'critical' );
		$this->_cache->generate_dynamic_assets_files( $btf_assets_data );
	}

	/**
	 * Split block assets into above-the-fold and below-the-fold lists.
	 *
	 * @since ??
	 *
	 * @param array $block_assets_list Block assets list.
	 * @param array $atf_blocks        Above-the-fold blocks.
	 *
	 * @return array Tuple of [atf_block_assets_list, btf_block_assets_list].
	 */
	private function _split_block_assets( array $block_assets_list, array $atf_blocks ): array {
		$atf_block_assets_list = [];
		$btf_block_assets_list = $block_assets_list;

		foreach ( $atf_blocks as $block_name ) {
			if ( isset( $block_assets_list[ $block_name ] ) ) {
				$atf_block_assets_list[ $block_name ] = $block_assets_list[ $block_name ];
				unset( $btf_block_assets_list[ $block_name ] );
			}
		}

		return [ $atf_block_assets_list, $btf_block_assets_list ];
	}

	/**
	 * Merge preset features into early attributes.
	 *
	 * This prevents late generation from being triggered when the same preset features are detected again.
	 * We don't write to postmeta here - that happens once at the end in get_late_attributes().
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private function _merge_preset_features_to_early_attributes(): void {
		if ( empty( $this->_store->feature()->presets_feature_used ) ) {
			return;
		}

		// Initialize _early_attributes as an array if it doesn't exist yet.
		if ( ! is_array( $this->_store->detection()->early_attributes ) ) {
			$this->_store->detection()->early_attributes = [];
		}

		// Merge preset features into _early_attributes (preset features take precedence).
		// Only merge non-empty values - empty arrays mean "not found in presets" and shouldn't block content detection.
		$non_empty_preset_features = DynamicAssetsUtils::filter_meaningful_features( $this->_store->feature()->presets_feature_used );
		if ( ! empty( $non_empty_preset_features ) ) {
			$this->_store->detection()->early_attributes = array_replace_recursive( $this->_store->detection()->early_attributes, $non_empty_preset_features );
		}
	}

	/**
	 * Build early modules list from blocks and shortcodes.
	 *
	 * @since ??
	 *
	 * @return array Array of early module names.
	 */
	private function _build_early_modules(): array {
		if ( empty( $this->_store->detection()->early_shortcodes ) ) {
			return $this->_store->detection()->early_blocks;
		}

		// Convert shortcode names to block names for asset management.
		return array_unique(
			array_merge(
				$this->_store->detection()->early_blocks,
				array_map( [ DynamicAssetsUtils::class, 'get_block_name_from_shortcode' ], $this->_store->detection()->early_shortcodes )
			)
		);
	}

	/**
	 * Check if the current page has reCaptcha-enabled modules.
	 *
	 * Public wrapper for detection class method.
	 *
	 * @since ??
	 *
	 * @return bool True if reCaptcha-enabled modules found, false otherwise.
	 */
	public function has_recaptcha_enabled(): bool {
		return $this->_detection->has_recaptcha_enabled();
	}

	/**
	 * Get a cached feature detection result from detection state.
	 *
	 * This method provides access to cached feature detection results stored in
	 * detection_state->early_attributes, allowing other classes (like StaticCSS)
	 * to access feature detection results without duplicating detection logic.
	 *
	 * @since ??
	 *
	 * @param string $feature_name  Feature name to retrieve.
	 * @param mixed  $default_value Default value if not cached.
	 *
	 * @return mixed Cached value or default if not found.
	 */
	public function get_cached_feature_detection( string $feature_name, $default_value = false ) {
		if ( ! is_array( $this->_store->detection()->early_attributes ) || ! isset( $this->_store->detection()->early_attributes[ $feature_name ] ) ) {
			return $default_value;
		}

		$cached_value = $this->_store->detection()->early_attributes[ $feature_name ];

		// Handle array format (boolean features stored as arrays).
		if ( is_array( $cached_value ) ) {
			$filtered = array_filter( $cached_value );
			if ( ! empty( $filtered ) ) {
				// If it's a single boolean value in array, return the boolean.
				if ( 1 === count( $filtered ) && is_bool( reset( $filtered ) ) ) {
					return reset( $filtered );
				}
				// Array feature - return the full array.
				return $cached_value;
			}
			// Empty array means feature was not detected. Return default.
			return $default_value;
		}

		return $cached_value;
	}
}
