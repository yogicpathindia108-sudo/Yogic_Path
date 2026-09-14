<?php
/**
 * Class BlockParser
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\FrontEnd\BlockParser;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block.

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentUtils;
use ET\Builder\Packages\Module\Layout\Components\DynamicData\DynamicData;
use ET\Builder\Packages\ModuleLibrary\Modules;
use ET\Builder\Migration\MigrationContext;
use ET\Builder\Packages\Module\Options\Loop\LoopUtils;
use WP_Block_Parser_Block;
use ET\Builder\FrontEnd\BlockParser\BlockParserBlock;
use ET\Builder\FrontEnd\BlockParser\BlockParserUtils;
use ET\Builder\FrontEnd\BlockParser\SimpleBlockParser;
use ET_Post_Stack;

/**
 * Class BlockParser
 *
 * Parses a document and constructs a list of parsed block objects
 *
 * @since ??
 */
class BlockParser extends \WP_Block_Parser {

	/**
	 * It's a property that is used to store the instance of the BlockParserStore class.
	 *
	 * @since ??
	 *
	 * @var number
	 */
	protected $_store_instance = null;

	/**
	 * An array to hold empty attributes for a block.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	public $empty_attrs = [];

	/**
	 * An array to hold the modules that have been loaded.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	protected static $_modules_loaded = null;

	/**
	 * Flag to skip Divi's custom block parsing and use WordPress's default parser.
	 *
	 * When set to true, this prevents Divi from processing blocks and delegates
	 * parsing to WordPress's native block parser. This is typically used when
	 * the content doesn't contain any Divi blocks, allowing standard WordPress
	 * blocks to be processed normally.
	 *
	 * @since ??
	 *
	 * @var bool
	 */
	private $_skip_parsing = false;

	/**
	 * Get the instance of the BlockParserStore class
	 *
	 * @since ??
	 *
	 * @return number
	 */
	public function get_store_instance() {
		return $this->_store_instance;
	}

