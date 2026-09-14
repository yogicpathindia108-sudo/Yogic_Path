<?php
/**
 * Dynamic Assets Enqueue Handler.
 *
 * Handles enqueuing scripts and styles for dynamic assets processing.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets\DynamicAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\Assets\DetectFeature;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\CacheState;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\DetectionState;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\EnqueueState;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\FeatureState;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\FrontEnd\Module\ScriptData;
use ET_Core_Cache_Directory;
use ET_Core_PageResource;
use Throwable;

/**
 * Dynamic Assets Enqueue class.
 *
 * Handles enqueuing scripts and styles for dynamic assets processing.
 *
 * @since ??
 * @internal Dynamic Assets owns construction and lifecycle registration for this coordinator.
 */
class DynamicAssetsEnqueue {

	/**
	 * Cache state container.
	 *
	 * @var CacheState
	 */
	private CacheState $cache_state;

	/**
	 * Detection state container.
	 *
	 * @var DetectionState
	 */
	private DetectionState $detection_state;

	/**
	 * Enqueue state container.
	 *
	 * @var EnqueueState
	 */
	private EnqueueState $enqueue_state;

	/**
	 * Feature state container.
	 *
	 * @var FeatureState
	 */
	private FeatureState $feature_state;

	/**
	 * Content handler.
	 *
	 * @var DynamicAssetsContent
	 */
	private DynamicAssetsContent $content;

	/**
	 * Detection handler.
	 *
	 * @var DynamicAssetsDetection
	 */
	private DynamicAssetsDetection $detection;

	/**
	 * Dependency checker.
	 *
	 * @var DynamicAssetsDependencyChecker
	 */
	private DynamicAssetsDependencyChecker $dependency_checker;

	/**
	 * Request-scoped integration asset provider collection.
	 *
	 * @var IntegrationAssetsProviderCollection
	 */
	private IntegrationAssetsProviderCollection $integration_assets_provider_collection;

	/**
	 * Constructor.
	 *
	 * @since ??
	 *
	 * @param CacheState                     $cache_state        Cache state container.
	 * @param DetectionState                 $detection_state    Detection state container.
	 * @param EnqueueState                   $enqueue_state      Enqueue state container.
	 * @param FeatureState                   $feature_state      Feature state container.
	 * @param DynamicAssetsContent           $content            Content handler.
	 * @param DynamicAssetsDetection         $detection          Detection handler.
	 * @param DynamicAssetsDependencyChecker $dependency_checker Dependency checker.
	 * @param IntegrationAssetsProviderCollection|null $integration_assets_provider_collection Optional provider collection. Production
	 *                                                                                construction supplies the request collection.
	 *                                                                                Omission creates an empty collection.
	 */
	public function __construct(
		CacheState $cache_state,
		DetectionState $detection_state,
		EnqueueState $enqueue_state,
		FeatureState $feature_state,
		DynamicAssetsContent $content,
		DynamicAssetsDetection $detection,
		DynamicAssetsDependencyChecker $dependency_checker,
		?IntegrationAssetsProviderCollection $integration_assets_provider_collection = null
	) {
		$this->cache_state        = $cache_state;
		$this->detection_state    = $detection_state;
		$this->enqueue_state      = $enqueue_state;
		$this->feature_state      = $feature_state;
		$this->content            = $content;
		$this->detection          = $detection;
		$this->dependency_checker = $dependency_checker;
		$this->integration_assets_provider_collection = $integration_assets_provider_collection ?? new IntegrationAssetsProviderCollection();
	}

	/**
	 * Get script configurations for unified enqueuing.
	 *
	 * Returns an array of all scripts that need to be enqueued, with their detection methods.
	 * This allows a single unified system to handle both early and late detection.
	 *
	 * @since ??
	 *
	 * @return array Array of script configurations.
	 */
	private function _get_script_configurations(): array {
		return [
			// Feature detection map scripts.
			'motion_effects'           => [
				'enqueue_state_prop' => 'motion_effects',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_scroll_script' ],
				'feature_name'       => 'scroll_effects_enabled',
				'late_flag'          => 'late_use_motion_effect',
			],
			'sticky'                   => [
				'enqueue_state_prop' => 'sticky',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_sticky_script' ],
				'feature_name'       => 'sticky_position_enabled',
				'late_flag'          => 'late_use_sticky',
			],
			'animation'                => [
				'enqueue_state_prop' => 'animation',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_animation_script' ],
				'feature_name'       => 'animation_style',
				'late_flag'          => 'late_use_animation_style',
				'script_data_check'  => 'animation',
			],
			'interactions'             => [
				'enqueue_state_prop' => 'interactions',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_interactions_script' ],
				'feature_name'       => 'interactions_enabled',
				'script_data_check'  => 'interactions',
			],
			'link'                     => [
				'enqueue_state_prop' => 'link',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_link_script' ],
				'feature_name'       => 'link_enabled',
				'late_flag'          => 'late_use_link',
			],
			// Module dependency scripts.
			'before_after_image'       => [
				'enqueue_state_prop' => 'before_after_image',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_before_after_image_script' ],
				'module_deps'        => [ 'divi/before-after-image' ],
			],
			'circle_counter'           => [
				'enqueue_state_prop' => 'circle_counter',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_circle_counter_script' ],
				'module_deps'        => [ 'divi/circle-counter' ],
			],
			'number_counter'           => [
				'enqueue_state_prop' => 'number_counter',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_number_counter_script' ],
				'module_deps'        => [ 'divi/number-counter' ],
			],
			'charts'                   => [
				'enqueue_state_prop' => 'charts',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_charts_script' ],
				'module_deps'        => [ 'divi/charts' ],
			],
			'contact_form'             => [
				'enqueue_state_prop' => 'contact_form',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_contact_form_script' ],
				'module_deps'        => [ 'divi/contact-form' ],
			],
			'post_filter_item'         => [
				'enqueue_state_prop' => 'post_filter_item',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_post_filter_item_script' ],
				'module_deps'        => [
					'divi/post-filter-item',
					'divi/post-filter',
				],
				'content_contains'   => [
					'et_pb_post_filter__item-range',
					'et_pb_post_filter__item-multiple-order',
				],
			],
			'post_filter'              => [
				'enqueue_state_prop' => 'post_filter',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_post_filter_script' ],
				'module_deps'        => [ 'divi/post-filter' ],
				'content_contains'   => 'data-apply-mode="auto"',
			],
			'dropdown'                 => [
				'enqueue_state_prop' => 'dropdown',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_dropdown_script' ],
				'module_deps'        => [ 'divi/dropdown' ],
			],
			'woocommerce_cart_totals'  => [
				'enqueue_state_prop' => 'woocommerce_cart_totals',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_woocommerce_cart_totals_script' ],
				'module_deps'        => [ 'divi/woocommerce-cart-totals' ],
			],
			'signup'                   => [
				'enqueue_state_prop' => 'signup',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_signup_script' ],
				'module_deps'        => [ 'divi/signup' ],
			],
			'tooltip'                  => [
				'enqueue_state_prop' => 'tooltip',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_tooltip_script' ],
				'module_deps'        => [ 'divi/tooltip' ],
			],
			'table_of_contents'        => [
				'enqueue_state_prop' => 'table_of_contents',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_table_of_contents_script' ],
				'module_deps'        => [ 'divi/table-of-contents' ],
			],
			'lottie'                   => [
				'enqueue_state_prop' => 'lottie',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_lottie_script' ],
				'module_deps'        => [ 'divi/lottie' ],
			],
			'group_carousel'           => [
				'enqueue_state_prop' => 'group_carousel',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_group_carousel_script' ],
				'module_deps'        => [ 'divi/group-carousel' ],
			],
			'woocommerce_cart_scripts' => [
				'enqueue_state_prop' => 'woocommerce_cart_scripts',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_woocommerce_cart_scripts' ],
				'module_deps'        => [ 'divi/woocommerce-cart-products' ],
			],
			'magnific_popup'           => [
				'enqueue_state_prop' => 'magnific_popup',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_magnific_popup_script' ],
				'feature_name'       => 'lightbox',
				'module_deps'        => [
					'divi/gallery',
					'core/gallery',
					'divi/instagram-feed',
					'divi/woocommerce-product-gallery',
				],
				'late_flag'          => 'late_show_in_lightbox',
			],
			'fitvids'                  => [
				'enqueue_state_prop' => 'fitvids',
				'enqueue_function'   => [ DynamicAssetsUtils::class, 'enqueue_fitvids_script' ],
				'feature_name'       => 'media_embedded_in_content',
				'module_deps'        => [
					'divi/blog',
					'divi/slider',
					'divi/video',
					'divi/video-slider',
					'divi/slide-video',
					'divi/code',
					'divi/fullwidth-code',
					'divi/portfolio',
					'divi/filterable-portfolio',
				],
			],
		];
	}

