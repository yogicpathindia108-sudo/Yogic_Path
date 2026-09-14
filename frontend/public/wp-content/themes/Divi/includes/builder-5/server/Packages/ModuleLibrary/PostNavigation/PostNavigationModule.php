<?php
/**
 * ModuleLibrary: Post Navigation Module class.
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostNavigation;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\URLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\StyleCommon\CommonStyle;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Background\BackgroundClassnames;
use ET\Builder\Packages\Module\Options\Background\BackgroundComponents;
use ET\Builder\Packages\Module\Options\Background\BackgroundParallaxScriptData;
use ET\Builder\Packages\Module\Options\Background\BackgroundVideoScriptData;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use stdClass;
use WP_Block_Type_Registry;
use WP_Block;
use WP_Query;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroup;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\Module\Options\Loop\LoopUtils;
use ET\Builder\Packages\ModuleLibrary\LoopQueryRegistry;

/**
 * `PostNavigationModule` is consisted of functions used for Post Navigation Module such as Front-End rendering, REST API Endpoints etc.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class PostNavigationModule implements DependencyInterface {

	/**
	 * Module custom CSS fields.
	 *
	 * This function is equivalent of JS function cssFields located in
	 * visual-builder/packages/module-library/src/components/post-nav/custom-css.ts.
	 *
	 * @since ??
	 *
	 * @return array The array of custom CSS fields.
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/post-nav' )->customCssFields;
	}

	/**
	 * Set CSS class names to the module.
	 *
	 * This function is equivalent of JS function moduleClassnames located in
	 * visual-builder/packages/module-library/src/components/post-nav/module-classnames.ts.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $id                  Module unique ID.
	 *     @type string $name                Module name with namespace.
	 *     @type array  $attrs               Module attributes.
	 *     @type array  $childrenIds         Module children IDs.
	 *     @type bool   $hasModule           Flag that indicates if module has child modules.
	 *     @type bool   $isFirst             Flag that indicates if module is first in the row.
	 *     @type bool   $isLast              Flag that indicates if module is last in the row.
	 *     @type object $classnamesInstance  Instance of Instance of ET\Builder\Packages\Module\Layout\Components\Classnames class.
	 *
	 *     // FE only.
	 *     @type int|null $storeInstance The ID of instance where this block stored in BlockParserStore.
	 *     @type int      $orderIndex    The order index of the element.
	 * }
	 */
	public static function module_classnames( $args ) {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Module Classname.
		$classnames_instance->add( 'nav-single' );

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => $attrs['module']['decoration'] ?? [],
				]
			)
		);
	}

	/**
	 * Set script data of used module options.
	 *
	 * This function is equivalent of JS function ModuleScriptData located in
	 * visual-builder/packages/module-library/src/components/post-navigation/module-script-data.tsx.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *   Array of arguments.
	 *
	 *   @type string         $id            Module id.
	 *   @type string         $name          Module name.
	 *   @type string         $selector      Module selector.
	 *   @type array          $attrs         Module attributes.
	 *   @type int            $storeInstance The ID of instance where this block stored in BlockParserStore class.
	 *   @type ModuleElements $elements      ModuleElements instance.
	 * }
	 */
	public static function module_script_data( $args ) {
		// Assign variables.
		$id             = $args['id'] ?? '';
		$name           = $args['name'] ?? '';
		$selector       = $args['selector'] ?? '';
		$attrs          = $args['attrs'] ?? [];
		$elements       = $args['elements'];
		$store_instance = $args['storeInstance'] ?? null;

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);

		// Background Script Data for individual navigation links.
		BackgroundVideoScriptData::set(
			[
				'id'            => $id . '_prev',
				'selector'      => $selector . ' .nav-previous a',
				'attr'          => $attrs['links']['decoration']['background'] ?? [],
				'storeInstance' => $store_instance,
			]
		);

		BackgroundVideoScriptData::set(
			[
				'id'            => $id . '_next',
				'selector'      => $selector . ' .nav-next a',
				'attr'          => $attrs['links']['decoration']['background'] ?? [],
				'storeInstance' => $store_instance,
			]
		);

		BackgroundParallaxScriptData::set(
			[
				'id'            => $id . '_prev',
				'selector'      => $selector . ' .nav-previous a',
				'attr'          => $attrs['links']['decoration']['background'] ?? [],
				'name'          => 'module',
				'storeInstance' => $store_instance,
			]
		);

		BackgroundParallaxScriptData::set(
			[
				'id'            => $id . '_next',
				'selector'      => $selector . ' .nav-next a',
				'attr'          => $attrs['links']['decoration']['background'] ?? [],
				'name'          => 'module',
				'storeInstance' => $store_instance,
			]
		);

		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'hoverSelector' => $selector,
				'setContent'    => [
					[
						'selector' => $selector . ' .nav-previous .nav-label',
						'data'     => $attrs['links']['advanced']['prevText'] ?? [],
					],
					[
						'selector' => $selector . ' .nav-next .nav-label',
						'data'     => $attrs['links']['advanced']['nextText'] ?? [],
					],
				],
			]
		);
	}


	/**
	 * Module container style declaration for pagination fix.
	 *
	 * Adds column-gap: 0 to prevent unwanted spacing in flex layout.
	 *
	 * @since ??
	 *
	 * @param array $params Parameters.
	 *
	 * @return string
	 */
	public static function paginationContainerStyleDeclaration( array $params ): string {
		$display = $params['attrValue']['display'] ?? '';

		if ( 'flex' !== $display ) {
			return '';
		}

		return 'column-gap: 0';
	}

	/**
	 * WP-PageNavi wrapper style declaration for pagination fix.
	 *
	 * Adds flex: auto to make the wrapper span full width in flex layout.
	 *
	 * @since ??
	 *
	 * @param array $params Parameters.
	 *
	 * @return string
	 */
	public static function paginationWrapperStyleDeclaration( array $params ): string {
		$display = $params['attrValue']['display'] ?? '';

		if ( 'flex' !== $display ) {
			return '';
		}

		return 'flex: auto';
	}

	/**
	 * Set CSS styles to the module.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * visual-builder/packages/module-library/src/components/post-nav/module-styles.tsx.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string         $id                       Module unique ID.
	 *     @type string         $name                     Module name with namespace.
	 *     @type array          $attrs                    Module attributes.
	 *     @type array          $parentAttrs              Parent module attributes.
	 *     @type array          $siblingAttrs             Sibling module attributes.
	 *     @type array          $defaultPrintedStyleAttrs Default printed style attributes.
	 *     @type string         $orderClass               Module CSS selector.
	 *     @type string         $parentOrderClass         Parent module CSS selector.
	 *     @type string         $wrapperOrderClass        Wrapper module CSS selector.
	 *     @type array          $settings                 Custom settings.
	 *     @type ModuleElements $elements                 ModuleElements instance.
	 *
	 *     // VB only.
	 *     @type string $state Attributes state.
	 *     @type string $mode  Style mode.
	 *
	 *     // FE only.
	 *     @type int|null $storeInstance The ID of instance where this block stored in BlockParserStore.
	 *     @type int      $orderIndex    The order index of the element.
	 * }
	 */
	public static function module_styles( $args ) {
		$attrs                       = $args['attrs'] ?? [];
		$elements                    = $args['elements'];
		$settings                    = $args['settings'] ?? [];
		$order_class                 = $args['orderClass'] ?? '';
		$is_inside_sticky_module     = $elements->get_is_inside_sticky_module();
		$sticky_parent_order_class   = $elements->get_sticky_parent_order_class();
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];

		$links_font_attr = $attrs['links']['decoration']['font']['font'] ?? [];
		$links_color     = $links_font_attr['desktop']['value']['color'] ?? null;

		// Preset-only link color is stored in preset printed style attrs; merged $attrs can still omit it (#48688).
		$preset_links_font  = $elements->preset_printed_style_attrs['links']['decoration']['font']['font'] ?? [];
		$preset_links_color = $preset_links_font['desktop']['value']['color'] ?? null;

		if ( ! empty( $links_color ) ) {
			$effective_links_color = $links_color;
		} elseif ( ! empty( $preset_links_color ) ) {
			$effective_links_color = $preset_links_color;
		} else {
			$effective_links_color = null;
		}

		$primary_color = empty( $effective_links_color ) ? GlobalData::get_accent_color( 'primary' ) : null;
		if ( empty( $primary_color ) && empty( $effective_links_color ) ) {
			$primary_color = 'var(--gcid-primary-color, #2ea3f2)';
		}

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					// Module.
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'defaultPrintedStyleAttrs' => $default_printed_style_attrs['module']['decoration'] ?? [],
								'disabledOn'               => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
								'advancedStyles'           => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr' => $attrs['module']['decoration']['border'] ?? [],
											'declarationFunction' => function ( $params ) use ( $attrs ) {
												$overflow_attr = $attrs['module']['decoration']['overflow'] ?? [];
												return Declarations::overflow_for_border_radius_style_declaration( $params, $overflow_attr );
											},
										],
									],
								],
							],
						]
					),

					( empty( $effective_links_color ) && ! empty( $primary_color ) )
						? CommonStyle::style(
							[
								'selector'   => "{$order_class} .wp-pagenavi a, {$order_class} .wp-pagenavi span.current, {$order_class} .wp-pagenavi span.pages, {$order_class} .wp-pagenavi span.extend, .et_pb_posts_nav{$order_class} span a, .et_pb_posts_nav{$order_class} span a span, .et_pb_posts_nav{$order_class} span:not(.nav-previous):not(.nav-next)",
								'attr'       => [
									'desktop' => [
										'value' => $primary_color,
									],
								],
								'property'   => 'color',
								'important'  => true,
								'orderClass' => $order_class,
							]
						)
						: null,
					// Links.
					$elements->style(
						[
							'attrName' => 'links',
						]
					),
					CommonStyle::style(
						[
							'selector'            => "{$order_class} .wp-pagenavi a, {$order_class} .wp-pagenavi span, {$order_class} .pagination a, .et_pb_posts_nav{$order_class} span.nav-previous a, .et_pb_posts_nav{$order_class} span.nav-next a",
							'attr'                => $attrs['links']['decoration']['border'] ?? [],
							'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
							'orderClass'          => $order_class,
						]
					),

					// Pagination fix: Apply column-gap and flex styles when using flex layout.
					CommonStyle::style(
						[
							'selector'            => $order_class,
							'attr'                => $attrs['module']['decoration']['layout'] ?? [
								'desktop' => [
									'value' => [
										'display' => 'flex',
									],
								],
							],
							'declarationFunction' => [ self::class, 'paginationContainerStyleDeclaration' ],
							'orderClass'          => $order_class,
						]
					),

					CommonStyle::style(
						[
							'selector'            => "{$order_class} .wp-pagenavi-wrapper",
							'attr'                => $attrs['module']['decoration']['layout'] ?? [
								'desktop' => [
									'value' => [
										'display' => 'flex',
									],
								],
							],
							'declarationFunction' => [ self::class, 'paginationWrapperStyleDeclaration' ],
							'orderClass'          => $order_class,
						]
					),

					CssStyle::style(
						[
							'selector'               => '.et_pb_posts_nav' . $order_class,
							'attr'                   => $attrs['css'] ?? [],
							'cssFields'              => self::custom_css(),
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
				],
			]
		);
	}

	/**
	 * Get current post ID.
	 *
	 * @since ??
	 *
	 * @return int The current post ID.
	 */
	protected static function _get_current_post_id() {
		$post_id = get_queried_object_id();

		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		return $post_id;
	}

	/**
	 * Get the Post Navigation data.
	 *
	 * @since ??
	 *
	 * @param array $args An array of arguments.
	 *
	 * @return array The array of Post Navigation data.
	 */
	public static function get_post_navigation( array $args = [] ) {
		global $post;

		$defaults = [
			'post_id'       => self::_get_current_post_id(),
			'in_same_term'  => 'off',
			'taxonomy_name' => 'category',
			'prev_text'     => '%title',
			'next_text'     => '%title',
		];

		$args = wp_parse_args( $args, $defaults );

		// taxonomy name overwrite if in_same_term option is set to off and no taxonomy name defined.
		if ( '' === $args['taxonomy_name'] || 'off' === $args['in_same_term'] ) {
			$args['taxonomy_name'] = is_singular( 'project' ) ? 'project_category' : 'category';
		}

		$in_same_term = ! ( ! $args['in_same_term'] || 'off' === $args['in_same_term'] );

		et_core_nonce_verified_previously();
		if ( $args['post_id'] ) {
			$post_id = $args['post_id'];
		} elseif ( is_object( $post ) && isset( $post->ID ) ) {
			$post_id = $post->ID;
		} elseif ( is_singular() ) {
			// If it's a single post or page.
			$post_id = self::_get_current_post_id();
		} else {
			return [
				'posts_navigation' => [
					'next' => '',
					'prev' => '',
				],
			];
		}

		// Set current post as global $post.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Override global $post for navigation context.
		$post = get_post( $post_id );

		// Get next post.
		$next_post = get_next_post( $in_same_term, '', $args['taxonomy_name'] );

		$next = new stdClass();

		if ( ! empty( $next_post ) ) {

			// Decode HTML entities for VB REST consumption.
			$next_title = isset( $next_post->post_title ) ? html_entity_decode( get_the_title( $next_post ) ) : html_entity_decode( esc_html__( 'Next Post' ) );

			$next_date      = mysql2date( get_option( 'date_format' ), $next_post->post_date );
			$next_permalink = isset( $next_post->ID ) ? esc_url( get_the_permalink( $next_post->ID ) ) : '';

			$next_processed_title = '' === $args['next_text'] ? '%title' : $args['next_text'];

			// Process WordPress' wildcards.
			$next_processed_title = str_replace(
				[ '%title', '%date', '%link' ],
				[
					$next_title,
					$next_date,
					$next_permalink,
				],
				$next_processed_title
			);

			$next->title     = $next_processed_title;
			$next->id        = isset( $next_post->ID ) ? (int) $next_post->ID : '';
			$next->permalink = $next_permalink;
		}

		// Get prev post.
		$prev_post = get_previous_post( $in_same_term, '', $args['taxonomy_name'] );

		$prev = new stdClass();

		if ( ! empty( $prev_post ) ) {

			// Decode HTML entities for VB REST consumption.
			$prev_title = isset( $prev_post->post_title ) ? html_entity_decode( get_the_title( $prev_post ) ) : html_entity_decode( esc_html__( 'Previous Post' ) );

			$prev_date = mysql2date( get_option( 'date_format' ), $prev_post->post_date );

			$prev_permalink = isset( $prev_post->ID ) ? esc_url( get_the_permalink( $prev_post->ID ) ) : '';

			$prev_processed_title = '' === $args['prev_text'] ? '%title' : $args['prev_text'];

			// Process WordPress' wildcards.
			$prev_processed_title = str_replace(
				[ '%title', '%date', '%link' ],
				[
					$prev_title,
					$prev_date,
					$prev_permalink,
				],
				$prev_processed_title
			);

			$prev->title     = $prev_processed_title;
			$prev->id        = isset( $prev_post->ID ) ? (int) $prev_post->ID : '';
			$prev->permalink = $prev_permalink;
		}

		// Check if WP Page Navi plugin is active and generate HTML.
		$wp_page_navi_html = '';
		$use_wp_page_navi  = false;

		if ( function_exists( 'wp_pagenavi' ) ) {
			// For regular post navigation, we need to create a query that represents the current post context.
			// This allows WP Page Navi to generate proper pagination for single post navigation.
			$current_query = new WP_Query(
				[
					'post_type'      => get_post_type( $post_id ),
					'posts_per_page' => 1,
					'post__in'       => [ $post_id ],
					'no_found_rows'  => false,
				]
			);

			if ( $current_query->have_posts() ) {
				ob_start();
				wp_pagenavi( [ 'query' => $current_query ] );
				$raw_html = ob_get_clean();

				// Sanitize the HTML output to prevent XSS attacks before sending to frontend.
				$wp_page_navi_html = wp_kses_post( $raw_html );
				$use_wp_page_navi  = ! empty( $wp_page_navi_html );
			}

			wp_reset_postdata();
		}

		// Enhanced response format with WP Page Navi support.
		$response = [
			'next' => $next,
			'prev' => $prev,
		];

		// Add WP Page Navi data if available.
		if ( $use_wp_page_navi ) {
			$response['wp_page_navi_html'] = $wp_page_navi_html;
			$response['use_wp_page_navi']  = true;
		}

		return $response;
	}

	/**
	 * Get pagination data for a connected loop.
	 *
	 * @since ??
	 *
	 * @param string   $loop_module_id  The ID of the module with the loop to connect to.
	 * @param array    $args            An array of arguments.
	 * @param int|null $store_instance  Optional. The store instance to search in. Default null.
	 *
	 * @return array Array with 'next' and 'prev' objects containing pagination data.
	 */
	public static function get_loop_pagination( $loop_module_id, $args = [], $store_instance = null ) {
		// For loop-connected pagination, we use namespaced URL parameters to support multiple loops.
		// Each loop gets its own parameter: loop-{id}=N
		// This prevents conflicts when multiple loops exist on the same page.

		$next = new stdClass();
		$prev = new stdClass();

		// Use text from args, or default values if not provided.
		$next_text = ! empty( $args['next_text'] ) ? $args['next_text'] : 'Next';
		$prev_text = ! empty( $args['prev_text'] ) ? $args['prev_text'] : 'Previous';

		// If this is Visual Builder mode, we don't want to navigate to the next page.
		// We'll use a placeholder permalink and text, but still check for WP Page Navi.
		if ( $args['is_vb'] ) {
			$next->title     = $next_text;
			$next->permalink = '#';
			$next->id        = 0; // Placeholder ID.

			// Check if WP Page Navi plugin is active for Visual Builder preview.
			$wp_page_navi_html = '';
			$use_wp_page_navi  = false;

			if ( function_exists( 'wp_pagenavi' ) ) {
				// For VB mode, create a minimal mock query for WP Page Navi preview.
				$mock_query                               = new WP_Query();
				$mock_query->found_posts                  = 50; // Mock total posts.
				$mock_query->max_num_pages                = 5;  // Mock pages.
				$mock_query->query_vars['paged']          = 1;  // Current page.
				$mock_query->query_vars['posts_per_page'] = 10; // Posts per page.

				// Generate WP Page Navi HTML for Visual Builder preview using echo => false.
				$wp_page_navi_html = wp_pagenavi(
					[
						'query' => $mock_query,
						'echo'  => false,
					]
				);

				// Sanitize the HTML output.
				$wp_page_navi_html = wp_kses_post( $wp_page_navi_html );
				$use_wp_page_navi  = ! empty( $wp_page_navi_html );
			}

			// Enhanced VB response with WP Page Navi support.
			$response = [
				'next' => $next,
			];

			// Add WP Page Navi data if available.
			if ( $use_wp_page_navi ) {
				$response['wp_page_navi_html'] = $wp_page_navi_html;
				$response['use_wp_page_navi']  = true;
			}

			return $response;
		}

		// CRITICAL: Run predictive query generation BEFORE getting pagination data
		// This ensures the loop query is available for total_pages calculation
		// regardless of WP Page Navi plugin status.
		$loop_query = LoopQueryRegistry::get_query( $loop_module_id, $store_instance );

		$module_pagination_data = self::get_loop_pagination_data( $loop_module_id, $store_instance );
		$total_pages            = $module_pagination_data['total_pages'] ?? 1;

		// Create namespaced parameter name for this specific loop.
		$loop_page_param = $loop_module_id;

		// Get current page from namespaced URL parameter or default to 1.
		// If no loop parameter exists, we're on page 1 (clean URL approach).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter for pagination, no security risk.
		$current_page = isset( $_GET[ $loop_page_param ] ) ? max( 1, (int) $_GET[ $loop_page_param ] ) : 1;
		$next_page    = $current_page + 1;
		$prev_page    = max( 1, $current_page - 1 );

		// Get the current URL for building pagination URLs.
		$current_url = URLUtility::get_canonical_current_request_url();

		// Show next button only if current page is less than total pages.
		if ( $current_page < $total_pages ) {
			// Build the next page URL with namespaced parameter.
			$next_url = add_query_arg( $loop_page_param, $next_page, $current_url );

			$next->title     = $next_text;
			$next->permalink = $next_url;
			$next->id        = 0; // Placeholder ID.
		}

		// Show previous button only if current page is 2 or higher.
		if ( $current_page >= 2 ) {
			// Build the previous page URL with namespaced parameter.
			$prev_url = add_query_arg( $loop_page_param, $prev_page, $current_url );

			$prev->title     = $prev_text;
			$prev->permalink = $prev_url;
			$prev->id        = 0; // Placeholder ID.
		}

		// Check if WP Page Navi plugin is active and generate HTML for loop pagination.
		$wp_page_navi_html = '';
		$use_wp_page_navi  = false;

		if ( function_exists( 'wp_pagenavi' ) ) {
			// Retrieve the loop query from registry (with automatic predictive generation if needed).
			$loop_query = LoopQueryRegistry::get_query( $loop_module_id, $store_instance );

			if ( $loop_query && $loop_query instanceof WP_Query ) {
				// Set the current page for the query to ensure proper pagination context.
				$loop_query->set( 'paged', $current_page );

				// Clean base URL without pagination parameters (canonical request URL).
				$base_url = remove_query_arg(
					[
						$loop_page_param,
						'paged',
						'page',
					],
					URLUtility::get_canonical_current_request_url()
				);

				// Add filter to intercept WP Page Navi's URL generation at the source.
				add_filter(
					'get_pagenum_link',
					function ( $result, $pagenum ) use ( $loop_page_param, $base_url ) {
						// Get clean base URL without any pagination parameters.
						$clean_url = remove_query_arg( [ 'paged', 'page', $loop_page_param ], $base_url );

						// Add loop parameter for all pages, including page 1.
						// This ensures consistency and helps with navigation when multiple loops are present.
						$loop_url = add_query_arg( $loop_page_param, $pagenum, $clean_url );
						return $loop_url;
					},
					10,
					2
				);

				// Add temporary filter to modify WP Page Navi URLs for this specific loop.
				add_filter(
					'wp_pagenavi',
					// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Callback signature required by filter.
					function ( $html, $args ) use ( $loop_page_param ) {
						// Convert /page/2/ URLs to ?loop-slnfd4uvma=2 format.
						$html = preg_replace_callback(
							'/href="([^"]*\/page\/(\d+)\/[^"]*)"/',
							function ( $matches ) use ( $loop_page_param ) {
								$page_number = $matches[2];
								$current_url = URLUtility::get_canonical_current_request_url();
								$loop_url    = add_query_arg(
									$loop_page_param,
									$page_number,
									$current_url
								);
								return 'href="' . esc_url( $loop_url ) . '"';
							},
							$html
						);
						return $html;
					},
					10,
					2
				);

				// Generate WP Page Navi HTML using the loop query.
				$wp_page_navi_html = wp_pagenavi(
					[
						'query' => $loop_query,
						'echo'  => false,
					]
				);

				// Remove filters after use to avoid affecting other instances.
				remove_all_filters( 'get_pagenum_link' );
				remove_all_filters( 'wp_pagenavi' );

				// Sanitize the HTML output to prevent XSS attacks before sending to frontend.
				$wp_page_navi_html = wp_kses_post( $wp_page_navi_html );
				$use_wp_page_navi  = ! empty( $wp_page_navi_html );
			}
		}

		// Enhanced response format with WP Page Navi support.
		$response = [
			'next' => $next,
			'prev' => $prev,
		];

		// Add WP Page Navi data if available.
		if ( $use_wp_page_navi ) {
			$response['wp_page_navi_html'] = $wp_page_navi_html;
			$response['use_wp_page_navi']  = true;
		}

		return $response;
	}

	/**
	 * Get loop pagination data by loop ID.
	 *
	 * This function retrieves pagination information for a given loop ID.
	 * You can use this to manage loop pagination by getting total_pages and current_page.
	 *
	 * @since ??
	 *
	 * @param string $loop_id        The loop ID to search for.
	 * @param int    $store_instance Optional. The store instance to search in. Default null.
	 *
	 * @return array|null Array with pagination info if found, null otherwise.
	 *                    Returns: ['total_pages' => int, 'current_page' => int, 'module_id' => string]
	 */
	public static function get_loop_pagination_data( $loop_id, $store_instance = null ) {
		// Get all modules from the BlockParserStore.
		$all_modules = BlockParserStore::get_all( $store_instance );

		if ( empty( $all_modules ) ) {
			return null;
		}

		// Search through all modules to find one with matching loop ID.
		foreach ( $all_modules as $module ) {
			// Check if this module has loop settings.
			$module_loop_id = $module->attrs['module']['advanced']['loop']['loop_pagination_id'] ?? null;

			if ( ! $module_loop_id ) {
				continue;
			}

			// Check if this matches our target loop pagination ID.
			if ( $loop_id === $module_loop_id ) {
				$total_pages_attr       = $module->attrs['module']['advanced']['loop']['loop_pagination_total_pages'] ?? null;
				$calculated_total_pages = $total_pages_attr ?? 1;

				// Check if we can get the loop query and calculate total pages from it.
				$loop_query = LoopQueryRegistry::get_query( $loop_id );
				if ( $loop_query && $loop_query instanceof WP_Query ) {
					$actual_total_pages = $loop_query->max_num_pages;

					// If the query has more accurate pagination data, use it.
					if ( $actual_total_pages > $calculated_total_pages ) {
						$calculated_total_pages = $actual_total_pages;
					}
				}

				return [
					'total_pages' => $calculated_total_pages,
				];
			}
		}

		return null;
	}

	/**
	 * Module render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * This function is equivalent of JS function PostNavEdit located in
	 * visual-builder/packages/module-library/src/components/post-nav/edit.tsx.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by VB.
	 * @param string         $child_modules_content       Block content (child modules content).
	 * @param WP_Block       $block                       Parsed block object that being rendered.
	 * @param ModuleElements $elements                    Instance of ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements class.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string The module HTML output.
	 */
	public static function render_callback( $attrs, $child_modules_content, $block, $elements, $default_printed_style_attrs ) {
		$children_ids = ChildrenUtils::extract_children_ids( $block );
		$show_prev    = ModuleUtils::has_value(
			$attrs['links']['advanced']['showPrev'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'off' !== $value;
				},
			]
		);
		$show_next    = ModuleUtils::has_value(
			$attrs['links']['advanced']['showNext'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'off' !== $value;
				},
			]
		);

		if ( ! $show_prev && ! $show_next ) {
			return '';
		}

		$in_same_term = ModuleUtils::has_value(
			$attrs['module']['advanced']['inSameTerm'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'off' !== $value;
				},
			]
		);

		$taxonomy_name = $attrs['module']['advanced']['taxonomyName']['desktop']['value'] ?? 'category';
		$prev_text     = $attrs['links']['advanced']['prevText']['desktop']['value'] ?? '';
		$next_text     = $attrs['links']['advanced']['nextText']['desktop']['value'] ?? '';
		$target_loop   = $attrs['module']['advanced']['targetLoop']['desktop']['value'] ?? 'main_query';

		// Prepare args for both regular and loop pagination.
		$args = [
			'post_id'       => self::_get_current_post_id(),
			'in_same_term'  => $in_same_term,
			'taxonomy_name' => $taxonomy_name,
			'prev_text'     => $prev_text,
			'next_text'     => $next_text,
			'is_vb'         => Conditions::is_vb_enabled(),
		];

		// Check if this pagination is connected to a specific loop.
		if ( 'main_query' !== $target_loop ) {
			// Get pagination for connected loop.
			$posts_navigation = self::get_loop_pagination( $target_loop, $args, $block->parsed_block['storeInstance'] ?? null );
		} else {
			// Use default post navigation behavior.
			$posts_navigation = self::get_post_navigation( $args );
		}

		$style_components = $elements->style_components(
			[
				'attrName' => 'module',
			]
		);

		$left_arrow = HTMLUtility::render(
			[
				'tag'        => 'span',
				'attributes' => [
					'class' => 'meta-nav',
				],
				'children'   => '&larr; ',
			]
		);

		$prev_background_components = BackgroundComponents::component(
			[
				'attr'          => $attrs['links']['decoration']['background'] ?? [],
				'id'            => $block->parsed_block['id'] . '_prev',
				'storeInstance' => $block->parsed_block['storeInstance'] ?? null,
			]
		);

		$prev_background_classnames = BackgroundClassnames::classnames( $attrs['links']['decoration']['background'] ?? [] );

		$prev_nav_inner = HTMLUtility::render(
			[
				'tag'               => 'a',
				'attributes'        => [
					'href'  => esc_url( $posts_navigation['prev']->permalink ?? '' ),
					'rel'   => 'prev',
					'class' => $prev_background_classnames,
				],
				'children'          => [
					$style_components,
					$prev_background_components,
					$left_arrow,
					HTMLUtility::render(
						[
							'tag'        => 'span',
							'attributes' => [
								'class' => 'nav-label',
							],
							'children'   => esc_html( $posts_navigation['prev']->title ?? '' ),
						]
					),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		$right_arrow = HTMLUtility::render(
			[
				'tag'        => 'span',
				'attributes' => [
					'class' => 'meta-nav',
				],
				'children'   => ' &rarr;',
			]
		);

		$next_background_components = BackgroundComponents::component(
			[
				'attr'          => $attrs['links']['decoration']['background'] ?? [],
				'id'            => $block->parsed_block['id'] . '_next',
				'storeInstance' => $block->parsed_block['storeInstance'] ?? null,
			]
		);

		$next_background_classnames = BackgroundClassnames::classnames( $attrs['links']['decoration']['background'] ?? [] );

		$next_nav_inner = HTMLUtility::render(
			[
				'tag'               => 'a',
				'attributes'        => [
					'href'  => esc_url( $posts_navigation['next']->permalink ?? '' ),
					'rel'   => 'next',
					'class' => $next_background_classnames,
				],
				'children'          => [
					$style_components,
					$next_background_components,
					HTMLUtility::render(
						[
							'tag'        => 'span',
							'attributes' => [
								'class' => 'nav-label',
							],
							'children'   => esc_html( $posts_navigation['next']->title ?? '' ),
						]
					),
					$right_arrow,
				],
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		// Check if WP Page Navi HTML is available and use it instead of manual Previous/Next links.
		if ( ! empty( $posts_navigation['wp_page_navi_html'] ) && ! empty( $posts_navigation['use_wp_page_navi'] ) ) {
			// Use WP Page Navi HTML for numbered pagination.
			$children = [
				HTMLUtility::render(
					[
						'tag'               => 'div',
						'attributes'        => [
							'class' => 'et_pb_posts_nav wp-pagenavi-wrapper',
						],
						'children'          => $posts_navigation['wp_page_navi_html'],
						'childrenSanitizer' => 'et_core_esc_previously',
					]
				),
			];
		} else {
			// Fallback to manual Previous/Next links when WP Page Navi is not available.
			$prev_nav_html = $show_prev && ! empty( $posts_navigation['prev']->permalink ) ? HTMLUtility::render(
				[
					'tag'               => 'span',
					'attributes'        => [
						'class' => 'nav-previous',
					],
					'children'          => $prev_nav_inner,
					'childrenSanitizer' => 'et_core_esc_previously',
				]
			) : null;

			$next_nav_html = $show_next && ! empty( $posts_navigation['next']->permalink ) ? HTMLUtility::render(
				[
					'tag'               => 'span',
					'attributes'        => [
						'class' => 'nav-next',
					],
					'children'          => $next_nav_inner,
					'childrenSanitizer' => 'et_core_esc_previously',
				]
			) : null;

			$children = [
				$prev_nav_html,
				$next_nav_html,
			];
		}

		$html_attrs = [];

		if ( 'main_query' !== $target_loop && is_string( $target_loop ) && '' !== $target_loop ) {
			$html_attrs['data-target-loop'] = esc_attr( $target_loop );
		}

		return Module::render(
			[
				// FE only.
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'                    => $attrs,
				'id'                       => $block->parsed_block['id'],
				'elements'                 => $elements,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'name'                     => $block->block_type->name,
				'moduleCategory'           => $block->block_type->category,
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'htmlAttrs'                => $html_attrs,
				'parentAttrs'              => [],
				'parentId'                 => '',
				'parentName'               => '',
				'childrenIds'              => $children_ids,
				'children'                 => array_merge( $children, [ $child_modules_content ] ),
			]
		);
	}

	/**
	 * Loads `PostNavigationModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load() {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/post-nav/';

		add_filter( 'divi_conversion_presets_attrs_map', [ PostNavigationPresetAttrsMap::class, 'get_map' ], 10, 2 );

		// Ensure that all filters and actions applied during module registration are registered before calling `ModuleRegistration::register_module()`.
		// However, for consistency, register all module-specific filters and actions prior to invoking `ModuleRegistration::register_module()`.
		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
