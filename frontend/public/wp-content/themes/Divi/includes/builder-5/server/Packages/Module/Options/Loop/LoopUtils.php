<?php
/**
 * Module: LoopUtils class.
 *
 * @package Builder\Packages\Module\Options\Loop
 */

namespace ET\Builder\Packages\Module\Options\Loop;

// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames -- Reserved keywords used intentionally for clarity in utility functions.
use WP_Query;
use WP_Term_Query;
use WP_User_Query;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\Packages\Module\Layout\Components\DynamicData\DynamicData;
use ET\Builder\Packages\ModuleLibrary\LoopQueryRegistry;
use ET\Builder\Packages\Module\Options\Loop\LoopContext;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentACFUtils;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentUtils;
use ET\Builder\Packages\Module\Options\Loop\QueryResults\QueryResultsController;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElementsUtils;
use WP_Block_Parser;
use ET_Post_Stack;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * LoopUtils class.
 *
 * @since ??
 */
class LoopUtils {
	/**
	 * Default posts per page for current_page context queries.
	 *
	 * @var int
	 */
	const CURRENT_PAGE_DEFAULT_PER_PAGE = 10;

	/**
	 * Loop id to post type map populated during loop preprocessing.
	 *
	 * @var array<string, string>
	 */
	private static $_loop_target_post_types = [];

	/**
	 * Loop id to order-by context map populated during loop preprocessing.
	 *
	 * @var array<string, array{query_type: string, post_type: string, taxonomy: string}>
	 */
	private static $_loop_order_by_contexts = [];

	/**
	 * Loop id to include/exclude term group attrs populated during loop preprocessing.
	 *
	 * @var array<string, array{include: array, exclude: array}>
	 */
	private static $_loop_term_restriction_attrs = [];

	/**
	 * Register the target post type for a loop id.
	 *
	 * Populated during loop preprocessing so modules that render before the loop
	 * module enters BlockParserStore can still resolve the effective post type.
	 *
	 * @since ??
	 *
	 * @param string $loop_id   Loop id.
	 * @param string $post_type Post type slug.
	 *
	 * @return void
	 */
	public static function register_loop_target_post_type( string $loop_id, string $post_type ): void {
		$loop_id   = trim( $loop_id );
		$post_type = sanitize_key( $post_type );

		if ( '' === $loop_id || '' === $post_type ) {
			return;
		}

		self::$_loop_target_post_types[ $loop_id ] = $post_type;
	}

	/**
	 * Get a pre-registered target post type for a loop id.
	 *
	 * @since ??
	 *
	 * @param string $loop_id Loop id.
	 *
	 * @return string Post type slug or empty string.
	 */
	public static function get_registered_loop_target_post_type( string $loop_id ): string {
		$loop_id = trim( $loop_id );

		return self::$_loop_target_post_types[ $loop_id ] ?? '';
	}

	/**
	 * Reset the loop target post type registry.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function reset_loop_target_post_type_registry(): void {
		self::$_loop_target_post_types = [];
	}

	/**
	 * Register order-by context for a loop id.
	 *
	 * Populated during loop preprocessing so modules that render before the loop
	 * module enters BlockParserStore can still resolve terms/user/menu order-by options.
	 *
	 * @since ??
	 *
	 * @param string $loop_id    Loop id.
	 * @param string $query_type Loop query type slug.
	 * @param string $taxonomy   Taxonomy slug for terms loops.
	 * @param string $post_type  Post type slug for post-type loops.
	 *
	 * @return void
	 */
	public static function register_loop_order_by_context(
		string $loop_id,
		string $query_type,
		string $taxonomy = '',
		string $post_type = ''
	): void {
		$loop_id = trim( $loop_id );

		if ( '' === $loop_id || '' === $query_type ) {
			return;
		}

		self::$_loop_order_by_contexts[ $loop_id ] = [
			'query_type' => sanitize_key( $query_type ),
			'post_type'  => sanitize_key( $post_type ),
			'taxonomy'   => sanitize_key( $taxonomy ),
		];
	}

	/**
	 * Get a pre-registered order-by context for a loop id.
	 *
	 * @since ??
	 *
	 * @param string $loop_id Loop id.
	 *
	 * @return array{query_type: string, post_type: string, taxonomy: string}
	 */
	public static function get_registered_loop_order_by_context( string $loop_id ): array {
		$loop_id = trim( $loop_id );

		return self::$_loop_order_by_contexts[ $loop_id ] ?? [];
	}

	/**
	 * Reset the loop order-by context registry.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function reset_loop_order_by_context_registry(): void {
		self::$_loop_order_by_contexts = [];
	}

	/**
	 * Register include/exclude term group attrs for a loop id.
	 *
	 * Populated during loop preprocessing so modules that render before the loop
	 * module enters BlockParserStore can still resolve taxonomy list restrictions.
	 *
	 * @since ??
	 *
	 * @param string $loop_id       Loop id.
	 * @param array  $include_terms Include term group attrs.
	 * @param array  $exclude_terms Exclude term group attrs.
	 *
	 * @return void
	 */
	public static function register_loop_term_restriction_attrs(
		string $loop_id,
		array $include_terms,
		array $exclude_terms
	): void {
		$loop_id = trim( $loop_id );

		if ( '' === $loop_id ) {
			return;
		}

		self::$_loop_term_restriction_attrs[ $loop_id ] = [
			'include' => $include_terms,
			'exclude' => $exclude_terms,
		];
	}

	/**
	 * Get pre-registered include/exclude term group attrs for a loop id.
	 *
	 * @since ??
	 *
	 * @param string $loop_id Loop id.
	 *
	 * @return array{include: array, exclude: array}
	 */
	public static function get_registered_loop_term_restriction_attrs( string $loop_id ): array {
		$loop_id = trim( $loop_id );

		return self::$_loop_term_restriction_attrs[ $loop_id ] ?? [
			'include' => [],
			'exclude' => [],
		];
	}

	/**
	 * Reset the loop term restriction attrs registry.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function reset_loop_term_restriction_attrs_registry(): void {
		self::$_loop_term_restriction_attrs = [];
	}

	/**
	 * Resolve order-by list-item context from a loop id.
	 *
	 * @since ??
	 *
	 * @param string   $target_loop_id Target loop id.
	 * @param int|null $store_instance Optional block parser store instance.
	 *
	 * @return array{query_type: string, post_type: string, taxonomy: string}
	 */
	public static function resolve_loop_order_by_context_from_loop_id( string $target_loop_id, $store_instance = null ): array {
		$target_loop_id = trim( $target_loop_id );
		$default_context = [
			'query_type' => 'post_types',
			'post_type'  => 'post',
			'taxonomy'   => '',
		];

		if ( '' === $target_loop_id ) {
			return $default_context;
		}

		$module = self::find_module_by_loop_id( $target_loop_id, $store_instance );

		if ( null !== $module ) {
			return self::_resolve_order_by_context_from_loop_attrs( $module->attrs );
		}

		$registered_context = self::get_registered_loop_order_by_context( $target_loop_id );

		if ( ! empty( $registered_context ) ) {
			return array_merge( $default_context, $registered_context );
		}

		$post_type = self::resolve_target_post_type_from_loop_id( $target_loop_id, $store_instance );

		return [
			'query_type' => 'post_types',
			'post_type'  => '' !== $post_type ? $post_type : 'post',
			'taxonomy'   => '',
		];
	}

	/**
	 * Register frontend hooks for loop target post type pre-registration.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_filter( 'the_content', [ self::class, 'pre_register_loop_target_post_types_from_content' ], 8 );
		add_filter( 'et_builder_render_layout', [ self::class, 'pre_register_loop_target_post_types_from_content' ], 8 );
	}

	/**
	 * Pre-register loop target post types before block rendering begins.
	 *
	 * Front-end layout rendering uses `do_blocks()`, which walks blocks in document order.
	 * Modules such as post-filter-item can render before a sibling loop module enters
	 * BlockParserStore, so loop post types must be discovered from the full document first.
	 *
	 * @since ??
	 *
	 * @param string $content Layout content.
	 *
	 * @return string Unmodified layout content.
	 */
	public static function pre_register_loop_target_post_types_from_content( string $content ): string {
		if ( isset( $GLOBALS['divi_generating_excerpt'] ) && $GLOBALS['divi_generating_excerpt'] ) {
			return $content;
		}

		if ( ! self::has_any_loop_enabled_blocks( $content ) ) {
			return $content;
		}

		$wordpress_block_parser = new WP_Block_Parser();
		$parsed_blocks          = $wordpress_block_parser->parse( $content );

		if ( ! empty( $parsed_blocks ) ) {
			self::_register_loop_target_post_types_from_blocks( $parsed_blocks );
		}

		return $content;
	}