	/**
	 * Unified script enqueuing method.
	 *
	 * Handles both feature detection map scripts and module dependency scripts.
	 * Can be called in both early and late phases - automatically skips already-enqueued scripts.
	 *
	 * @since ??
	 *
	 * @param string $script_key      Key from script configurations.
	 * @param array  $current_modules Current modules array for dependency checking.
	 *
	 * @return void
	 */
	private function _enqueue_script_unified( string $script_key, array $current_modules ): void {
		$configs = $this->_get_script_configurations();

		if ( ! isset( $configs[ $script_key ] ) ) {
			return;
		}

		$config = $configs[ $script_key ];

		// Skip if already enqueued.
		if ( $this->enqueue_state->{$config['enqueue_state_prop']} ) {
			return;
		}

		/*
		 * When JS on demand is disabled (VB app / Preview paths), enqueue without
		 * use-based module or feature detection so VB detection stays off.
		 * Still mark enqueue state so early/late passes honor the skip guard and
		 * do not re-run deregister/register helpers in the same request.
		 */
		if ( DynamicAssetsUtils::disable_js_on_demand() ) {
			/*
			 * Keep WooCommerce cart scripts dependency-gated in the VB app window.
			 * Loading them without a cart module can trigger third-party cart refresh
			 * loops that reload the builder iframe.
			 */
			if (
				'woocommerce_cart_scripts' === $script_key
				&& Conditions::is_vb_app_window()
				&& ! $this->dependency_checker->check_for_dependency( $config['module_deps'], $current_modules )
			) {
				return;
			}

			call_user_func( $config['enqueue_function'] );
			$this->enqueue_state->{$config['enqueue_state_prop']} = true;
			return;
		}

		$should_enqueue = false;

		// Check module dependencies if provided.
		if ( ! empty( $config['module_deps'] ) ) {
			$should_enqueue = $this->dependency_checker->check_for_dependency(
				$config['module_deps'],
				$current_modules
			);
		}

		// Check rendered markup marker in page content if provided.
		if ( ! $should_enqueue && ! empty( $config['content_contains'] ) ) {
			$content         = $this->content->get_all_content();
			$content_markers = is_array( $config['content_contains'] ) ? $config['content_contains'] : [ $config['content_contains'] ];

			foreach ( $content_markers as $marker ) {
				if ( ! empty( $content ) && str_contains( $content, $marker ) ) {
					$should_enqueue = true;
					break;
				}
			}
		}

		// Check feature detection map (cached value) if provided.
		if ( ! $should_enqueue && ! empty( $config['feature_name'] ) ) {
			$feature_detection_map = DynamicAssetsUtils::get_feature_detection_map( $this->detection_state->options );
			if ( isset( $feature_detection_map[ $config['feature_name'] ] ) ) {
				$detection_callback = $feature_detection_map[ $config['feature_name'] ]['callback'];
				$callback_args      = array_merge(
					[ $this->content->get_all_content() ],
					$feature_detection_map[ $config['feature_name'] ]['additional_args'] ?? []
				);

				$should_enqueue = $this->detection->get_cached_or_detect_feature(
					$config['feature_name'],
					$detection_callback,
					$callback_args
				);
			}
		}

		// Check late use flag if provided.
		if ( ! $should_enqueue && ! empty( $config['late_flag'] ) ) {
			$late_flag_prop = $config['late_flag'];
			$should_enqueue = $this->feature_state->{$late_flag_prop} ?? false;
		}

		// Check script data if provided (e.g., for interactions from presets).
		if ( ! $should_enqueue && ! empty( $config['script_data_check'] ) ) {
			$script_data    = ScriptData::get_data( $config['script_data_check'] );
			$should_enqueue = ! empty( $script_data );
		}

		// Set enqueue state if detected.
		if ( $should_enqueue ) {
			$this->enqueue_state->{$config['enqueue_state_prop']} = true;
		}

		$enqueue_flag = $this->enqueue_state->{$config['enqueue_state_prop']};

		/*
		 * Avoid loading `wc-cart` in VB app window when it was not explicitly required by
		 * module dependencies. In this context, forcing `wc-cart` via JS-on-demand fallback
		 * can trigger third-party cart refresh loops and reload the builder iframe.
		 */
		if ( 'woocommerce_cart_scripts' === $script_key && Conditions::is_vb_app_window() && ! $enqueue_flag ) {
			return;
		}

		// Enqueue script if needed.
		if ( $this->dependency_checker->should_enqueue( $enqueue_flag ) ) {
			call_user_func( $config['enqueue_function'] );
		}
	}

	/**
	 * Enqueue all scripts using unified system.
	 *
	 * Calls the unified enqueuing method for all configured scripts.
	 * Works in both early and late phases.
	 *
	 * @since ??
	 *
	 * @param array $current_modules Current modules array for dependency checking.
	 *
	 * @return void
	 */
	private function _enqueue_all_scripts( array $current_modules ): void {
		$configs = $this->_get_script_configurations();

		foreach ( array_keys( $configs ) as $script_key ) {
			$this->_enqueue_script_unified( $script_key, $current_modules );
		}
	}