	/**
	 * Gets the block class map list.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function get_block_class_map_list() {
		// Define the base namespace for all modules.
		$base_namespace = 'ET\Builder\Packages\ModuleLibrary\\';

		$modules = [
			'divi/accordion-item'              => $base_namespace . 'AccordionItem\AccordionItemModule',
			'divi/accordion'                   => $base_namespace . 'Accordion\AccordionModule',
			'divi/audio'                       => $base_namespace . 'Audio\AudioModule',
			'divi/before-after-image'          => $base_namespace . 'BeforeAfterImage\BeforeAfterImageModule',
			'divi/breadcrumbs'                 => $base_namespace . 'Breadcrumbs\BreadcrumbsModule',
			'divi/counter'                     => $base_namespace . 'BarCountersItem\BarCountersItemModule',
			'divi/counters'                    => $base_namespace . 'BarCounters\BarCountersModule',
			'divi/blog'                        => $base_namespace . 'Blog\BlogModule',
			'divi/blurb'                       => $base_namespace . 'Blurb\BlurbModule',
			'divi/button'                      => $base_namespace . 'Button\ButtonModule',
			'divi/payment-button'              => $base_namespace . 'PaymentButton\PaymentButtonModule',
			'divi/canvas-portal'               => $base_namespace . 'CanvasPortal\CanvasPortalModule',
			'divi/cta'                         => $base_namespace . 'CTA\CTAModule',
			'divi/circle-counter'              => $base_namespace . 'CircleCounter\CircleCounterModule',
			'divi/charts'                      => $base_namespace . 'Charts\ChartsModule',
			'divi/code'                        => $base_namespace . 'Code\CodeModule',
			'divi/column-inner'                => $base_namespace . 'ColumnInner\ColumnInnerModule',
			'divi/column'                      => $base_namespace . 'Column\ColumnModule',
			'divi/comments'                    => $base_namespace . 'Comments\CommentsModule',
			'divi/contact-field'               => $base_namespace . 'ContactField\ContactFieldModule',
			'divi/contact-form'                => $base_namespace . 'ContactForm\ContactFormModule',
			'divi/countdown-timer'             => $base_namespace . 'CountdownTimer\CountdownTimerModule',
			'divi/divider'                     => $base_namespace . 'Divider\DividerModule',
			'divi/dropdown'                    => $base_namespace . 'Dropdown\DropdownModule',
			'divi/filterable-portfolio'        => $base_namespace . 'FilterablePortfolio\FilterablePortfolioModule',
			'divi/fullwidth-code'              => $base_namespace . 'FullwidthCode\FullwidthCodeModule',
			'divi/fullwidth-header'            => $base_namespace . 'FullwidthHeader\FullwidthHeaderModule',
			'divi/fullwidth-image'             => $base_namespace . 'FullwidthImage\FullwidthImageModule',
			'divi/fullwidth-map'               => $base_namespace . 'FullwidthMap\FullwidthMapModule',
			'divi/fullwidth-menu'              => $base_namespace . 'FullwidthMenu\FullwidthMenuModule',
			'divi/fullwidth-portfolio'         => $base_namespace . 'FullwidthPortfolio\FullwidthPortfolioModule',
			'divi/fullwidth-post-content'      => $base_namespace . 'FullwidthPostContent\FullwidthPostContentModule',
			'divi/fullwidth-post-slider'       => $base_namespace . 'FullwidthPostSlider\FullwidthPostSliderModule',
			'divi/fullwidth-post-title'        => $base_namespace . 'FullwidthPostTitle\FullwidthPostTitleModule',
			'divi/fullwidth-slider'            => $base_namespace . 'FullwidthSlider\FullwidthSliderModule',
			'divi/gallery'                     => $base_namespace . 'Gallery\GalleryModule',
			'divi/group'                       => $base_namespace . 'Group\GroupModule',
			'divi/group-carousel'              => $base_namespace . 'GroupCarousel\GroupCarouselModule',
			'divi/heading'                     => $base_namespace . 'Heading\HeadingModule',
			'divi/icon'                        => $base_namespace . 'Icon\IconModule',
			'divi/icon-list'                   => $base_namespace . 'IconList\IconListModule',
			'divi/icon-list-item'              => $base_namespace . 'IconListItem\IconListItemModule',
			'divi/image'                       => $base_namespace . 'Image\ImageModule',
			'divi/instagram-feed'              => $base_namespace . 'InstagramFeed\InstagramFeedModule',
			'divi/link'                        => $base_namespace . 'Link\LinkModule',
			'divi/lottie'                      => $base_namespace . 'Lottie\LottieModule',
			'divi/login'                       => $base_namespace . 'Login\LoginModule',
			'divi/map-pin'                     => $base_namespace . 'MapItem\MapItemModule',
			'divi/map'                         => $base_namespace . 'Map\MapModule',
			'divi/menu'                        => $base_namespace . 'Menu\MenuModule',
			'divi/number-counter'              => $base_namespace . 'NumberCounter\NumberCounterModule',
			'divi/portfolio'                   => $base_namespace . 'Portfolio\PortfolioModule',
			'divi/post-content'                => $base_namespace . 'PostContent\PostContentModule',
			'divi/post-filter'                 => $base_namespace . 'PostFilter\PostFilterModule',
			'divi/post-filter-item'            => $base_namespace . 'PostFilterItem\PostFilterItemModule',
			'divi/post-nav'                    => $base_namespace . 'PostNavigation\PostNavigationModule',
			'divi/post-slider'                 => $base_namespace . 'PostSlider\PostSliderModule',
			'divi/post-title'                  => $base_namespace . 'PostTitle\PostTitleModule',
			'divi/pricing-table'               => $base_namespace . 'PricingTablesItem\PricingTablesItemModule',
			'divi/pricing-tables'              => $base_namespace . 'PricingTables\PricingTablesModule',
			'divi/row-inner'                   => $base_namespace . 'RowInner\RowInnerModule',
			'divi/row'                         => $base_namespace . 'Row\RowModule',
			'divi/search'                      => $base_namespace . 'Search\SearchModule',
			'divi/section'                     => $base_namespace . 'Section\SectionModule',
			'divi/sidebar'                     => $base_namespace . 'Sidebar\SidebarModule',
			'divi/signup-custom-field'         => $base_namespace . 'SignupCustomField\SignupCustomFieldModule',
			'divi/signup'                      => $base_namespace . 'Signup\SignupModule',
			'divi/slide'                       => $base_namespace . 'Slide\SlideModule',
			'divi/slider'                      => $base_namespace . 'Slider\SliderModule',
			'divi/svg'                         => $base_namespace . 'Svg\SvgModule',
			'divi/social-media-follow-network' => $base_namespace . 'SocialMediaFollowItem\SocialMediaFollowItemModule',
			'divi/social-media-follow'         => $base_namespace . 'SocialMediaFollow\SocialMediaFollowModule',
			'divi/tab'                         => $base_namespace . 'Tab\TabModule',
			'divi/tabs'                        => $base_namespace . 'Tabs\TabsModule',
			'divi/table-of-contents'           => $base_namespace . 'TableOfContents\TableOfContentsModule',
			'divi/team-member'                 => $base_namespace . 'TeamMember\TeamMemberModule',
			'divi/testimonial'                 => $base_namespace . 'Testimonial\TestimonialModule',
			'divi/timeline'                    => $base_namespace . 'Timeline\TimelineModule',
			'divi/timeline-item'               => $base_namespace . 'TimelineItem\TimelineItemModule',
			'divi/text'                        => $base_namespace . 'Text\TextModule',
			'divi/toggle'                      => $base_namespace . 'Toggle\ToggleModule',
			'divi/tooltip'                     => $base_namespace . 'Tooltip\TooltipModule',
			'divi/video-slider-item'           => $base_namespace . 'VideoSliderItem\VideoSliderItemModule',
			'divi/video-slider'                => $base_namespace . 'VideoSlider\VideoSliderModule',
			'divi/video'                       => $base_namespace . 'Video\VideoModule',
		];

		if ( class_exists( '\WPCF7_ContactForm' ) ) {
			$modules['divi/contact-form-7'] = $base_namespace . 'ContactForm7\ContactForm7Module';
		}

		if ( class_exists( '\GFForms' ) ) {
			$modules['divi/gravity-forms'] = $base_namespace . 'GravityForms\GravityFormsModule';
		}

		/*
		 * Imagely Gallery module (formerly NextGEN Gallery) — only register
		 * when the plugin is active. We probe the legacy `C_NextGEN_Bootstrap`
		 * class because the plugin still ships it under its original symbol
		 * name even after rebranding to Imagely.
		 */
		if ( class_exists( 'C_NextGEN_Bootstrap' ) ) {
			$modules['divi/imagely-gallery'] = $base_namespace . 'ImagelyGallery\ImagelyGalleryModule';
		}