	/**
	 * Recursively register loop target post types from parsed blocks.
	 *
	 * @since ??
	 *
	 * @param array $blocks Parsed blocks.
	 *
	 * @return void
	 */
	private static function _register_loop_target_post_types_from_blocks( array $blocks ): void {
		foreach ( $blocks as $block ) {
			if ( empty( $block ) ) {
				continue;
			}

			if ( self::_is_block_loop_enabled( $block ) ) {
				$loop_id = $block['attrs']['module']['advanced']['loop']['desktop']['value']['loopId'] ?? '';

				if ( is_string( $loop_id ) && '' !== trim( $loop_id ) ) {
					$loop_data = self::get_query_args_from_attrs( $block['attrs'] ?? [] );
					self::_register_loop_target_post_type_from_loop_data( trim( $loop_id ), $loop_data );
					self::_register_loop_order_by_context_from_loop_data( trim( $loop_id ), $loop_data );
					self::_register_loop_term_restriction_attrs_from_block_attrs( trim( $loop_id ), $block['attrs'] ?? [] );
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::_register_loop_target_post_types_from_blocks( $block['innerBlocks'] );
			}
		}
	}

	/**
	 * Find module by loop ID in BlockParserStore.
	 *
	 * This function searches through all modules in the BlockParserStore to find
	 * a module with a matching loop ID. Used for predictive query generation
	 * when pagination is placed above the loop.
	 *
	 * @since ??
	 *
	 * @param string   $target_loop_id  The loop ID to search for.
	 * @param int|null $store_instance  Optional. The store instance to search in. Default null.
	 *
	 * @return object|null The module object if found, null otherwise.
	 */
	public static function find_module_by_loop_id( $target_loop_id, $store_instance = null ) {
		if ( empty( $target_loop_id ) ) {
			return null;
		}

		$instance_ids = BlockParserStore::get_instance_ids();

		if ( empty( $instance_ids ) ) {
			return null;
		}

		$preferred_instance = BlockParserStore::has_instance( $store_instance ) ? (int) $store_instance : BlockParserStore::get_instance();

		if ( null !== $preferred_instance ) {
			array_unshift( $instance_ids, $preferred_instance );
			$instance_ids = array_values( array_unique( $instance_ids ) );
		}

		foreach ( $instance_ids as $instance_id ) {
			$all_modules = BlockParserStore::get_all( $instance_id );

			if ( empty( $all_modules ) ) {
				continue;
			}

			foreach ( $all_modules as $module ) {
				$module_loop_id = $module->attrs['module']['advanced']['loop']['desktop']['value']['loopId'] ?? null;

				if ( ! $module_loop_id ) {
					continue;
				}

				if ( $target_loop_id === $module_loop_id ) {
					return $module;
				}
			}
		}

		return null;
	}

	/**
	 * Resolve effective post type slug from a loop id.
	 *
	 * @since ??
	 *
	 * @param string   $target_loop_id Target loop id.
	 * @param int|null $store_instance Optional block parser store instance.
	 *
	 * @return string Post type slug or empty string when not applicable.
	 */
	public static function resolve_target_post_type_from_loop_id( string $target_loop_id, $store_instance = null ): string {
		$target_loop_id = trim( $target_loop_id );

		if ( '' === $target_loop_id ) {
			return '';
		}

		$module = self::find_module_by_loop_id( $target_loop_id, $store_instance );

		if ( null !== $module ) {
			$resolved_post_type = self::_resolve_target_post_type_from_loop_attrs( $module->attrs );

			if ( '' !== $resolved_post_type ) {
				self::register_loop_target_post_type( $target_loop_id, $resolved_post_type );

				return $resolved_post_type;
			}
		}

		return self::get_registered_loop_target_post_type( $target_loop_id );
	}

	/**
	 * Resolve include/exclude term ID restrictions for one taxonomy from loop attrs.
	 *
	 * @since ??
	 *
	 * @param array  $loop_attrs    Loop attrs (`module.advanced.loop`).
	 * @param string $taxonomy_slug Taxonomy slug to resolve restrictions for.
	 *
	 * @return array{include: int[], exclude: int[], has_include_restriction: bool} Include and exclude term ID lists.
	 */
	public static function resolve_loop_term_restrictions_for_taxonomy( array $loop_attrs, string $taxonomy_slug ): array {
		$include_terms = $loop_attrs['desktop']['value']['includePostWithSpecificTerms'] ?? [];
		$exclude_terms = $loop_attrs['desktop']['value']['excludePostWithSpecificTerms'] ?? [];
		$taxonomy_slug = sanitize_key( $taxonomy_slug );

		$include_ids               = self::_extract_valid_term_ids_for_taxonomy( is_array( $include_terms ) ? $include_terms : [], $taxonomy_slug );
		$exclude_ids               = self::_extract_valid_term_ids_for_taxonomy( is_array( $exclude_terms ) ? $exclude_terms : [], $taxonomy_slug );
		$has_include_restriction   = self::_has_term_group_for_taxonomy( is_array( $include_terms ) ? $include_terms : [], $taxonomy_slug );

		if ( ! empty( $include_ids ) && ! empty( $exclude_ids ) ) {
			$include_ids = array_values( array_diff( $include_ids, $exclude_ids ) );
		}

		return [
			'include'                 => $include_ids,
			'exclude'                 => $exclude_ids,
			'has_include_restriction' => $has_include_restriction,
		];
	}

	/**
	 * Resolve include/exclude term ID restrictions from a target loop id.
	 *
	 * @since ??
	 *
	 * @param string   $target_loop_id  Target loop id.
	 * @param string   $taxonomy_slug   Taxonomy slug to resolve restrictions for.
	 * @param int|null $store_instance  Optional block parser store instance.
	 *
	 * @return array{include: int[], exclude: int[], has_include_restriction: bool} Include and exclude term ID lists.
	 */
	public static function resolve_loop_term_restrictions_for_taxonomy_from_loop_id(
		string $target_loop_id,
		string $taxonomy_slug,
		$store_instance = null
	): array {
		$target_loop_id = trim( $target_loop_id );

		if ( '' === $target_loop_id || '' === $taxonomy_slug ) {
			return [
				'include'                 => [],
				'exclude'                 => [],
				'has_include_restriction' => false,
			];
		}

		$module = self::find_module_by_loop_id( $target_loop_id, $store_instance );

		if ( null !== $module ) {
			$loop_attrs = $module->attrs['module']['advanced']['loop'] ?? [];

			return self::resolve_loop_term_restrictions_for_taxonomy( is_array( $loop_attrs ) ? $loop_attrs : [], $taxonomy_slug );
		}

		$registered_term_attrs = self::get_registered_loop_term_restriction_attrs( $target_loop_id );

		if ( ! empty( $registered_term_attrs['include'] ) || ! empty( $registered_term_attrs['exclude'] ) ) {
			return self::resolve_loop_term_restrictions_for_taxonomy(
				[
					'desktop' => [
						'value' => [
							'includePostWithSpecificTerms' => $registered_term_attrs['include'],
							'excludePostWithSpecificTerms' => $registered_term_attrs['exclude'],
						],
					],
				],
				$taxonomy_slug
			);
		}

		return [
			'include'                 => [],
			'exclude'                 => [],
			'has_include_restriction' => false,
		];
	}

	/**
	 * Filter term IDs to those that exist in a taxonomy.
	 *
	 * @since ??
	 *
	 * @param array  $term_ids Array of term IDs to validate.
	 * @param string $taxonomy Taxonomy name to validate terms against.
	 *
	 * @return array Array of valid term IDs.
	 */
	public static function filter_valid_term_ids( array $term_ids, string $taxonomy ): array {
		return self::_filter_invalid_term_ids( $term_ids, $taxonomy );
	}

	/**
	 * Extract validated term IDs for one taxonomy from loop term group attrs.
	 *
	 * @since ??
	 *
	 * @param array  $term_groups   Loop include/exclude term group attrs.
	 * @param string $taxonomy_slug Taxonomy slug to match.
	 *
	 * @return int[] Valid term IDs for the taxonomy.
	 */
	private static function _extract_valid_term_ids_for_taxonomy( array $term_groups, string $taxonomy_slug ): array {
		foreach ( $term_groups as $term_group ) {
			if ( ! isset( $term_group['categoryId'], $term_group['selectedOptions'] ) ) {
				continue;
			}

			$taxonomy = sanitize_key( $term_group['categoryId'] );

			if ( $taxonomy !== $taxonomy_slug ) {
				continue;
			}

			$terms = [];

			if ( is_array( $term_group['selectedOptions'] ) ) {
				$terms = array_map( 'intval', array_filter( array_column( $term_group['selectedOptions'], 'value' ) ) );
			}

			if ( empty( $terms ) ) {
				return [];
			}

			return self::_filter_invalid_term_ids( $terms, $taxonomy );
		}

		return [];
	}

	/**
	 * Check whether loop attrs configure a term group for one taxonomy.
	 *
	 * @since ??
	 *
	 * @param array  $term_groups   Loop include/exclude term group attrs.
	 * @param string $taxonomy_slug Taxonomy slug to match.
	 *
	 * @return bool Whether a matching term group is configured.
	 */
	private static function _has_term_group_for_taxonomy( array $term_groups, string $taxonomy_slug ): bool {
		foreach ( $term_groups as $term_group ) {
			if ( ! isset( $term_group['categoryId'], $term_group['selectedOptions'] ) ) {
				continue;
			}

			$taxonomy = sanitize_key( $term_group['categoryId'] );

			if ( $taxonomy !== $taxonomy_slug ) {
				continue;
			}

			return is_array( $term_group['selectedOptions'] ) && ! empty( $term_group['selectedOptions'] );
		}

		return false;
	}

	/**
	 * Generate query predictively when registry is empty.
	 *
	 * This function implements predictive query generation by finding the target
	 * loop module and generating its query before the loop actually renders.
	 * The generated query is stored in the registry for reuse by the loop.
	 *
	 * @since ??
	 *
	 * @param string   $loop_module_id  The loop ID to generate query for.
	 * @param int|null $store_instance  Optional. The store instance to search in. Default null.
	 *
	 * @return WP_Query|null The generated query object, or null if generation failed.
	 */
	public static function generate_predictive_query( $loop_module_id, $store_instance = null ) {
		$target_module = self::find_module_by_loop_id( $loop_module_id, $store_instance );

		if ( ! $target_module ) {
			return null;
		}

		$loop_data = self::get_query_args_from_attrs( $target_module->attrs );

		if ( empty( $loop_data['query_args'] ) ) {
			return null;
		}

		/*
		 * This filter is required so that features like the Post Filter module
		 * can intercept and modify the loop's computed query arguments before executing the actual query.
		 * For example, the Post Filter module adjusts $loop_data here to apply user-selected filters,
		 * ensuring that the results shown in the loop reflect current filter UI state.
		 */
		$loop_data = apply_filters(
			'divi_loop_data_after_execution',
			$loop_data,
			$target_module->attrs,
			[
				'attrs'         => $target_module->attrs,
				'storeInstance' => $store_instance,
			]
		);

		$query_result = self::execute_query( $loop_data['query_args'], $loop_data['query_type'] );
		$loop_query   = $query_result['query_object'] ?? null;

		if (
			$loop_query &&
			( $loop_query instanceof WP_Query || $loop_query instanceof WP_User_Query || $loop_query instanceof WP_Term_Query )
		) {
			LoopQueryRegistry::store( $loop_module_id, $loop_query, $loop_data['query_args'], $loop_data['query_type'] );
		}

		return $loop_query;
	}

	/**
	 * Build WP_Query arguments from module $attrs.
	 *
	 * @since ??
	 *
	 * @param array $attrs The block attributes that were saved by the Visual Builder.
	 *
	 * @return array The WP_Query arguments array.
	 */
	public static function get_query_args_from_attrs( $attrs ) {
		$loop = isset( $attrs['module']['advanced']['loop'] )
			? $attrs['module']['advanced']['loop']
			: [];

		$loop_enabled = isset( $loop['desktop']['value']['enable'] )
			? sanitize_key( $loop['desktop']['value']['enable'] )
			: '';

		$query_type = isset( $loop['desktop']['value']['queryType'] )
			? sanitize_key( $loop['desktop']['value']['queryType'] )
			: 'post_types';

		$default_order_by = self::_get_default_order_by_for_query_type( $query_type );

		// Handle query type mapping - post_taxonomies should be treated as terms query.
		if ( 'post_taxonomies' === $query_type ) {
			$query_type = 'terms';
		}

		if ( 'post_types' === $query_type ) {
			// For post types query, extract post types from subTypes if available.
			$post_type = self::_extract_sub_type_values( $loop );
			// Allow empty post_type to be handled by _build_post_query_args which will set it to 'any' for all post types.
		} else {
			// For other query types, extract post types from subTypes.
			$post_type = self::_extract_sub_type_values( $loop );
		}

		$order_by_raw = isset( $loop['desktop']['value']['orderBy'] )
			? sanitize_key( $loop['desktop']['value']['orderBy'] )
			: $default_order_by;

		$order_raw = isset( $loop['desktop']['value']['order'] )
			? sanitize_key( self::_extract_order_by_value( $loop['desktop']['value']['order'] ) )
			: 'DESC';

		$post_per_page = isset( $loop['desktop']['value']['postPerPage'] )
			? absint( $loop['desktop']['value']['postPerPage'] )
			: 10;

		$post_offset = isset( $loop['desktop']['value']['postOffset'] )
			? absint( $loop['desktop']['value']['postOffset'] )
			: 0;

		// Check if we're on a paginated page and adjust offset.
		$current_page = 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter for pagination, no security risk.
		if ( isset( $_GET ) && is_array( $_GET ) ) {
			// Look for loop-specific page parameter.
			$loop_id = isset( $loop['desktop']['value']['loopId'] ) ? $loop['desktop']['value']['loopId'] : null;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter for pagination, no security risk.
			if ( $loop_id && isset( $_GET[ $loop_id ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter for pagination, no security risk.
				$current_page = max( 1, (int) $_GET[ $loop_id ] );
			}
		}

		// Calculate pagination offset if we're on a page other than 1.
		if ( $current_page > 1 ) {
			$pagination_offset = ( $current_page - 1 ) * $post_per_page;
			$post_offset      += $pagination_offset;
		}

		$ignore_stickys_post = isset( $loop['desktop']['value']['ignoreStickysPost'] )
			? sanitize_key( $loop['desktop']['value']['ignoreStickysPost'] )
			: '';

		$exclude_current_post = isset( $loop['desktop']['value']['excludeCurrentPost'] )
			? sanitize_key( $loop['desktop']['value']['excludeCurrentPost'] )
			: 'off';

		// Get advanced filtering attributes.
		// NOTE: These arrays are sanitized downstream in their respective _build_* functions:
		// - Taxonomy arrays: categoryId sanitized with sanitize_key(), term IDs with intval() in _build_taxonomy_query().
		// - Post ID arrays: values sanitized with intval() in _build_post_inclusion_exclusion_query().
		$include_post_with_specific_terms = isset( $loop['desktop']['value']['includePostWithSpecificTerms'] )
			? $loop['desktop']['value']['includePostWithSpecificTerms']
			: [];

		$exclude_post_with_specific_terms = isset( $loop['desktop']['value']['excludePostWithSpecificTerms'] )
			? $loop['desktop']['value']['excludePostWithSpecificTerms']
			: [];

		$include_specific_posts = isset( $loop['desktop']['value']['includeSpecificPosts'] )
			? $loop['desktop']['value']['includeSpecificPosts']
			: [];

		$exclude_specific_posts = isset( $loop['desktop']['value']['excludeSpecificPosts'] )
			? $loop['desktop']['value']['excludeSpecificPosts']
			: [];

		// Get meta query attributes.
		// NOTE: Meta query arrays are sanitized downstream in _build_meta_query():
		// - metaKey/metaValue sanitized with sanitize_text_field().
		// - compare operators validated against allowlist, type validated against allowlist.
		$meta_query_attrs = isset( $loop['desktop']['value']['metaQuery'] )
			? $loop['desktop']['value']['metaQuery']
			: [];

		// Get search attribute.
		$search = isset( $loop['desktop']['value']['search'] )
			? sanitize_text_field( $loop['desktop']['value']['search'] )
			: '';

		$params = [
			'loop_enabled'                     => $loop_enabled,
			'post_type'                        => $post_type,
			'query_type'                       => $query_type,
			'order_by_raw'                     => $order_by_raw,
			'order_raw'                        => $order_raw,
			'post_per_page'                    => $post_per_page,
			'post_offset'                      => $post_offset,
			'ignore_stickys_post'              => $ignore_stickys_post,
			'exclude_current_post'             => $exclude_current_post,
			'include_post_with_specific_terms' => $include_post_with_specific_terms,
			'exclude_post_with_specific_terms' => $exclude_post_with_specific_terms,
			'include_specific_posts'           => $include_specific_posts,
			'exclude_specific_posts'           => $exclude_specific_posts,
			'meta_query_attrs'                 => $meta_query_attrs,
			'search'                           => $search,
		];

		// Check if this is a user query and handle accordingly.
		if ( self::_is_user_query( $query_type ) ) {
			return self::_build_user_query_args( $params );
		}

		// Check if this is a terms query and handle accordingly.
		if ( self::_is_terms_query( $query_type ) ) {
			return self::_build_terms_query_args( $params );
		}

		// Check if this is a menus query and handle accordingly.
		if ( self::_is_menus_query( $query_type ) ) {
			return self::_build_menus_query_args( $params );
		}

		if ( DynamicContentACFUtils::is_repeater_query( $query_type ) ) {
			return DynamicContentACFUtils::build_repeater_query_args( $params );
		}

		// Default to post query handling.
		return self::_build_post_query_args( $params );
	}

	/**
	 * Check if the query type is a user query.
	 *
	 * @since ??
	 *
	 * @param string $query_type The query type.
	 *
	 * @return bool True if it's a user query, false otherwise.
	 */
	private static function _is_user_query( $query_type ) {
		$user_query_types = [ 'user_roles', 'users' ];
		return in_array( $query_type, $user_query_types, true );
	}

	/**
	 * Check if the query type is a terms query.
	 *
	 * @since ??
	 *
	 * @param string $query_type The query type.
	 *
	 * @return bool True if it's a terms query, false otherwise.
	 */
	private static function _is_terms_query( $query_type ) {
		$terms_query_types = [ 'terms', 'post_taxonomies' ];
		return in_array( $query_type, $terms_query_types, true );
	}

	/**
	 * Check if the query type is a menus query.
	 *
	 * @since ??
	 *
	 * @param string $query_type The query type.
	 *
	 * @return bool True if it's a menus query, false otherwise.
	 */
	private static function _is_menus_query( $query_type ) {
		return 'menus' === $query_type;
	}

	/**
	 * Build WP_User_Query arguments for user queries.
	 *
	 * @since ??
	 *
	 * @param array $params Extracted parameters from loop settings.
	 *
	 * @return array The query result array.
	 */
	private static function _build_user_query_args( $params ) {
		// NOTE: All user input parameters ($params) are sanitized in get_query_args_from_attrs()
		// before reaching this function. No additional sanitization is needed for basic parameters.

		// Build WP_User_Query arguments.
		$query_args = [
			'orderby' => $params['order_by_raw'],
			'order'   => $params['order_raw'],
		];

		// Handle user roles (post_type contains roles for user queries).
		$roles = $params['post_type'];
		if ( ! empty( $roles ) ) {
			$query_args['role__in'] = array_map( 'sanitize_key', $roles );
		}

		// Handle pagination.
		if ( $params['post_per_page'] > 0 ) {
			$query_args['number'] = $params['post_per_page'];
		}

		if ( $params['post_offset'] > 0 ) {
			$query_args['offset'] = $params['post_offset'];
		}

		// Handle search.
		if ( ! empty( $params['search'] ) ) {
			$query_args['search'] = '*' . $params['search'] . '*';
		}

		// Handle meta query.
		if ( ! empty( $params['meta_query_attrs'] ) ) {
			$meta_query = self::build_meta_query( $params['meta_query_attrs'] );
			if ( ! empty( $meta_query ) ) {
				$query_args['meta_query'] = $meta_query;
			}
		}

		// Handle user inclusion/exclusion.
		$user_include = [];
		$user_exclude = [];

		if ( ! empty( $params['include_specific_posts'] ) && is_array( $params['include_specific_posts'] ) ) {
			$user_include = array_map( 'intval', array_filter( array_column( $params['include_specific_posts'], 'value' ) ) );
		}

		if ( ! empty( $params['exclude_specific_posts'] ) && is_array( $params['exclude_specific_posts'] ) ) {
			$user_exclude = array_map( 'intval', array_filter( array_column( $params['exclude_specific_posts'], 'value' ) ) );
		}

		if ( ! empty( $user_include ) ) {
			$query_args['include'] = $user_include;
		}

		if ( ! empty( $user_exclude ) ) {
			$query_args['exclude'] = $user_exclude;
		}

		$result = [
			'loop_enabled' => $params['loop_enabled'],
			'query_args'   => $query_args,
			'query_type'   => $params['query_type'],
			'post_type'    => $params['post_type'], // Contains roles for user queries.
		];

		return $result;
	}

	/**
	 * Build WP_Query arguments for post queries.
	 *
	 * @since ??
	 *
	 * @param array $params Extracted parameters from loop settings.
	 *
	 * @return array The query result array.
	 */
	private static function _build_post_query_args( $params ) {
		// NOTE: All user input parameters ($params) are sanitized in get_query_args_from_attrs()
		// before reaching this function. No additional sanitization is needed for basic parameters.

		$post_type = $params['post_type'];

		// Handle empty post_type parameter - set to 'any' to query all post types.
		if ( empty( $post_type ) ) {
			$post_type = [ 'any' ];
		} else {
			$post_type        = array_map( 'sanitize_key', $post_type );
			$valid_post_types = [];

			// Use a loop to avoid per-call closure allocation from array_filter().
			foreach ( $post_type as $type ) {
				if ( post_type_exists( $type ) ) {
					$valid_post_types[] = $type;
				}
			}
			$post_type = array_values( $valid_post_types );

			// If all selected post types are invalid, fall back to querying all post types.
			if ( empty( $post_type ) ) {
				$post_type = [ 'any' ];
			}
		}

		// Build WP_Query arguments.
		$query_args = [
			'post_type'   => $post_type,
			'post_status' => 'publish',
			'orderby'     => $params['order_by_raw'],
			'order'       => $params['order_raw'],
		];

		// Handle post status for 'any' post type and arrays.
		if ( in_array( 'attachment', $post_type, true ) || in_array( 'any', $post_type, true ) ) {
			$query_args['post_status'] = [ 'publish', 'inherit', 'private' ];
		}

		// Only include posts_per_page if set by attribute (not default from get_option).
		if ( $params['post_per_page'] > 0 ) {
			$query_args['posts_per_page'] = $params['post_per_page'];
		}

		// Only include offset if not 0.
		if ( 0 !== $params['post_offset'] ) {
			$query_args['offset'] = $params['post_offset'];
		}

		// Handle taxonomy filtering with intersection logic.
		$tax_query_parts = self::_build_taxonomy_query( $params['include_post_with_specific_terms'], $params['exclude_post_with_specific_terms'] );
		if ( ! empty( $tax_query_parts ) ) {
			$query_args['tax_query'] = $tax_query_parts;
		}

		// Handle post inclusion/exclusion filtering.
		$post_query_args = self::_build_post_inclusion_exclusion_query( $params['include_specific_posts'], $params['exclude_specific_posts'] );
		$query_args      = array_merge( $query_args, $post_query_args );

		// Handle meta query parameters.
		// Note: $params['meta_query_attrs'] is sanitized within the build_meta_query method.
		$meta_query = self::build_meta_query( $params['meta_query_attrs'] );
		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		// Add search query if specified.
		if ( ! empty( $params['search'] ) ) {
			$query_args['s'] = $params['search'];
		}

		// Handle sticky posts for post type 'post' or when querying all post types ('any').
		$post_types = is_array( $post_type ) ? $post_type : [ $post_type ];
		if ( 'any' === $post_type || in_array( 'post', $post_types, true ) ) {
			// Handle explicit ignore_sticky_posts parameter.
			if ( 'on' === $params['ignore_stickys_post'] ) {
				$query_args['ignore_sticky_posts'] = 1;

				// Get all sticky posts.
				$sticky_posts = get_option( 'sticky_posts' );

				if ( ! empty( $sticky_posts ) ) {
					if ( isset( $query_args['post__not_in'] ) ) {
						$query_args['post__not_in'] = array_unique(
							array_merge( $query_args['post__not_in'], $sticky_posts )
						);
					} else {
						$query_args['post__not_in'] = $sticky_posts;
					}
				}
			}
		}

		// Handle post exclusions.
		$excluded_ids = [];

		// Use ET_Post_Stack::get_main_post_id() for Theme Builder compatibility.
		$main_post_id    = ET_Post_Stack::get_main_post_id();
		$current_post_id = $main_post_id ?? get_the_ID();

		// Handle exclude_current_post setting.
		if ( 'on' === $params['exclude_current_post'] && $current_post_id ) {
			$excluded_ids[] = $current_post_id;
		}

		// Apply post exclusions if any.
		if ( ! empty( $excluded_ids ) ) {
			$excluded_ids = array_unique( $excluded_ids );
			if ( isset( $query_args['post__not_in'] ) ) {
				$query_args['post__not_in'] = array_unique(
					array_merge( $query_args['post__not_in'], $excluded_ids )
				);
			} else {
				$query_args['post__not_in'] = $excluded_ids;
			}
		}

		$result = [
			'loop_enabled' => $params['loop_enabled'],
			'query_args'   => $query_args,
			'query_type'   => $params['query_type'],
			'post_type'    => $post_type,
		];

		return $result;
	}

	/**
	 * Filter out non-existent term IDs for a specific taxonomy.
	 *
	 * @since ??
	 *
	 * @param array  $term_ids Array of term IDs to validate.
	 * @param string $taxonomy Taxonomy name to validate terms against.
	 *
	 * @return array Array of valid term IDs.
	 */
	private static function _filter_invalid_term_ids( $term_ids, $taxonomy ) {
		$valid_term_ids = [];

		foreach ( $term_ids as $term_id ) {
			$term_id = intval( $term_id );
			$term    = term_exists( $term_id, $taxonomy );
			if ( ! empty( $term ) ) {
				$valid_term_ids[] = $term_id;
			}
		}

		return $valid_term_ids;
	}

	/**
	 * Build taxonomy query from include/exclude specific terms.
	 *
	 * @since ??
	 *
	 * @param array $include_terms Array of term inclusion data.
	 * @param array $exclude_terms Array of term exclusion data.
	 *
	 * @return array Formatted tax_query array for WP_Query.
	 */
	private static function _build_taxonomy_query( $include_terms, $exclude_terms ) {
		$include_taxonomies = [];
		$exclude_taxonomies = [];

		// Process inclusion terms.
		if ( ! empty( $include_terms ) && is_array( $include_terms ) ) {
			foreach ( $include_terms as $term_group ) {
				if ( ! isset( $term_group['categoryId'], $term_group['selectedOptions'] ) ) {
					continue;
				}

				$taxonomy = sanitize_key( $term_group['categoryId'] );
				$terms    = [];

				if ( is_array( $term_group['selectedOptions'] ) ) {
					$terms = array_map( 'intval', array_filter( array_column( $term_group['selectedOptions'], 'value' ) ) );
				}

				// Filter out non-existent terms.
				if ( ! empty( $terms ) ) {
					$terms = self::_filter_invalid_term_ids( $terms, $taxonomy );
				}

				if ( ! empty( $terms ) ) {
					$include_taxonomies[ $taxonomy ] = $terms;
				}
			}
		}

		// Process exclusion terms.
		if ( ! empty( $exclude_terms ) && is_array( $exclude_terms ) ) {
			foreach ( $exclude_terms as $term_group ) {
				if ( ! isset( $term_group['categoryId'], $term_group['selectedOptions'] ) ) {
					continue;
				}

				$taxonomy = sanitize_key( $term_group['categoryId'] );
				$terms    = [];

				if ( is_array( $term_group['selectedOptions'] ) ) {
					$terms = array_map( 'intval', array_filter( array_column( $term_group['selectedOptions'], 'value' ) ) );
				}

				// Filter out non-existent terms.
				if ( ! empty( $terms ) ) {
					$terms = self::_filter_invalid_term_ids( $terms, $taxonomy );
				}

				if ( ! empty( $terms ) ) {
					$exclude_taxonomies[ $taxonomy ] = $terms;
				}
			}
		}

		// Build taxonomy queries with proper include/exclude logic.
		$tax_query_parts = [];

		// Process inclusion queries (remove any terms that also appear in exclude for same taxonomy).
		foreach ( $include_taxonomies as $taxonomy => $include_terms_list ) {
			// If this taxonomy also has exclusions, remove conflicting terms from includes.
			if ( isset( $exclude_taxonomies[ $taxonomy ] ) ) {
				$final_include_terms = array_diff( $include_terms_list, $exclude_taxonomies[ $taxonomy ] );
			} else {
				$final_include_terms = $include_terms_list;
			}

			if ( ! empty( $final_include_terms ) ) {
				$tax_query_parts[] = [
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => array_values( $final_include_terms ),
					'operator' => 'IN',
				];
			}
		}

		// Process exclusion queries (create separate NOT IN clauses for all exclude conditions).
		foreach ( $exclude_taxonomies as $taxonomy => $exclude_terms_list ) {
			if ( ! empty( $exclude_terms_list ) ) {
				$tax_query_parts[] = [
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $exclude_terms_list,
					'operator' => 'NOT IN',
				];
			}
		}

		// Apply relation logic.
		if ( ! empty( $tax_query_parts ) ) {
			if ( count( $tax_query_parts ) === 1 ) {
				// Single taxonomy condition.
				return $tax_query_parts;
			} else {
				// Multiple taxonomy conditions.
				$has_exclude_conditions = false;
				foreach ( $tax_query_parts as $part ) {
					if ( 'NOT IN' === $part['operator'] ) {
						$has_exclude_conditions = true;
						break;
					}
				}

				if ( $has_exclude_conditions ) {
					// When exclude conditions exist, use AND relation so excludes take priority.
					$final_tax_query = array_merge( [ 'relation' => 'AND' ], $tax_query_parts );
				} else {
					// Only include conditions, use OR relation.
					$final_tax_query = array_merge( [ 'relation' => 'OR' ], $tax_query_parts );
				}

				return $final_tax_query;
			}
		}

		return [];
	}

	/**
	 * Build post inclusion/exclusion query parameters.
	 *
	 * @since ??
	 *
	 * @param array $include_posts Array of post inclusion data.
	 * @param array $exclude_posts Array of post exclusion data.
	 *
	 * @return array Query arguments for post inclusion/exclusion.
	 */
	private static function _build_post_inclusion_exclusion_query( $include_posts, $exclude_posts ) {

		$query_args = [];
		$post_in    = [];
		$post_out   = [];

		// Parse include posts.
		if ( ! empty( $include_posts ) && is_array( $include_posts ) ) {
			$post_in = array_map( 'intval', array_filter( array_column( $include_posts, 'value' ) ) );
		}

		// Parse exclude posts.
		if ( ! empty( $exclude_posts ) && is_array( $exclude_posts ) ) {
			$post_out = array_map( 'intval', array_filter( array_column( $exclude_posts, 'value' ) ) );
		}

		// Apply exclude-override-include logic.
		if ( ! empty( $post_in ) && ! empty( $post_out ) ) {
			// Remove any post IDs that appear in both include and exclude from the include list.
			$final_post_in = array_diff( $post_in, $post_out );

			// Apply include condition only if there are remaining posts to include.
			if ( ! empty( $final_post_in ) ) {
				$query_args['post__in'] = array_values( $final_post_in );
			}

			// Always apply exclude condition.
			$query_args['post__not_in'] = $post_out;
		} elseif ( ! empty( $post_in ) ) {
			// Only inclusion specified.
			$query_args['post__in'] = $post_in;
		} elseif ( ! empty( $post_out ) ) {
			// Only exclusion specified.
			$query_args['post__not_in'] = $post_out;
		}

		return $query_args;
	}

	/**
	 * Whether a meta query attribute value should be treated as provided (not omitted).
	 *
	 * Do not use empty() here: in PHP, empty( 0 ) and empty( '0' ) are true but are valid meta values.
	 *
	 * @since ??
	 *
	 * @param mixed $meta_value Raw meta value from meta query item attributes.
	 *
	 * @return bool
	 */
	private static function _meta_query_item_value_is_provided( $meta_value ): bool {
		if ( is_numeric( $meta_value ) ) {
			return true;
		}

		if ( is_string( $meta_value ) ) {
			return '' !== $meta_value;
		}

		return false;
	}

	/**
	 * Build meta query from meta query attributes (unified utility).
	 *
	 * This is a reusable utility function that can be used by both LoopUtils
	 * and QueryResultsController to build meta queries consistently.
	 *
	 * @since ??
	 *
	 * @param array $meta_query_items Array of meta query items.
	 *
	 * @return array Formatted meta_query array for WP_Query/WP_User_Query.
	 */
	public static function build_meta_query( array $meta_query_items ): array {
		$meta_query = [];

		// Meta query is optional - return empty array if not provided.
		if ( empty( $meta_query_items ) ) {
			return $meta_query;
		}

		$valid_compares = [
			'=',
			'!=',
			'>',
			'>=',
			'<',
			'<=',
			'LIKE',
			'NOT LIKE',
			'IN',
			'NOT IN',
			'BETWEEN',
			'NOT BETWEEN',
			'EXISTS',
			'NOT EXISTS',
			'REGEXP',
			'NOT REGEXP',
			'RLIKE',
		];

		// Process each meta query item.
		foreach ( $meta_query_items as $meta_item ) {
			if ( ! is_array( $meta_item ) ) {
				continue;
			}

			// Support both field name formats: key/value and metaKey/metaValue.
			$meta_key   = $meta_item['key'] ?? $meta_item['metaKey'] ?? '';
			$meta_value = $meta_item['value'] ?? $meta_item['metaValue'] ?? '';

			$compare_normalized = null;
			if ( isset( $meta_item['compare'] ) && '' !== trim( (string) $meta_item['compare'] ) ) {
				// Don't use sanitize_text_field() as it converts < to &lt; which breaks validation.
				$candidate_compare = strtoupper( trim( $meta_item['compare'] ) );
				if ( in_array( $candidate_compare, $valid_compares, true ) ) {
					$compare_normalized = $candidate_compare;
				}
			}

			$is_exists_compare = in_array( $compare_normalized, [ 'EXISTS', 'NOT EXISTS' ], true );

			if ( empty( $meta_key ) ) {
				continue;
			}

			// EXISTS / NOT EXISTS may omit value; other compares require a usable value (0 / '0' are valid).
			if ( ! $is_exists_compare && ! self::_meta_query_item_value_is_provided( $meta_value ) ) {
				continue;
			}

			// Build meta query clause.
			$meta_clause = [
				'key' => sanitize_text_field( $meta_key ),
			];

			$include_value = ! $is_exists_compare || self::_meta_query_item_value_is_provided( $meta_value );
			if ( $include_value ) {
				$meta_clause['value'] = sanitize_text_field( is_scalar( $meta_value ) ? (string) $meta_value : '' );
			}

			if ( null !== $compare_normalized ) {
				$meta_clause['compare'] = $compare_normalized;

				// Convert value to array for operators that require arrays.
				if ( in_array( $compare_normalized, [ 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ], true ) ) {
					$value = sanitize_text_field( is_scalar( $meta_value ) ? (string) $meta_value : '' );
					if ( str_contains( $value, ',' ) ) {
						$meta_clause['value'] = array_map( 'trim', explode( ',', $value ) );
					} else {
						$meta_clause['value'] = [ $value ];
					}
				}
			}

			// Add type if provided.
			if ( isset( $meta_item['type'] ) && ! empty( $meta_item['type'] ) ) {
				$valid_types = [
					'NUMERIC',
					'BINARY',
					'CHAR',
					'DATE',
					'DATETIME',
					'DECIMAL',
					'SIGNED',
					'TIME',
					'UNSIGNED',
				];

				$type = strtoupper( sanitize_text_field( $meta_item['type'] ) );

				if ( in_array( $type, $valid_types, true ) ) {
					// If DECIMAL type is selected, use DECIMAL(10,3) for proper decimal handling.
					if ( 'DECIMAL' === $type ) {
						$meta_clause['type'] = 'DECIMAL(10,3)';
					} else {
						$meta_clause['type'] = $type;
					}
				}
			}

			$meta_query[] = $meta_clause;
		}

		// Set relation to OR for multiple meta query clauses.
		if ( count( $meta_query ) > 1 ) {
			$meta_query['relation'] = 'OR';
		}

		return $meta_query;
	}

	/**
	 * Execute a query with the generated args, or extract results from existing query object.
	 *
	 * @since ??
	 *
	 * @param array      $query_args     Query arguments.
	 * @param string     $query_type     Optional. The type of query to execute ('post_types', 'user_roles', etc.).
	 * @param mixed|null $existing_query Optional. Existing query object to extract results from, or null for fresh query.
	 *
	 * @return array Query results array with 'results', 'total_pages', and optionally 'query_object'.
	 */
	public static function execute_query( $query_args, $query_type = 'post_types', $existing_query = null ) {
		// Handle existing (cached) query objects - eliminates code duplication.
		if ( $existing_query ) {
			return self::_extract_results_from_existing_query( $existing_query, $query_type, $query_args );
		}

		// Handle fresh queries based on type.
		if ( self::_is_user_query( $query_type ) ) {
			return self::_execute_user_query( $query_args );
		}

		if ( self::_is_terms_query( $query_type ) ) {
			return self::_execute_terms_query( $query_args );
		}

		if ( self::_is_menus_query( $query_type ) ) {
			return self::_execute_menus_query( $query_args );
		}

		if ( DynamicContentACFUtils::is_repeater_query( $query_type ) ) {
			// Ensure current_post_id is present in query args for repeater queries.
			if ( ! isset( $query_args['current_post_id'] ) || empty( $query_args['current_post_id'] ) ) {
				$query_args['current_post_id'] = QueryResultsController::get_current_post_id( $query_args );
			}
			if ( ! isset( $query_args['query_all_posts'] ) ) {
				$query_args['query_all_posts'] = true;
			}
			return DynamicContentACFUtils::execute_repeater_query( $query_args );
		}

		// Default to post query.
		return self::_execute_post_query( $query_args );
	}

	/**
	 * Extract results from an existing (cached) query object.
	 *
	 * This function uses the same formatting logic as fresh queries, ensuring
	 * perfect consistency between cached and fresh query result processing.
	 *
	 * @since ??
	 *
	 * @param mixed  $query_object The existing query object (WP_Query, WP_User_Query, WP_Term_Query).
	 * @param string $query_type   The query type (for context/validation).
	 * @param array  $query_args   Original query arguments (needed for pagination calculations).
	 *
	 * @return array Query results array with 'results', 'query_object', and 'total_pages'.
	 */
	private static function _extract_results_from_existing_query( $query_object, $query_type, $query_args ) {
		// NOTE: Repeater queries are not true WordPress queries - they are ACF field data
		// processors that retrieve and process meta field values. Unlike WP_Query, WP_User_Query,
		// and WP_Term_Query, repeater queries don't create reusable query objects and instead
		// perform custom field data retrieval, processing, and manual pagination via array_slice().
		// This is why repeater queries are never cached in LoopQueryRegistry.

		// Use the same formatting logic as fresh queries to ensure perfect consistency.
		if ( $query_object instanceof WP_Query ) {
			return self::_format_post_query_results( $query_object, $query_args );
		} elseif ( $query_object instanceof WP_User_Query ) {
			return self::_format_user_query_results( $query_object, $query_args );
		} elseif ( $query_object instanceof WP_Term_Query ) {
			return self::_format_terms_query_results( $query_object, $query_args );
		}

		// Fallback for unexpected query object types.
		return [
			'results'      => [],
			'total_pages'  => 0,
			'query_object' => $query_object,
		];
	}

	/**
	 * Format results from a WP_Query object.
	 *
	 * This function extracts and formats results from a WP_Query object,
	 * ensuring consistent result structure for both fresh and cached queries.
	 *
	 * @since ??
	 *
	 * @param WP_Query $query      The WP_Query object.
	 * @param array    $query_args Original query arguments (for consistency).
	 *
	 * @return array The formatted query result array.
	 */

	/**
	 * Format post query results.
	 *
	 * @since ??
	 *
	 * @param WP_Query|WP_Error $query      Query result.
	 * @param array             $query_args Query arguments.
	 *
	 * @return array Formatted results.
	 *
	 * @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Parameter required for future use.
	 */
	private static function _format_post_query_results( $query, $query_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Parameter required for future use.
		if ( is_wp_error( $query ) || empty( $query->posts ) ) {
			return [
				'results'      => null,
				'total_pages'  => null,
				'query_object' => null,
			];
		}

		// Return array structure for backward compatibility, but include query object.
		return [
			'results'      => $query->posts,
			'total_pages'  => $query->max_num_pages,
			'query_object' => $query,
		];
	}

	/**
	 * Execute a WP_Query for posts.
	 *
	 * @since ??
	 *
	 * @param array $query_args WP_Query arguments.
	 *
	 * @return array The query result array.
	 */
	private static function _execute_post_query( $query_args ) {
		$query = new WP_Query( $query_args );
		return self::_format_post_query_results( $query, $query_args );
	}

	/**
	 * Format results from a WP_User_Query object.
	 *
	 * This function extracts and formats results from a WP_User_Query object,
	 * ensuring consistent result structure for both fresh and cached queries.
	 *
	 * @since ??
	 *
	 * @param WP_User_Query $query      The WP_User_Query object.
	 * @param array         $query_args Original query arguments (needed for pagination calculation).
	 *
	 * @return array The formatted query result array.
	 */
	private static function _format_user_query_results( $query, $query_args ) {
		$users = $query->get_results();

		if ( is_wp_error( $query ) || empty( $users ) ) {
			return [
				'results'      => null,
				'total_pages'  => null,
				'query_object' => null,
			];
		}

		// Calculate total pages manually since WP_User_Query doesn't have max_num_pages.
		$total_pages = 0;
		if ( isset( $query_args['number'] ) && $query_args['number'] > 0 ) {
			$total_users = $query->get_total();
			if ( $total_users > 0 ) {
				$total_pages = ceil( $total_users / $query_args['number'] );
			}
		}

		// Return array structure for backward compatibility, but include query object.
		return [
			'results'      => $users,
			'total_pages'  => $total_pages,
			'query_object' => $query,
		];
	}

	/**
	 * Execute a WP_User_Query for users.
	 *
	 * @since ??
	 *
	 * @param array $query_args WP_User_Query arguments.
	 *
	 * @return array The executed query result array.
	 */
	private static function _execute_user_query( $query_args ) {
		$query = new WP_User_Query( $query_args );
		return self::_format_user_query_results( $query, $query_args );
	}

	/**
	 * Format results from a WP_Term_Query object.
	 *
	 * This function extracts and formats results from a WP_Term_Query object,
	 * ensuring consistent result structure for both fresh and cached queries.
	 *
	 * @since ??
	 *
	 * @param WP_Term_Query $query      The WP_Term_Query object.
	 * @param array         $query_args Original query arguments (needed for pagination calculation).
	 *
	 * @return array The formatted query result array.
	 */
	private static function _format_terms_query_results( $query, $query_args ) {
		$terms = $query->get_terms();

		if ( is_wp_error( $query ) || empty( $terms ) ) {
			return [
				'results'      => null,
				'total_pages'  => null,
				'query_object' => null,
			];
		}

		// Calculate total pages manually since WP_Term_Query doesn't have max_num_pages.
		$total_pages = 0;
		if ( isset( $query_args['number'] ) && $query_args['number'] > 0 ) {
			// Create count query args by removing pagination parameters.
			$count_args = $query_args;
			unset( $count_args['number'] );
			unset( $count_args['offset'] );
			$count_args['fields'] = 'count';

			$total_terms = wp_count_terms( $count_args );
			if ( ! is_wp_error( $total_terms ) && $total_terms > 0 ) {
				$total_pages = ceil( $total_terms / $query_args['number'] );
			}
		}

		// Return array structure for backward compatibility, but include query object.
		return [
			'results'      => $terms,
			'total_pages'  => $total_pages,
			'query_object' => $query,
		];
	}

	/**
	 * Execute a WP_Term_Query for terms.
	 *
	 * @since ??
	 *
	 * @param array $query_args WP_Term_Query arguments.
	 *
	 * @return array The executed query result array.
	 */
	private static function _execute_terms_query( $query_args ) {
		$query = new WP_Term_Query( $query_args );
		return self::_format_terms_query_results( $query, $query_args );
	}

	/**
	 * Build query arguments for menus query.
	 *
	 * @since ??
	 *
	 * @param array $params The parameters array.
	 *
	 * @return array The query arguments array.
	 */
	private static function _build_menus_query_args( array $params ): array {
		// NOTE: All user input parameters ($params) are sanitized in get_query_args_from_attrs()
		// before reaching this function. No additional sanitization is needed for basic parameters.

		// For menus queries, menu_id is stored in post_type (similar to how roles are stored for user queries).
		$menu_ids = $params['post_type'] ?? [];

		// Handle multiple menu IDs - convert array to comma-separated string or use string as-is.
		$menu_id = '';
		if ( ! empty( $menu_ids ) && is_array( $menu_ids ) ) {
			// Convert array to comma-separated string for multiple menus.
			$menu_id = implode( ',', array_map( 'strval', $menu_ids ) );
		} elseif ( ! empty( $menu_ids ) && is_string( $menu_ids ) ) {
			$menu_id = $menu_ids;
		}

		$query_args = [
			'query_type'     => 'menus',
			'menu_id'        => $menu_id,
			'menus_per_page' => $params['post_per_page'] ?? 10,
			'menu_offset'    => $params['post_offset'] ?? 0,
			'order_by'       => $params['order_by_raw'] ?? 'menu_order',
			'order'          => $params['order_raw'] ?? 'DESC',
		];

		$result = [
			'loop_enabled' => $params['loop_enabled'],
			'query_args'   => $query_args,
			'query_type'   => $params['query_type'],
			'post_type'    => $params['post_type'], // Contains menu_id(s) for menus queries.
		];

		return $result;
	}

	/**
	 * Execute a menus query.
	 *
	 * @since ??
	 *
	 * @param array $query_args Menu query arguments.
	 *
	 * @return array The executed query result array.
	 */
	private static function _execute_menus_query( array $query_args ): array {
		// Use QueryResultsController to get menu items.
		$result = QueryResultsController::get_menus_results( $query_args );

		// Format the result to match expected structure.
		$items = $result['items'] ?? [];

		return [
			'results'      => $items,
			'total_pages'  => $result['total_pages'] ?? 0,
			'query_object' => null, // Menus don't use WordPress query objects.
		];
	}

	/**
	 * Render the standardized 'No Results Found' message for Loop Builder modules.
	 *
	 * This should be used by any module implementing loop queries to ensure consistent UI.
	 *
	 * @since ??
	 *
	 * @return string The rendered HTML for the no results message.
	 */
	public static function render_no_results_found_message() {
		// Use HTMLUtility for consistent markup and escaping.
		return HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [ 'class' => 'entry' ],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => HTMLUtility::render(
					[
						'tag'        => 'h2',
						'attributes' => [ 'class' => 'not-found-title' ],
						'children'   => __( 'No Results Found.', 'et_builder_5' ),
					]
				) . HTMLUtility::render(
					[
						'tag'      => 'p',
						'children' => __( 'The page you requested could not be found.', 'et_builder_5' ) . ' ' . __( 'Try refining your search, or use the navigation above to locate the post.', 'et_builder_5' ),
					]
				),
			]
		);
	}

	/**
	 * Generate dummy posts with lorem ipsum content.
	 *
	 * @since ??
	 *
	 * @param int $per_page Number of dummy posts to generate.
	 *
	 * @return array Array of dummy post objects.
	 */
	public static function generate_dummy_posts( int $per_page ): array {
		$posts        = [];
		$current_user = wp_get_current_user();
		$home_url     = get_option( 'home' );
		$current_date = current_time( 'F j, Y' );

		$lorem_title   = 'Lorem Ipsum Dolor Sit Amet';
		$lorem_excerpt = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';

		for ( $i = 0; $i < $per_page; $i++ ) {
			$post_id = 100000 + $i + 1; // Generate unique sequential IDs starting from 100001.

			// Generate permalink using home URL and post ID.
			$permalink = trailingslashit( $home_url );

			$posts[] = [
				'id'         => $post_id,
				'title'      => $lorem_title,
				'excerpt'    => $lorem_excerpt,
				'permalink'  => $permalink,
				'date'       => $current_date,
				'categories' => '[{"name":"Technology","url":"#"},{"name":"Web Design","url":"#"}]',
				'tags'       => '[{"name":"WordPress","url":"#"},{"name":"Divi","url":"#"},{"name":"Tutorial","url":"#"}]',
				'terms'      => '[{"name":"Technology","url":"#"},{"name":"Web Design","url":"#"}]',
				'post_type'  => 'post',
				'thumbnail'  => ET_BUILDER_PLACEHOLDER_LANDSCAPE_IMAGE_DATA,
				'author'     => $current_user->display_name ? $current_user->display_name : 'admin',
			];
		}

		return $posts;
	}

	/**
	 * Resolve target post type from loop module attrs.
	 *
	 * @since ??
	 *
	 * @param array $attrs Loop module attrs.
	 *
	 * @return string Post type slug or empty string.
	 */
	private static function _resolve_target_post_type_from_loop_attrs( array $attrs ): string {
		$loop         = $attrs['module']['advanced']['loop'] ?? [];
		$loop_enabled = sanitize_key( $loop['desktop']['value']['enable'] ?? '' );

		if ( 'on' !== $loop_enabled ) {
			return '';
		}

		$query_type  = sanitize_key( $loop['desktop']['value']['queryType'] ?? 'post_types' );
		$sub_types   = self::_extract_sub_type_values( $loop );
		$first_value = ! empty( $sub_types ) ? (string) $sub_types[0] : '';

		if ( 'post_types' === $query_type ) {
			return $first_value;
		}

		if ( 'current_page' === $query_type ) {
			return '' !== $first_value ? $first_value : 'post';
		}

		return '';
	}

	/**
	 * Register loop target post type from parsed loop data.
	 *
	 * @since ??
	 *
	 * @param string $loop_id   Loop id.
	 * @param array  $loop_data Parsed loop data.
	 *
	 * @return void
	 */
	private static function _register_loop_target_post_type_from_loop_data( string $loop_id, array $loop_data ): void {
		$post_type = self::_extract_first_post_type_slug_from_loop_data( $loop_data );

		if ( '' !== $post_type ) {
			self::register_loop_target_post_type( $loop_id, $post_type );
		}
	}

	/**
	 * Register loop order-by context from parsed loop data.
	 *
	 * @since ??
	 *
	 * @param string $loop_id   Loop id.
	 * @param array  $loop_data Parsed loop data.
	 *
	 * @return void
	 */
	private static function _register_loop_order_by_context_from_loop_data( string $loop_id, array $loop_data ): void {
		$query_type    = sanitize_key( $loop_data['query_type'] ?? 'post_types' );
		$sub_type      = $loop_data['post_type'] ?? '';
		$first_subtype = '';

		if ( is_array( $sub_type ) && ! empty( $sub_type ) ) {
			$first_subtype = sanitize_key( (string) $sub_type[0] );
		} elseif ( is_string( $sub_type ) && '' !== $sub_type ) {
			$first_subtype = sanitize_key( $sub_type );
		}

		if ( in_array( $query_type, [ 'post_taxonomies', 'terms' ], true ) ) {
			self::register_loop_order_by_context( $loop_id, 'post_taxonomies', $first_subtype );

			return;
		}

		if ( 'user_roles' === $query_type ) {
			self::register_loop_order_by_context( $loop_id, 'user_roles' );

			return;
		}

		if ( 'menus' === $query_type ) {
			self::register_loop_order_by_context( $loop_id, 'menus', '', $first_subtype );

			return;
		}

		if ( 'current_page' === $query_type ) {
			$post_type = self::_extract_first_post_type_slug_from_loop_data( $loop_data );

			self::register_loop_order_by_context(
				$loop_id,
				'current_page',
				'',
				'' !== $post_type ? $post_type : 'post'
			);

			return;
		}

		$post_type = self::_extract_first_post_type_slug_from_loop_data( $loop_data );

		self::register_loop_order_by_context(
			$loop_id,
			'post_types',
			'',
			'' !== $post_type ? $post_type : 'post'
		);
	}

	/**
	 * Register loop term restriction attrs from parsed block attrs.
	 *
	 * @since ??
	 *
	 * @param string $loop_id      Loop id.
	 * @param array  $block_attrs  Parsed block attrs.
	 *
	 * @return void
	 */
	private static function _register_loop_term_restriction_attrs_from_block_attrs( string $loop_id, array $block_attrs ): void {
		$loop          = $block_attrs['module']['advanced']['loop'] ?? [];
		$include_terms = $loop['desktop']['value']['includePostWithSpecificTerms'] ?? [];
		$exclude_terms = $loop['desktop']['value']['excludePostWithSpecificTerms'] ?? [];

		self::register_loop_term_restriction_attrs(
			$loop_id,
			is_array( $include_terms ) ? $include_terms : [],
			is_array( $exclude_terms ) ? $exclude_terms : []
		);
	}

	/**
	 * Checks whether a loop subTypes entry looks like a value-label pair.
	 *
	 * @since ??
	 *
	 * @param mixed $entry Raw entry value.
	 *
	 * @return bool True when entry has a string `value` property.
	 */
	private static function _is_loop_sub_type_value_label_item( $entry ): bool {
		if ( ! is_array( $entry ) && ! is_object( $entry ) ) {
			return false;
		}

		$item = (array) $entry;

		return isset( $item['value'] ) && is_string( $item['value'] );
	}

	/**
	 * Filters and normalizes loop subTypes value-label entries.
	 *
	 * @since ??
	 *
	 * @param array $entries Raw entries.
	 *
	 * @return array|null Normalized entries, empty array when input is empty, or null when none are valid.
	 */
	private static function _filter_valid_loop_sub_type_entries( array $entries ): ?array {
		$valid_entries = array_values(
			array_filter(
				$entries,
				[ self::class, '_is_loop_sub_type_value_label_item' ]
			)
		);

		if ( empty( $valid_entries ) ) {
			return null;
		}

		return array_map(
			static function ( $entry ) {
				$item = (array) $entry;

				return [
					'value' => $item['value'],
					'label' => isset( $item['label'] ) && is_string( $item['label'] ) ? $item['label'] : $item['value'],
				];
			},
			$valid_entries
		);
	}

	/**
	 * Resolve order-by context from loop module attrs.
	 *
	 * @since ??
	 *
	 * @param array $attrs Loop module attrs.
	 *
	 * @return array{query_type: string, post_type: string, taxonomy: string}
	 */
	private static function _resolve_order_by_context_from_loop_attrs( array $attrs ): array {
		$default_context = [
			'query_type' => 'post_types',
			'post_type'  => 'post',
			'taxonomy'   => '',
		];

		$loop         = $attrs['module']['advanced']['loop'] ?? [];
		$loop_enabled = sanitize_key( $loop['desktop']['value']['enable'] ?? '' );

		if ( 'on' !== $loop_enabled ) {
			return $default_context;
		}

		$loop_data = self::get_query_args_from_attrs( $attrs );
		$loop_id   = sanitize_key( $loop['desktop']['value']['loopId'] ?? '' );

		if ( '' !== $loop_id ) {
			self::_register_loop_order_by_context_from_loop_data( $loop_id, $loop_data );
		}

		$registered_context = '' !== $loop_id ? self::get_registered_loop_order_by_context( $loop_id ) : [];

		if ( ! empty( $registered_context ) ) {
			return array_merge( $default_context, $registered_context );
		}

		return $default_context;
	}

	/**
	 * Extract the first post type slug from parsed loop data.
	 *
	 * @since ??
	 *
	 * @param array $loop_data Parsed loop data.
	 *
	 * @return string Post type slug or empty string.
	 */
	private static function _extract_first_post_type_slug_from_loop_data( array $loop_data ): string {
		$query_type = sanitize_key( $loop_data['query_type'] ?? '' );
		$post_type  = $loop_data['post_type'] ?? null;

		if ( 'post_types' === $query_type ) {
			if ( is_array( $post_type ) && ! empty( $post_type ) ) {
				return sanitize_key( (string) $post_type[0] );
			}

			if ( is_string( $post_type ) && '' !== $post_type ) {
				return sanitize_key( $post_type );
			}
		}

		if ( 'current_page' === $query_type ) {
			if ( is_array( $post_type ) && ! empty( $post_type ) ) {
				return sanitize_key( (string) $post_type[0] );
			}

			return 'post';
		}

		return '';
	}

	/**
	 * Checks whether an array uses numeric keys (array-like serialization shape).
	 *
	 * @since ??
	 *
	 * @param array $value Array value.
	 *
	 * @return bool True when all keys are numeric.
	 */
	private static function _is_numeric_keyed_array_like( array $value ): bool {
		if ( empty( $value ) ) {
			return false;
		}

		foreach ( array_keys( $value ) as $key ) {
			if ( is_int( $key ) ) {
				continue;
			}

			if ( is_string( $key ) && ctype_digit( $key ) ) {
				continue;
			}

			return false;
		}

		return true;
	}

	/**
	 * Checks whether an array is a zero-indexed sequential list.
	 *
	 * @since ??
	 *
	 * @param array $value Array value.
	 *
	 * @return bool True when keys are 0..n-1.
	 */
	private static function _is_sequential_array( array $value ): bool {
		if ( empty( $value ) ) {
			return true;
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Normalizes runtime loop `subTypes` values into value-label item arrays.
	 *
	 * Mirrors Visual Builder `normalizeLoopSubTypes` read-path rules for VB/FE parity.
	 *
	 * @since ??
	 *
	 * @param mixed $raw_value Raw `subTypes` attribute value.
	 *
	 * @return array|null Normalized items, empty array for intentional empty selection, or null to use caller defaults.
	 */
	private static function _normalize_loop_sub_types_items( $raw_value ): ?array {
		if ( null === $raw_value ) {
			return null;
		}

		if ( is_array( $raw_value ) ) {
			if ( empty( $raw_value ) ) {
				return [];
			}

			if ( self::_is_numeric_keyed_array_like( $raw_value ) && ! self::_is_sequential_array( $raw_value ) ) {
				return self::_filter_valid_loop_sub_type_entries( array_values( $raw_value ) );
			}

			return self::_filter_valid_loop_sub_type_entries( $raw_value );
		}

		if ( is_string( $raw_value ) ) {
			$trimmed = trim( $raw_value );

			if ( '' === $trimmed ) {
				return null;
			}

			$tags = array_values(
				array_filter(
					array_map( 'trim', explode( ',', $trimmed ) ),
					static function ( $tag ) {
						return '' !== $tag;
					}
				)
			);

			if ( empty( $tags ) ) {
				return null;
			}

			return array_map(
				static function ( $tag ) {
					return [
						'value' => $tag,
						'label' => $tag,
					];
				},
				$tags
			);
		}

		if ( is_object( $raw_value ) ) {
			$record = (array) $raw_value;

			if ( ! self::_is_numeric_keyed_array_like( $record ) ) {
				return null;
			}

			return self::_filter_valid_loop_sub_type_entries( array_values( $record ) );
		}

		return null;
	}

	/**
	 * Extract and sanitize sub-type values from loop configuration.
	 *
	 * @param array $loop The loop configuration array.
	 * @return string Comma-separated string of sanitized values, or empty string if none found.
	 */
	private static function _extract_sub_type_values( array $loop ): array {
		$sub_types        = $loop['desktop']['value']['subTypes'] ?? null;
		$normalized_items = self::_normalize_loop_sub_types_items( $sub_types );

		if ( null === $normalized_items || empty( $normalized_items ) ) {
			return [];
		}

		$values = array_filter(
			array_map(
				static function ( $item ) {
					if ( ! isset( $item['value'] ) ) {
						return null;
					}
					// For numeric values (like menu IDs), use absint to ensure positive integers.
					// sanitize_key would strip leading numbers, which breaks numeric IDs.
					$value = $item['value'];
					if ( is_numeric( $value ) ) {
						return (string) absint( $value );
					}
					return sanitize_key( $value );
				},
				$normalized_items
			)
		);

		return $values;
	}

	/**
	 * Default order by value for a loop query type when attrs omit orderBy.
	 *
	 * Mirrors Visual Builder `defaultOrderByType` in loop group constants.
	 *
	 * @since ??
	 *
	 * @param string $query_type Loop query type from saved attrs.
	 *
	 * @return string Default order by key.
	 */
	private static function _get_default_order_by_for_query_type( string $query_type ): string {
		switch ( $query_type ) {
			case 'current_page':
				return 'relevance';
			case 'post_taxonomies':
				return 'name';
			case 'user_roles':
				return 'display_name';
			case 'menus':
				return 'menu_order';
			case 'post_types':
			default:
				return 'date';
		}
	}

	/**
	 * Extract and sanitize order by value from loop configuration.
	 *
	 * @param string $order The order value from loop configuration.
	 * @return string The sanitized order value.
	 */
	private static function _extract_order_by_value( string $order ): string {
		if ( empty( $order ) || 'descending' === $order ) {
			return 'DESC';
		}

		return 'ASC';
	}

	/**
	 * Get excluded taxonomies.
	 *
	 * @since ??
	 *
	 * @return array The excluded taxonomies.
	 */
	public static function get_excluded_taxonomies(): array {
		$excluded_taxonomies = [
			'nav_menu',
			'link_category',
			'post_format',
			'layout_category',
			'layout_pack',
			'layout_type',
			'scope',
			'module_width',
			'wp_theme',
		];

		return apply_filters( 'et_builder_loop_terms_excluded_taxonomies', $excluded_taxonomies );
	}

	/**
	 * Build WP_Term_Query arguments for terms queries.
	 *
	 * @since ??
	 *
	 * @param array $params Extracted parameters from loop settings.
	 *
	 * @return array The query result array.
	 */
	private static function _build_terms_query_args( $params ) {
		// NOTE: All user input parameters ($params) are sanitized in get_query_args_from_attrs()
		// before reaching this function. No additional sanitization is needed for basic parameters.

		$taxonomy = $params['post_type']; // For terms queries, taxonomy is stored in post_type.

		// If no taxonomy is selected, use all taxonomies.
		if ( empty( $taxonomy ) ) {
			$excluded_taxonomies = self::get_excluded_taxonomies();

			$taxonomy = array_values( array_diff( get_taxonomies(), $excluded_taxonomies ) );
		}

		// Handle multiple taxonomies.
		if ( ! empty( $taxonomy ) ) {
			$taxonomy = array_map( 'sanitize_key', $taxonomy );
		}

		// Build WP_Term_Query arguments.
		$query_args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'orderby'    => $params['order_by_raw'],
			'order'      => $params['order_raw'],
		];

		// Handle pagination.
		if ( $params['post_per_page'] > 0 ) {
			$query_args['number'] = $params['post_per_page'];
		}

		if ( $params['post_offset'] > 0 ) {
			$query_args['offset'] = $params['post_offset'];
		}

		// Handle search.
		if ( ! empty( $params['search'] ) ) {
			$query_args['search'] = $params['search'];
		}

		// Handle meta query parameters.
		// Note: $params['meta_query_attrs'] is sanitized within the build_meta_query method.
		$meta_query = self::build_meta_query( $params['meta_query_attrs'] );
		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		$result = [
			'loop_enabled' => $params['loop_enabled'],
			'query_args'   => $query_args,
			'query_type'   => $params['query_type'],
			'post_type'    => $taxonomy, // For terms queries, this represents the taxonomy.
		];

		return $result;
	}



	/**
	 * Gets the actual content for a loop name.
	 *
	 * @since ??
	 *
	 * @param string $name       The loop name (e.g., "loop_post_title").
	 * @param string $query_type The type of query.
	 * @param mixed  $post       The loop object (WP_Post, WP_User, WP_Term, ACF Repeater, etc.).
	 * @param array  $settings   Optional. Field settings for customization. Default [].
	 *
	 * @return string The actual content.
	 */
	public static function get_loop_content_by_variable_name( string $name, string $query_type, $post = null, array $settings = [] ): string {
		// Ensure we have a valid post object.
		if ( null === $post ) {
			return '';
		}

		if ( DynamicContentACFUtils::is_repeater_query( $query_type ) ) {
			return DynamicContentACFUtils::get_repeater_field_content( $name, $post, $settings );
		}

		// Delegate to specific handler methods based on query type.
		switch ( $query_type ) {
			case 'post_types':
				return self::_get_post_loop_content( $name, $post, $settings );

			case 'current_page':
				return self::_get_post_loop_content( $name, $post, $settings );

			case 'terms':
				return self::_get_term_loop_content( $name, $post );

			case 'user_roles':
				return self::_get_user_loop_content( $name, $post );

			case 'menus':
				return self::_get_menu_loop_content( $name, $post );

			default:
				return ''; // Return empty string for unknown query types.
		}
	}

	/**
	 * Get loop content for post type queries.
	 *
	 * @since ??
	 *
	 * @param string $name     The loop variable name.
	 * @param mixed  $post     The WP_Post object.
	 * @param array  $settings Optional. Field settings for customization. Default [].
	 *
	 * @return string The loop content.
	 */
	private static function _get_post_loop_content( string $name, $post, array $settings = [] ): string {
		// Validate that we have a proper WP_Post object.
		if ( ! is_object( $post ) || ! isset( $post->ID ) || ! is_a( $post, 'WP_Post' ) ) {
			return '';
		}

		switch ( $name ) {
			case 'loop_post_title':
				return isset( $post->post_title ) ? get_the_title( $post ) : '';

			case 'loop_post_excerpt':
				$value = '';

				// Get word limit setting once for consistent use throughout.
				$words              = isset( $settings['words'] ) ? absint( $settings['words'] ) : 0;
				$excerpt_length     = $words;
				$has_manual_excerpt = isset( $post->post_excerpt ) && ! empty( $post->post_excerpt );
				$is_divi_post       = et_pb_is_pagebuilder_used( $post->ID );

				if ( $has_manual_excerpt ) {
					// Apply the_excerpt filter to manual excerpts to ensure proper formatting.
					$value = apply_filters( 'the_excerpt', $post->post_excerpt );
				} elseif ( $is_divi_post ) {
					// Fall back to auto-generated excerpt if no manual excerpt exists.
					// For Divi Builder posts, get the rendered content and create excerpt from it.
					// Set global flag to prevent loop processing during excerpt generation.
					$GLOBALS['divi_generating_excerpt'] = true;

					$previous_excerpt_render_post_id             = $GLOBALS['divi_loop_excerpt_render_post_id'] ?? null;
					$GLOBALS['divi_loop_excerpt_render_post_id'] = (int) $post->ID;

					try {
						$rendered_content = apply_filters( 'the_content', $post->post_content );
						$value            = wp_strip_all_tags( $rendered_content );
					} finally {
						// Always reset flag, even if an exception occurs.
						$GLOBALS['divi_generating_excerpt'] = false;

						if ( null === $previous_excerpt_render_post_id ) {
							unset( $GLOBALS['divi_loop_excerpt_render_post_id'] );
						} else {
							$GLOBALS['divi_loop_excerpt_render_post_id'] = $previous_excerpt_render_post_id;
						}
					}
				} elseif ( $words > 0 ) {
					// Fall back to auto-generated excerpt if no manual excerpt exists.
					// For non-Divi posts, use wp_trim_excerpt() only if no custom word limit is set.
					// If custom limit is set, get full content and apply limit ourselves.
					// Custom word limit set: get full content and apply limit.
					$value = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
				} else {
					// Fall back to auto-generated excerpt if no manual excerpt exists.
					// No custom limit: use WordPress default excerpt (55 words).
					$value = wp_trim_excerpt( '', $post );
				}

				// Apply word limits: manual excerpts honor explicit words setting; Divi Builder posts get default limits; others when explicitly set.
				if ( $has_manual_excerpt && 0 < $words ) {
					$excerpt_length = $words;
				} elseif ( ! $has_manual_excerpt && $is_divi_post ) {
					$excerpt_length = 0 < $words ? $words : apply_filters( 'excerpt_length', 55 );
				} elseif ( ! $has_manual_excerpt && ! $is_divi_post && 0 < $words ) {
					// For non-Divi posts with custom word limit, use the custom limit.
					$excerpt_length = $words;
				}

				if ( 0 < $excerpt_length ) {
					// Explicitly use WordPress excerpt_more filter to ensure consistent ellipsis format.
					$excerpt_more = apply_filters( 'excerpt_more', ' [&hellip;]' );
					$value        = wp_trim_words( wp_strip_all_tags( $value ), $excerpt_length, $excerpt_more );
				}

				// Handle read more text if specified in settings.
				$read_more_label = $settings['read_more_label'] ?? '';
				if ( ! empty( $read_more_label ) ) {
					$permalink = get_permalink( $post->ID );
					if ( $permalink ) {
						$value .= sprintf(
							' <a href="%1$s">%2$s</a>',
							esc_url( $permalink ),
							esc_html( $read_more_label )
						);
					}
				}

				return wp_kses_post( $value );

			case 'loop_post_date':
				$timestamp = get_post_timestamp( $post->ID );
				return esc_html( ModuleUtils::format_date( $timestamp, $settings ) );

			case 'loop_post_modified_date':
				$timestamp = get_post_timestamp( $post->ID, 'modified' );
				return esc_html( ModuleUtils::format_date( $timestamp, $settings ) );

			case 'loop_post_author':
				if ( ! isset( $post->post_author ) ) {
					return '';
				}

				$author = get_userdata( $post->post_author );
				if ( ! $author ) {
					return '';
				}

				// Get settings.
				$name_format      = $settings['name_format'] ?? 'display_name';
				$link             = $settings['link'] ?? 'off';
				$link_destination = $settings['link_destination'] ?? 'author_archive';
				$is_link          = 'on' === $link;
				$link_target      = 'author_archive' === $link_destination ? '_self' : '_blank';
				$label            = '';
				$url              = '';

				// Handle name format.
				switch ( $name_format ) {
					case 'display_name':
						$label = $author->display_name;
						break;
					case 'first_last_name':
						$label = $author->first_name . ' ' . $author->last_name;
						break;
					case 'last_first_name':
						$filtered_names = array_filter( [ $author->last_name, $author->first_name ], 'strlen' );
						$label          = ! empty( $filtered_names ) ? implode( ', ', $filtered_names ) : '';
						break;
					case 'first_name':
						$label = $author->first_name;
						break;
					case 'last_name':
						$label = $author->last_name;
						break;
					case 'nickname':
						$label = $author->nickname;
						break;
					case 'username':
						$label = $author->user_login;
						break;
					default:
						$label = $author->display_name;
						break;
				}

				// Handle link destination.
				if ( $is_link ) {
					switch ( $link_destination ) {
						case 'author_archive':
							$url = get_author_posts_url( $author->ID );
							break;
						case 'author_website':
							$url = $author->user_url;
							break;
					}
				}

				// Return plain text if link is disabled or URL is empty.
				if ( ! $is_link || empty( $url ) ) {
					return $label ? esc_html( $label ) : '';
				}

				// Return HTML link when link is enabled.
				$value = sprintf(
					'<a href="%1$s" target="%2$s">%3$s</a>',
					esc_url( $url ),
					et_core_intentionally_unescaped( $link_target, 'fixed_string' ),
					esc_html( $label )
				);

				return wp_kses_post( $value );

			case 'loop_post_author_bio':
				if ( ! isset( $post->post_author ) ) {
					return '';
				}
				$author_bio = get_the_author_meta( 'description', $post->post_author );
				return $author_bio ? wp_kses_post( $author_bio ) : '';

			case 'loop_post_link':
				$permalink = get_the_permalink( $post->ID );
				return $permalink ? esc_url( $permalink ) : '';

			case 'loop_post_comment_count':
				$count   = (string) get_comments_number( $post->ID );
				$link    = $settings['link_to_comments_page'] ?? 'on';
				$is_link = 'on' === $link;

				if ( $is_link ) {
					$value = sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( get_comments_link( $post->ID ) ),
						esc_html( $count )
					);

					return wp_kses_post( $value );
				}

				return $count;

			case 'loop_post_id':
				return (string) absint( $post->ID );

			case 'loop_post_thumbnail':
				$thumbnail = get_the_post_thumbnail( $post->ID, 'full' );
				return $thumbnail ? $thumbnail : '';

			case 'loop_post_featured_image':
				$thumbnail_size = $settings['thumbnail_size'] ?? 'large';
				// Handle attachment post types specially - they ARE the images themselves.
				if ( 'attachment' === $post->post_type ) {
					$featured_image_url = wp_get_attachment_image_url( $post->ID, $thumbnail_size );
					if ( $featured_image_url ) {
						// Store attachment ID for later processing.
						global $divi_loop_image_ids;
						if ( ! isset( $divi_loop_image_ids ) ) {
							$divi_loop_image_ids = [];
						}
						$divi_loop_image_ids[ esc_url( $featured_image_url ) ] = $post->ID;
					}
				} else {
					$featured_image_url = get_the_post_thumbnail_url( $post->ID, $thumbnail_size );
					if ( $featured_image_url ) {
						// Store attachment ID for later processing.
						$attachment_id = get_post_thumbnail_id( $post->ID );
						if ( $attachment_id ) {
							// Store this in a global variable for the filter to use.
							global $divi_loop_image_ids;
							if ( ! isset( $divi_loop_image_ids ) ) {
								$divi_loop_image_ids = [];
							}
							$divi_loop_image_ids[ esc_url( $featured_image_url ) ] = $attachment_id;
						}
					}
				}
				return $featured_image_url ? esc_url( $featured_image_url ) : '';

			case 'loop_post_featured_image_alt_text':
				// Handle attachment post types specially - they ARE the images themselves.
				if ( 'attachment' === $post->post_type ) {
					$attachment_id = $post->ID;
				} else {
					$attachment_id = get_post_thumbnail_id( $post->ID );
				}
				if ( ! $attachment_id ) {
					return '';
				}
				$alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				return $alt_text ? $alt_text : '';

			case 'loop_post_featured_image_title_text':
				// Handle attachment post types specially - they ARE the images themselves.
				if ( 'attachment' === $post->post_type ) {
					$attachment_id = $post->ID;
				} else {
					$attachment_id = get_post_thumbnail_id( $post->ID );
				}
				if ( ! $attachment_id ) {
					return '';
				}
				$attachment = get_post( $attachment_id );
				if ( ! $attachment ) {
					return '';
				}
				return $attachment->post_title ? $attachment->post_title : '';

			case 'loop_post_author_profile_picture':
				if ( ! isset( $post->post_author ) ) {
					return '';
				}
				$author_avatar_url = get_avatar_url( $post->post_author );
				return $author_avatar_url ? esc_url( $author_avatar_url ) : '';

			case 'loop_post_terms':
				// Get settings.
				$taxonomy_type = $settings['taxonomy_type'] ?? 'category';
				$separator     = $settings['separator'] ?? ', ';
				$links_enabled = ( $settings['links'] ?? 'off' ) === 'on';

				// Handle any taxonomy type.
				if ( 'category' === $taxonomy_type ) {
					// Use WordPress native category function.
					if ( $links_enabled ) {
						// get_the_category_list() automatically handles links and separators.
						$content = get_the_category_list( $separator, '', $post->ID );
					} else {
						// Get categories without links.
						$categories = get_the_category( $post->ID );
						if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
							$terms_list = [];
							foreach ( $categories as $category ) {
								$terms_list[] = esc_html( $category->name );
							}
							$content = implode( $separator, $terms_list );
						} else {
							$content = '';
						}
					}
				} elseif ( 'post_tag' === $taxonomy_type ) {
					// Use WordPress native tags function.
					if ( $links_enabled ) {
						// get_the_tag_list() automatically handles links and separators.
						$content = get_the_tag_list( '', $separator, '', $post->ID );
					} else {
						// Get tags without links.
						$tags = get_the_tags( $post->ID );
						if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
							$terms_list = [];
							foreach ( $tags as $tag ) {
								$terms_list[] = esc_html( $tag->name );
							}
							$content = implode( $separator, $terms_list );
						} else {
							$content = '';
						}
					}
				} else {
					// Handle any custom taxonomy.
					$taxonomy_object = get_taxonomy( $taxonomy_type );
					$terms           = ( $taxonomy_object && $taxonomy_object->public ) ? get_the_terms( $post->ID, $taxonomy_type ) : [];
					if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
						$terms_list = [];
						foreach ( $terms as $term ) {
							if ( $links_enabled ) {
								$term_link    = get_term_link( $term, $taxonomy_type );
								$terms_list[] = ! is_wp_error( $term_link )
									? '<a href="' . esc_url( $term_link ) . '">' . esc_html( $term->name ) . '</a>'
									: esc_html( $term->name );
							} else {
								$terms_list[] = esc_html( $term->name );
							}
						}
						$content = implode( $separator, $terms_list );
					} else {
						$content = '';
					}
				}