	/**
	 * Enqueue dynamic assets (CSS/JS files).
	 *
	 * @since 4.10.0
	 */
	public function enqueue_dynamic_assets(): void {
		// Dynamic frontend assets are not required in the VB top window.
		if ( Conditions::is_vb_top_window() ) {
			return;
		}

		$dynamic_assets = $this->get_dynamic_assets_files();

		if ( empty( $dynamic_assets ) || ! DynamicAssetsUtils::use_dynamic_assets() ) {
			return;
		}

		$body = [];
		$head = [];

		$cache_dir = ET_Core_Cache_Directory::instance();
		$base_url  = $cache_dir->url;
		$base_path = $cache_dir->path;

		$version = ET_BUILDER_PRODUCT_VERSION;

		foreach ( $dynamic_assets as $dynamic_asset ) {
			// Ignore empty files.
			$abs_file = str_replace( $base_url, $base_path, $dynamic_asset );
			if ( 0 === et_()->WPFS()->size( $abs_file ) ) {
				continue;
			}

			$type     = pathinfo( wp_parse_url( $dynamic_asset, PHP_URL_PATH ), PATHINFO_EXTENSION );
			$filename = pathinfo( wp_parse_url( $dynamic_asset, PHP_URL_PATH ), PATHINFO_FILENAME );
			$filepath = et_()->path( $this->cache_state->cache_dir_path, $this->cache_state->folder_name, "{$filename}.{$type}" );

			// Bust PHP's stats cache for the resource file to ensure we get the latest timestamp.
			clearstatcache( true, $filepath );

			$filetime    = filemtime( $filepath );
			$version     = $filetime ? $filetime : ET_BUILDER_PRODUCT_VERSION;
			$is_late     = str_contains( $filename, 'late' );
			$is_critical = str_contains( $filename, 'critical' );
			$is_css      = 'css' === $type;
			$late_slug   = true === $is_late ? '-late' : '';

			$deps   = [ $this->get_style_css_handle() ];
			$handle = $this->cache_state->owner . '-dynamic' . $late_slug;

			if ( wp_style_is( $handle ) ) {
				continue;
			}

			$in_footer = str_contains( $dynamic_asset, 'footer' );

			$asset = (object) [
				'type'      => $type,
				'src'       => $dynamic_asset,
				'deps'      => $deps,
				'in_footer' => $is_css ? 'all' : $in_footer,
			];

			if ( $is_critical ) {
				$body[ $handle ] = $asset;
			} else {
				$head[ $handle ] = $asset;
			}
		}

		// Enqueue inline styles.
		if ( ! empty( $body ) ) {
			$this->cache_state->enqueued_assets = (object) [
				'head' => $head,
				'body' => $body,
			];

			$cache_dir = ET_Core_Cache_Directory::instance();
			$path      = $cache_dir->path;
			$url       = $cache_dir->url;
			$styles    = '';
			$handle    = '';

			foreach ( $this->cache_state->enqueued_assets->body as $handle => $asset ) {
				$file    = str_replace( $url, $path, $asset->src );
				$styles .= et_()->WPFS()->get_contents( $file );
			}

			$handle .= '-critical';

			// Create empty style which will enqueue no external file but still allow us
			// to add inline content to it.
			wp_register_style( $handle, false, [ $this->get_style_css_handle() ], $version );
			wp_enqueue_style( $handle );
			wp_add_inline_style( $handle, $styles );

			add_filter( 'style_loader_tag', [ $this, 'defer_head_style' ], 10, 4 );
		}

		// Enqueue styles.
		foreach ( $head as $handle => $asset ) {
			$is_css           = 'css' === $asset->type;
			$enqueue_function = $is_css ? 'wp_enqueue_style' : 'wp_enqueue_script';

			$enqueue_function(
				$handle,
				$asset->src,
				$asset->deps,
				$version,
				$asset->in_footer
			);
		}
	}

	/**
	 * Print deferred styles in the head.
	 *
	 * @since ??
	 *
	 * @param string $tag    The link tag for the enqueued style.
	 * @param string $handle The style's registered handle.
	 * @param string $href   The stylesheet's source URL.
	 * @param string $media  The stylesheet's media attribute.
	 *
	 * @return string
	 */
	public function defer_head_style( string $tag, string $handle, string $href, string $media ): string {
		if ( empty( $this->cache_state->enqueued_assets->head[ $handle ] ) ) {
			// Ignore assets not enqueued by this class.
			return $tag;
		}

		// Use 'prefetch' when Mod PageSpeed is detected because it removes 'preload' links.
		$rel = et_builder_is_mod_pagespeed_enabled() ? 'prefetch' : 'preload';

		/* This filter is documented in includes/builder-5/server/FrontEnd/Assets/CriticalCSS.php */
		$rel = apply_filters( 'divi_frontend_assets_ctitical_css_deferred_styles_rel', $rel );

		return sprintf(
			"<link rel='%s' id='%s-css' href='%s' as='style' media='%s' onload=\"%s\" />\n",
			$rel,
			$handle,
			$href,
			$media,
			"this.onload=null;this.rel='stylesheet'"
		);
	}

	/**
	 * Inject fallback assets when needed.
	 * We don't know what content might appear on blog module pagination.
	 * Fallback .css is injected on these pages.
	 *
	 * @since ??
	 * @return void
	 */
	public function maybe_inject_fallback_dynamic_assets(): void {
		if ( ! $this->_is_cachable_request() ) {
			return;
		}

		// Check cache first, then fallback to detection if needed.
		$has_excerpt_content_on = $this->detection->get_cached_or_detect_feature(
			'excerpt_content_on',
			[ DetectFeature::class, 'has_excerpt_content_on' ],
			[ $this->content->get_all_content(), $this->detection_state->options ]
		);

		$show_content = $has_excerpt_content_on || $this->feature_state->late_show_content;

		// Update cache if late detection changed the value.
		// Note: excerpt_content_on is already cached in detection_state->early_attributes.
		// We update it here only if late_show_content flag changes the final result.
		if ( $show_content && ! $has_excerpt_content_on ) {
			if ( ! is_array( $this->detection_state->early_attributes ) ) {
				$this->detection_state->early_attributes = [];
			}
			$this->detection_state->early_attributes['excerpt_content_on'] = [ true ];
		}

		if ( in_array( 'divi/blog', $this->detection_state->all_modules, true ) && $show_content ) {
			$assets_path   = DynamicAssetsUtils::get_dynamic_assets_path( true );
			$fallback_file = "{$assets_path}/css/_fallback{$this->cache_state->cpt_suffix}{$this->cache_state->rtl_suffix}.css";

			// Inject the fallback assets into `<head>`.
			?>
			<script type="application/javascript">
				(function() {
					var fallback_styles = <?php echo wp_json_encode( $fallback_file ); ?>;
					var pagination_link = document.querySelector('.et_pb_ajax_pagination_container .wp-pagenavi a,.et_pb_ajax_pagination_container .pagination a');

					if (pagination_link && fallback_styles.length) {
						pagination_link.addEventListener('click', function(event) {
							if (0 === document.querySelectorAll('link[href="' + fallback_styles + '"]').length) {
								var link = document.createElement('link');
								link.rel = "stylesheet";
								link.id = 'et-dynamic-fallback-css';
								link.href = fallback_styles;

								document.getElementsByTagName('head')[0].appendChild(link);
							}
						});
					}
				})();
			</script>
			<?php
		}
	}