		/*
		 * Additional WooCommerce Modules dependencies.
		 */
		if ( et_is_woocommerce_plugin_active() ) {
			$modules['divi/shop']                                 = $base_namespace . 'WooCommerce\Products\WooCommerceProductsModule';
			$modules['divi/woocommerce-breadcrumb']               = $base_namespace . 'WooCommerce\Breadcrumb\WooCommerceBreadcrumbModule';
			$modules['divi/woocommerce-cart-notice']              = $base_namespace . 'WooCommerce\CartNotice\WooCommerceCartNoticeModule';
			$modules['divi/woocommerce-product-add-to-cart']      = $base_namespace . 'WooCommerce\ProductAddToCart\WooCommerceProductAddToCartModule';
			$modules['divi/woocommerce-product-additional-info']  = $base_namespace . 'WooCommerce\ProductAdditionalInfo\WooCommerceProductAdditionalInfoModule';
			$modules['divi/woocommerce-product-description']      = $base_namespace . 'WooCommerce\ProductDescription\WooCommerceProductDescriptionModule';
			$modules['divi/woocommerce-product-gallery']          = $base_namespace . 'WooCommerce\ProductGallery\WooCommerceProductGalleryModule';
			$modules['divi/woocommerce-product-images']           = $base_namespace . 'WooCommerce\ProductImages\WooCommerceProductImagesModule';
			$modules['divi/woocommerce-product-meta']             = $base_namespace . 'WooCommerce\ProductMeta\WooCommerceProductMetaModule';
			$modules['divi/woocommerce-product-price']            = $base_namespace . 'WooCommerce\ProductPrice\WooCommerceProductPriceModule';
			$modules['divi/woocommerce-product-rating']           = $base_namespace . 'WooCommerce\ProductRating\WooCommerceProductRatingModule';
			$modules['divi/woocommerce-product-reviews']          = $base_namespace . 'WooCommerce\ProductReviews\WooCommerceProductReviewsModule';
			$modules['divi/woocommerce-product-stock']            = $base_namespace . 'WooCommerce\ProductStock\WooCommerceProductStockModule';
			$modules['divi/woocommerce-product-tabs']             = $base_namespace . 'WooCommerce\ProductTabs\WooCommerceProductTabsModule';
			$modules['divi/woocommerce-product-title']            = $base_namespace . 'WooCommerce\ProductTitle\WooCommerceProductTitleModule';
			$modules['divi/woocommerce-product-upsell']           = $base_namespace . 'WooCommerce\ProductUpsell\WooCommerceProductUpsellModule';
			$modules['divi/woocommerce-related-products']         = $base_namespace . 'WooCommerce\RelatedProducts\WooCommerceRelatedProductsModule';
			$modules['divi/woocommerce-cart-products']            = $base_namespace . 'WooCommerce\CartProducts\WooCommerceCartProductsModule';
			$modules['divi/woocommerce-cart-totals']              = $base_namespace . 'WooCommerce\CartTotals\WooCommerceCartTotalsModule';
			$modules['divi/woocommerce-checkout-additional-info'] = $base_namespace . 'WooCommerce\CheckoutInformation\WooCommerceCheckoutInformationModule';
			$modules['divi/woocommerce-checkout-billing']         = $base_namespace . 'WooCommerce\CheckoutBilling\WooCommerceCheckoutBillingModule';
			$modules['divi/woocommerce-checkout-order-details']   = $base_namespace . 'WooCommerce\CheckoutOrderDetails\WooCommerceCheckoutOrderDetailsModule';
			$modules['divi/woocommerce-checkout-payment-info']    = $base_namespace . 'WooCommerce\CheckoutPaymentInfo\WooCommerceCheckoutPaymentInfoModule';
			$modules['divi/woocommerce-checkout-shipping']        = $base_namespace . 'WooCommerce\CheckoutShipping\WooCommerceCheckoutShippingModule';
			$modules['divi/woocommerce-cross-sells']              = $base_namespace . 'WooCommerce\CrossSells\WooCommerceCrossSellsModule';
		}