				return $content;

			default:
				if ( StringUtility::starts_with( $name, 'loop_product_' ) ) {
					return WooCommerceLoopHandler::get_loop_content( $name, $post, $settings );
				}

				return '';
		}
	}

	/**
	 * Get loop content for term queries.
	 *
	 * @since ??
	 *
	 * @param string $name The loop variable name.
	 * @param mixed  $term The WP_Term object.
	 *
	 * @return string The loop content.
	 */
	private static function _get_term_loop_content( string $name, $term ): string {
		// Validate that we have a proper WP_Term object.
		if ( ! is_object( $term ) || ! isset( $term->term_id ) || ! is_a( $term, 'WP_Term' ) ) {
			return '';
		}

		switch ( $name ) {
			case 'loop_term_name':
				return isset( $term->name ) ? esc_html( $term->name ) : '';

			case 'loop_term_description':
				return isset( $term->description ) ? wp_kses_post( $term->description ) : '';

			case 'loop_term_permalink':
				$term_link = get_term_link( $term->term_id );
				return ! is_wp_error( $term_link ) ? esc_url( $term_link ) : '';

			case 'loop_term_count':
				return isset( $term->count ) ? (string) $term->count : '0';

			case 'loop_term_taxonomy':
				return isset( $term->taxonomy ) ? esc_html( $term->taxonomy ) : '';

			case 'loop_term_featured_image':
				$attachment_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
				if ( $attachment_id > 0 ) {
					$img_url = wp_get_attachment_url( $attachment_id );
					return $img_url ? esc_url( $img_url ) : '';
				}
				return '';

			default:
				return ''; // Return empty string for unknown fields.
		}
	}

	/**
	 * Get loop content for user queries.
	 *
	 * @since ??
	 *
	 * @param string $name The loop variable name.
	 * @param mixed  $user The WP_User object.
	 *
	 * @return string The loop content.
	 */
	private static function _get_user_loop_content( string $name, $user ): string {
		// Validate that we have a proper WP_User object.
		if ( ! is_object( $user ) || ! isset( $user->ID ) || ! is_a( $user, 'WP_User' ) ) {
			return '';
		}

		switch ( $name ) {
			case 'loop_user_name':
				return isset( $user->display_name ) ? esc_html( $user->display_name ) : '';

			case 'loop_user_username':
				return isset( $user->user_login ) ? esc_html( $user->user_login ) : '';

			case 'loop_user_email':
				return isset( $user->user_email ) ? esc_html( $user->user_email ) : '';

			case 'loop_user_avatar':
				$avatar_url = get_avatar_url( $user->ID );
				return $avatar_url ? esc_url( $avatar_url ) : '';

			case 'loop_user_description':
				return isset( $user->description ) ? wp_kses_post( $user->description ) : '';

			case 'loop_user_url':
				$author_url = get_author_posts_url( $user->ID );
				return $author_url ? esc_url( $author_url ) : '';

			default:
				return ''; // Return empty string for unknown fields.
		}
	}

	/**
	 * Get loop content for menu queries.
	 *
	 * @since ??
	 *
	 * @param string $name The loop variable name.
	 * @param mixed  $menu_item The menu item object or array.
	 *
	 * @return string The loop content.
	 */
	private static function _get_menu_loop_content( string $name, $menu_item ): string {
		// Handle both object and array formats.
		$is_object = is_object( $menu_item );
		$is_array  = is_array( $menu_item );

		if ( ! $is_object && ! $is_array ) {
			return '';
		}

		switch ( $name ) {
			case 'loop_menu_text':
				if ( $is_object && isset( $menu_item->text ) ) {
					return esc_html( $menu_item->text );
				} elseif ( $is_array && isset( $menu_item['text'] ) ) {
					return esc_html( $menu_item['text'] );
				}
				return '';

			case 'loop_menu_link':
				if ( $is_object && isset( $menu_item->link ) ) {
					return esc_url( $menu_item->link );
				} elseif ( $is_array && isset( $menu_item['link'] ) ) {
					return esc_url( $menu_item['link'] );
				}
				return '';

			case 'loop_menu_menu_order':
				if ( $is_object && isset( $menu_item->menu_order ) ) {
					return esc_html( (string) $menu_item->menu_order );
				} elseif ( $is_array && isset( $menu_item['menu_order'] ) ) {
					return esc_html( (string) $menu_item['menu_order'] );
				}
				return '';

			case 'loop_menu_attr_title':
				if ( $is_object && isset( $menu_item->attr_title ) ) {
					return esc_attr( $menu_item->attr_title );
				} elseif ( $is_array && isset( $menu_item['attr_title'] ) ) {
					return esc_attr( $menu_item['attr_title'] );
				}
				return '';

			case 'loop_menu_classes':
				$classes = [];
				if ( $is_object && isset( $menu_item->classes ) ) {
					$classes = is_array( $menu_item->classes ) ? $menu_item->classes : [];
				} elseif ( $is_array && isset( $menu_item['classes'] ) ) {
					$classes = is_array( $menu_item['classes'] ) ? $menu_item['classes'] : [];
				}
				return ! empty( $classes ) ? esc_attr( implode( ' ', $classes ) ) : '';

			case 'loop_menu_xfn':
				if ( $is_object && isset( $menu_item->xfn ) ) {
					return esc_attr( $menu_item->xfn );
				} elseif ( $is_array && isset( $menu_item['xfn'] ) ) {
					return esc_attr( $menu_item['xfn'] );
				}
				return '';

			case 'loop_menu_description':
				if ( $is_object && isset( $menu_item->description ) ) {
					return wp_kses_post( $menu_item->description );
				} elseif ( $is_array && isset( $menu_item['description'] ) ) {
					return wp_kses_post( $menu_item['description'] );
				}
				return '';

			default:
				return ''; // Return empty string for unknown fields.
		}
	}

	/**
	 * Recursively search for specific key values in array data.
	 *
	 * @since ??
	 *
	 * @param mixed  $data   The data to search through.
	 * @param string $target The key to search for.
	 *
	 * @return array Array of found values.
	 */
	private static function _find_key_values( $data, string $target ): array {
		$results = [];

		if ( ! is_array( $data ) ) {
			return $results;
		}

		array_walk_recursive(
			$data,
			function ( $value, $key ) use ( $target, &$results ) {
				if ( $key === $target ) {
					$results[] = $value;
				}
			}
		);

		return $results;
	}

	/**
	 * Extract loop position from module attributes.
	 *
	 * @since ??
	 *
	 * @param array  $attrs     Module attributes.
	 * @param string $view_mode View mode (desktop, tablet, phone).
	 *
	 * @return int|null Loop position (0-based) or null if not found.
	 */
	public static function extract_loop_position_from_attrs( array $attrs, string $view_mode = 'desktop' ): ?int {
		if ( empty( $attrs ) || ! is_array( $attrs ) ) {
			return null;
		}

		foreach ( $attrs as $attr_key => $attr_value ) {
			if ( ! is_array( $attr_value ) ) {
				continue;
			}

			$inner_content_value = $attr_value['innerContent'][ $view_mode ]['value'] ?? null;

			if ( $inner_content_value && is_string( $inner_content_value ) ) {
				$json_data = DynamicData::get_data_value( $inner_content_value );

				if ( $json_data && isset( $json_data['settings']['loop_position'] ) ) {
					$position_1_based = intval( $json_data['settings']['loop_position'] );
					$position_0_based = max( 0, $position_1_based - 1 );

					return $position_0_based;
				}
			}
		}

		$loop_position_results = self::_find_key_values( $attrs, 'loop_position' );

		foreach ( $loop_position_results as $position_value ) {
			if ( is_numeric( $position_value ) ) {
				$position_1_based = intval( $position_value );
				$position_0_based = max( 0, $position_1_based - 1 );

				return $position_0_based;
			}
		}

		return null;
	}

	/**
	 * Detect the number of columns per row from block attributes.
	 *
	 * @since ??
	 *
	 * @param array $block_attrs Block attributes.
	 *
	 * @return int Number of columns (minimum 1).
	 */
	public static function detect_columns_per_row( array $block_attrs ): int {
		if ( empty( $block_attrs ) ) {
			return 1;
		}

		$structure_paths = [
			'module.advanced.flexColumnStructure.desktop.value',
			'module.advanced.columnStructure.desktop.value',
		];

		foreach ( $structure_paths as $path ) {
			$column_structure = self::_get_nested_value( $block_attrs, $path );

			if ( $column_structure && is_string( $column_structure ) ) {
				if ( str_starts_with( $column_structure, 'equal-columns_' ) ) {
					$parts = explode( '_', $column_structure );
					if ( isset( $parts[1] ) && is_numeric( $parts[1] ) ) {
						$columns = max( 1, intval( $parts[1] ) );
						return $columns;
					}
				} elseif ( str_contains( $column_structure, ',' ) ) {
					$columns = max( 1, count( explode( ',', $column_structure ) ) );
					return $columns;
				}
			}
		}

		return 1;
	}

	/**
	 * Get nested value from array using dot notation path.
	 *
	 * @since ??
	 *
	 * @param array  $array The array to search.
	 * @param string $path  Dot notation path (e.g., 'module.advanced.loop.enable').
	 *
	 * @return mixed The found value or null.
	 */
	private static function _get_nested_value( array $array, string $path ) {
		$keys  = explode( '.', $path );
		$value = $array;

		foreach ( $keys as $key ) {
			if ( ! is_array( $value ) || ! isset( $value[ $key ] ) ) {
				return null;
			}
			$value = $value[ $key ];
		}

		return $value;
	}

	/**
	 * Calculate the loop post index using the position formula.
	 *
	 * @since ??
	 *
	 * @param int $loop_position         Loop position (0-based).
	 * @param int $parent_loop_iteration Current parent loop iteration.
	 * @param int $columns_per_row       Number of columns per row.
	 *
	 * @return int Calculated post index.
	 */
	public static function calculate_loop_post_index( int $loop_position, int $parent_loop_iteration, int $columns_per_row = 1 ): int {
		$calculated_index = ( $parent_loop_iteration * $columns_per_row ) + $loop_position;

		return $calculated_index;
	}

	/**
	 * Checks if the content includes any loop-enabled blocks.
	 *
	 * Performs a fast string search to detect loop patterns before expensive parsing.
	 * This optimization prevents unnecessary processing when no loops are present.
	 *
	 * Uses a limited search window approach to handle nested JSON structures while
	 * preventing memory exhaustion on large content. The method searches for all "loop:"
	 * occurrences, then extracts a bounded substring (50KB max) around each occurrence
	 * and applies a regex pattern with limited quantifier to detect "enable":"on" within
	 * that window. This ensures loop-enabled blocks are detected even when they appear
	 * later in the document after disabled loops or other content.
	 *
	 * This approach follows the pattern used in DetectFeature::has_interactions_enabled()
	 * and prevents the memory issues that occurred in the previous fix attempt (PR #7608).
	 *
	 * @param string $content The block serialized content to be checked.
	 *
	 * @return bool True if any loop-enabled blocks are found, false otherwise.
	 */
	public static function has_any_loop_enabled_blocks( $content ) {
		// Bail early if content is empty.
		if ( empty( $content ) ) {
			return false;
		}

		// Quick check before expensive regex - bail early if loop object token doesn't exist.
		$loop_pos = strpos( $content, '"loop":' );
		if ( false === $loop_pos ) {
			return false;
		}

		// Scan all loop occurrences to find any enabled loop, not just the first one.
		// This prevents false negatives when the first loop is disabled or when enabled
		// loops appear later in large documents.
		$content_length = strlen( $content );
		$window_size    = 51200; // 50KB limit per window.
		$search_offset  = 0;

		// Iterate through all "loop:" occurrences in the content.
		while ( false !== $loop_pos ) {
			// Extract limited substring (50KB max) starting from the current loop token position.
			// This prevents memory exhaustion on large pages while still handling nested JSON structures.
			// The 50KB limit is sufficient for loop configuration objects (even with nested structures).
			$substring = substr( $content, $loop_pos, min( $window_size, $content_length - $loop_pos ) );

			// Check if this loop block is enabled using regex pattern with limited quantifier.
			// The pattern matches "loop" followed by nested structures (up to 50KB) containing "enable":"on".
			// The {0,51200}? limit prevents exponential backtracking while still allowing
			// matching across nested JSON structures. The 's' flag allows . to match newlines.
			// Regex101 link: https://regex101.com/r/U4b01B/1 (original pattern reference).
			$has_loop_enabled = preg_match(
				'/"loop"\s*:\s*\{.{0,51200}?"enable"\s*:\s*"on"/s',
				$substring
			) === 1;

			// Return true as soon as we find one enabled loop.
			if ( $has_loop_enabled ) {
				return true;
			}

			// Continue searching from the next position.
			$search_offset = $loop_pos + 1;
			$loop_pos      = strpos( $content, '"loop":', $search_offset );
		}

		// No enabled loops found after scanning all occurrences.
		return false;
	}

	/**
	 * Check if the current page has paginated loops.
	 *
	 * Detects pagination by checking:
	 * 1. If page content has loop-enabled blocks
	 * 2. If loop pagination parameters contain numeric values > 1.
	 *
	 * @since ??
	 *
	 * @return bool True if paginated loops detected, false otherwise.
	 */
	public static function has_paginated_loops(): bool {
		// Get current post content to check for loops.
		// Use ET_Post_Stack::get() for Theme Builder compatibility to get layout post from stack.
		$post    = ET_Post_Stack::get();
		$post_id = $post ? $post->ID : get_the_ID();
		if ( ! $post_id ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}

		// Check if page has any loop-enabled blocks.
		$has_loops = self::has_any_loop_enabled_blocks( $post->post_content );
		if ( ! $has_loops ) {
			return false;
		}

		// Check URL parameters for pagination indicators.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter check, no security risk.
		if ( ! isset( $_GET ) || ! is_array( $_GET ) ) {
			return false;
		}

		// Loop pagination uses module IDs as parameter names with page numbers as values.
		// Check for loop-specific parameters only to avoid false positives from unrelated query args.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter check, no security risk.
		foreach ( $_GET as $param => $value ) {
			if ( is_string( $param ) && str_starts_with( $param, 'loop-' ) && is_numeric( $value ) && (int) $value > 1 ) {
				// Found pagination parameter - page has paginated loops.
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether content contains random-order loop configuration.
	 *
	 * @since ??
	 *
	 * @param string $content Post or layout content.
	 *
	 * @return bool True if random order loops detected in content, false otherwise.
	 */
	private static function _content_has_random_order_loops( string $content ): bool {
		if ( empty( $content ) ) {
			return false;
		}

		if ( ! self::has_any_loop_enabled_blocks( $content ) ) {
			return false;
		}

		// Look for "orderBy":"rand" in the loop configuration.
		// Regex101: link https://regex101.com/r/dK9Gdo/1.
		return preg_match(
			'/"orderBy"\s*:\s*"rand"/',
			$content
		) === 1;
	}

	/**
	 * Collect post content strings to inspect for random-order loop detection.
	 *
	 * Includes the ET_Post_Stack post, the current post, and active Theme Builder
	 * layout posts so detection works at the wp hook before TB layout render.
	 *
	 * @since ??
	 *
	 * @return string[] Non-empty post content strings.
	 */
	private static function _get_loop_detection_content_sources(): array {
		$contents = [];
		$seen_ids = [];

		$collect_post_content = static function ( int $post_id ) use ( &$contents, &$seen_ids ) {
			if ( $post_id <= 0 || isset( $seen_ids[ $post_id ] ) ) {
				return;
			}

			$seen_ids[ $post_id ] = true;
			$post                 = get_post( $post_id );

			if ( $post && ! empty( $post->post_content ) ) {
				$contents[] = $post->post_content;
			}
		};

		// Use ET_Post_Stack::get() for Theme Builder compatibility to get layout post from stack.
		$stack_post = ET_Post_Stack::get();
		if ( $stack_post ) {
			$collect_post_content( absint( $stack_post->ID ) );
		}

		$current_post_id = get_the_ID();
		if ( $current_post_id ) {
			$collect_post_content( absint( $current_post_id ) );
		}

		// Active Theme Builder layouts.
		if ( function_exists( 'et_theme_builder_get_template_layouts' ) ) {
			foreach ( DynamicAssetsUtils::get_theme_builder_template_ids() as $template_id ) {
				$collect_post_content( absint( $template_id ) );
			}
		}

		return $contents;
	}

	/**
	 * Check if the current page has loops with random order.
	 *
	 * Detects random order loops by checking:
	 * 1. If page content has loop-enabled blocks
	 * 2. If any loop blocks have orderBy set to 'rand'
	 *
	 * @since ??
	 *
	 * @return bool True if random order loops detected, false otherwise.
	 */
	public static function current_page_has_random_order_loops(): bool {
		foreach ( self::_get_loop_detection_content_sources() as $content ) {
			if ( self::_content_has_random_order_loops( $content ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the current post ID based on the query type and query item.
	 *
	 * @since ??
	 *
	 * @param string $query_type The type of query.
	 * @param object $query_item The query item.
	 *
	 * @return int The current post ID.
	 */
	private static function _get_current_post_id( $query_type, $query_item ) {
		if ( self::_is_terms_query( $query_type ) ) {
			return $query_item->term_id;
		} elseif ( self::_is_user_query( $query_type ) ) {
			return $query_item->ID;
		} elseif ( self::_is_menus_query( $query_type ) ) {
			// For menu items, return the menu item ID.
			if ( is_object( $query_item ) && isset( $query_item->id ) ) {
				return (int) $query_item->id;
			} elseif ( is_array( $query_item ) && isset( $query_item['id'] ) ) {
				return (int) $query_item['id'];
			}
			return 0;
		} elseif ( DynamicContentACFUtils::is_repeater_query( $query_type ) ) {
			return (int) $query_item['_post_id'];
		}

		return $query_item->ID;
	}

	/**
	 * Process dynamic content for modules, handling loop context automatically.
	 *
	 * This is a public utility that modules can use to process dynamic content.
	 * It automatically detects and handles loop contexts when available.
	 *
	 * @since ??
	 *
	 * @param string $content The content to process.
	 * @param array  $attrs   Optional. Module attributes for loop context detection.
	 *
	 * @return string The processed content.
	 */
	public static function process_dynamic_content( string $content, array $attrs = [] ): string {
		if ( empty( $content ) || ! str_contains( $content, '$variable(' ) ) {
			return $content;
		}

		$loop_iteration = $attrs['__loop_iteration'] ?? null;
		$loop_context   = LoopContext::get();

		if ( null === $loop_iteration || ! $loop_context ) {
			// Fallback to standard processing.
			return DynamicData::get_processed_dynamic_data( $content, get_the_ID() );
		}

		$loop_object = $loop_context->get_result_for_position( 0 );
		$query_type  = $loop_context->get_query_type();

		return self::_process_loop_dynamic_content( $content, $query_type, $loop_object );
	}

	/**
	 * Process loop dynamic content with the correct context.
	 *
	 * This is a reusable utility that handles the common pattern of processing
	 * dynamic content within loop contexts by determining the correct post ID
	 * and applying the appropriate loop context parameters.
	 *
	 * @since ??
	 *
	 * @param string $content     The content to process.
	 * @param string $query_type  The type of query.
	 * @param mixed  $query_item  The query item (post, term, user, etc.).
	 *
	 * @return string The processed content.
	 */
	private static function _process_loop_dynamic_content( string $content, string $query_type, $query_item ): string {
		$current_post_id = self::_get_current_post_id( $query_type, $query_item );

		return DynamicData::get_processed_dynamic_data(
			$content,
			null,
			false,
			$current_post_id,
			$query_type,
			$query_item
		);
	}

	/**
	 * Parses document and duplicates blocks when loop is enabled.
	 *
	 * @since ??
	 *
	 * @param string $document The GB document content.
	 *
	 * @return string The processed document with duplicated loop blocks.
	 */
	/**
	 * Parses document and duplicates blocks when loop is enabled.
	 *
	 * @since ??
	 *
	 * @param string $document The GB document content.
	 *
	 * @return string The processed document with duplicated loop blocks.
	 */
	public static function parse_and_duplicate_loop_blocks( string $document ): string {
		// Skip loop processing when generating excerpts to prevent infinite recursion.
		// This prevents loops from being processed during excerpt generation, which would trigger
		// content rendering that needs excerpts, creating a cycle. Nested loops within loop items
		// are still supported and will process normally.
		if ( isset( $GLOBALS['divi_generating_excerpt'] ) && $GLOBALS['divi_generating_excerpt'] ) {
			return $document;
		}

		// Use the native WordPress block parser to avoid infinite recursion.
		$wordpress_block_parser = new WP_Block_Parser();
		$parsed_blocks          = $wordpress_block_parser->parse( $document );

		if ( empty( $parsed_blocks ) ) {
			return $document;
		}

		self::_register_loop_target_post_types_from_blocks( $parsed_blocks );

		// Process blocks recursively to handle nested structures.
		$duplicated_blocks = self::_duplicate_loop_enabled_blocks( $parsed_blocks );

		// Serialize back to GB format.
		$serialized_document = serialize_blocks( $duplicated_blocks );
		return $serialized_document;
	}

	/**
	 * Recursively processes blocks to duplicate loop-enabled ones.
	 * Optimized for performance by batching operations and reducing serialization overhead.
	 *
	 * @since ??
	 *
	 * @param array $blocks Array of parsed blocks.
	 *
	 * @return array Processed blocks with loop duplications.
	 */
	private static function _duplicate_loop_enabled_blocks( array $blocks ): array {
		$output_blocks = [];

		foreach ( $blocks as $current_block ) {
			// Early exit for empty blocks.
			if ( empty( $current_block ) ) {
				continue;
			}

			// Check if this block has loop enabled.
			$has_loop_enabled = self::_is_block_loop_enabled( $current_block );

			if ( $has_loop_enabled ) {
				// Process loop-enabled block.
				$loop_blocks   = self::_process_single_loop_block( $current_block );
				$output_blocks = array_merge( $output_blocks, $loop_blocks );
			} else {
				/*
				 * CRITICAL: Process non-loop blocks to handle nested structures.
				 *
				 * Why this is essential:
				 * 1. RECURSIVE LOOP DISCOVERY: Non-loop parent blocks (sections, rows, columns)
				 *    can contain loop-enabled child blocks that need processing.
				 *
				 *    Example: Section > Row > Column > Text Module (with loop enabled)
				 *    Without this processing, the Text Module's loop would be ignored!
				 *
				 * 2. INNERBLOCK SYNCHRONIZATION: When child blocks get duplicated due to loops,
				 *    the parent's innerContent array must be updated to match the new count.
				 *    This prevents block structure corruption and rendering issues.
				 *
				 * 3. COMPLEX LAYOUT SUPPORT: Real-world Divi pages have deeply nested structures
				 *    where loops can exist at any level. This ensures all loops are discovered
				 *    and processed regardless of nesting depth.
				 *
				 * Without this step: Nested loops would be completely ignored, causing:
				 * - Dynamic content to not render
				 * - Block structure corruption
				 * - Page layout breaking
				 * - Loss of loop functionality in complex layouts
				 */
				$processed_block = self::_process_non_loop_block( $current_block );
				$output_blocks[] = $processed_block;
			}
		}

		return $output_blocks;
	}

	/**
	 * Processes a single loop-enabled block and returns duplicated blocks.
	 *
	 * @since ??
	 *
	 * @param array $block The loop-enabled block to process.
	 *
	 * @return array Array of duplicated blocks.
	 */
	private static function _process_single_loop_block( array $block ): array {
		// Extract loop ID to track circular references.
		$loop_id = isset( $block['attrs']['module']['advanced']['loop']['desktop']['value']['loopId'] )
			? $block['attrs']['module']['advanced']['loop']['desktop']['value']['loopId']
			: null;

		// Track currently processing loop IDs to prevent circular references.
		// Use a static array to track loop IDs across recursive calls.
		static $processing_loop_ids = [];

		// If this loop is already being processed, skip it to prevent circular recursion.
		// This allows nested loops (different IDs) but prevents the same loop from processing itself.
		if ( ! empty( $loop_id ) && in_array( $loop_id, $processing_loop_ids, true ) ) {
			// Return the block unchanged to prevent recursion.
			return [ $block ];
		}

		// Add this loop ID to the processing stack.
		if ( ! empty( $loop_id ) ) {
			$processing_loop_ids[] = $loop_id;
		}

		try {
			// Get the loop data.
			$loop_data = self::get_query_args_from_attrs( $block['attrs'] );

			if ( ! empty( $loop_id ) ) {
				self::_register_loop_target_post_type_from_loop_data( $loop_id, $loop_data );
			}

			/*
			* Filter: `divi_loop_data_before_execution`,
			*
			* Allows modification of the complete loop data array immediately before it is processed
			* to construct the final WP_Query arguments. This filter fires before any context-specific
			* parsing takes place (such as adapting for archive pages, taxonomy views, etc.), making it
			* ideal for setting base query arguments, supplementing with additional query conditions,
			* or pre-processing loop values. Any changes here may be further adjusted by Divi context
			* logic downstream.
			*
			* @param array $loop_data   Full loop data array generated from block attributes, including
			*                           user settings and query_args sub-array (may be incomplete at this stage).
			* @param array $block_attrs All attributes for the current block.
			* @param array $block       The full parsed block array for the loop-enabled module.
			*
			* @return array Modified loop data array that will be used for subsequent context-specific
			*               handling and to eventually build the final query.
			*/
			$loop_data = apply_filters( 'divi_loop_data_before_execution', $loop_data, $block['attrs'], $block );

			// Handle current page query type which is applied on index page.
			if ( 'current_page' === $loop_data['query_type'] ) {
				$prior_query_args        = $loop_data['query_args'];
				$posts_per_page          = (int) ( $prior_query_args['posts_per_page'] ?? self::CURRENT_PAGE_DEFAULT_PER_PAGE );
				$post_offset             = (int) ( $prior_query_args['offset'] ?? 0 );
				$loop_data['query_args'] = self::_merge_current_page_loop_sort_args(
					self::build_current_page_query_args( $posts_per_page, $post_offset ),
					$prior_query_args
				);

				if ( isset( $loop_data['query_args']['post_type'] ) ) {
					$loop_data['post_type'] = $loop_data['query_args']['post_type'];
				}
			}

			// Extract loop ID from attributes for registry storage.
			$loop_id = isset( $block['attrs']['module']['advanced']['loop']['desktop']['value']['loopId'] )
			? $block['attrs']['module']['advanced']['loop']['desktop']['value']['loopId']
			: null;

			/**
			 * Filters the loop data after all processing but before query execution.
			 *
			 * This filter runs AFTER all context-specific query modifications have been applied:
			 * - current_page handling (singular/archive)
			 * - taxonomy parameters (category, tag, custom taxonomies)
			 * - author/date/search parameters
			 * - post type archive parameters
			 *
			 * Use this filter when you need final control over query arguments before execution.
			 * For modifications that should happen BEFORE context processing, use the
			 * 'divi_loop_data_before_execution' filter instead.
			 *
			 * @since ??
			 *
			 * @param array $loop_data Array containing:
			 *                         - 'query_args': WP_Query arguments array
			 *                         - 'query_type': Type of query (post_types, terms, user_roles, etc.)
			 *                         - 'post_type': Post type(s) being queried
			 * @param array $block_attrs The block attributes from Visual Builder.
			 * @param array $block The complete block array including blockName, attrs, innerBlocks, etc.
			 *
			 * @return array Modified loop data. Must contain same keys as input.
			 */
			$loop_data = apply_filters( 'divi_loop_data_after_execution', $loop_data, $block['attrs'], $block );

			$existing_query = null;
			if ( ! empty( $loop_id ) ) {
				$existing_query = LoopQueryRegistry::get_query_if_matches(
					$loop_id,
					$loop_data['query_args'],
					$loop_data['query_type']
				);
			}

			// Use unified execute_query function for both fresh and cached queries.
			$query = self::execute_query( $loop_data['query_args'], $loop_data['query_type'], $existing_query );

			// Store new queries in registry (only if not already cached or mismatched).
			if ( null === $existing_query ) {
				$query_object   = isset( $query['query_object'] ) ? $query['query_object'] : null;
				$is_valid_query = $query_object instanceof WP_Query ||
				$query_object instanceof WP_User_Query ||
				$query_object instanceof WP_Term_Query;

				if ( ! empty( $loop_id ) && $is_valid_query ) {
					LoopQueryRegistry::store( $loop_id, $query_object, $loop_data['query_args'], $loop_data['query_type'] );
				}
			}

			// Handle empty results.
			if ( empty( $query['results'] ) ) {
				$block['attrs']['__loop_no_results'] = true;

				if ( ! empty( $loop_id ) ) {
					$block['attrs']['__loop_source_id'] = $loop_id;
				}

				return [ $block ];
			}

			$is_accordion_item = 'divi/accordion-item' === ( $block['blockName'] ?? '' );
			$query_results     = $query['results'];
			$query_type        = $loop_data['query_type'];
			$duplicated_blocks = [];

			$columns_per_row = self::detect_columns_per_row( $block['attrs'] );
			$iteration       = 0;

			// Prepare base block once (remove loop settings to prevent recursion).
			$base_block = $block;
			if ( isset( $base_block['attrs']['module']['advanced']['loop'] ) ) {
				unset( $base_block['attrs']['module']['advanced']['loop'] );
			}

			// Process each query result.
			foreach ( $query_results as $result_index => $query_item ) {
				LoopContext::set_position_context(
					$query_results,
					$columns_per_row,
					$result_index,
					$query_type,
					$iteration
				);

				// For accordion items, ensure only the first looped item is open.
				if ( $is_accordion_item && isset( $attrs['module']['advanced']['open'] ) ) {
					$is_first_accordion_item = BlockParserStore::is_first( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

					// Ensure only one Accordion item is open at a time by opening only the first iteration of the first Accordion item.
					$base_block['attrs']['module']['advanced']['open']['desktop']['value'] = $is_first_accordion_item
					&& ( 0 === $iteration )
					? 'on'
					: 'off';
				}

				// Pass iteration to modules so they can handle loop-specific behaviors.
				// For example, tabs use this to ensure only the first tab in the first iteration is active,
				// similar to how accordion items use it to ensure only the first item in the first iteration is open.
				$base_block['attrs']['__loop_iteration'] = $iteration;
				$base_block['attrs']['__loop_source_id']  = $loop_id;

				// Store post ID for later use during rendering (when $query_item is no longer available).
				$post_id = self::_get_current_post_id( $query_type, $query_item );
				if ( $post_id > 0 ) {
					$base_block['attrs']['__loop_post_id'] = $post_id;
				}

				$duplicated_block = self::_create_duplicated_block(
					$base_block,
					$query_item,
					$result_index,
					$query_type,
					$is_accordion_item
				);

				if ( null !== $duplicated_block ) {
					$duplicated_blocks[] = $duplicated_block;
				}

				++$iteration;
			}

			LoopContext::clear();

			// Add loop pagination data to the first block.
			if ( ! empty( $loop_id ) ) {
				$duplicated_blocks[0]['attrs']['module']['advanced']['loop']['loop_pagination_id']          = $loop_id;
				$duplicated_blocks[0]['attrs']['module']['advanced']['loop']['loop_pagination_total_pages'] = $query['total_pages'];
			}

			return $duplicated_blocks;
		} finally {
			// Always remove this loop ID from the processing stack, even if an exception occurs.
			if ( ! empty( $loop_id ) ) {
				$key = array_search( $loop_id, $processing_loop_ids, true );
				if ( false !== $key ) {
					unset( $processing_loop_ids[ $key ] );
					// Re-index array to maintain sequential keys.
					$processing_loop_ids = array_values( $processing_loop_ids );
				}
			}
		}
	}

	/**
	 * Creates a single duplicated block for a query result.
	 *
	 * @since ??
	 *
	 * @param array  $base_block       The base block to duplicate.
	 * @param object $query_item       The query result item.
	 * @param int    $result_index     The index of the result.
	 * @param string $query_type       The type of query.
	 * @param bool   $is_accordion_item Whether this is an accordion item.
	 *
	 * @return array|null The duplicated block or null on failure.
	 */
	private static function _create_duplicated_block( array $base_block, $query_item, int $result_index, string $query_type, bool $is_accordion_item ): ?array {
		// Create block copy.
		$duplicated_block = $base_block;

		// Handle accordion-specific logic.
		if ( $is_accordion_item ) {
			$should_be_open = 0 === $result_index;
			$duplicated_block['attrs']['module']['advanced']['open']['desktop']['value'] = $should_be_open ? 'on' : 'off';
		}

		// Process inner blocks recursively.
		if ( ! empty( $duplicated_block['innerBlocks'] ) ) {
			$initial_count = count( $duplicated_block['innerBlocks'] );
			// Process nested loops FIRST, before replacing parent loop variables.
			// This ensures nested loops are processed with their original block structure.
			$processed_inner_blocks = [];
			foreach ( $duplicated_block['innerBlocks'] as $inner_block ) {
				if ( self::_is_block_loop_enabled( $inner_block ) ) {
					// Process nested loop block - this will return multiple duplicated blocks.
					// Each duplicated block will have nested loop variables replaced with its own query item
					// inside _create_duplicated_block when processing the nested loop.
					$nested_loop_blocks = self::_process_single_loop_block( $inner_block );
					// After processing nested loop, replace parent loop variables in each nested loop block.
					// This ensures nested loop blocks have BOTH nested loop variables (already replaced)
					// AND parent loop variables (replaced here) correctly set.
					foreach ( $nested_loop_blocks as &$nested_block ) {
						// Replace parent loop variables in nested loop block's attributes.
						if ( isset( $nested_block['attrs'] ) && is_array( $nested_block['attrs'] ) ) {
							$nested_block['attrs'] = self::replace_loop_variables_in_attrs(
								$nested_block['attrs'],
								$query_type,
								$query_item
							);
						}
						// Replace parent loop variables in nested loop block's inner blocks.
						if ( ! empty( $nested_block['innerBlocks'] ) ) {
							$temp_nested_block           = [ 'innerBlocks' => $nested_block['innerBlocks'] ];
							$temp_nested_block           = self::process_loop_block_inner_blocks( $temp_nested_block, $query_type, $query_item );
							$nested_block['innerBlocks'] = $temp_nested_block['innerBlocks'];
						}
					}
					unset( $nested_block ); // Break reference.
					$processed_inner_blocks = array_merge( $processed_inner_blocks, $nested_loop_blocks );
				} else {
					// For non-loop blocks, first process any nested loops inside them,
					// then replace loop variables with the parent loop's query item.
					$processed_block = self::_process_non_loop_block( $inner_block );
					// Replace loop variables in non-loop blocks with parent loop's query item.
					// Create a temporary block structure to process inner blocks.
					$temp_block                     = [ 'innerBlocks' => $processed_block['innerBlocks'] ?? [] ];
					$temp_block                     = self::process_loop_block_inner_blocks( $temp_block, $query_type, $query_item );
					$processed_block['innerBlocks'] = $temp_block['innerBlocks'];
					// Also replace loop variables in the block's own attributes.
					if ( isset( $processed_block['attrs'] ) && is_array( $processed_block['attrs'] ) ) {
						$processed_block['attrs'] = self::replace_loop_variables_in_attrs(
							$processed_block['attrs'],
							$query_type,
							$query_item
						);
					}
					$processed_inner_blocks[] = $processed_block;
				}
			}
			$duplicated_block['innerBlocks'] = $processed_inner_blocks;
			// Update innerContent array if block count changed.
			$final_count = count( $duplicated_block['innerBlocks'] );
			if ( $final_count !== $initial_count ) {
				$duplicated_block['innerContent'] = array_fill( 0, $final_count, null );
			}
		}

		// Process attributes with dynamic content.
		$duplicated_block['attrs'] = self::replace_loop_variables_in_attrs( $duplicated_block['attrs'], $query_type, $query_item );

		return $duplicated_block;
	}

	/**
	 * Processes a non-loop block, handling inner blocks recursively.
	 *
	 * This method is CRITICAL for proper loop processing in nested block structures.
	 * It ensures that loop-enabled blocks nested inside non-loop parent blocks
	 * (sections, rows, columns, etc.) are discovered and processed correctly.
	 *
	 * Key Functions:
	 * 1. RECURSIVE PROCESSING: Searches through all inner blocks to find hidden loops
	 * 2. STRUCTURE PRESERVATION: Maintains proper block hierarchy during processing
	 * 3. CONTENT SYNCHRONIZATION: Updates innerContent arrays when block counts change
	 *
	 * Example Scenario:
	 * ```
	 * Section (non-loop)
	 * ├── Row (non-loop)
	 * │   ├── Column (non-loop)
	 * │   │   └── Text Module (LOOP-ENABLED) ← This would be missed without this method!
	 * │   └── Column (non-loop)
	 * │       └── Blog Module (LOOP-ENABLED) ← This too!
	 * ```
	 *
	 * Without this processing:
	 * - Nested loops would never be discovered
	 * - Dynamic content inside nested structures wouldn't render
	 * - Complex Divi layouts would break
	 * - Block structure integrity would be compromised
	 *
	 * @since ??
	 *
	 * @param array $block The block to process.
	 *
	 * @return array The processed block with inner blocks handled recursively.
	 */
	private static function _process_non_loop_block( array $block ): array {
		// Process inner blocks if they exist.
		if ( ! empty( $block['innerBlocks'] ) ) {
			$initial_count = count( $block['innerBlocks'] );

			/*
			 * RECURSIVE CALL: This is where the magic happens!
			 *
			 * We recursively call _duplicate_loop_enabled_blocks() on the inner blocks,
			 * which ensures that ANY loop-enabled blocks nested inside this non-loop
			 * block will be discovered and processed, regardless of how deep they are.
			 *
			 * This recursive approach handles complex nested structures like:
			 * Section > Row > Column > Inner Section > Inner Row > Loop-Enabled Module
			 */
			$block['innerBlocks'] = self::_duplicate_loop_enabled_blocks( $block['innerBlocks'] );

			$final_count = count( $block['innerBlocks'] );

			/*
			 * INNERBLOCK SYNCHRONIZATION:
			 *
			 * If the number of inner blocks changed (due to loop duplication),
			 * we need to update the innerContent array to match. This prevents
			 * block structure corruption and ensures proper rendering.
			 *
			 * Example: If a child block had 5 loop items, the inner blocks count
			 * would change from 1 to 5, so we need 5 placeholders in innerContent.
			 */
			if ( $final_count !== $initial_count ) {
				$block['innerContent'] = array_fill( 0, $final_count, null );
			}
		}

		return $block;
	}

	/**
	 * Checks if a block has loop enabled.
	 *
	 * @since ??
	 *
	 * @param array $block The parsed block.
	 *
	 * @return bool True if loop is enabled, false otherwise.
	 */
	private static function _is_block_loop_enabled( array $block ): bool {
		$attrs      = $block['attrs'] ?? [];
		$loop_attrs = $attrs['module']['advanced']['loop'] ?? [];
		return ( $loop_attrs['desktop']['value']['enable'] ?? 'off' ) === 'on';
	}

	/**
	 * Process all inner blocks in a loop block to replace loop variables with actual content.
	 *
	 * This function specifically handles the processing of inner blocks within a loop-enabled block.
	 * It recursively traverses all nested inner blocks and replaces loop variables in their attributes
	 * with actual loop content. The function modifies the block structure in place and returns the updated block.
	 * Additionally, it updates all child module IDs with the loop unique ID to ensure unique identification.
	 *
	 * The function:
	 * - Processes all inner blocks recursively at any depth
	 * - Updates block attributes with resolved loop variables
	 * - Updates all child module IDs with loop unique ID
	 * - Modifies the loop block structure directly
	 * - Handles complex nested structures efficiently
	 * - Returns the updated block object
	 *
	 * @example
	 * Section (loop enabled) contains:
	 * - Text module with loop_post_title → gets resolved to actual title, ID updated
	 * - Column containing Button with loop_post_link → gets resolved to actual link, IDs updated
	 *
	 * @since ??
	 *
	 * @param array  $loop_block     The loop block containing inner blocks to process.
	 * @param string $query_type     The type of loop query (post_types, terms, users, etc.).
	 * @param mixed  $query_item     The current loop iteration result object (WP_Post, WP_Term, WP_User, etc.).
	 *
	 * @return array The updated loop block with all inner blocks processed and IDs updated.
	 */
	public static function process_loop_block_inner_blocks( array $loop_block, string $query_type, $query_item ): array {
		if ( ! isset( $loop_block['innerBlocks'] ) || empty( $loop_block['innerBlocks'] ) ) {
			return self::replace_loop_variables_in_attrs( $loop_block, $query_type, $query_item );
		}

		self::_process_inner_blocks_recursively( $loop_block['innerBlocks'], $query_type, $query_item );

		return $loop_block;
	}

	/**
	 * Recursively process inner blocks array to replace loop variables.
	 *
	 * This is a helper function that handles the recursive processing of nested inner blocks.
	 * It processes each block's attributes and recursively handles any nested inner blocks.
	 * Additionally, it updates all child module IDs with the loop unique ID.
	 *
	 * @since ??
	 *
	 * @param array  $inner_blocks   Array of inner blocks to process (passed by reference).
	 * @param string $query_type     The type of loop query (post_types, terms, users, etc.).
	 * @param mixed  $query_item     The current loop iteration result object (WP_Post, WP_Term, WP_User, etc.).
	 */
	private static function _process_inner_blocks_recursively( array &$inner_blocks, string $query_type, $query_item ): void {
		foreach ( $inner_blocks as &$inner_block ) {
			// Process this inner block's attributes.
			if ( isset( $inner_block['attrs'] ) && is_array( $inner_block['attrs'] ) ) {
				$inner_block['attrs'] = self::replace_loop_variables_in_attrs(
					$inner_block['attrs'],
					$query_type,
					$query_item
				);
			}

			// Recursively process nested inner blocks if they exist.
			if ( isset( $inner_block['innerBlocks'] ) && ! empty( $inner_block['innerBlocks'] ) ) {
				self::_process_inner_blocks_recursively(
					$inner_block['innerBlocks'],
					$query_type,
					$query_item
				);
			}
		}
	}

	/**
	 * Replace loop variables in attributes with actual loop content.
	 *
	 * Recursively processes data structures (strings, arrays) to find $variable() patterns
	 * and replaces them with the corresponding dynamic loop content. This enables loop
	 * variables like "loop_post_title" to be rendered as actual post titles within loop contexts.
	 *
	 * The function handles:
	 * - String values containing $variable() patterns
	 * - Nested arrays with $variable() patterns in values
	 * - Complex attribute structures from module elements
	 *
	 * @example
	 * Input:  '$variable({"type":"content","value":{"name":"loop_post_title","settings":{"before":"","after":""}}})'
	 * Output: 'Hello World' (actual post title from current loop iteration)
	 *
	 * @example
	 * Input:  ['title' => '$variable(...)', 'class' => 'my-class']
	 * Output: ['title' => 'Hello World', 'class' => 'my-class']
	 *
	 * @since ??
	 *
	 * @param mixed  $data       The data to process (string, array, or other types).
	 * @param string $query_type The type of loop query (post_types, terms, users, etc.).
	 * @param mixed  $query_item The current loop iteration result object (WP_Post, WP_Term, WP_User, etc.).
	 * @param array  $attr_path  The path of keys traversed in the attrs array.
	 *
	 * @return mixed The processed data with $variable() patterns replaced by actual loop content.
	 */
	public static function replace_loop_variables_in_attrs( $data, $query_type, $query_item, $attr_path = [] ) {
		if ( is_string( $data ) ) {
			// Special handling for product loops with "current" product field value.
			if (
				'current' === $data &&
				in_array( $query_type, [ 'post_types', 'current_page' ], true ) &&
				isset( $query_item->post_type ) &&
				isset( $query_item->ID )
			) {
				// Verify it's actually a product post type and the attribute path is a WooCommerce product field.
				if ( 'product' === $query_item->post_type && self::_is_woocommerce_product_attr_path( $attr_path ) ) {
					return (string) $query_item->ID;
				}
			}

			return self::_process_loop_dynamic_content( $data, $query_type, $query_item );
		}

		if ( is_array( $data ) ) {
			$processed_data = [];
			foreach ( $data as $key => $value ) {
				$new_path               = array_merge( $attr_path, [ $key ] );
				$processed_data[ $key ] = self::replace_loop_variables_in_attrs( $value, $query_type, $query_item, $new_path );
			}

			// After processing array recursively, check if we're at innerContent level for image element.
			// Populate alt/title attributes for Loop Builder featured images.
			if ( self::_is_image_inner_content_path( $attr_path ) && isset( $query_item->ID ) && $query_item->ID > 0 ) {
				$processed_data = self::_populate_featured_image_alt_title( $processed_data, $query_type, $query_item );
			}

			return $processed_data;
		}

		return $data;
	}

	/**
	 * Check if the attribute path represents a WooCommerce product field.
	 *
	 * @since ??
	 *
	 * @param array $attr_path The path of keys traversed in the attrs array.
	 *
	 * @return bool True if this is a WooCommerce product field path.
	 */
	private static function _is_woocommerce_product_attr_path( array $attr_path ): bool {
		$pattern = [ 'content', 'advanced', 'product', 'desktop', 'value' ];

		if ( count( $attr_path ) < count( $pattern ) ) {
			return false;
		}

		return array_slice( $attr_path, -count( $pattern ) ) === $pattern;
	}

	/**
	 * Check if the attribute path indicates we're processing innerContent for an image element.
	 *
	 * @since ??
	 *
	 * @param array $attr_path The path of keys traversed in the attrs array.
	 *
	 * @return bool True if this is an image element innerContent path.
	 */
	private static function _is_image_inner_content_path( array $attr_path ): bool {
		// Check if path ends with ['image', 'innerContent'] or ['imageIcon', 'innerContent'].
		$path_length = count( $attr_path );
		if ( $path_length < 2 ) {
			return false;
		}

		$last_two = array_slice( $attr_path, -2 );

		if ( 'innerContent' !== $last_two[1] ) {
			return false;
		}

		return in_array( $last_two[0], [ 'image', 'imageIcon' ], true );
	}

	/**
	 * Populate alt and title attributes for featured images in Loop Builder.
	 *
	 * This method detects featured images using the $divi_loop_image_ids global array
	 * and populates alt/title attributes when they're empty, using post metadata.
	 *
	 * @since ??
	 *
	 * @param array  $inner_content The inner content array with breakpoints and states.
	 * @param string $query_type   The query type (e.g., 'post_types').
	 * @param mixed  $query_item    The query item (WP_Post object).
	 *
	 * @return array The updated inner content array with populated alt/title attributes.
	 */
	private static function _populate_featured_image_alt_title( array $inner_content, string $query_type, $query_item ): array {
		global $divi_loop_image_ids;

		// Check if we have the global array and query item with valid ID.
		if ( ! isset( $divi_loop_image_ids ) || ! is_array( $divi_loop_image_ids ) ) {
			return $inner_content;
		}

		if ( ! isset( $query_item->ID ) || $query_item->ID <= 0 ) {
			return $inner_content;
		}

		// Detect if this is a featured image using the global array.
		$featured_image_url = ModuleElementsUtils::detect_featured_image_url( $inner_content );
		if ( ! $featured_image_url ) {
			return $inner_content;
		}

		// Get post object for resolving alt/title.
		$loop_post_object = get_post( $query_item->ID );
		if ( ! $loop_post_object ) {
			return $inner_content;
		}

		// Resolve alt text using backend support case.
		$resolved_alt_text = DynamicContentUtils::get_resolved_value(
			[
				'name'            => 'loop_post_featured_image_alt_text',
				'loop_id'         => $query_item->ID,
				'loop_query_type' => $query_type,
				'loop_object'     => $loop_post_object,
				'settings'        => [],
				'context'         => 'display',
			]
		);

		// Resolve title text using backend support case.
		$resolved_title_text = DynamicContentUtils::get_resolved_value(
			[
				'name'            => 'loop_post_featured_image_title_text',
				'loop_id'         => $query_item->ID,
				'loop_query_type' => $query_type,
				'loop_object'     => $loop_post_object,
				'settings'        => [],
				'context'         => 'display',
			]
		);

		// Only populate if we have values (don't add empty alt attribute).
		$has_alt_text   = ( false !== $resolved_alt_text && '' !== $resolved_alt_text );
		$has_title_text = (bool) $resolved_title_text;

		// Use existing helper function to populate across all breakpoints/states.
		return ModuleElementsUtils::populate_alt_title_across_breakpoints(
			$inner_content,
			$resolved_alt_text,
			$resolved_title_text,
			$has_alt_text,
			$has_title_text
		);
	}

	/**
	 * Wraps a render callback to handle loop "no results" scenarios.
	 *
	 * When a loop has no results (indicated by the '__loop_no_results' attribute),
	 * this wrapper will return an empty string for child modules or display
	 * a "no results found" message for parent modules.
	 *
	 * @since ??
	 *
	 * @param callable $original_callback The original module render callback.
	 *
	 * @return callable The wrapped callback that handles loop no-results cases.
	 */
	public static function wrap_render_callback_for_loop_no_results( callable $original_callback ): callable {
		return function ( $attrs, $content, $block, $elements, $default_printed_style_attrs ) use ( $original_callback ) {
			if ( isset( $attrs['__loop_no_results'] ) && $attrs['__loop_no_results'] ) {
				// For child modules with no loop results, return empty string instead of rendering.
				if ( ModuleRegistration::is_child_module( $block->name ) ) {
					return '';
				}
				$content = self::render_no_results_found_message();

				// Hook to allow modification of the no results output.
				$content = apply_filters(
					'divi_loop_no_results_output',
					$content,
					$attrs,
					$block,
					$elements,
					$default_printed_style_attrs
				);
			}

			$output = call_user_func(
				$original_callback,
				$attrs,
				$content,
				$block,
				$elements,
				$default_printed_style_attrs
			);

			// Hook to allow modification of the final output.
			$output = apply_filters(
				'divi_loop_rendered_output',
				$output,
				$attrs,
				$block,
				$elements,
				$default_printed_style_attrs
			);

			return $output;
		};
	}

	/**
	 * Apply taxonomy archive constraints to query args.
	 *
	 * @since ??
	 *
	 * @param array  $query_args Current query args.
	 * @param string $taxonomy   Taxonomy slug.
	 * @param int    $term_id    Term ID.
	 *
	 * @return array Updated query args.
	 */
	private static function _apply_taxonomy_archive_query_args( array $query_args, string $taxonomy, int $term_id ): array {
		// Handle core WordPress taxonomies with specific parameters for better performance.
		if ( 'category' === $taxonomy ) {
			$query_args['cat'] = $term_id;
		} elseif ( 'post_tag' === $taxonomy ) {
			$query_args['tag_id'] = $term_id;
		} else {
			// Handle all other taxonomies (custom taxonomies, WooCommerce, etc.).
			$query_args['tax_query'] = [
				[
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term_id,
				],
			];

			// Try to determine post type from taxonomy.
			$tax_object = get_taxonomy( $taxonomy );
			if ( $tax_object && ! empty( $tax_object->object_type ) ) {
				$query_args['post_type'] = $tax_object->object_type;
			}
		}

		return $query_args;
	}

	/**
	 * Resolve year, month, and day query vars for date archives.
	 *
	 * Falls back to the main query object and the `m` query var when `get_query_var()`
	 * does not expose archive date parts during Theme Builder layout rendering.
	 *
	 * @since ??
	 *
	 * @return array{
	 *     year: int,
	 *     monthnum: int,
	 *     day: int,
	 * }
	 */
	private static function _resolve_archive_date_query_vars(): array {
		global $wp_query;

		$year     = absint( get_query_var( 'year' ) );
		$monthnum = absint( get_query_var( 'monthnum' ) );
		$day      = absint( get_query_var( 'day' ) );
		$m        = (string) get_query_var( 'm' );

		if ( $wp_query instanceof WP_Query ) {
			if ( 0 >= $year ) {
				$year = absint( $wp_query->get( 'year' ) );
			}

			if ( 0 >= $monthnum ) {
				$monthnum = absint( $wp_query->get( 'monthnum' ) );
			}

			if ( 0 >= $day ) {
				$day = absint( $wp_query->get( 'day' ) );
			}

			if ( '' === $m ) {
				$m = (string) $wp_query->get( 'm' );
			}
		}

		if ( '' !== $m ) {
			if ( 0 >= $year && strlen( $m ) >= 4 ) {
				$year = absint( substr( $m, 0, 4 ) );
			}

			if ( 0 >= $monthnum && strlen( $m ) >= 6 ) {
				$monthnum = absint( substr( $m, 4, 2 ) );
			}

			if ( 0 >= $day && strlen( $m ) >= 8 ) {
				$day = absint( substr( $m, 6, 2 ) );
			}
		}

		return [
			'year'     => $year,
			'monthnum' => $monthnum,
			'day'      => $day,
		];
	}

	/**
	 * Resolve year, month, and day from date-archive URL path segments.
	 *
	 * @since ??
	 *
	 * @param string[] $path_segments URL path segments after the site root.
	 *
	 * @return array{
	 *     year: int,
	 *     monthnum: int,
	 *     day: int,
	 * }
	 */
	private static function _resolve_date_vars_from_path_segments( array $path_segments ): array {
		$date_vars = [
			'year'     => 0,
			'monthnum' => 0,
			'day'      => 0,
		];

		$path_segment_count = count( $path_segments );

		if ( 1 === $path_segment_count && preg_match( '/^\d{4}$/', $path_segments[0] ) ) {
			$date_vars['year'] = absint( $path_segments[0] );
		} elseif (
			2 === $path_segment_count
			&& preg_match( '/^\d{4}$/', $path_segments[0] )
			&& preg_match( '/^\d{1,2}$/', $path_segments[1] )
		) {
			$monthnum = absint( $path_segments[1] );

			// Reject non-date numeric paths such as /2022/99/.
			if ( 1 <= $monthnum && 12 >= $monthnum ) {
				$date_vars['year']     = absint( $path_segments[0] );
				$date_vars['monthnum'] = $monthnum;
			}
		} elseif (
			3 === $path_segment_count
			&& preg_match( '/^\d{4}$/', $path_segments[0] )
			&& preg_match( '/^\d{1,2}$/', $path_segments[1] )
			&& preg_match( '/^\d{1,2}$/', $path_segments[2] )
		) {
			$monthnum = absint( $path_segments[1] );
			$day      = absint( $path_segments[2] );

			// Reject non-date numeric paths such as /2022/13/40/.
			if ( 1 <= $monthnum && 12 >= $monthnum && 1 <= $day && 31 >= $day ) {
				$date_vars['year']     = absint( $path_segments[0] );
				$date_vars['monthnum'] = $monthnum;
				$date_vars['day']      = $day;
			}
		}

		return $date_vars;
	}

	/**
	 * Apply date archive year/month/day filters to current_page query args.
	 *
	 * @since ??
	 *
	 * @param array $query_args Base query arguments.
	 * @param array{
	 *     year: int,
	 *     monthnum: int,
	 *     day: int,
	 * }|null $date_vars Optional pre-resolved date vars.
	 *
	 * @return array WP_Query arguments with date archive filters applied.
	 */
	private static function _apply_date_archive_query_args( array $query_args, ?array $date_vars = null ): array {
		if ( null === $date_vars ) {
			$date_vars = self::_resolve_archive_date_query_vars();
		}

		$year = absint( $date_vars['year'] ?? 0 );

		if ( 0 >= $year ) {
			return $query_args;
		}

		$query_args['year'] = $year;

		$monthnum = absint( $date_vars['monthnum'] ?? 0 );
		$day      = absint( $date_vars['day'] ?? 0 );

		if ( 0 < $day && 0 < $monthnum ) {
			$query_args['monthnum'] = $monthnum;
			$query_args['day']      = $day;
		} elseif ( 0 < $monthnum ) {
			$query_args['monthnum'] = $monthnum;
		}

		return $query_args;
	}

	/**
	 * Merge loop-driven sort keys from prior post query args onto current_page context args.
	 *
	 * After {@see self::build_current_page_query_args()} replaces `query_args`, this reapplies
	 * `orderby`, `order`, and non-empty `meta_query` from `_build_post_query_args()` so archive
	 * context stays intact while user-selected ordering is preserved.
	 *
	 * @since ??
	 *
	 * @param array $built_args Args returned from build_current_page_query_args().
	 * @param array $prior_args Args from _build_post_query_args() before current_page replacement.
	 *
	 * @return array Merged WP_Query arguments.
	 */
	private static function _merge_current_page_loop_sort_args( array $built_args, array $prior_args ): array {
		if ( isset( $prior_args['orderby'] ) ) {
			$built_args['orderby'] = $prior_args['orderby'];
		}

		if ( isset( $prior_args['order'] ) ) {
			$built_args['order'] = $prior_args['order'];
		}

		if ( ! empty( $prior_args['meta_query'] ) ) {
			$built_args['meta_query'] = $prior_args['meta_query'];
		}

		return $built_args;
	}

	/**
	 * Build intentionally empty query args for current_page contexts without loopable content.
	 *
	 * @since ??
	 *
	 * @param array $query_args Base query arguments.
	 *
	 * @return array WP_Query arguments that return no posts.
	 */
	private static function _build_empty_current_page_query_args( array $query_args ): array {
		// WordPress strips 0 from post__in via wp_parse_id_list(), so use a non-existent ID.
		$query_args['post__in']            = [ PHP_INT_MAX ];
		$query_args['ignore_sticky_posts'] = 1;

		return $query_args;
	}

	/**
	 * Build query args for current_page query type based on current page context.
	 *
	 * @since ??
	 *
	 * @param int    $posts_per_page         Number of posts per page.
	 * @param int    $offset                 Query offset.
	 * @param string $context_url            Optional current page URL for REST requests without archive context.
	 * @param string $main_loop_type         Optional main loop type from settings data.
	 * @param array  $main_loop_settings_data Optional main loop settings data payload.
	 *
	 * @return array WP_Query arguments.
	 */
	public static function build_current_page_query_args( int $posts_per_page, int $offset, string $context_url = '', string $main_loop_type = '', array $main_loop_settings_data = [] ): array {
		$query_args = [
			'posts_per_page' => $posts_per_page,
			'offset'         => $offset,
			'post_status'    => 'publish',
		];

		// 404 requests have no current page context; return an intentionally empty query.
		if ( is_404() ) {
			return self::_build_empty_current_page_query_args( $query_args );
		}

		$has_runtime_context = is_singular() || is_archive() || is_author() || is_date() || is_search() || is_home();

		// In REST requests, archive context is often unavailable.
		// First, use explicit main-loop context from settings when available.
		$context_main_loop_type = sanitize_key( $main_loop_type );
		$context_taxonomy       = sanitize_key( (string) ( $main_loop_settings_data['taxonomy'] ?? '' ) );
		$context_term_id        = absint( $main_loop_settings_data['termId'] ?? 0 );
		$context_post_type      = sanitize_key( (string) ( $main_loop_settings_data['postType'] ?? '' ) );
		$context_author_id      = absint( $main_loop_settings_data['authorId'] ?? 0 );

		if ( ! $has_runtime_context && '' !== $context_main_loop_type ) {
			if ( '404' === $context_main_loop_type ) {
				return self::_build_empty_current_page_query_args( $query_args );
			}

			if ( 'category' === $context_main_loop_type ) {
				$context_taxonomy = 'category';
			} elseif ( 'tag' === $context_main_loop_type ) {
				$context_taxonomy = 'post_tag';
			}

			if ( in_array( $context_main_loop_type, [ 'category', 'tag', 'taxonomy' ], true ) && '' !== $context_taxonomy && 0 < $context_term_id ) {
				return self::_apply_taxonomy_archive_query_args( $query_args, $context_taxonomy, $context_term_id );
			}

			if ( 'post_type_archive' === $context_main_loop_type && '' !== $context_post_type ) {
				$query_args['post_type'] = [ $context_post_type ];
				return $query_args;
			}

			if ( 'author' === $context_main_loop_type && 0 < $context_author_id ) {
				$query_args['author'] = $context_author_id;
				return $query_args;
			}
		}

		// If explicit main-loop context is unavailable, use the current page URL as fallback.
		if ( ! $has_runtime_context && ! empty( $context_url ) ) {
			$context_url_parts_raw = wp_parse_url( esc_url_raw( $context_url ) );
			$context_url_parts     = is_array( $context_url_parts_raw ) ? $context_url_parts_raw : [];
			$context_query         = [];
			$context_path          = isset( $context_url_parts['path'] ) ? trim( (string) $context_url_parts['path'], '/' ) : '';
			$path_segments         = '' !== $context_path ? array_values( array_filter( explode( '/', $context_path ) ) ) : [];

			if ( isset( $context_url_parts['query'] ) ) {
				parse_str( (string) $context_url_parts['query'], $context_query );
			}

			$query_args['post_type'] = 'post';

			// Normalize common archive pagination suffix: /.../page/2/.
			$trimmed_path_segments = $path_segments;
			$segments_count        = count( $trimmed_path_segments );
			if ( $segments_count >= 2 && 'page' === $trimmed_path_segments[ $segments_count - 2 ] && is_numeric( $trimmed_path_segments[ $segments_count - 1 ] ) ) {
				array_pop( $trimmed_path_segments );
				array_pop( $trimmed_path_segments );
			}

			$taxonomy_handled = false;
			$taxonomies       = get_taxonomies( [ 'public' => true ], 'objects' );

			foreach ( $taxonomies as $taxonomy_slug => $taxonomy_object ) {
				$rewrite_slug = '';
				if ( isset( $taxonomy_object->rewrite['slug'] ) && is_string( $taxonomy_object->rewrite['slug'] ) ) {
					$rewrite_slug = trim( $taxonomy_object->rewrite['slug'], '/' );
				}

				if ( '' === $rewrite_slug || count( $trimmed_path_segments ) < 2 || $trimmed_path_segments[0] !== $rewrite_slug ) {
					continue;
				}

				$term_slug = end( $trimmed_path_segments );
				if ( ! is_string( $term_slug ) || '' === $term_slug ) {
					continue;
				}

				$term = get_term_by( 'slug', $term_slug, $taxonomy_slug );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}

				$query_args = self::_apply_taxonomy_archive_query_args( $query_args, $taxonomy_slug, (int) $term->term_id );

				$taxonomy_handled = true;
				break;
			}

			if ( ! $taxonomy_handled && ! empty( $trimmed_path_segments ) ) {
				$post_types = get_post_types( [ 'public' => true ], 'objects' );

				foreach ( $post_types as $post_type_slug => $post_type_object ) {
					$archive_slug = '';

					if ( is_string( $post_type_object->has_archive ) ) {
						$archive_slug = trim( $post_type_object->has_archive, '/' );
					} elseif ( true === $post_type_object->has_archive ) {
						$archive_slug = isset( $post_type_object->rewrite['slug'] ) && is_string( $post_type_object->rewrite['slug'] )
							? trim( $post_type_object->rewrite['slug'], '/' )
							: $post_type_slug;
					}

					if ( '' === $archive_slug || $trimmed_path_segments[0] !== $archive_slug ) {
						continue;
					}

					$query_args['post_type'] = [ $post_type_slug ];
					break;
				}
			}

			if ( ! empty( $context_query['s'] ) ) {
				$query_args['s']         = sanitize_text_field( (string) $context_query['s'] );
				$query_args['post_type'] = [ 'any' ];
			}

			if ( ! empty( $context_query['author_name'] ) ) {
				$query_args['author_name'] = sanitize_text_field( (string) $context_query['author_name'] );
			}

			if ( ! empty( $context_query['post_format'] ) ) {
				$query_args['post_format'] = sanitize_text_field( (string) $context_query['post_format'] );
			}

			if ( ! empty( $context_query['year'] ) ) {
				$query_args['year'] = absint( $context_query['year'] );
			}

			if ( ! empty( $context_query['monthnum'] ) ) {
				$query_args['monthnum'] = absint( $context_query['monthnum'] );
			}

			if ( ! empty( $context_query['day'] ) ) {
				$query_args['day'] = absint( $context_query['day'] );
			}

			if ( empty( $query_args['year'] ) && ! empty( $trimmed_path_segments ) ) {
				$path_date_vars = self::_resolve_date_vars_from_path_segments( $trimmed_path_segments );

				if ( 0 < $path_date_vars['year'] ) {
					$query_args = self::_apply_date_archive_query_args( $query_args, $path_date_vars );
				}
			}

			return $query_args;
		}

		// Check if we're on a single post/page.
		if ( is_singular() ) {
			// Get the current post/page.
			$current_post = get_queried_object();

			if ( $current_post && isset( $current_post->ID ) ) {
				// Set query args to get only the current post.
				$query_args['post_type']      = $current_post->post_type;
				$query_args['p']              = $current_post->ID;
				$query_args['posts_per_page'] = 1;
			}
		} else {
			// We're on an archive page - set up loop query.
			$query_args['post_type'] = 'post';

			// Check for taxonomy archives first (most comprehensive approach).
			$queried_object   = get_queried_object();
			$taxonomy_handled = false;

			// Handle any taxonomy archive (including custom taxonomies like WooCommerce).
			if ( $queried_object && isset( $queried_object->taxonomy, $queried_object->term_id ) ) {
				$query_args       = self::_apply_taxonomy_archive_query_args(
					$query_args,
					(string) $queried_object->taxonomy,
					(int) $queried_object->term_id
				);
				$taxonomy_handled = true;
			}

			// Handle post type archives (e.g., /projects/, /products/, etc.).
			if ( ! $taxonomy_handled && $queried_object && isset( $queried_object->name ) && is_a( $queried_object, 'WP_Post_Type' ) ) {
				$post_type_name = $queried_object->name;

				// Set the post type for the query.
				$query_args['post_type'] = [ $post_type_name ];

				$taxonomy_handled = true;
			}

			// Only check other conditions if taxonomy wasn't handled.
			if ( ! $taxonomy_handled ) {
				$archive_date_vars = self::_resolve_archive_date_query_vars();

				if ( is_author() ) {
					// Author archive.
					$query_args['author'] = get_queried_object_id();
				} elseif ( is_search() ) {
					// Search results.
					$search_query = get_search_query();
					if ( ! empty( $search_query ) ) {
						$query_args['s'] = $search_query;

						// Include all public and searchable post types in search results.
						// Using 'any' automatically excludes post types with 'exclude_from_search' => true.
						$query_args['post_type'] = [ 'any' ];
					}
				} elseif ( is_date() || 0 < $archive_date_vars['year'] ) {
					// Date archive: also apply when main query date vars survive but conditional tags do not.
					$query_args = self::_apply_date_archive_query_args( $query_args, $archive_date_vars );
				}
			}

			// Handle additional query vars that might be set.
			$context_vars = [ 'author_name', 'post_format' ];
			foreach ( $context_vars as $var ) {
				$value = get_query_var( $var );
				if ( ! empty( $value ) ) {
					$query_args[ $var ] = $value;
				}
			}
		}

		return $query_args;
	}
}
