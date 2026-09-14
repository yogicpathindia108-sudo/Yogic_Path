<?php
/**
 * REST: RESTRegistration class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Route\RESTRoute;
use ET\Builder\Packages\GlobalData\GlobalDataController;
use ET\Builder\Packages\GlobalData\GlobalPresetController;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentOptionsController;
use ET\Builder\Packages\Module\Layout\Components\DynamicData\DynamicDataController;
use ET\Builder\Packages\Module\Options\Conditions\RESTControllers\AuthorConditionRESTController;
use ET\Builder\Packages\Module\Options\Conditions\RESTControllers\ConditionsStatusRESTController;
use ET\Builder\Packages\Module\Options\Conditions\RESTControllers\PostMetaFieldsRESTController;
use ET\Builder\Packages\Module\Options\Conditions\RESTControllers\PostsRESTController;
use ET\Builder\Packages\Module\Options\Conditions\RESTControllers\CategoriesRESTController;
use ET\Builder\Packages\Module\Options\Conditions\RESTControllers\TagsRESTController;
use ET\Builder\Packages\Module\Options\Conditions\RESTControllers\UserRoleConditionRESTController;
use ET\Builder\Packages\Module\Options\Conditions\RESTControllers\PostTypeConditionRESTController;
use ET\Builder\Packages\Module\Options\Loop\QueryType\QueryTypeController;
use ET\Builder\Packages\Module\Options\Loop\QueryResults\QueryResultsController;
use ET\Builder\Packages\Module\Options\Loop\QueryOrderBy\QueryOrderByController;
use ET\Builder\Packages\Module\Options\Loop\QueryTaxonomies\QueryTaxonomiesController;
use ET\Builder\Packages\Module\Options\Loop\QueryPosts\QueryPostsController;
use ET\Builder\Packages\Module\Options\WooCommerceSelectProduct\WooCommerceSelectProductController;
use ET\Builder\Packages\ModuleLibrary\PostFilterItem\PostFilterCustomFieldOptionsController;
use ET\Builder\Packages\ModuleLibrary\PostFilterItem\PostFilterCustomFieldValueOptionsCacheController;
use ET\Builder\Packages\ModuleLibrary\PostFilterItem\PostFilterCustomFieldValueOptionsController;
use ET\Builder\Packages\ModuleLibrary\PostFilterItem\PostFilterFieldListItemsController;
use ET\Builder\Packages\ModuleLibrary\PostFilterItem\PostFilterProductPriceRangeController;
use ET\Builder\Packages\ModuleLibrary\Audio\AudioController;
use ET\Builder\Packages\ModuleLibrary\Breadcrumbs\BreadcrumbsController;
use ET\Builder\Packages\ModuleLibrary\Blog\BlogController;
use ET\Builder\Packages\ModuleLibrary\Blog\PostTypeController;
use ET\Builder\Packages\ModuleLibrary\FilterablePortfolio\FilterablePortfolioController;
use ET\Builder\Packages\ModuleLibrary\FullwidthPortfolio\FullwidthPortfolioController;
use ET\Builder\Packages\ModuleLibrary\FullwidthMenu\FullwidthMenuHTMLController;
use ET\Builder\Packages\ModuleLibrary\FullwidthMenu\FullwidthMenuTermsController;
use ET\Builder\Packages\ModuleLibrary\Gallery\GalleryController;
use ET\Builder\Packages\ModuleLibrary\ImagelyGallery\ImagelyGalleryController;
use ET\Builder\Packages\ModuleLibrary\ImagelyGallery\ImagelyGalleryService;
use ET\Builder\Packages\ModuleLibrary\Image\ImageController;
use ET\Builder\Packages\ModuleLibrary\InstagramFeed\InstagramFeedController;
use ET\Builder\Packages\ModuleLibrary\PaymentButton\PaymentButtonController;
use ET\Builder\Packages\ModuleLibrary\Menu\MenuHTMLController;
use ET\Builder\Packages\ModuleLibrary\Menu\MenuTermsController;
use ET\Builder\Packages\ModuleLibrary\Portfolio\PortfolioController;
use ET\Builder\Packages\ModuleLibrary\PostNavigation\PostNavigationController;
use ET\Builder\Packages\ModuleLibrary\Sidebar\SidebarController;
use ET\Builder\Packages\ModuleLibrary\ContactForm7\ContactForm7Controller;
use ET\Builder\Packages\ModuleLibrary\GravityForms\GravityFormsController;
use ET\Builder\Packages\ModuleLibrary\Video\VideoCoverController;
use ET\Builder\Packages\ModuleLibrary\Video\VideoHTMLController;
use ET\Builder\Packages\ModuleLibrary\Video\VideoThumbnailController;
use ET\Builder\Packages\ModuleLibrary\VideoSlider\VideoSlideThumbnailController;
use ET\Builder\Packages\ShortcodeModule\Module\ShortcodeModuleBatchController;
use ET\Builder\Packages\ShortcodeModule\Module\ShortcodeModuleController;
use ET\Builder\VisualBuilder\REST\AILayoutSaveDefault\AILayoutSaveDefaultController;
use ET\Builder\VisualBuilder\REST\Breakpoint\BreakpointController;
use ET\Builder\VisualBuilder\REST\CloudApp\CloudAppController;
use ET\Builder\VisualBuilder\REST\ContentConversion\ContentConversionController;
use ET\Builder\VisualBuilder\REST\ContentMigration\ContentMigrationController;
use ET\Builder\VisualBuilder\REST\CustomFont\CustomFontController;
use ET\Builder\VisualBuilder\REST\DiviLibrary\DiviLibraryController;
use ET\Builder\VisualBuilder\REST\OutsideVb\OutsideVbController;
use ET\Builder\VisualBuilder\REST\PreferencesWorkspace\PreferencesWorkspaceController;
use ET\Builder\VisualBuilder\REST\Portability\PortabilityController;
use ET\Builder\VisualBuilder\REST\SyncToServer\SyncToServerController;
use ET\Builder\VisualBuilder\REST\UpdateDefaultColors\UpdateDefaultColorsController;
use ET\Builder\VisualBuilder\REST\SpamProtectionService\SpamProtectionServiceController;
use ET\Builder\VisualBuilder\REST\EmailService\EmailServiceController;
use ET\Builder\VisualBuilder\REST\MenuManager\MenuItemsController;
use ET\Builder\VisualBuilder\REST\MenuManager\MenuManagerController;
use ET\Builder\VisualBuilder\SettingsData\SettingsDataController;
use ET\Builder\VisualBuilder\REST\ModuleRender\ModuleRenderController;
use ET\Builder\VisualBuilder\REST\PageManager\PageManagerController;
use ET\Builder\VisualBuilder\REST\RecentPosts\RecentPostsController;
use ET\Builder\VisualBuilder\REST\Announcements\AnnouncementsController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\Breadcrumb\WooCommerceBreadcrumbController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\CartNotice\WooCommerceCartNoticeController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductAddToCart\WooCommerceProductAddToCartController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductAdditionalInfo\WooCommerceProductAdditionalInfoController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductDescription\WooCommerceProductDescriptionController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductGallery\WooCommerceProductGalleryController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductImages\WooCommerceProductImagesController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductMeta\WooCommerceProductMetaController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductPrice\WooCommerceProductPriceController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductRating\WooCommerceProductRatingController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductReviews\WooCommerceProductReviewsController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductStock\WooCommerceProductStockController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductTabs\WooCommerceProductTabsController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductTitle\WooCommerceProductTitleController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductUpsell\WooCommerceProductUpsellController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\RelatedProducts\WooCommerceRelatedProductsController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\Products\WooCommerceProductsController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\CartProducts\WooCommerceCartProductsController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\CartTotals\WooCommerceCartTotalsController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\CheckoutInformation\WooCommerceCheckoutInformationController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\CheckoutBilling\WooCommerceCheckoutBillingController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\CheckoutShipping\WooCommerceCheckoutShippingController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\CheckoutPaymentInfo\WooCommerceCheckoutPaymentInfoController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\CheckoutOrderDetails\WooCommerceCheckoutOrderDetailsController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\CrossSells\WooCommerceCrossSellsController;
use ET\Builder\VisualBuilder\REST\InstagramService\InstagramServiceController;
use ET\Builder\VisualBuilder\REST\PaymentService\PaymentServiceController;


/**
 * `RESTRegistration` class registers REST API endpoints upon calling `load()`, These endpoints are used in different parts of Divi.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class RESTRegistration implements DependencyInterface {

	/**
	 * Loads and registers all REST routes.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {

		$route = new RESTRoute( 'divi/v1' );

		/**
		 * `/settings-data` REST routes for getting `divi/settings`' state.
		 */
		$route
			->prefix( '/settings-data' )
			->group(
				function ( $router ) {
					$router->get( '/after-app-load', SettingsDataController::class );
					$router->get(
						'/nonces',
						[
							'args'                => [ SettingsDataController::class, 'nonces_args' ],
							'callback'            => [ SettingsDataController::class, 'nonces' ],
							'permission_callback' => [ SettingsDataController::class, 'nonces_permission' ],
							'nonce_policy'        => RESTRoute::NONCE_POLICY_WP_ONLY,
						]
					);
				}
			);

		/**
		 * `/breakpoints` REST routes for managing breakpoints.
		 */
		$route
			->prefix( '/breakpoints' )
			->group(
				function ( $router ) {

					/**
					 * Update breakpoints
					 */
					$router->post(
						'/update',
						[
							'args'                => [ BreakpointController::class, 'update_args' ],
							'callback'            => [ BreakpointController::class, 'update' ],
							'permission_callback' => [ BreakpointController::class, 'update_permission' ],
						]
					);
				}
			);

		/**
		 * `/module-data` REST routes.
		 */
		$route
			->prefix( '/module-data' )
			->group(
				function ( $router ) {
					/**
					 * Breadcrumbs module.
					 */
					$router->post(
						'/breadcrumbs/html',
						[
							'args'                => [ BreadcrumbsController::class, 'index_args' ],
							'callback'            => [ BreadcrumbsController::class, 'index' ],
							'permission_callback' => [ BreadcrumbsController::class, 'index_permission' ],
						]
					);

					if ( et_is_woocommerce_plugin_active() ) {
						/**
						 * WooCommerce Breadcrumbs Module.
						 */
						$router->post(
							'/woocommerce/breadcrumb/html',
							[
								'args'                => [ WooCommerceBreadcrumbController::class, 'index_args' ],
								'callback'            => [ WooCommerceBreadcrumbController::class, 'index' ],
								'permission_callback' => [ WooCommerceBreadcrumbController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Cart Notice Module.
						 */
						$router->post(
							'/woocommerce/cart-notice/html',
							[
								'args'                => [ WooCommerceCartNoticeController::class, 'index_args' ],
								'callback'            => [ WooCommerceCartNoticeController::class, 'index' ],
								'permission_callback' => [ WooCommerceCartNoticeController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Product Add to Cart Module.
						 */
						$router->post(
							'/woocommerce/product-add-to-cart/html',
							[
								'args'                => [ WooCommerceProductAddToCartController::class, 'index_args' ],
								'callback'            => [ WooCommerceProductAddToCartController::class, 'index' ],
								'permission_callback' => [ WooCommerceProductAddToCartController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Product Additional Info Module.
						 */
						$router->post(
							'/woocommerce/product-additional-info/html',
							[
								'args'                => [ WooCommerceProductAdditionalInfoController::class, 'index_args' ],
								'callback'            => [ WooCommerceProductAdditionalInfoController::class, 'index' ],
								'permission_callback' => [ WooCommerceProductAdditionalInfoController::class, 'index_permission' ],
							]
						);

						/**
						 * Woo Product Description Module
						 */
						$router->post(
							'/woocommerce/product-description/html',
							[
								'args'                => [ WooCommerceProductDescriptionController::class, 'index_args' ],
								'callback'            => [ WooCommerceProductDescriptionController::class, 'index' ],
								'permission_callback' => [ WooCommerceProductDescriptionController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Product Gallery Module.
						 */
						$router->post(
							'/woocommerce/product-gallery/html',
							[
								'args'                => [ WooCommerceProductGalleryController::class, 'index_args' ],
								'callback'            => [ WooCommerceProductGalleryController::class, 'index' ],
								'permission_callback' => [ WooCommerceProductGalleryController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Product Images Module.
						 */
						$router->post(
							'/woocommerce/product-images/html',
							[
								'args'                => [ WooCommerceProductImagesController::class, 'index_args' ],
								'callback'            => [ WooCommerceProductImagesController::class, 'index' ],
								'permission_callback' => [ WooCommerceProductImagesController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Product Meta Module.
						 */
						$router->post(
							'/woocommerce/product-meta/html',
							[
								'args'                => [ WooCommerceProductMetaController::class, 'index_args' ],
								'callback'            => [ WooCommerceProductMetaController::class, 'index' ],
								'permission_callback' => [ WooCommerceProductMetaController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Product Price Module.
						 */
						$router->post(
							'/woocommerce/product-price/html',
							[
								'args'                => [ WooCommerceProductPriceController::class, 'index_args' ],
								'callback'            => [ WooCommerceProductPriceController::class, 'index' ],
								'permission_callback' => [ WooCommerceProductPriceController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Product Rating Module.
						 */
						$router->post(
							'/woocommerce/product-rating/html',
							[
								'args'                => [ WooCommerceProductRatingController::class, 'index_args' ],
								'callback'            => [ WooCommerceProductRatingController::class, 'index' ],
								'permission_callback' => [ WooCommerceProductRatingController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Product Reviews Module.
						 */
						$router->post(
							'/woocommerce/product-reviews/html',
							[
								'args'                => [ WooCommerceProductReviewsController::class, 'index_args' ],
								'callback'            => [ WooCommerceProductReviewsController::class, 'index' ],
								'permission_callback' => [ WooCommerceProductReviewsController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Product Stock Module.
						 */
						$router->post(
							'/woocommerce/product-stock/html',
							[
								'args'                => [ WooCommerceProductStockController::class, 'index_args' ],
								'callback'            => [ WooCommerceProductStockController::class, 'index' ],
								'permission_callback' => [ WooCommerceProductStockController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Product Tabs Module.
						 */
						$router->post(
							'/woocommerce/product-tabs/html',
							[
								'args'                => [ WooCommerceProductTabsController::class, 'index_args' ],
								'callback'            => [ WooCommerceProductTabsController::class, 'index' ],
								'permission_callback' => [ WooCommerceProductTabsController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Product Title Module.
						 */
						$router->post(
							'/woocommerce/product-title/html',
							[
								'args'                => [ WooCommerceProductTitleController::class, 'index_args' ],
								'callback'            => [ WooCommerceProductTitleController::class, 'index' ],
								'permission_callback' => [ WooCommerceProductTitleController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Product Upsell Module.
						 */
						$router->post(
							'/woocommerce/product-upsell/html',
							[
								'args'                => [ WooCommerceProductUpsellController::class, 'index_args' ],
								'callback'            => [ WooCommerceProductUpsellController::class, 'index' ],
								'permission_callback' => [ WooCommerceProductUpsellController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Products Module.
						 */
						$router->post(
							'/woocommerce/products/html',
							[
								'args'                => [ WooCommerceProductsController::class, 'index_args' ],
								'callback'            => [ WooCommerceProductsController::class, 'index' ],
								'permission_callback' => [ WooCommerceProductsController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Related Products Module.
						 */
						$router->post(
							'/woocommerce/related-products/html',
							[
								'args'                => [ WooCommerceRelatedProductsController::class, 'index_args' ],
								'callback'            => [ WooCommerceRelatedProductsController::class, 'index' ],
								'permission_callback' => [ WooCommerceRelatedProductsController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Cart Products Module.
						 */
						$router->post(
							'/woocommerce/cart-products/html',
							[
								'args'                => [ WooCommerceCartProductsController::class, 'index_args' ],
								'callback'            => [ WooCommerceCartProductsController::class, 'index' ],
								'permission_callback' => [ WooCommerceCartProductsController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Cart Totals Module.
						 */
						$router->post(
							'/woocommerce/cart-totals/html',
							[
								'args'                => [ WooCommerceCartTotalsController::class, 'index_args' ],
								'callback'            => [ WooCommerceCartTotalsController::class, 'index' ],
								'permission_callback' => [ WooCommerceCartTotalsController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Checkout Information Module.
						 */
						$router->post(
							'/woocommerce/checkout-information/html',
							[
								'args'                => [ WooCommerceCheckoutInformationController::class, 'index_args' ],
								'callback'            => [ WooCommerceCheckoutInformationController::class, 'index' ],
								'permission_callback' => [ WooCommerceCheckoutInformationController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Checkout Billing Module.
						 */
						$router->post(
							'/woocommerce/checkout-billing/html',
							[
								'args'                => [ WooCommerceCheckoutBillingController::class, 'index_args' ],
								'callback'            => [ WooCommerceCheckoutBillingController::class, 'index' ],
								'permission_callback' => [ WooCommerceCheckoutBillingController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Checkout Details Module.
						 */
						$router->post(
							'/woocommerce/checkout-order-details/html',
							[
								'args'                => [ WooCommerceCheckoutOrderDetailsController::class, 'index_args' ],
								'callback'            => [ WooCommerceCheckoutOrderDetailsController::class, 'index' ],
								'permission_callback' => [ WooCommerceCheckoutOrderDetailsController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Checkout Payment Module.
						 */
						$router->post(
							'/woocommerce/checkout-payment-info/html',
							[
								'args'                => [ WooCommerceCheckoutPaymentInfoController::class, 'index_args' ],
								'callback'            => [ WooCommerceCheckoutPaymentInfoController::class, 'index' ],
								'permission_callback' => [ WooCommerceCheckoutPaymentInfoController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Checkout Shipping Module.
						 */
						$router->post(
							'/woocommerce/checkout-shipping/html',
							[
								'args'                => [ WooCommerceCheckoutShippingController::class, 'index_args' ],
								'callback'            => [ WooCommerceCheckoutShippingController::class, 'index' ],
								'permission_callback' => [ WooCommerceCheckoutShippingController::class, 'index_permission' ],
							]
						);

						/**
						 * WooCommerce Cross Sells Module.
						 */
						$router->post(
							'/woocommerce/cross-sells/html',
							[
								'args'                => [ WooCommerceCrossSellsController::class, 'index_args' ],
								'callback'            => [ WooCommerceCrossSellsController::class, 'index' ],
								'permission_callback' => [ WooCommerceCrossSellsController::class, 'index_permission' ],
							]
						);
					}

					if ( class_exists( '\WPCF7_ContactForm' ) ) {
						/**
						 * Contact Form 7 module.
						 */
						$router->post(
							'/contact-form-7/html',
							[
								'args'                => [ ContactForm7Controller::class, 'index_args' ],
								'callback'            => [ ContactForm7Controller::class, 'index' ],
								'permission_callback' => [ ContactForm7Controller::class, 'index_permission' ],
							]
						);
					}

					if ( class_exists( '\GFForms' ) ) {
						/**
						 * Gravity Forms module.
						 */
						$router->post(
							'/gravity-forms/html',
							[
								'args'                => [ GravityFormsController::class, 'index_args' ],
								'callback'            => [ GravityFormsController::class, 'index' ],
								'permission_callback' => [ GravityFormsController::class, 'index_permission' ],
							]
						);
					}

					/**
					 * Gallery Module
					 */
					$router->get( '/gallery/attachments', GalleryController::class );

					/**
					 * Video module.
					 */
					$router->get( '/video/html', VideoHTMLController::class );
					$router->get( '/video/thumbnail', VideoThumbnailController::class );
					$router->get( '/video/cover', VideoCoverController::class );

					/**
					 * Video Slider module.
					 */
					$router->get( '/video-slide/thumbnail', VideoSlideThumbnailController::class );

					/**
					 * Audio module.
					 */
					$router->get( '/audio/html', AudioController::class );

					/**
					 * Menu module.
					 */
					$router->get( '/menu/html', MenuHTMLController::class );
					$router->get( '/menu/terms', MenuTermsController::class );

					/**
					 * Fullwidth Menu module.
					 */
					$router->get( '/fullwidth-menu/html', FullwidthMenuHTMLController::class );
					$router->get( '/fullwidth-menu/terms', FullwidthMenuTermsController::class );

					/**
					 * Portfolio module.
					 */
					$router->get( '/portfolio/posts', PortfolioController::class );

					/**
					 * Filterable Portfolio module.
					 */
					$router->get( '/filterable-portfolio/posts', FilterablePortfolioController::class );

					/**
					 * Post Navigation module.
					 */
					$router->get( '/post-navigation/navigation', PostNavigationController::class );

					/**
					 * Fullwidth Portfolio module.
					 */
					$router->get( '/fullwidth-portfolio/posts', FullwidthPortfolioController::class );

					/**
					 * Shortcode module.
					 *
					 * phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
					 * TODO feat(D5, Shortcode Module): Move Shortcode module REST API route registration
					 * to ShortcodeModule package.
					 *
					 * @see https://github.com/elegantthemes/Divi/issues/32183
					 */
					$router->get( '/shortcode-module/html', ShortcodeModuleController::class );
					$router->post(
						'/shortcode-module/html/batch',
						[
							'args'                => [ ShortcodeModuleBatchController::class, 'index_args' ],
							'callback'            => [ ShortcodeModuleBatchController::class, 'index' ],
							'permission_callback' => [ ShortcodeModuleBatchController::class, 'index_permission' ],
						]
					);

					/**
					 * Sidebar module.
					 */
					$router->get( '/sidebar/html', SidebarController::class );

					/**
					 * Blog module.
					 */
					$router->get( '/blog/posts', BlogController::class );

					/**
					 * Blog module.
					 */
					$router->get( '/blog/types', PostTypeController::class );

					/**
					 * Imagely Gallery module.
					 */
					$router->group(
						function ( $router ) {
							if ( ! class_exists( 'C_NextGEN_Bootstrap' ) || ! ImagelyGalleryService::is_runtime_available() ) {
								return;
							}

							/**
							 * The plugin detection class `C_NextGEN_Bootstrap` is intentionally
							 * kept because that is still the runtime class shipped by the
							 * (rebranded) Imagely plugin. The gallery list is delivered through
							 * the settings payload (`SettingsDataCallbacks::imagely_gallery()`
							 * -> `imagelyGallery` key), so only the preview endpoint is registered here.
							 */
							$router->get(
								'/imagely-gallery/preview',
								[
									'args'                => [ ImagelyGalleryController::class, 'preview_args' ],
									'callback'            => [ ImagelyGalleryController::class, 'preview' ],
									'permission_callback' => [ ImagelyGalleryController::class, 'preview_permission' ],
								]
							);
						}
					);

					/**
					 * Image module.
					 */
					$router->post(
						'/image/server-rendering-attributes',
						[
							'args'                => [ ImageController::class, 'server_rendering_attributes_args' ],
							'callback'            => [ ImageController::class, 'server_rendering_attributes' ],
							'permission_callback' => [ ImageController::class, 'server_rendering_attributes_permission' ],
						]
					);
				}
			);

		/**
		 * `/dynamic-content` REST routes.
		 */
		$route
			->prefix( '/dynamic-content' )
			->group(
				function ( $router ) {

					/**
					 * Dynamic Content Options.
					 */
					$router->get( '/options', DynamicContentOptionsController::class );
				}
			);

		/**
		 * `/option-data` REST routes.
		 */
		$route
		->prefix( '/option-data' )
		->group(
			function ( $router ) {

				/**
				 * Conditions option.
				 */
				$router->post( '/conditions/status', ConditionsStatusRESTController::class );
				$router->get( '/conditions/posts', PostsRESTController::class );
				$router->get( '/conditions/post-meta-fields', PostMetaFieldsRESTController::class );
				$router->get( '/conditions/user-role', UserRoleConditionRESTController::class );
				$router->get( '/conditions/author', AuthorConditionRESTController::class );
				$router->get( '/conditions/post-type', PostTypeConditionRESTController::class );
				$router->get( '/conditions/categories', CategoriesRESTController::class );
				$router->get( '/conditions/tags', TagsRESTController::class );
			}
		);

		/**
		 * `/loop/query-types` REST routes.
		 */
		$route->prefix( '/loop' )
			->group(
				function ( $router ) {

					/**
					 * Query Type Options.
					 */
					$router->get(
						'/query-types',
						[
							'args'                => [ QueryTypeController::class, 'index_args' ],
							'callback'            => [ QueryTypeController::class, 'index' ],
							'permission_callback' => [ QueryTypeController::class, 'index_permission' ],
						]
					);

					/**
					 * Query Result Options.
					 */
					$router->get(
						'/query-results',
						[
							'args'                => [ QueryResultsController::class, 'index_args' ],
							'callback'            => [ QueryResultsController::class, 'index' ],
							'permission_callback' => [ QueryResultsController::class, 'index_permission' ],
						]
					);
					/**
					 * Query Order By.
					 */
					$router->get(
						'/query-order-by',
						[
							'args'                => [ QueryOrderByController::class, 'index_args' ],
							'callback'            => [ QueryOrderByController::class, 'index' ],
							'permission_callback' => [ QueryOrderByController::class, 'index_permission' ],
						]
					);

					/**
					 * Query Taxonomies with Terms.
					 */
					$router->get(
						'/query-taxonomies',
						[
							'args'                => [ QueryTaxonomiesController::class, 'index_args' ],
							'callback'            => [ QueryTaxonomiesController::class, 'index' ],
							'permission_callback' => [ QueryTaxonomiesController::class, 'index_permission' ],
						]
					);

					/**
					 * Post Filter Item custom field option discovery.
					 */
					$router->get(
						'/custom-field-options',
						[
							'args'                => [ PostFilterCustomFieldOptionsController::class, 'index_args' ],
							'callback'            => [ PostFilterCustomFieldOptionsController::class, 'index' ],
							'permission_callback' => [ PostFilterCustomFieldOptionsController::class, 'index_permission' ],
						]
					);

					/**
					 * Post Filter Item field list items.
					 */
					$router->get(
						'/field-list-items',
						[
							'args'                => [ PostFilterFieldListItemsController::class, 'index_args' ],
							'callback'            => [ PostFilterFieldListItemsController::class, 'index' ],
							'permission_callback' => [ PostFilterFieldListItemsController::class, 'index_permission' ],
						]
					);

					/**
					 * Post Filter Item custom field value options.
					 */
					$router->get(
						'/custom-field-value-options',
						[
							'args'                => [ PostFilterCustomFieldValueOptionsController::class, 'index_args' ],
							'callback'            => [ PostFilterCustomFieldValueOptionsController::class, 'index' ],
							'permission_callback' => [ PostFilterCustomFieldValueOptionsController::class, 'index_permission' ],
						]
					);

					/**
					 * Post Filter Item custom field value options cache.
					 */
					$router->delete(
						'/custom-field-value-options/cache',
						[
							'args'                => [ PostFilterCustomFieldValueOptionsCacheController::class, 'delete_args' ],
							'callback'            => [ PostFilterCustomFieldValueOptionsCacheController::class, 'delete' ],
							'permission_callback' => [ PostFilterCustomFieldValueOptionsCacheController::class, 'delete_permission' ],
						]
					);

					/**
					 * Post Filter Item WooCommerce product price range metadata.
					 */
					$router->get(
						'/product-price-range',
						[
							'args'                => [ PostFilterProductPriceRangeController::class, 'index_args' ],
							'callback'            => [ PostFilterProductPriceRangeController::class, 'index' ],
							'permission_callback' => [ PostFilterProductPriceRangeController::class, 'index_permission' ],
						]
					);

					/**
					 * Query Posts.
					 */
					$router->get(
						'/query-posts',
						[
							'args'                => [ QueryPostsController::class, 'index_args' ],
							'callback'            => [ QueryPostsController::class, 'index' ],
							'permission_callback' => [ QueryPostsController::class, 'index_permission' ],
						]
					);
				}
			);

		/**
		 * `/divi-library` REST routes.
		 */
		$route->post(
			'/divi-library',
			[
				'args'                => [ DiviLibraryController::class, 'index_args' ],
				'callback'            => [ DiviLibraryController::class, 'index' ],
				'permission_callback' => [ DiviLibraryController::class, 'index_permission' ],
			]
		);

		$route->post(
			'/divi-library/cloud-token',
			[
				'args'                => [ DiviLibraryController::class, 'get_token_args' ],
				'callback'            => [ DiviLibraryController::class, 'get_token' ],
				'permission_callback' => [ DiviLibraryController::class, 'get_token_permission' ],
			]
		);

		$route->post(
			'/divi-library/item',
			[
				'args'                => [ DiviLibraryController::class, 'show_args' ],
				'callback'            => [ DiviLibraryController::class, 'show' ],
				'permission_callback' => [ DiviLibraryController::class, 'show_permission' ],
			]
		);

		$route->post(
			'/divi-library/update-terms',
			[
				'args'                => [ DiviLibraryController::class, 'update_args' ],
				'callback'            => [ DiviLibraryController::class, 'update' ],
				'permission_callback' => [ DiviLibraryController::class, 'update_permission' ],
			]
		);

		$route->post(
			'/divi-library/update-item',
			[
				'args'                => [ DiviLibraryController::class, 'update_item_args' ],
				'callback'            => [ DiviLibraryController::class, 'update_item' ],
				'permission_callback' => [ DiviLibraryController::class, 'update_item_permission' ],
			]
		);

		$route->post(
			'/divi-library/convert-item',
			[
				'args'                => [ DiviLibraryController::class, 'convert_item_args' ],
				'callback'            => [ DiviLibraryController::class, 'convert_item' ],
				'permission_callback' => [ DiviLibraryController::class, 'convert_item_permission' ],
			]
		);

		$route->post(
			'/divi-library/split-item',
			[
				'args'                => [ DiviLibraryController::class, 'split_item_args' ],
				'callback'            => [ DiviLibraryController::class, 'split_item' ],
				'permission_callback' => [ DiviLibraryController::class, 'split_item_permission' ],
			]
		);

		$route->post(
			'/divi-library/load',
			[
				'args'                => [ DiviLibraryController::class, 'load_args' ],
				'callback'            => [ DiviLibraryController::class, 'load' ],
				'permission_callback' => [ DiviLibraryController::class, 'load_permission' ],
			]
		);
		$route->post(
			'/divi-library/create-item',
			[
				'args'                => [ DiviLibraryController::class, 'create_item_args' ],
				'callback'            => [ DiviLibraryController::class, 'create_item' ],
				'permission_callback' => [ DiviLibraryController::class, 'create_item_permission' ],
			]
		);
		$route->post(
			'/divi-library/save',
			[
				'args'                => [ DiviLibraryController::class, 'save_args' ],
				'callback'            => [ DiviLibraryController::class, 'save' ],
				'permission_callback' => [ DiviLibraryController::class, 'save_permission' ],
			]
		);
		$route->post(
			'/divi-library/upload-image',
			[
				'args'                => [ DiviLibraryController::class, 'upload_image_args' ],
				'callback'            => [ DiviLibraryController::class, 'upload_image' ],
				'permission_callback' => [ DiviLibraryController::class, 'upload_image_permission' ],
			]
		);
		$route->post(
			'/divi-library/item-location',
			[
				'args'                => [ DiviLibraryController::class, 'item_location_args' ],
				'callback'            => [ DiviLibraryController::class, 'item_location' ],
				'permission_callback' => [ DiviLibraryController::class, 'item_location_permission' ],
			]
		);

		/**
		 * `/custom-font` REST routes.
		 */
		$route->post(
			'/custom-font/add',
			[
				'args'                => [ CustomFontController::class, 'store_args' ],
				'callback'            => [ CustomFontController::class, 'store' ],
				'permission_callback' => [ CustomFontController::class, 'store_permission' ],
			]
		);
		$route->post(
			'/custom-font/remove',
			[
				'args'                => [ CustomFontController::class, 'destroy_args' ],
				'callback'            => [ CustomFontController::class, 'destroy' ],
				'permission_callback' => [ CustomFontController::class, 'destroy_permission' ],
			]
		);

		/**
		 * `/portability` REST routes.
		 */
		$route->post(
			'/portability/export',
			[
				'args'                => [ PortabilityController::class, 'show_args' ],
				'callback'            => [ PortabilityController::class, 'show' ],
				'permission_callback' => [ PortabilityController::class, 'show_permission' ],
			]
		);

		$route->post(
			'/portability/import',
			[
				'args'                => [ PortabilityController::class, 'store_args' ],
				'callback'            => [ PortabilityController::class, 'store' ],
				'permission_callback' => [ PortabilityController::class, 'store_permission' ],
			]
		);

		/**
		 * `/outside-vb` REST routes for AI agent tools (Theme Builder templates, theme options, layout export, post layout).
		 */
		$route->post(
			'/outside-vb/theme-builder/list-templates',
			[
				'args'                => [ OutsideVbController::class, 'list_templates_args' ],
				'callback'            => [ OutsideVbController::class, 'list_templates' ],
				'permission_callback' => [ OutsideVbController::class, 'theme_builder_permission' ],
			]
		);

		$route->post(
			'/outside-vb/theme-builder/create-template',
			[
				'args'                => [ OutsideVbController::class, 'create_template_args' ],
				'callback'            => [ OutsideVbController::class, 'create_template' ],
				'permission_callback' => [ OutsideVbController::class, 'theme_builder_permission' ],
			]
		);

		$route->post(
			'/outside-vb/theme-builder/update-template',
			[
				'args'                => [ OutsideVbController::class, 'update_template_args' ],
				'callback'            => [ OutsideVbController::class, 'update_template' ],
				'permission_callback' => [ OutsideVbController::class, 'update_template_permission' ],
			]
		);

		$route->post(
			'/outside-vb/theme-builder/delete-template',
			[
				'args'                => [ OutsideVbController::class, 'delete_template_args' ],
				'callback'            => [ OutsideVbController::class, 'delete_template' ],
				'permission_callback' => [ OutsideVbController::class, 'delete_template_permission' ],
			]
		);

		$route->post(
			'/outside-vb/theme-builder/assign-template',
			[
				'args'                => [ OutsideVbController::class, 'assign_template_args' ],
				'callback'            => [ OutsideVbController::class, 'assign_template' ],
				'permission_callback' => [ OutsideVbController::class, 'assign_template_permission' ],
			]
		);

		$route->post(
			'/outside-vb/theme-options/get',
			[
				'args'                => [ OutsideVbController::class, 'get_theme_options_args' ],
				'callback'            => [ OutsideVbController::class, 'get_theme_options' ],
				'permission_callback' => [ OutsideVbController::class, 'theme_options_permission' ],
			]
		);

		$route->post(
			'/outside-vb/theme-options/update',
			[
				'args'                => [ OutsideVbController::class, 'update_theme_option_args' ],
				'callback'            => [ OutsideVbController::class, 'update_theme_option' ],
				'permission_callback' => [ OutsideVbController::class, 'theme_options_permission' ],
			]
		);

		$route->post(
			'/outside-vb/export-layout',
			[
				'args'                => [ OutsideVbController::class, 'export_layout_args' ],
				'callback'            => [ OutsideVbController::class, 'export_layout' ],
				'permission_callback' => [ OutsideVbController::class, 'export_layout_permission' ],
			]
		);

		$route->post(
			'/outside-vb/posts/set-layout',
			[
				'args'                => [ OutsideVbController::class, 'set_post_layout_args' ],
				'callback'            => [ OutsideVbController::class, 'set_post_layout' ],
				'permission_callback' => [ OutsideVbController::class, 'set_post_layout_permission' ],
			]
		);

		/**
		 * `/sync-to-server` REST routes.
		 */
		$route->post(
			'/sync-to-server',
			[
				'args'                => [ SyncToServerController::class, 'update_args' ],
				'callback'            => [ SyncToServerController::class, 'update' ],
				'permission_callback' => [ SyncToServerController::class, 'update_permission' ],
			]
		);

		/**
		 * `/preferences-workspaces` REST routes.
		 */
		$route->post(
			'/preferences-workspaces/create',
			[
				'args'                => [ PreferencesWorkspaceController::class, 'create_args' ],
				'callback'            => [ PreferencesWorkspaceController::class, 'create' ],
				'permission_callback' => [ PreferencesWorkspaceController::class, 'create_permission' ],
			]
		);

		$route->post(
			'/preferences-workspaces/set-default',
			[
				'args'                => [ PreferencesWorkspaceController::class, 'set_default_args' ],
				'callback'            => [ PreferencesWorkspaceController::class, 'set_default' ],
				'permission_callback' => [ PreferencesWorkspaceController::class, 'set_default_permission' ],
			]
		);

		$route->post(
			'/preferences-workspaces/update',
			[
				'args'                => [ PreferencesWorkspaceController::class, 'update_args' ],
				'callback'            => [ PreferencesWorkspaceController::class, 'update' ],
				'permission_callback' => [ PreferencesWorkspaceController::class, 'update_permission' ],
			]
		);

		$route->post(
			'/preferences-workspaces/delete',
			[
				'args'                => [ PreferencesWorkspaceController::class, 'delete_args' ],
				'callback'            => [ PreferencesWorkspaceController::class, 'delete' ],
				'permission_callback' => [ PreferencesWorkspaceController::class, 'delete_permission' ],
			]
		);

		/**
		 * `/ai_layout_save_defaults` REST routes.
		 */
		$route->post(
			'/ai_layout_save_defaults',
			[
				'args'                => [ AILayoutSaveDefaultController::class, 'update_args' ],
				'callback'            => [ AILayoutSaveDefaultController::class, 'update' ],
				'permission_callback' => [ AILayoutSaveDefaultController::class, 'update_permission' ],
			]
		);

		/**
		 * `/update-default-colors` REST routes.
		 */
		$route->post(
			'/update-default-colors',
			[
				'args'                => [ UpdateDefaultColorsController::class, 'update_args' ],
				'callback'            => [ UpdateDefaultColorsController::class, 'update' ],
				'permission_callback' => [ UpdateDefaultColorsController::class, 'update_permission' ],
			]
		);

		/**
		 * `/dynamic-data` REST routes.
		 */
		$route->post(
			'/dynamic-data',
			[
				'args'                => [ DynamicDataController::class, 'index_args' ],
				'callback'            => [ DynamicDataController::class, 'index' ],
				'permission_callback' => [ DynamicDataController::class, 'index_permission' ],
			]
		);

		/**
		 * `/content-conversion` REST routes.
		 */
		$route->post(
			'/content-conversion',
			[
				'args'                => [ ContentConversionController::class, 'convert_content_args' ],
				'callback'            => [ ContentConversionController::class, 'convert_content' ],
				'permission_callback' => [ ContentConversionController::class, 'convert_content_permission' ],
			]
		);

		/**
		 * `/content-migration` REST routes.
		 */
		$route->post(
			'/content-migration',
			[
				'args'                => [ ContentMigrationController::class, 'migrate_content_args' ],
				'callback'            => [ ContentMigrationController::class, 'migrate_content' ],
				'permission_callback' => [ ContentMigrationController::class, 'migrate_content_permission' ],
			]
		);

		/**
		 * `/preset-conversion` REST routes.
		 */
		$route->post(
			'/preset-conversion',
			[
				'args'                => [ ContentConversionController::class, 'convert_presets_args' ],
				'callback'            => [ ContentConversionController::class, 'convert_presets' ],
				'permission_callback' => [ ContentConversionController::class, 'convert_presets_permission' ],
			]
		);

		/**
		 * `/update-account` REST routes.
		 */
		$route->post(
			'/update-account',
			[
				'args'                => [ CloudAppController::class, 'update_account_args' ],
				'callback'            => [ CloudAppController::class, 'update_account' ],
				'permission_callback' => [ CloudAppController::class, 'update_account_permission' ],
			]
		);

		/**
		 * `/spam-protection-provider` REST routes.
		 */
		$route
			->prefix( '/spam-protection-service' )
			->group(
				function ( $router ) {
					$router->post(
						'/create',
						[
							'args'                => [ SpamProtectionServiceController::class, 'create_args' ],
							'callback'            => [ SpamProtectionServiceController::class, 'create' ],
							'permission_callback' => [ SpamProtectionServiceController::class, 'create_permission' ],
						]
					);
					$router->post(
						'/delete',
						[
							'args'                => [ SpamProtectionServiceController::class, 'delete_args' ],
							'callback'            => [ SpamProtectionServiceController::class, 'delete' ],
							'permission_callback' => [ SpamProtectionServiceController::class, 'delete_permission' ],
						]
					);
				}
			);

		/**
		 * `/email-service` REST routes.
		 */
		$route
			->prefix( '/email-service' )
			->group(
				function ( $router ) {
					$router->post(
						'/create',
						[
							'args'                => [ EmailServiceController::class, 'create_args' ],
							'callback'            => [ EmailServiceController::class, 'create' ],
							'permission_callback' => [ EmailServiceController::class, 'create_permission' ],
						]
					);
					$router->post(
						'/read',
						[
							'args'                => [ EmailServiceController::class, 'read_args' ],
							'callback'            => [ EmailServiceController::class, 'read' ],
							'permission_callback' => [ EmailServiceController::class, 'read_permission' ],
						]
					);
					$router->post(
						'/delete',
						[
							'args'                => [ EmailServiceController::class, 'delete_args' ],
							'callback'            => [ EmailServiceController::class, 'delete' ],
							'permission_callback' => [ EmailServiceController::class, 'delete_permission' ],
						]
					);
				}
			);

		/**
		 * `/instagram-feed` REST routes.
		 */
		$route->post(
			'/instagram-feed/read',
			[
				'args'                => [ InstagramFeedController::class, 'index_args' ],
				'callback'            => [ InstagramFeedController::class, 'index' ],
				'permission_callback' => [ InstagramFeedController::class, 'index_permission' ],
			]
		);

		/**
		 * `/payment-button` REST routes.
		 */
		$route->post(
			'/payment-button/read',
			[
				'args'                => [ PaymentButtonController::class, 'read_args' ],
				'callback'            => [ PaymentButtonController::class, 'read' ],
				'permission_callback' => [ PaymentButtonController::class, 'read_permission' ],
			]
		);

		/**
		 * `/payment-service` REST routes.
		 */
		$route
			->prefix( '/payment-service' )
			->group(
				function ( $router ) {
					$router->post(
						'/create',
						[
							'args'                => [ PaymentServiceController::class, 'create_args' ],
							'callback'            => [ PaymentServiceController::class, 'create' ],
							'permission_callback' => [ PaymentServiceController::class, 'create_permission' ],
						]
					);
					$router->post(
						'/read',
						[
							'args'                => [ PaymentServiceController::class, 'read_args' ],
							'callback'            => [ PaymentServiceController::class, 'read' ],
							'permission_callback' => [ PaymentServiceController::class, 'read_permission' ],
						]
					);
					$router->post(
						'/delete',
						[
							'args'                => [ PaymentServiceController::class, 'delete_args' ],
							'callback'            => [ PaymentServiceController::class, 'delete' ],
							'permission_callback' => [ PaymentServiceController::class, 'delete_permission' ],
						]
					);
					$router->post(
						'/update',
						[
							'args'                => [ PaymentServiceController::class, 'update_args' ],
							'callback'            => [ PaymentServiceController::class, 'update' ],
							'permission_callback' => [ PaymentServiceController::class, 'update_permission' ],
						]
					);
				}
			);

		/**
		 * `/instagram-service` REST routes.
		 */
		$route
			->prefix( '/instagram-service' )
			->group(
				function ( $router ) {
					$router->post(
						'/create',
						[
							'args'                => [ InstagramServiceController::class, 'create_args' ],
							'callback'            => [ InstagramServiceController::class, 'create' ],
							'permission_callback' => [ InstagramServiceController::class, 'create_permission' ],
						]
					);
					$router->post(
						'/read',
						[
							'args'                => [ InstagramServiceController::class, 'read_args' ],
							'callback'            => [ InstagramServiceController::class, 'read' ],
							'permission_callback' => [ InstagramServiceController::class, 'read_permission' ],
						]
					);
					$router->post(
						'/delete',
						[
							'args'                => [ InstagramServiceController::class, 'delete_args' ],
							'callback'            => [ InstagramServiceController::class, 'delete' ],
							'permission_callback' => [ InstagramServiceController::class, 'delete_permission' ],
						]
					);
				}
			);

		/**
		 * `/spam-protection-service` REST routes.
		 */
		$route
		->prefix( '/spam-protection-service' )
		->group(
			function ( $router ) {
				$router->post(
					'/create',
					[
						'args'                => [ SpamProtectionServiceController::class, 'create_args' ],
						'callback'            => [ SpamProtectionServiceController::class, 'create' ],
						'permission_callback' => [ SpamProtectionServiceController::class, 'create_permission' ],
					]
				);
				$router->post(
					'/delete',
					[
						'args'                => [ SpamProtectionServiceController::class, 'delete_args' ],
						'callback'            => [ SpamProtectionServiceController::class, 'delete' ],
						'permission_callback' => [ SpamProtectionServiceController::class, 'delete_permission' ],
					]
				);
			}
		);

		/**
		 * `/global-data/global-colors` REST routes.
		 */
		$route->post(
			'/global-data/global-colors',
			[
				'args'                => [ GlobalDataController::class, 'update_global_colors_args' ],
				'callback'            => [ GlobalDataController::class, 'update_global_colors' ],
				'permission_callback' => [ GlobalDataController::class, 'update_global_colors_permission' ],
			]
		);

		/**
		 * `/global-data/global-fonts` REST routes.
		 */
		$route->post(
			'/global-data/global-fonts',
			[
				'args'                => [ GlobalDataController::class, 'update_global_fonts_args' ],
				'callback'            => [ GlobalDataController::class, 'update_global_fonts' ],
				'permission_callback' => [ GlobalDataController::class, 'update_global_fonts_permission' ],
			]
		);

		/**
		 * `/global-data/global-variables` REST routes.
		 */
		$route->post(
			'/global-data/global-variables',
			[
				'args'                => [ GlobalDataController::class, 'update_global_variables_args' ],
				'callback'            => [ GlobalDataController::class, 'update_global_variables' ],
				'permission_callback' => [ GlobalDataController::class, 'update_global_variables_permission' ],
			]
		);

		/**
		 * Register route `/global-data/global-preset/sync`.
		 */
		$route->post(
			'/global-data/global-preset/sync',
			[
				'args'                => [ GlobalPresetController::class, 'sync_args' ],
				'callback'            => [ GlobalPresetController::class, 'sync' ],
				'permission_callback' => [ GlobalPresetController::class, 'sync_permission' ],
			]
		);

		/**
		 * `/module-render` REST routes.
		 */
		$route->post(
			'/module-render',
			[
				'args'                => [ ModuleRenderController::class, 'module_render_args' ],
				'callback'            => [ ModuleRenderController::class, 'module_render' ],
				'permission_callback' => [ ModuleRenderController::class, 'module_render_permission' ],
			]
		);

		/**
		 * WooCommerce Select Product option.
		 */
		$route->get(
			'/woocommerce/search-products',
			[
				'args'                => [ WooCommerceSelectProductController::class, 'index_args' ],
				'callback'            => [ WooCommerceSelectProductController::class, 'index' ],
				'permission_callback' => [ WooCommerceSelectProductController::class, 'index_permission' ],
			]
		);

		/**
		 * Recent Posts endpoint.
		 */
		$route->get( '/recent-posts', RecentPostsController::class );

		/**
		 * Announcements endpoints.
		 */
		$route->post(
			'/announcements/mark-read',
			[
				'args'                => [ AnnouncementsController::class, 'mark_read_args' ],
				'callback'            => [ AnnouncementsController::class, 'mark_read' ],
				'permission_callback' => [ AnnouncementsController::class, 'mark_read_permission' ],
			]
		);

		/**
		 * Page Manager endpoints.
		 */
		$route->get( '/page-manager', PageManagerController::class );

		$route->post(
			'/page-manager/create',
			[
				'args'                => [ PageManagerController::class, 'create_args' ],
				'callback'            => [ PageManagerController::class, 'create' ],
				'permission_callback' => [ PageManagerController::class, 'create_permission' ],
			]
		);

		$route->post(
			'/page-manager/duplicate',
			[
				'args'                => [ PageManagerController::class, 'duplicate_args' ],
				'callback'            => [ PageManagerController::class, 'duplicate' ],
				'permission_callback' => [ PageManagerController::class, 'duplicate_permission' ],
			]
		);

		$route->post(
			'/page-manager/trash',
			[
				'args'                => [ PageManagerController::class, 'trash_args' ],
				'callback'            => [ PageManagerController::class, 'trash' ],
				'permission_callback' => [ PageManagerController::class, 'trash_permission' ],
			]
		);

		$route->post(
			'/page-manager/update-order',
			[
				'args'                => [ PageManagerController::class, 'update_order_args' ],
				'callback'            => [ PageManagerController::class, 'update_order' ],
				'permission_callback' => [ PageManagerController::class, 'update_order_permission' ],
			]
		);

		$route->post(
			'/page-manager/update',
			[
				'args'                => [ PageManagerController::class, 'update_args' ],
				'callback'            => [ PageManagerController::class, 'update' ],
				'permission_callback' => [ PageManagerController::class, 'update_permission' ],
			]
		);

		$route->post(
			'/page-manager/update-status',
			[
				'args'                => [ PageManagerController::class, 'update_status_args' ],
				'callback'            => [ PageManagerController::class, 'update_status' ],
				'permission_callback' => [ PageManagerController::class, 'update_status_permission' ],
			]
		);

		$route->get(
			'/page-manager/search',
			[
				'args'                => [ PageManagerController::class, 'search_args' ],
				'callback'            => [ PageManagerController::class, 'search' ],
				'permission_callback' => [ PageManagerController::class, 'search_permission' ],
			]
		);

		$route->get(
			'/page-manager/show',
			[
				'args'                => [ PageManagerController::class, 'show_args' ],
				'callback'            => [ PageManagerController::class, 'show' ],
				'permission_callback' => [ PageManagerController::class, 'show_permission' ],
			]
		);

		/**
		 * Menu manager endpoints.
		 */
		$route->post(
			'/menu-manager/create',
			[
				'args'                => [ MenuManagerController::class, 'create_args' ],
				'callback'            => [ MenuManagerController::class, 'create' ],
				'permission_callback' => [ MenuManagerController::class, 'menu_permission' ],
			]
		);

		$route->post(
			'/menu-manager/delete',
			[
				'args'                => [ MenuManagerController::class, 'delete_args' ],
				'callback'            => [ MenuManagerController::class, 'delete' ],
				'permission_callback' => [ MenuManagerController::class, 'menu_permission' ],
			]
		);

		$route->post(
			'/menu-manager/assign-location',
			[
				'args'                => [ MenuManagerController::class, 'assign_location_args' ],
				'callback'            => [ MenuManagerController::class, 'assign_location' ],
				'permission_callback' => [ MenuManagerController::class, 'menu_permission' ],
			]
		);

		$route->post(
			'/menu-manager/unassign-location',
			[
				'args'                => [ MenuManagerController::class, 'unassign_location_args' ],
				'callback'            => [ MenuManagerController::class, 'unassign_location' ],
				'permission_callback' => [ MenuManagerController::class, 'menu_permission' ],
			]
		);

		$route->post(
			'/menu-manager/resolve',
			[
				'args'                => [ MenuManagerController::class, 'resolve_args' ],
				'callback'            => [ MenuManagerController::class, 'resolve' ],
				'permission_callback' => [ MenuManagerController::class, 'resolve_permission' ],
			]
		);

		$route->post(
			'/menu-manager/items/create',
			[
				'args'                => [ MenuItemsController::class, 'create_args' ],
				'callback'            => [ MenuItemsController::class, 'create' ],
				'permission_callback' => [ MenuItemsController::class, 'menu_item_permission' ],
			]
		);

		$route->post(
			'/menu-manager/items/delete',
			[
				'args'                => [ MenuItemsController::class, 'delete_args' ],
				'callback'            => [ MenuItemsController::class, 'delete' ],
				'permission_callback' => [ MenuItemsController::class, 'menu_item_permission' ],
			]
		);

		$route->post(
			'/menu-manager/items/reorder',
			[
				'args'                => [ MenuItemsController::class, 'reorder_args' ],
				'callback'            => [ MenuItemsController::class, 'reorder' ],
				'permission_callback' => [ MenuItemsController::class, 'menu_item_permission' ],
			]
		);
	}
}