		return $modules;
	}

	/**
	 * Load the module corresponding to the given block name
	 *
	 * @param string $block_name The name of the block to load the module for.
	 */
	protected static function _load_module_from_block_name( $block_name ) {
		// Mapping from block name to class name,
		// The keys represent the block names, and the values represent the corresponding class names.
		$block_to_class_map = self::get_block_class_map_list();

		/**
		 * Filter to add or modify the block to class mapping.
		 *
		 * This filter allows you to add or modify the block to class mapping. The block to class mapping is used to
		 * determine the class name of the module corresponding to the given block name. The block to class mapping is
		 * used to load the module corresponding to the given block name.
		 *
		 * @since ??
		 *
		 * @param array $block_to_class_map The block to class mapping. The keys represent the block names, and the values
		 *                                  represent the corresponding (FQCN) class names.
		 */
		$block_to_class_map = apply_filters( 'divi_block_parser_block_to_class_map', $block_to_class_map );

		/**
		 * Fires before loading the module corresponding to the given block name.
		 *
		 * @since ??
		 *
		 * @param string $block_name The name of the block.
		 */
		do_action( 'divi_block_parser_before_load_module', $block_name );

		if ( null === $block_name ) {
			return null;
		}

		// Look for the class name in the block to class mapping.
		$class_name = isset( $block_to_class_map[ $block_name ] ) ? $block_to_class_map[ $block_name ] : null;

		// bail early if the class name is empty.
		if ( empty( $class_name ) ) {
			return null;
		}

		// if the class name is already loaded, bail early.
		if ( ! empty( self::$_modules_loaded ) && in_array( $class_name, self::$_modules_loaded, true ) ) {
			return null;
		}

		/**
		 * Holds an instance of the module loader class.
		 *
		 * @var ET\Builder\Packages\ModuleLibrary\Module $module_loader
		 */
		$module_loader = new $class_name();
		$module_loader->load();

		if ( empty( self::$_modules_loaded ) ) {
			self::$_modules_loaded = [];
		}

		self::$_modules_loaded[] = $class_name;
	}

	/**
	 * Processes the next token from the input document
	 * and returns whether to proceed processing more tokens
	 *
	 * This is the "next step" function that essentially
	 * takes a token as its input and decides what to do
	 * with that token before descending deeper into a
	 * nested block tree or continuing along the document
	 * or breaking out of a level of nesting.
	 *
	 * @internal
	 * @since ??
	 *
	 * @return bool
	 */
	public function proceed() {
		if ( $this->_skip_parsing ) {
			return parent::proceed();
		}

		$next_token = $this->next_token();
		[ $token_type, $block_name, $attrs, $start_offset, $token_length ] = $next_token;
		$stack_depth = count( $this->stack );

		// If this is a VB load, skip loading the module,
		// as VB loads all modules at once. See:
		// server/Packages/ModuleLibrary/Modules.php.
		if ( ! Conditions::should_register_all_d5_modules() ) {
			// proceed to load the module, just in time,
			// since they werent all loaded at once.
			self::_load_module_from_block_name( $block_name );
		}

		// we may have some HTML soup before the next block.
		$leading_html_start = $start_offset > $this->offset ? $this->offset : null;

		switch ( $token_type ) {
			case 'no-more-tokens':
				// if not in a block then flush output.
				if ( 0 === $stack_depth ) {
					$this->add_freeform();
					return false;
				}

				/*
				 * Otherwise we have a problem
				 * This is an error
				 *
				 * we have options
				 * - treat it all as freeform text
				 * - assume an implicit closer (easiest when not nesting)
				 */

				// for the easy case we'll assume an implicit closer.
				if ( 1 === $stack_depth ) {
					$this->add_block_from_stack();
					return false;
				}

				/*
				 * for the nested case where it's more difficult we'll
				 * have to assume that multiple closers are missing
				 * and so we'll collapse the whole stack piecewise
				 */
				$stack_count = count( $this->stack );
				while ( 0 < $stack_count ) {
					$this->add_block_from_stack();
					$stack_count = count( $this->stack );
				}
				return false;

			case 'void-block':
				/*
				 * easy case is if we stumbled upon a void block
				 * in the top-level of the document
				 */
				if ( 0 === $stack_depth ) {
					if ( isset( $leading_html_start ) ) {
						$this->output[] = (array) $this->freeform(
							substr(
								$this->document,
								$leading_html_start,
								$start_offset - $leading_html_start
							)
						);
					}

					$this->output[] = (array) BlockParserStore::add(
						new BlockParserBlock(
							$block_name,
							$attrs,
							[],
							'',
							[],
							$this->get_store_instance(),
							'divi/root',
							BlockParserStore::get_layout_type()
						)
					);
					$this->offset   = $start_offset + $token_length;
					return true;
				}

				$inner_block  = BlockParserStore::add(
					new BlockParserBlock(
						$block_name,
						$attrs,
						[],
						'',
						[],
						$this->get_store_instance(),
						'divi/root',
						BlockParserStore::get_layout_type()
					)
				);
				$parent_index = count( $this->stack ) - 1;
				$parent_block = $this->stack[ $parent_index ] ?? null;

				if ( $parent_block ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP uses camelCase in \WP_Block_Parser_Block
					$inner_block->parentId = $parent_block->block->id;
				}

				// otherwise we found an inner block.
				$this->add_inner_block(
					$inner_block,
					$start_offset,
					$token_length
				);
				$this->offset = $start_offset + $token_length;
				return true;

			case 'block-opener':
				// track all newly-opened blocks on the stack.
				array_push(
					$this->stack,
					new BlockParserFrame(
						BlockParserStore::add(
							new BlockParserBlock(
								$block_name,
								$attrs,
								[],
								'',
								[],
								$this->get_store_instance(),
								'divi/root',
								BlockParserStore::get_layout_type()
							)
						),
						$start_offset,
						$token_length,
						$start_offset + $token_length,
						$leading_html_start
					)
				);
				$this->offset = $start_offset + $token_length;
				return true;

			case 'block-closer':
				/*
				 * if we're missing an opener we're in trouble
				 * This is an error
				 */
				if ( 0 === $stack_depth ) {
					/*
					 * we have options
					 * - assume an implicit opener
					 * - assume _this_ is the opener
					 * - give up and close out the document
					 */
					$this->add_freeform();
					return false;
				}

				// if we're not nesting then this is easy - close the block.
				if ( 1 === $stack_depth ) {
					$this->add_block_from_stack( $start_offset );
					$this->offset = $start_offset + $token_length;
					return true;
				}

				/*
				 * otherwise we're nested, and we have to close out the current
				 * block and add it as a new innerBlock to the parent
				 */
				$stack_top                        = array_pop( $this->stack );
				$html                             = substr( $this->document, $stack_top->prev_offset, $start_offset - $stack_top->prev_offset );
				$stack_top->block->innerHTML     .= $html;
				$stack_top->block->innerContent[] = $html;
				$stack_top->prev_offset           = $start_offset + $token_length;

				$parent_index = count( $this->stack ) - 1;
				$parent_block = $this->stack[ $parent_index ] ?? null;

				if ( $parent_block ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP uses camelCase in \WP_Block_Parser_Block
					$stack_top->block->parentId = $parent_block->block->id;
				}

				$this->add_inner_block(
					$stack_top->block,
					$stack_top->token_start,
					$stack_top->token_length,
					$start_offset + $token_length
				);
				$this->offset = $start_offset + $token_length;
				return true;

			default:
				// This is an error.
				$this->add_freeform();
				return false;
		}
	}

	/**
	 * Returns a new block object for freeform HTML
	 *
	 * @internal
	 * @since ??
	 *
	 * @param string $inner_html HTML content of block.
	 *
	 * @return WP_Block_Parser_Block|BlockParserBlock A freeform block object.
	 */
	public function freeform( $inner_html ) {
		if ( $this->_skip_parsing ) {
			return parent::freeform( $inner_html );
		}

		return BlockParserStore::add(
			new BlockParserBlock(
				null,
				$this->empty_attrs,
				[],
				$inner_html,
				[ $inner_html ],
				$this->get_store_instance(),
				'divi/root',
				BlockParserStore::get_layout_type()
			)
		);
	}

	/**
	 * Combine post attributes with local attributes.
	 *
	 * Combine by swapping out the values of the post attributes with the local attributes.
	 *
	 * @since ??
	 *
	 * @param array $attrs Original attributes (passed by reference).
	 * @param array $local_attrs Local attributes.
	 *
	 * @return array Combined attributes.
	 */
	public function combine_local_attrs( array &$attrs, array $local_attrs ): array {
		foreach ( $local_attrs as $key => $value ) {
			if ( is_array( $value ) ) {
				// Ensure that $attrs[$key] is an array before recursive call.
				if ( ! isset( $attrs[ $key ] ) || ! is_array( $attrs[ $key ] ) ) {
					$attrs[ $key ] = [];
				}
				// Use reference to the array element for recursive call.
				$target_array = &$attrs[ $key ];
				$this->combine_local_attrs( $target_array, $value );
			} else {
				$attrs[ $key ] = $value;
			}
		}

		return $attrs;
	}

	/**
	 * Unwrap degenerate nested `localAttrs` chains on global-layout instances.
	 *
	 * Matches JS `unwrapDegenerateLocalAttrsChain` so PHP merge stays aligned with VB parsing.
	 *
	 * @since ??
	 *
	 * @param array $local_attrs Parsed `localAttrs` from `divi/global-layout` block attrs.
	 * @param int   $max_iterations Maximum peel steps (guards malicious depth).
	 *
	 * @return array
	 */
	private function unwrap_degenerate_local_attrs_chain( array $local_attrs, int $max_iterations = 64 ): array {
		$current       = $local_attrs;
		$iterations    = 0;
		$max_safe_loop = $max_iterations;

		while ( $iterations < $max_safe_loop ) {
			$keys = array_keys( $current );

			if ( 1 !== count( $keys ) || 'localAttrs' !== $keys[0] ) {
				break;
			}

			$inner = $current['localAttrs'] ?? null;

			if ( ! is_array( $inner ) ) {
				break;
			}

			$current     = $inner;
			$iterations += 1;
		}

		return $current;
	}

	/**
	 * Get `post_content` of a global layout if the post exists and matches the given arguments.
	 *
	 * This helper is intended to simplify the way to get `post_content` object of a global layout since we already know the ID.
	 * Instead of using the complex and heavy `WP_Query` class, we use the light and cached `get_post` build-in function.
	 *
	 * @since ??
	 *
	 * @param string  $content The content of the global layout.
	 * @param string  $post_id The ID of the post.
	 * @param array   $fields Optional. An array of `key => value` arguments to match against the post object. Default `[]`.
	 * @param array   $capabilities Optional. An array of user capability to match against the current user. Defaults `[]`.
	 * @param boolean $mask_post_password Optional. Whether to mask `post_password` field. Default `true`.
	 * @param string  $inner_html Optional. The innerHTML content from global-layout block for local children. Default `''`.
	 *
	 * @return string|null The post content or null on failure.
	 */
	public function get_global_layout_content( string $content, string $post_id, array $fields = [], array $capabilities = [], bool $mask_post_password = true, string $inner_html = '' ) {
		global $_is_parsing_global_layout;

		$post = get_post( $post_id );

		if ( ! $post ) {
			return null;
		}

		// Set $_is_parsing_global_layout so parser knows that it's parsing a global layout.
		$_is_parsing_global_layout = true;

		$parsed_global_layout = parse_blocks( $content );

		// Preprocess placeholder-wrapped global module content before parsing.
		$preprocessed_content = BlockParserStore::_expand_placeholder_wrapped_blocks( $post->post_content );
		$parsed_actual_post   = BlockParserStore::_filter_whitespace_blocks( parse_blocks( $preprocessed_content ) );

		// Unset $_is_parsing_global_layout so parser can continue working normally.
		$_is_parsing_global_layout = false;

		// Find the first non-placeholder block with attributes.
		$content_block_index = 0;
		$post_attrs          = [];

		foreach ( $parsed_actual_post as $index => $block ) {
			// Skip placeholder blocks and blocks without attributes.
			if (
				isset( $block['blockName'] ) &&
				'divi/placeholder' !== $block['blockName'] &&
				! empty( $block['attrs'] )
			) {
				$content_block_index = $index;
				$post_attrs          = $block['attrs'];
				break;
			}
		}

		$local_attrs = $parsed_global_layout[0]['attrs']['localAttrs'] ?? [];

		if ( is_array( $local_attrs ) && ! empty( $local_attrs ) ) {
			$local_attrs = $this->unwrap_degenerate_local_attrs_chain( $local_attrs );
		}

		// Update post attributes with the ones updated with local attributes.
		$parsed_actual_post[ $content_block_index ]['attrs'] = $this->combine_local_attrs( $post_attrs, $local_attrs );

		// Check for local children in innerHTML content.
		if ( ! empty( $inner_html ) ) {
			// Parse innerHTML to check for local children blocks and filter whitespace blocks.
			$parsed_inner_html = BlockParserStore::_filter_whitespace_blocks( parse_blocks( $inner_html ) );

			// Filter out empty blocks and get actual child modules.
			$local_children_blocks = array_filter(
				$parsed_inner_html,
				function ( $block ) {
					return ! empty( $block['blockName'] ) && 'divi/placeholder' !== $block['blockName'];
				}
			);

			// If we have local children, replace the global module template's children.
			if ( ! empty( $local_children_blocks ) ) {
				// Replace the main module's innerBlocks with local children.
				$parsed_actual_post[ $content_block_index ]['innerBlocks'] = $local_children_blocks;

				// Also set localChildren attribute to indicate this module has local children.
				$parsed_actual_post[ $content_block_index ]['attrs']['localChildren'] = true;
			}
		}

		// serialize updated post content and add it to the post object.
		$post->post_content = serialize_blocks( $parsed_actual_post );

		$match = true;

		if ( $fields ) {
			foreach ( $fields as $field => $value ) {
				if ( ! isset( $post->{$field} ) ) {
					$match = false;
					break;
				}

				$match = is_array( $value ) && ! is_array( $post->{$field} ) ? in_array( $post->{$field}, $value, true ) : $post->{$field} === $value;

				if ( ! $match ) {
					break;
				}
			}
		}

		if ( $match && $capabilities ) {
			foreach ( $capabilities as $capability ) {
				if ( ! current_user_can( $capability, $post->ID ) ) {
					$match = false;
					break;
				}
			}
		}

		if ( $match ) {
			if ( $mask_post_password && $post->post_password ) {
				$post->post_password = '***';
			}

			return $post->post_content;
		}

		return null;
	}

	/**
	 * Check if current request is REST request with intent of updating a post.
	 * Initially made to detect whether current request is gutenberg saving request. However there is no
	 * clear check for it; Rest route for updating post looks like
	 * `/SITE_PATH/wp-json/wp/v2/POST_TYPE/POST_ID?_locale=user` so to detect whether current request is Gutenberg
	 * saving process, any POST, PUT, or PATCH method on REST Request will be considered as Gutenberg saving request.
	 *
	 * This is considered fine because this method is specifically use inside `BlockParser` to detect whether current
	 * BlockParser usage request is meant for expanding global layout placeholder or not. Global layout placeholder
	 * should only be expanded on FE.
	 *
	 * @since ??
	 *
	 * @return bool True if in save context, false otherwise.
	 */
	private static function _is_rest_update_request() {
		// Check for REST API save operations.
		if ( Conditions::is_rest_api_request() ) {
			$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

			if ( in_array( $request_method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the content includes any Divi modules.
	 *
	 * @param string $content The block serialized content to be checked.
	 *
	 * @return bool True if a Divi module is found, false otherwise.
	 */
	public static function has_any_divi_block( $content ) {
		// Bail early if content is empty.
		if ( empty( $content ) ) {
			return false;
		}

		// Currently the only feasible way of detecting a Divi block is by looking for desktop/value key pairs.
		$has_desktop_value_pattern = str_contains( $content, '"desktop":{"value"' );

		// Check if there are divi modules in the content.
		// This check is needed because some modules may not have the desktop/value key pattern.
		// Example is global module.
		$has_divi_block = str_contains( $content, '<!-- wp:divi' );

		return $has_desktop_value_pattern || $has_divi_block;
	}

	/**
	 * Parses a document and returns a list of block structures
	 *
	 * When encountering an invalid parse will return a best-effort
	 * parse. In contrast to the specification parser this does not
	 * return an error on invalid inputs.
	 *
	 * @since ??
	 *
	 * @param string $document Input document to be parsed.
	 *
	 * @return BlockParserBlock[]
	 */
	public function parse( $document ) {
		global $_is_parsing_global_layout;

		$document_has_blocks = has_blocks( $document );

		// Exit early if $document doesn't contain any Divi blocks, use WordPress's block parser to process the blocks.
		if ( ! $this->has_any_divi_block( $document ) ) {
			$this->_skip_parsing = true;

			if ( $document_has_blocks ) {
				// Re-register GB blocks.
				Modules::_re_register_gb_blocks();
			}

			return parent::parse( $document );
		}

		// Exit early if content contains WordPress wrapper blocks (post-content, navigation, block).
		// WordPress 6.8+ wraps content in these blocks during the first parsing pass for Block Hooks.
		// Delegating to WordPress's parser prevents Divi's order index increment issue during wrapper parsing.
		if ( BlockParserUtils::is_parsing_wrapper_blocks( $document ) ) {
			$this->_skip_parsing = true;

			if ( $document_has_blocks ) {
				// Re-register GB blocks.
				Modules::_re_register_gb_blocks();
			}

			return parent::parse( $document );
		}

		/**
		 * Filter to control whether order indexes should be reset during block parsing.
		 *
		 * This filter allows developers to modify whether module order indexes should be reset
		 * during block parsing. By default, order indexes are NOT reset to maintain sequential
		 * CSS class numbering across all rendering contexts (main content, inner content, and
		 * 3rd party plugin usage). This ensures that CSS classes like et_pb_section_0,
		 * et_pb_section_1, etc. remain sequential throughout the page, preventing duplicate
		 * class names when 3rd party plugins render Divi Library items using do_blocks().
		 *
		 * Developers can return true to force a reset if needed for specific use cases, but
		 * this is generally not recommended as it may cause duplicate CSS class issues.
		 *
		 * @since ??
		 *
		 * @param bool $reset_order_index Whether to reset order indexes. Default is false.
		 */
		$reset_order_index = apply_filters( 'divi_front_end_block_parser_reset_order_index', false );

		if ( $reset_order_index ) {
			BlockParserStore::reset_order_index();
		}

		// Flag whether we're currently parsing inner module content that uses the 'the_content' filter.
		// Example: Blog module content.
		$is_rendering_inner_content = BlockParserStore::is_rendering_inner_content();

		// Set to `true` when rendering main content.
		$new_store_instance = ! $is_rendering_inner_content;

		/**
		 * Filter to control whether a new BlockParserStore instance should be created.
		 *
		 * This filter allows developers to modify whether a new BlockParserStore instance should be created
		 * during block parsing. By default, a new store instance is created when parsing main document
		 * content. When parsing inner content (such as rendering Blog module content), the existing store instance
		 * is reused.
		 *
		 * @since ??
		 *
		 * @param bool $new_store_instance Whether a new store instance should be created. Default is True when
		 *                                 rendering main content, otherwise false.
		 */
		$new_store_instance = apply_filters( 'divi_front_end_block_parser_new_store_instance', $new_store_instance );

		if ( $new_store_instance ) {
			$this->_store_instance = BlockParserStore::new_instance();
		} else {
			$this->_store_instance = BlockParserStore::maybe_new_instance();
		}

		// If post has global layout, replace it with actual content.
		// We do it here to avoid processing blocks multiple times.
		// Skip global layout expansion if we're in migration context or save context.
		// Pattern for blocks with innerHTML (local children) - must be processed first.

		// Check conditions that might skip global layout processing.
		$should_skip = $_is_parsing_global_layout
			|| MigrationContext::is_active()
			|| self::_is_rest_update_request()
			|| 'saving_content' === BlockParserStore::get_layout_type();

		if ( $should_skip ) {
			$this->document = $document;
		} else {
			// Use a combined pattern that matches both self-closing and innerHTML blocks.
			// We'll determine the type in the callback based on what was matched.
			$global_layout_pattern = '/<!-- wp:divi\/global-layout(\s+\{.*?"globalModule":"([0-9]+)".*?\})(\s*\/-->|\s*-->([\s\S]*?)<!-- \/wp:divi\/global-layout -->)/s';

			$processed_document = preg_replace_callback(
				$global_layout_pattern,
				function ( $matches ) {
					$json_attrs = $matches[1];
					$module_id  = $matches[2];
					$closing    = $matches[3];

					// Determine if this is a self-closing block or innerHTML block.
					$is_self_closing = str_starts_with( ltrim( $closing ), '/-->' );

					if ( $is_self_closing ) {
						// Self-closing block (standard or local attributes).
						return BlockParserStore::get_global_layout_content(
							$matches[0], // Use full match.
							$module_id,
							[
								'post_type'   => ET_BUILDER_LAYOUT_POST_TYPE,
								'post_status' => 'publish',
							],
							[],
							true,
							'' // No innerHTML.
						);
					} else {
						// innerHTML block (local children).
						$inner_html = isset( $matches[4] ) ? trim( $matches[4] ) : '';

						// Reconstruct as self-closing for attribute parsing.
						$global_layout_block_for_parsing = '<!-- wp:divi/global-layout' . $json_attrs . ' /-->';

						return BlockParserStore::get_global_layout_content(
							$global_layout_block_for_parsing,
							$module_id,
							[
								'post_type'   => ET_BUILDER_LAYOUT_POST_TYPE,
								'post_status' => 'publish',
							],
							[],
							true,
							$inner_html
						);
					}
				},
				$document
			);

			$this->document = $processed_document;
		}

		$is_doing_content_filter = doing_filter( 'the_content' ) || doing_filter( 'et_builder_render_layout' );

		// Only run Dynamic Data processing when the content is being rendered on FE, by checking `the_content` filter
		// (visual builder on default layout / page) or `et_builder_render_layout` filter (theme builder / library layout).
		// Because we want Dynamic Data `$variable` only being replaced by its actual value when it is being rendered
		// not when it is being queried or retrieved programmatically.
		if ( $is_doing_content_filter && ! MigrationContext::is_active() ) {
			// If the content contains loop enabled blocks, parse and duplicate the loop blocks.
			// Skip loop processing when generating excerpts to prevent infinite recursion.
			$has_loop_enabled_blocks = LoopUtils::has_any_loop_enabled_blocks( $this->document );
			$is_generating_excerpt   = isset( $GLOBALS['divi_generating_excerpt'] ) && $GLOBALS['divi_generating_excerpt'];

			if ( $has_loop_enabled_blocks ) {
				// Skip loop duplication while a migration is active. Migration parses content
				// (via `MigrationContext`) before `do_blocks()`, and duplicating loop blocks there
				// bakes the current request's pagination state into the migrated markup. That
				// migrated result is reused across requests, so paginated loops would always render
				// the first page. Loop duplication must only happen at actual render time, where the
				// per-request pagination query arg is read.
				if ( ! $is_generating_excerpt ) {
					$this->document = LoopUtils::parse_and_duplicate_loop_blocks( $this->document );
				}
			}

			// Get processed dynamic data for all variables.
			$post_id        = DynamicContentUtils::get_dynamic_content_post_id();
			$this->document = DynamicData::get_processed_dynamic_data( $this->document, $post_id ? $post_id : null, true );
		}

		$this->offset      = 0;
		$this->output      = [];
		$this->stack       = [];
		$this->empty_attrs = json_decode( '{}', true );

		// phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedDo -- This is WP core codebase
		do {
			// twiddle our thumbs.
		} while ( $this->proceed() );

		return $this->output;
	}
}