	/**
	 * Inject late dynamic assets when needed.
	 * If late .css files exist, we need to grab them and
	 * inject them in the head.
	 *
	 * @since ??
	 * @return void
	 */
	public function maybe_inject_late_dynamic_assets(): void {
		if ( ! $this->_is_cachable_request() ) {
			return;
		}

		$late_assets         = [];
		$late_files          = (array) glob( "{$this->cache_state->cache_dir_path}/{$this->cache_state->folder_name}/et-{$this->cache_state->owner}-dynamic*late*" );
		$style_handle        = $this->get_style_css_handle();
		$inline_style_suffix = et_core_is_inline_stylesheet_enabled() ? '-inline' : '';

		if ( ! empty( $late_files ) ) {
			foreach ( $late_files as $file ) {
				$file_path       = et_()->normalize_path( $file );
				$late_asset_url  = esc_url_raw( et_()->path( $this->cache_state->cache_dir_url, $this->cache_state->folder_name, basename( $file_path ) ) );
				$late_asset_size = filesize( $file_path );

				if ( $late_asset_size ) {
					$late_assets[] = $late_asset_url;
				}
			}
		}

		// Don't inject empty files.
		if ( ! $late_assets ) {
			return;
		}

		// Inject the late assets into `<head>`.
		?>
		<script type="application/javascript">
			(function() {
				var file = <?php echo wp_json_encode( $late_assets ); ?>;
				var handle = document.getElementById('<?php echo esc_html( $style_handle . $inline_style_suffix . '-css' ); ?>');
				var location = handle.parentNode;

				if (0 === document.querySelectorAll('link[href="' + file + '"]').length) {
					var link = document.createElement('link');
					link.rel = 'stylesheet';
					link.id = 'et-dynamic-late-css';
					link.href = file;

					location.insertBefore(link, handle.nextSibling);
				}
			})();
		</script>
		<?php
	}

	/**
	 * Whether dynamic script enqueue may run for this request.
	 *
	 * Generation-off FE requests stay blocked (#50848). Preview / Customizer and
	 * the VB app window are allowed so scripts can enqueue without detection.
	 * Detection, CSS, and cache generation remain gated elsewhere
	 * (`should_initiate_dynamic_assets` / `should_generate_dynamic_assets`).
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private function _may_enqueue_dynamic_scripts(): bool {
		if ( DynamicAssetsUtils::should_generate_dynamic_assets() ) {
			return true;
		}

		// Theme Customizer and Preview keep script enqueue when generation is off.
		if ( is_customize_preview() || is_preview() || is_et_pb_preview() ) {
			return true;
		}

		// VB app window: enqueue scripts only; keep calculation/CSS/cache off.
		if ( Conditions::is_vb_app_window() ) {
			return true;
		}

		return false;
	}

	/**
	 * Coordinate frontend integration/plugin asset enqueue through Dynamic Assets ownership.
	 *
	 * Dynamic Assets owns early/late detection timing and content assembly. Integrations retain
	 * ownership of selecting and enqueueing their own plugin/add-on assets from supplied content.
	 *
	 * Visual Builder windows are skipped because the top window is only the builder shell and the
	 * app/canvas window relies on preview-route style injection instead of pre-head asset enqueue.
	 *
	 * @since ??
	 *
	 * @param string $request_type Whether this is the early or late pass.
	 *
	 * @return void
	 */
	private function _maybe_enqueue_frontend_integration_resources( string $request_type = 'early' ): void {
		if ( Conditions::is_vb_top_window() ) {
			return;
		}

		if ( Conditions::is_vb_app_window() ) {
			return;
		}

		$available_providers = $this->integration_assets_provider_collection->get_available_providers();

		if ( [] === $available_providers ) {
			return;
		}

		$content = $this->_get_frontend_integration_enqueue_content( $request_type );

		if ( '' === $content ) {
			return;
		}

		$context = new IntegrationAssetsContext( $content, $request_type );

		foreach ( $available_providers as $provider_id => $provider ) {
			try {
				$provider->enqueue( $context );
			} catch ( Throwable $error ) {
				$this->integration_assets_provider_collection->disable( $provider_id );

				_doing_it_wrong(
					__METHOD__,
					sprintf(
						// Translators: 1: integration provider ID; 2: request phase; 3: exception class.
						esc_html__( 'Integration asset provider "%1$s" failed during the %2$s pass and was disabled for this request (%3$s).', 'et_builder_5' ),
						esc_html( $provider_id ),
						esc_html( $request_type ),
						esc_html( get_class( $error ) )
					),
					'5.11.0'
				);
			}
		}
	}

	/**
	 * Assemble frontend integration content input from Dynamic Assets-owned state.
	 *
	 * Early pass: ordinary requests return `DynamicAssetsContent::get_all_content()` directly. Special
	 * and non-singular requests additionally inspect applicable Theme Builder layouts; archive, home,
	 * and search requests additionally inspect main-query posts not already represented in DA content.
	 * Late pass uses only genuine `late_block_content` and returns immediately when it is empty so it
	 * never reconsiders early-only sources.
	 *
	 * @since ??
	 *
	 * @param string $request_type Whether this is the early or late pass.
	 *
	 * @return string
	 */
	private function _get_frontend_integration_enqueue_content( string $request_type ): string {
		if ( 'late' === $request_type ) {
			return $this->detection_state->late_block_content;
		}

		$content_sources = [];
		$all_content     = $this->content->get_all_content();

		if ( '' !== $all_content ) {
			$content_sources[] = $all_content;
		}

		$is_404_or_archive = is_404() || is_archive();
		$is_query_index    = is_archive() || is_home() || is_search();

		/*
		 * DA content already represents every ordinary singular request. Only special/non-singular
		 * contexts can omit override-only layouts or main-query posts, so return before walking either
		 * source regardless of whether all-content contains a direct integration marker.
		 */
		if ( ! $is_404_or_archive && ! $is_query_index ) {
			return $all_content;
		}

		/*
		 * On special/non-singular requests, active Theme Builder layouts may be absent from assembled DA
		 * content. The template ID helper also includes disabled override-only layouts for 404/archive.
		 * Read applicable layout content so frontend integration assets remain pre-head, including when
		 * generation is disabled and cache-state template IDs were never initialized.
		 */
		foreach ( DynamicAssetsUtils::get_theme_builder_template_ids() as $template_id ) {
			$template_post    = get_post( (int) $template_id );
			$template_content = $template_post instanceof \WP_Post ? (string) $template_post->post_content : '';

			if ( '' !== $template_content && ! str_contains( $all_content, $template_content ) ) {
				$content_sources[] = $template_content;
			}
		}

		if ( $is_query_index ) {
			global $wp_query;

			// DA's non-singular content may omit posts an archive/blog/search index will render.
			if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
				foreach ( $wp_query->posts as $queried_post ) {
					$queried_content = $queried_post instanceof \WP_Post ? (string) $queried_post->post_content : '';

					if ( '' !== $queried_content && ! str_contains( $all_content, $queried_content ) ) {
						$content_sources[] = $queried_content;
					}
				}
			}
		}

		return implode( "\n", $content_sources );
	}

	/**
	 * Enqueue early dynamic JavaScript files.
	 *
	 * @since ??
	 */
	public function enqueue_dynamic_scripts_early(): void {
		/*
		 * `_may_enqueue_dynamic_scripts()` blocks normal generation-disabled frontend requests.
		 * `pre_initial_setup()` still assembles DA content in that case, so frontend integration assets
		 * remain coordinated here before the gate returns.
		 */
		if ( ! $this->_may_enqueue_dynamic_scripts() ) {
			$this->_maybe_enqueue_frontend_integration_resources( 'early' );
			return;
		}

		$this->enqueue_dynamic_scripts();
	}

	/**
	 * Enqueue late dynamic JavaScript files.
	 *
	 * @since 4.10.0
	 */
	public function enqueue_dynamic_scripts_late(): void {
		// Skip processing for non-content requests (static files, etc.).
		// However, allow script enqueuing in Theme Customizer, Preview, and VB
		// app window even though dynamic asset generation is disabled there.
		$is_preview_mode    = is_customize_preview() || is_preview() || is_et_pb_preview();
		$is_vb_app_window   = Conditions::is_vb_app_window();
		$is_dynamic_request = DynamicAssetsUtils::is_dynamic_front_end_request();

		if ( ! $is_dynamic_request && ! $is_preview_mode && ! $is_vb_app_window ) {
			return;
		}

		if ( ! $this->_may_enqueue_dynamic_scripts() ) {
			$this->_maybe_enqueue_frontend_integration_resources( 'late' );
			return;
		}

		$this->enqueue_dynamic_scripts( 'late' );
	}

	/**
	 * Enqueue dynamic JavaScript files.
	 *
	 * @since ??
	 *
	 * @param string $request_type whether early or late request.
	 */
	public function enqueue_dynamic_scripts( string $request_type = 'early' ): void {
		if ( ! in_array( $request_type, [ IntegrationAssetsContext::PHASE_EARLY, IntegrationAssetsContext::PHASE_LATE ], true ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Dynamic Assets scripts can only be enqueued for the "early" or "late" phase.', 'et_builder_5' ),
				'5.11.0'
			);

			return;
		}

		// Dynamic frontend scripts are not required in the VB top window.
		if ( Conditions::is_vb_top_window() ) {
			return;
		}

		if ( ! et_builder_is_frontend_or_builder() ) {
			return;
		}

		// Ensure _presets_feaure_used is set before late detection checks.
		// Skip content probes when JS on demand is disabled (VB app / Preview).
		if (
			'late' === $request_type
			&& empty( $this->feature_state->presets_feature_used )
			&& ! DynamicAssetsUtils::disable_js_on_demand()
		) {
			$this->feature_state->presets_feature_used = $this->detection->presets_feature_used( $this->content->get_all_content() );
		}

		$current_modules = 'late' === $request_type ? $this->detection_state->all_modules : $this->detection_state->early_modules;

		$this->_maybe_enqueue_frontend_integration_resources( $request_type );

		// Handle comments script.
		if ( ! $this->enqueue_state->comments ) {
			$comments_deps = [
				'divi/comments',
			];

			/*
			 * Only enqueue comments script for the product reviews module if woocommerce plugin is active.
			 */
			if ( et_is_woocommerce_plugin_active() ) {
				$comments_deps[] = 'divi/woocommerce-product-reviews';
			}

			$this->enqueue_state->comments = $this->dependency_checker->check_for_dependency( $comments_deps, $current_modules );

			// If comments module is found, enqueued scripts.
			// If this is a post with the builder disabled and comments enabled, enqueue scripts.
			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->comments ) || ( ( is_single() || is_page() || is_home() ) && comments_open() && ! $this->cache_state->page_builder_used ) ) {
				wp_enqueue_script( 'comment-reply' );
				DynamicAssetsUtils::enqueue_comments_script();
			}
		}

		// Handle jQuery mobile script.
		if ( ! $this->enqueue_state->jquery_mobile ) {
			$jquery_mobile_deps = [
				'divi/portfolio',
				'divi/slider',
				'divi/post-slider',
				'divi/fullwidth-slider',
				'divi/fullwidth-post-slider',
				'divi/video-slider',
				'divi/slide',
				'divi/tabs',
				'divi/woocommerce-product-tabs',
			];

			$this->enqueue_state->jquery_mobile = $this->dependency_checker->check_for_dependency( $jquery_mobile_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->jquery_mobile ) ) {
				DynamicAssetsUtils::enqueue_jquery_mobile_script();
			}
		}

		// Handle toggle module script.
		if ( ! $this->enqueue_state->toggle ) {
			$toggle_deps = [
				'divi/toggle',
				'divi/accordion',
			];

			$this->enqueue_state->toggle = $this->dependency_checker->check_for_dependency( $toggle_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->toggle ) ) {
				DynamicAssetsUtils::enqueue_toggle_script();
			}
		}

		// Handle audio module script.
		if ( ! $this->enqueue_state->audio ) {
			$audio_deps = [
				'divi/audio',
			];

			// Check if Blog module is present - always enqueue audio script for Blog module
			// since it can contain audio post formats that need MediaElement.js initialization.
			$has_blog_module = $this->dependency_checker->check_for_dependency( [ 'divi/blog' ], $current_modules );

			$this->enqueue_state->audio = $this->dependency_checker->check_post_format_dependency( 'audio' ) || $this->dependency_checker->check_for_dependency( $audio_deps, $current_modules ) || $has_blog_module;

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->audio ) ) {
				DynamicAssetsUtils::enqueue_audio_script();
			}
		}

		// Handle video overlay script.
		if ( ! $this->enqueue_state->video_overlay ) {
			$video_overlay_deps = [
				'divi/video',
				'divi/video-slider',
				'divi/blog',
			];

			$this->enqueue_state->video_overlay = $this->dependency_checker->check_post_format_dependency( 'video' ) || $this->dependency_checker->check_for_dependency( $video_overlay_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->video_overlay ) ) {
				DynamicAssetsUtils::enqueue_video_overlay_script();
			}
		}

		// Handle video lazy load script.
		// Only the dependency state is latched here; the actual script enqueue is
		// deferred to the late phase and gated on real below-the-fold presence
		// (see `has_below_fold_script_data_item`) so pages with only above-the-fold
		// videos don't pay the helper-script overhead.
		if ( ! $this->enqueue_state->video_lazy_load ) {
			$video_lazy_load_deps = [
				'divi/video',
				'divi/video-slider',
			];

			$this->enqueue_state->video_lazy_load = $this->dependency_checker->check_for_dependency( $video_lazy_load_deps, $current_modules );
		}

		// Handle map lazy load script data.
		if ( ! $this->enqueue_state->map_lazy_load ) {
			$map_lazy_load_deps = [
				'divi/map',
				'divi/fullwidth-map',
			];

			$this->enqueue_state->map_lazy_load = $this->dependency_checker->check_for_dependency( $map_lazy_load_deps, $current_modules );
		}

		// Handle search module script.
		if ( ! $this->enqueue_state->search ) {
			$search_deps = [
				'divi/search',
			];

			$this->enqueue_state->search = $this->dependency_checker->check_for_dependency( $search_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->search ) ) {
				DynamicAssetsUtils::enqueue_search_script();
			}
		}

		// Handle woo module script.
		if ( ! $this->enqueue_state->woo ) {
			$woo_deps = DynamicAssetsUtils::woo_deps();

			$this->enqueue_state->woo = $this->dependency_checker->check_for_dependency( $woo_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->woo ) ) {
				DynamicAssetsUtils::enqueue_woo_script();
			}
		}

		// Handle fullwidth_header module script.
		if ( ! $this->enqueue_state->fullwidth_header ) {
			$fullwidth_header_deps = [
				'divi/fullwidth-header',
			];

			$this->enqueue_state->fullwidth_header = $this->dependency_checker->check_for_dependency( $fullwidth_header_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->fullwidth_header ) ) {
				DynamicAssetsUtils::enqueue_fullwidth_header_script();
			}
		}

		// Handle blog script.
		if ( ! $this->enqueue_state->blog ) {
			$blog_deps = [
				'divi/blog',
			];

			$this->enqueue_state->blog = $this->dependency_checker->check_for_dependency( $blog_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->blog ) ) {
				DynamicAssetsUtils::enqueue_blog_script();
			}
		}

		// Handle pagination script.
		if ( ! $this->enqueue_state->pagination ) {
			$pagination_deps = [
				'divi/blog',
				'divi/portfolio',
				'divi/filterable-portfolio',
			];

			$this->enqueue_state->pagination = $this->dependency_checker->check_for_dependency( $pagination_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->pagination ) ) {
				DynamicAssetsUtils::enqueue_pagination_script();
			}
		}

		// Handle fullscreen section script.
		if ( ! $this->enqueue_state->fullscreen_section ) {
			if ( ! DynamicAssetsUtils::disable_js_on_demand() ) {
				$this->enqueue_state->fullscreen_section = $this->detection->get_cached_or_detect_feature(
					'fullscreen_section_enabled',
					[ DetectFeature::class, 'has_fullscreen_section_enabled' ],
					[ $this->content->get_all_content(), $this->detection_state->options ]
				);
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->fullscreen_section ) ) {
				DynamicAssetsUtils::enqueue_fullscreen_section_script();
			}
		}

		// Handle section divider script.
		if ( ! $this->enqueue_state->section_dividers ) {
			if ( ! DynamicAssetsUtils::disable_js_on_demand() ) {
				$this->enqueue_state->section_dividers = $this->detection->get_cached_or_detect_feature(
					'section_dividers_enabled',
					[ DetectFeature::class, 'has_section_dividers_enabled' ],
					[ $this->content->get_all_content(), $this->detection_state->options ]
				);
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->section_dividers ) ) {
				DynamicAssetsUtils::enqueue_section_dividers_script();
			}
		}

		// Handle slider module script.
		if ( ! $this->enqueue_state->slider ) {
			$slider_deps = [
				'divi/slider',
				'divi/fullwidth-slider',
				'divi/post-slider',
				'divi/fullwidth-post-slider',
				'divi/video-slider',
				'divi/gallery',
				'divi/woocommerce-product-gallery',
				'divi/blog',
				'core/gallery',
			];

			$this->enqueue_state->slider = $this->dependency_checker->check_post_format_dependency( 'gallery' ) || $this->dependency_checker->check_for_dependency( $slider_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->slider ) ) {
				DynamicAssetsUtils::enqueue_slider_script();
			}
		}

		// Handle map module script.
		if ( ! $this->enqueue_state->map ) {
			$map_deps = [
				'divi/map',
				'divi/fullwidth-map',
			];

			$this->enqueue_state->map = $this->dependency_checker->check_for_dependency( $map_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->map ) ) {
				DynamicAssetsUtils::enqueue_map_script();
			}
		}

		// Handle sidebar module script.
		if ( ! $this->enqueue_state->sidebar ) {
			$sidebar_deps = [
				'divi/sidebar',
			];

			$this->enqueue_state->sidebar = $this->dependency_checker->check_for_dependency( $sidebar_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->sidebar ) ) {
				DynamicAssetsUtils::enqueue_sidebar_script();
			}
		}

		// Handle testimonial module script.
		if ( ! $this->enqueue_state->testimonial ) {
			$testimonial_deps = [
				'divi/testimonial',
			];

			$this->enqueue_state->testimonial = $this->dependency_checker->check_for_dependency( $testimonial_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->testimonial ) ) {
				DynamicAssetsUtils::enqueue_testimonial_script();
			}
		}

		// Handle tabs module script.
		if ( ! $this->enqueue_state->tabs ) {
			$tabs_deps = [
				'divi/tabs',
				'divi/woocommerce-product-tabs',
			];

			$this->enqueue_state->tabs = $this->dependency_checker->check_for_dependency( $tabs_deps, $current_modules ) || $this->dependency_checker->check_post_type_dependency( 'product' );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->tabs ) ) {
				DynamicAssetsUtils::enqueue_tabs_script();
			}
		}

		// Handle fullwidth portfolio module script.
		if ( ! $this->enqueue_state->fullwidth_portfolio ) {
			$fullwidth_portfolio_deps = [
				'divi/fullwidth-portfolio',
			];

			$this->enqueue_state->fullwidth_portfolio = $this->dependency_checker->check_for_dependency( $fullwidth_portfolio_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->fullwidth_portfolio ) ) {
				DynamicAssetsUtils::enqueue_fullwidth_portfolio_script();
			}
		}

		// Handle filterable portfolio module script.
		if ( ! $this->enqueue_state->filterable_portfolio ) {
			$filterable_portfolio_deps = [
				'divi/filterable-portfolio',
			];

			$this->enqueue_state->filterable_portfolio = $this->dependency_checker->check_for_dependency( $filterable_portfolio_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->filterable_portfolio ) ) {
				DynamicAssetsUtils::enqueue_filterable_portfolio_script();
			}
		}

		// Handle video slider module script.
		if ( ! $this->enqueue_state->video_slider ) {
			$video_slider_deps = [
				'divi/video-slider',
			];

			$this->enqueue_state->video_slider = $this->dependency_checker->check_for_dependency( $video_slider_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->video_slider ) ) {
				DynamicAssetsUtils::enqueue_video_slider_script();
			}
		}

		// Handle countdown timer module script.
		if ( ! $this->enqueue_state->countdown_timer ) {
			$countdown_timer_deps = [
				'divi/countdown-timer',
			];

			$this->enqueue_state->countdown_timer = $this->dependency_checker->check_for_dependency( $countdown_timer_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->countdown_timer ) ) {
				DynamicAssetsUtils::enqueue_countdown_timer_script();
			}
		}

		// Handle bar counter module script.
		if ( ! $this->enqueue_state->bar_counter ) {
			$bar_counter_deps = [
				'divi/bar-counter',
			];

			$this->enqueue_state->bar_counter = $this->dependency_checker->check_for_dependency( $bar_counter_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->bar_counter ) ) {
				DynamicAssetsUtils::enqueue_bar_counter_script();
			}
		}

		// Handle easy pie chart script.
		if ( ! $this->enqueue_state->easypiechart ) {
			$easypiechart_deps = [
				'divi/blog',
				'divi/circle-counter',
				'divi/number-counter',
			];

			$this->enqueue_state->easypiechart = $this->dependency_checker->check_for_dependency( $easypiechart_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->easypiechart ) ) {
				DynamicAssetsUtils::enqueue_easypiechart_script();
			}
		}

		// Handle form conditions script.
		if ( ! $this->enqueue_state->form_conditions ) {
			$form_conditions_deps = [
				'divi/signup',
				'divi/contact-form',
			];

			$this->enqueue_state->form_conditions = $this->dependency_checker->check_for_dependency( $form_conditions_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->form_conditions ) ) {
				DynamicAssetsUtils::enqueue_form_conditions_script();
			}
		}

		// Handle menu module script.
		if ( ! $this->enqueue_state->menu ) {
			$menu_deps = [
				'divi/menu',
				'divi/fullwidth-menu',
			];

			$this->enqueue_state->menu = $this->dependency_checker->check_for_dependency( $menu_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->menu ) ) {
				DynamicAssetsUtils::enqueue_menu_script();
			}
		}

		// Handle gallery module script.
		if ( ! $this->enqueue_state->gallery ) {
			$gallery_deps = [
				'divi/gallery',
				'core/gallery',
				'divi/woocommerce-product-gallery',
			];

			$this->enqueue_state->gallery = $this->dependency_checker->check_post_format_dependency( 'gallery' ) || $this->dependency_checker->check_for_dependency( $gallery_deps, $current_modules );

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->gallery ) ) {
				DynamicAssetsUtils::enqueue_gallery_script();
			}
		}

		// Handle logged in script.
		if ( ! $this->enqueue_state->logged_in ) {

			$this->enqueue_state->logged_in = is_user_logged_in();

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->logged_in ) ) {
				DynamicAssetsUtils::enqueue_logged_in_script();
			}
		}

		// Handle salvattore script - only for D4 shortcodes with block mode enabled.
		if ( ! $this->enqueue_state->salvattore ) {
			if ( ! DynamicAssetsUtils::disable_js_on_demand() ) {
				$this->enqueue_state->salvattore = $this->detection->should_enqueue_salvattore_for_shortcodes();
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->salvattore ) ) {
				DynamicAssetsUtils::enqueue_salvattore_script();
			}
		}

		// Handle split testing script.
		if ( ! $this->enqueue_state->split_testing ) {
			if ( ! DynamicAssetsUtils::disable_js_on_demand() ) {
				$this->enqueue_state->split_testing = $this->detection->get_cached_or_detect_feature(
					'split_testing_enabled',
					[ DetectFeature::class, 'has_split_testing_enabled' ],
					[ $this->content->get_all_content(), $this->detection_state->options ]
				);
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->split_testing ) ) {
				DynamicAssetsUtils::enqueue_split_testing_script();
			}
		}

		// Handle Google Maps script.
		// Detection is latched (run once per request), but the enqueue decision must
		// run in both phases: the early phase handles the JS-on-demand-disabled
		// backward-compat fallback, and the late phase reads `map_lazy_load` script
		// data (only available after modules render) to enqueue for above-the-fold
		// maps. Gating the enqueue decision on `! enqueue_state->google_maps` would
		// latch the early-phase result and prevent the late-phase above-the-fold
		// check from ever running, leaving above-the-fold maps without the script.
		if ( ! $this->enqueue_state->google_maps ) {
			$google_maps_deps = [
				'divi/map',
				'divi/fullwidth-map',
			];

			$this->enqueue_state->google_maps = $this->dependency_checker->check_for_dependency( $google_maps_deps, $current_modules );
		}

		// Check if we should enqueue Google Maps script.
		// Only enqueue if:
		// 1. Maps are detected AND at least one map is above the fold (in late phase), OR
		// 2. JS on demand is disabled (backward compatibility).
		$should_enqueue_google_maps = false;

		if ( $this->enqueue_state->google_maps && et_pb_enqueue_google_maps_script() ) {
			// Only check script data in late phase (after modules render and script data is available).
			// In early phase, skip enqueuing - let late phase handle it after checking script data.
			if ( 'late' === $request_type ) {
				$map_lazy_load_data = ScriptData::get_data( 'map_lazy_load' );
				$has_above_fold_map = false;

				// Check if any map is above the fold.
				if ( ! empty( $map_lazy_load_data ) ) {
					foreach ( $map_lazy_load_data as $map_data ) {
						if ( ! empty( $map_data['is_above_the_fold'] ) ) {
							$has_above_fold_map = true;
							break;
						}
					}
				}

				// Only enqueue if at least one map is above the fold.
				// Below-fold maps will load the script dynamically via lazy loading.
				$should_enqueue_google_maps = $has_above_fold_map;
			}
			// Early phase: Skip enqueuing - script data not available yet.
			// Late phase will check script data and enqueue if needed.
		}

		// Also enqueue if JS on demand is disabled (backward compatibility).
		if ( ! $should_enqueue_google_maps && et_pb_enqueue_google_maps_script() && DynamicAssetsUtils::disable_js_on_demand() ) {
			$should_enqueue_google_maps = true;
		}

		if ( $should_enqueue_google_maps ) {
			DynamicAssetsUtils::enqueue_google_maps_script();
			// Latch the helper run so later passes do not re-enqueue in the same request.
			$this->enqueue_state->google_maps = true;
		}

		// Enqueue all scripts using unified system (works in both early and late phases).
		// Script files can be enqueued early if detected, script-data will be enqueued in late phase.
		$this->_enqueue_all_scripts( $current_modules );

		/*
		 * Script-data must be enqueued in the `late` phase, because we need the modules to be rendered first
		 * which adds the script-data as required.
		 *
		 * Note: All scripts are enqueued using the unified system in both early and late phases.
		 * The unified system automatically skips already-enqueued scripts, so it's safe to call in both phases.
		 */
		if ( 'late' === $request_type ) {
			// Re-check for late-detected features (unified system handles this automatically).
			$this->_enqueue_all_scripts( $current_modules );

			/*
			* Script-data must be enqueued in the `late` phase, because we need the modules to be rendered first
			* which adds the script-data as required.
			*
			* Note: Animation, interactions, link, motion effects, sticky, circle counter, number counter, contact form,
			* woocommerce cart totals, signup, lottie, group carousel, tooltip, and woocommerce cart scripts are enqueued
			* in the early phase if detected early, but script-data must still be enqueued here after modules render.
			*/
			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->animation ) || $this->feature_state->late_use_animation_style ) {
				ScriptData::enqueue_data( 'animation' );
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->interactions ) ) {
				ScriptData::enqueue_data( 'interactions' );
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->motion_effects ) ) {
				ScriptData::enqueue_data( 'scroll' );
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->sticky ) ) {
				ScriptData::enqueue_data( 'sticky' );
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->link ) || $this->feature_state->late_use_link ) {
				ScriptData::enqueue_data( 'link' );
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->contact_form ) ) {
				ScriptData::enqueue_data( 'contact_form' );
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->circle_counter ) ) {
				ScriptData::enqueue_data( 'circle_counter' );
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->number_counter ) ) {
				ScriptData::enqueue_data( 'number_counter' );
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->charts ) ) {
				ScriptData::enqueue_data( 'charts' );
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->woocommerce_cart_totals ) ) {
				ScriptData::enqueue_data( 'woocommerce_cart_totals' );
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->video_lazy_load ) && $this->has_below_fold_script_data_item( 'video_lazy_load' ) ) {
				// Ensure script is enqueued before localizing script data.
				DynamicAssetsUtils::enqueue_video_lazy_load_script();
				ScriptData::enqueue_data( 'video_lazy_load' );
			}

			// Enqueue map lazy load script data if present and at least one map is below the fold.
			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->map_lazy_load ) && $this->has_below_fold_script_data_item( 'map_lazy_load' ) ) {
				ScriptData::enqueue_data( 'map_lazy_load' );
			}

			// Enqueue slider lazy load script data if slider modules are present and at least one is below the fold.
			// The slider script itself is already enqueued via the slider dependency detection.
			if ( ( $this->dependency_checker->should_enqueue( $this->enqueue_state->slider ) ||
				$this->dependency_checker->should_enqueue( $this->enqueue_state->video_slider ) ) && $this->has_below_fold_script_data_item( 'slider_lazy_load' ) ) {
				ScriptData::enqueue_data( 'slider_lazy_load' );
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->signup ) ) {
				ScriptData::enqueue_data( 'signup' );
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->table_of_contents ) ) {
				ScriptData::enqueue_data( 'table_of_contents' );
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->lottie ) ) {
				ScriptData::enqueue_data( 'lottie' );
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->group_carousel ) ) {
				ScriptData::enqueue_data( 'group_carousel' );
			}

			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->tooltip ) ) {
				ScriptData::enqueue_data( 'tooltip' );
			}

			/*
			 * Originally `et_get_combined_script_handle()` enqueues from `FrontEnd::override_d4_fe_scripts` via
			 * `wp_enqueue_scripts` hook.
			 *
			 * However, we need to dequeue and enqueue `et_get_combined_script_handle()` here because the scripts for
			 * "link", "contact form", "circle counter", "number counter" and "signup" would only work if the
			 * `et_get_combined_script_handle()` loads after these scripts been loaded.
			 *
			 * We do this during late detection to ensure proper script loading order after all scripts have been enqueued.
			 */
			if ( $this->dependency_checker->should_enqueue( $this->enqueue_state->link )
				|| $this->dependency_checker->should_enqueue( $this->enqueue_state->contact_form )
				|| $this->dependency_checker->should_enqueue( $this->enqueue_state->circle_counter )
				|| $this->dependency_checker->should_enqueue( $this->enqueue_state->number_counter )
				|| $this->dependency_checker->should_enqueue( $this->enqueue_state->signup )
			) {
				$combined_script_handle = et_get_combined_script_handle();
				wp_dequeue_script( $combined_script_handle );
				wp_enqueue_script( $combined_script_handle );
			}
		}
	}


	/**
	 * Check whether a lazy-load script data collection has at least one
	 * below-the-fold item.
	 *
	 * Used to gate lazy-load helper script/data enqueuing so the overhead is
	 * only paid when there is actually something to defer. An item is considered
	 * below the fold when its `is_above_the_fold` flag is empty/falsy (items
	 * without the flag, e.g. video items added only when deferred, count as BTF).
	 *
	 * @since ??
	 *
	 * @param string $data_name Script data name (e.g. 'video_lazy_load').
	 *
	 * @return bool
	 */
	protected function has_below_fold_script_data_item( string $data_name ): bool {
		$data = ScriptData::get_data( $data_name );

		if ( empty( $data ) ) {
			return false;
		}

		foreach ( $data as $item ) {
			if ( empty( $item['is_above_the_fold'] ) ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Get dynamic assets files.
	 *
	 * @since ??
	 *
	 * @return array|void
	 */
	public function get_dynamic_assets_files() {
		if ( ! $this->_is_cachable_request() ) {
			return;
		}

		$dynamic_assets_files = [];

		// Clear stat cache once to ensure we get the latest file information,
		// especially important when files were just regenerated.
		clearstatcache( true );

		$files = (array) glob( "{$this->cache_state->cache_dir_path}/{$this->cache_state->folder_name}/et*-dynamic*{$this->cache_state->tb_prefix}*" );

		if ( empty( $files ) ) {
			return [];
		}

		foreach ( $files as $file ) {
			$basename = basename( $file );
			// Skip .stale marker files.
			if ( str_ends_with( $basename, '.stale' ) ) {
				continue;
			}

			// Skip stale files - check if file is marked as stale.
			if ( ET_Core_PageResource::is_file_stale( $file ) ) {
				continue;
			}

			$file_path              = et_()->normalize_path( $file );
			$dynamic_assets_files[] = et_()->path( $this->cache_state->cache_dir_url, $this->cache_state->folder_name, basename( $file_path ) );
		}

		return $dynamic_assets_files;
	}

	/**
	 * Get the handle of current theme's style.css handle.
	 *
	 * @since 4.10.0
	 *
	 * @return string
	 */
	public function get_style_css_handle(): string {
		$child_theme_suffix  = is_child_theme() && ! et_is_builder_plugin_active() ? '-parent' : '';
		$inline_style_suffix = et_core_is_inline_stylesheet_enabled() ? '-inline' : '';
		$product_prefix      = $this->cache_state->owner . '-style';

		$handle = 'divi-builder-style' === $product_prefix . $inline_style_suffix ? $product_prefix : $product_prefix . $child_theme_suffix . $inline_style_suffix;

		return $handle;
	}

	/**
	 * Check if request is cacheable.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private function _is_cachable_request(): bool {
		return DynamicAssetsUtils::should_generate_dynamic_assets() && DynamicAssetsUtils::use_dynamic_assets() && $this->cache_state->folder_name;
	}
}
