<?php
/**
 * Loop: LoopIdDeduplicationUtils.
 *
 * Ensures loopId values are unique across co-rendered Theme Builder layout contents.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Loop;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Utilities for deduplicating loop IDs across multiple layout content strings.
 *
 * @since ??
 */
class LoopIdDeduplicationUtils {

	/**
	 * Whether a deduplication persistence pass is currently running.
	 *
	 * @var bool
	 */
	private static $_is_persisting = false;

	/**
	 * Layout meta keys on et_template posts, in processing order.
	 *
	 * @var string[]
	 */
	public const TEMPLATE_LAYOUT_META_KEYS = [
		'_et_header_layout_id',
		'_et_body_layout_id',
		'_et_footer_layout_id',
	];

	/**
	 * Generate a unique loop identifier matching the Visual Builder format.
	 *
	 * @since ??
	 *
	 * @return string Loop ID in the form loop-{10-char-nanoid}.
	 */
	public static function generate_loop_id(): string {
		$chars     = '0123456789abcdefghijklmnopqrstuvwxyz';
		$max_index = strlen( $chars ) - 1;
		$id        = '';

		for ( $i = 0; $i < 10; $i++ ) {
			$id .= $chars[ random_int( 0, $max_index ) ];
		}

		return 'loop-' . $id;
	}

	/**
	 * Deduplicate loopId values across an ordered list of layout content strings.
	 *
	 * First-seen loopId in processing order is preserved; each later layout that
	 * reuses it receives its own generated loopId. Post Navigation targetLoop values
	 * are remapped via each layout's old-to-new map.
	 *
	 * @since ??
	 *
	 * @param array<string, string> $contents Associative map of layout key => Gutenberg content.
	 *
	 * @return array<string, string> Patched contents keyed the same as input.
	 */
	public static function deduplicate_loop_ids_across_contents( array $contents ): array {
		if ( count( $contents ) < 2 ) {
			return $contents;
		}

		$result        = $contents;
		$seen_loop_ids = [];
		$has_remaps    = false;

		foreach ( $contents as $key => $content ) {
			if ( ! is_string( $content ) || '' === $content || ! str_contains( $content, 'loop-' ) ) {
				continue;
			}

			$blocks              = parse_blocks( $content );
			$remapped_in_layout  = [];
			$seen_before_layout  = $seen_loop_ids;

			self::_collect_layout_loop_id_remaps( $blocks, $seen_loop_ids, $seen_before_layout, $remapped_in_layout );

			if ( empty( $remapped_in_layout ) ) {
				continue;
			}

			$has_remaps     = true;
			$result[ $key ] = self::_patch_loop_ids_in_content( $content, $remapped_in_layout );
		}

		if ( ! $has_remaps ) {
			return $contents;
		}

		return $result;
	}

	/**
	 * Deduplicate loop IDs across sibling layouts in a Theme Builder template.
	 *
	 * @since ??
	 *
	 * @param int $template_id et_template post ID.
	 *
	 * @return bool True when any layout content was updated.
	 */
	public static function deduplicate_template_sibling_layouts( int $template_id ): bool {
		if ( self::$_is_persisting || $template_id <= 0 ) {
			return false;
		}

		if ( ! defined( 'ET_THEME_BUILDER_TEMPLATE_POST_TYPE' ) || ET_THEME_BUILDER_TEMPLATE_POST_TYPE !== get_post_type( $template_id ) ) {
			return false;
		}

		$layout_entries = self::_get_template_layout_contents( $template_id );

		return self::deduplicate_layout_entries( $layout_entries );
	}

	/**
	 * Persist deduplicated layout content to layout posts.
	 *
	 * @since ??
	 *
	 * @param array<string, array{id: int, content: string}> $layout_entries   Layout entries keyed by template meta.
	 * @param array<string, string>                          $deduped_contents Deduplicated contents keyed like entries.
	 *
	 * @return bool True when any layout content was updated.
	 */
	public static function persist_deduplicated_layout_content_changes( array $layout_entries, array $deduped_contents ): bool {
		if ( self::$_is_persisting ) {
			return false;
		}

		$changed = false;

		self::$_is_persisting = true;

		foreach ( $layout_entries as $meta_key => $entry ) {
			$layout_id       = (int) $entry['id'];
			$deduped_content = $deduped_contents[ $meta_key ] ?? $entry['content'];

			if ( $deduped_content === $entry['content'] ) {
				continue;
			}

			wp_update_post(
				[
					'ID'           => $layout_id,
					'post_content' => wp_slash( $deduped_content ),
				]
			);

			clean_post_cache( $layout_id );
			$changed = true;
		}

		self::$_is_persisting = false;

		return $changed;
	}

	/**
	 * Deduplicate loop IDs across sibling layouts when one layout post is saved.
	 *
	 * @since ??
	 *
	 * @param int $layout_post_id Theme Builder layout post ID.
	 *
	 * @return bool True when any layout content was updated.
	 */
	public static function deduplicate_sibling_layouts_for_layout_post( int $layout_post_id ): bool {
		$template_id = self::get_template_id_for_layout( $layout_post_id );

		if ( $template_id <= 0 ) {
			return false;
		}

		return self::deduplicate_template_sibling_layouts( $template_id );
	}

	/**
	 * Find the et_template post ID that references a Theme Builder layout.
	 *
	 * @since ??
	 *
	 * @param int $layout_id Layout post ID.
	 *
	 * @return int Template post ID, or 0 when not found.
	 */
	public static function get_template_id_for_layout( int $layout_id ): int {
		$layout_id = (int) $layout_id;

		if ( $layout_id <= 0 || ! function_exists( 'et_theme_builder_is_layout_post_type' ) ) {
			return 0;
		}

		$layout_type = get_post_type( $layout_id );

		if ( ! et_theme_builder_is_layout_post_type( $layout_type ) ) {
			return 0;
		}

		$meta_key = "_{$layout_type}_id";

		$template_query = new \WP_Query(
			[
				'post_type'              => ET_THEME_BUILDER_TEMPLATE_POST_TYPE,
				'post_status'            => [ 'publish', 'draft', 'pending', 'private' ],
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => [
					[
						'key'     => $meta_key,
						'value'   => $layout_id,
						'compare' => '=',
					],
				],
			]
		);

		if ( ! $template_query->have_posts() ) {
			return 0;
		}

		return (int) $template_query->posts[0];
	}

	/**
	 * Resolve the et_template post ID for a Visual Builder save context.
	 *
	 * @since ??
	 *
	 * @param int $post_id Saved post ID (page/post or TB layout).
	 *
	 * @return int Template post ID, or 0 when not found.
	 */
	public static function get_template_id_for_tb_save_context( int $post_id ): int {
		$layouts = self::get_effective_tb_layouts_for_post( $post_id );

		if ( empty( $layouts ) ) {
			return 0;
		}

		if ( defined( 'ET_THEME_BUILDER_TEMPLATE_POST_TYPE' ) ) {
			$template_id = (int) ( $layouts[ ET_THEME_BUILDER_TEMPLATE_POST_TYPE ] ?? 0 );

			if ( 0 < $template_id ) {
				return $template_id;
			}
		}

		$layout_post_types = self::_get_tb_layout_post_types();

		foreach ( $layout_post_types as $layout_post_type ) {
			$layout_entry = $layouts[ $layout_post_type ] ?? null;
			$layout_id    = is_array( $layout_entry ) ? (int) ( $layout_entry['id'] ?? 0 ) : 0;

			if ( 0 >= $layout_id || $post_id === $layout_id ) {
				continue;
			}

			$template_id = self::get_template_id_for_layout( $layout_id );

			if ( 0 < $template_id ) {
				return $template_id;
			}
		}

		return 0;
	}

	/**
	 * Load effective Theme Builder layouts for a page/post request context.
	 *
	 * @since ??
	 *
	 * @param int $post_id Page or post ID.
	 *
	 * @return array Theme Builder layouts array, or empty when unavailable.
	 */
	public static function get_effective_tb_layouts_for_post( int $post_id ): array {
		if ( 0 >= $post_id || ! function_exists( 'et_theme_builder_get_template_layouts' ) || ! class_exists( 'ET_Theme_Builder_Request' ) ) {
			return [];
		}

		$request = \ET_Theme_Builder_Request::from_post( $post_id );
		$layouts = et_theme_builder_get_template_layouts( $request, false );

		return is_array( $layouts ) ? $layouts : [];
	}

	/**
	 * Build layout content entries from an et_theme_builder_get_template_layouts() result.
	 *
	 * Only overridden areas with a positive layout ID are included.
	 *
	 * @since ??
	 *
	 * @param array $layouts Theme Builder layouts array.
	 *
	 * @return array<string, array{id: int, content: string}>
	 */
	public static function get_layout_contents_from_tb_layouts( array $layouts ): array {
		$entries           = [];
		$layout_post_types = self::_get_tb_layout_post_types();

		foreach ( $layout_post_types as $layout_post_type ) {
			$layout_entry = $layouts[ $layout_post_type ] ?? null;

			if ( ! is_array( $layout_entry ) ) {
				continue;
			}

			$layout_id = (int) ( $layout_entry['id'] ?? 0 );
			$override  = ! empty( $layout_entry['override'] );

			if ( 0 >= $layout_id || ! $override ) {
				continue;
			}

			$content = get_post_field( 'post_content', $layout_id, 'raw' );

			if ( ! is_string( $content ) || '' === $content ) {
				continue;
			}

			$meta_key = self::_meta_key_for_layout_post_type( $layout_post_type );

			if ( '' === $meta_key ) {
				continue;
			}

			$entries[ $meta_key ] = [
				'id'      => $layout_id,
				'content' => $content,
			];
		}

		return $entries;
	}

	/**
	 * Deduplicate and persist an ordered set of layout content entries.
	 *
	 * @since ??
	 *
	 * @param array<string, array{id: int, content: string}> $layout_entries Layout entries.
	 *
	 * @return bool True when any layout content was updated.
	 */
	public static function deduplicate_layout_entries( array $layout_entries ): bool {
		if ( 2 > count( $layout_entries ) ) {
			return false;
		}

		$contents = [];

		foreach ( $layout_entries as $meta_key => $entry ) {
			$contents[ $meta_key ] = $entry['content'];
		}

		$deduped = self::deduplicate_loop_ids_across_contents( $contents );

		return self::persist_deduplicated_layout_content_changes( $layout_entries, $deduped );
	}

	/**
	 * Load sibling layout contents for a template in header → body → footer order.
	 *
	 * @since ??
	 *
	 * @param int $template_id et_template post ID.
	 *
	 * @return array<string, array{id: int, content: string}>
	 */
	public static function get_template_layout_contents_for_dedup( int $template_id ): array {
		return self::_get_template_layout_contents( $template_id );
	}

	/**
	 * Load sibling layout contents for a template in header → body → footer order.
	 *
	 * @since ??
	 *
	 * @param int $template_id et_template post ID.
	 *
	 * @return array<string, array{id: int, content: string}>
	 */
	private static function _get_template_layout_contents( int $template_id ): array {
		$entries = [];

		foreach ( self::TEMPLATE_LAYOUT_META_KEYS as $meta_key ) {
			$layout_id = (int) get_post_meta( $template_id, $meta_key, true );

			if ( $layout_id <= 0 ) {
				continue;
			}

			$content = get_post_field( 'post_content', $layout_id, 'raw' );

			if ( ! is_string( $content ) || '' === $content ) {
				continue;
			}

			$entries[ $meta_key ] = [
				'id'      => $layout_id,
				'content' => $content,
			];
		}

		return $entries;
	}

	/**
	 * Theme Builder layout post types in header → body → footer order.
	 *
	 * @since ??
	 *
	 * @return string[]
	 */
	private static function _get_tb_layout_post_types(): array {
		if ( ! defined( 'ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE' ) || ! defined( 'ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE' ) || ! defined( 'ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE' ) ) {
			return [];
		}

		return [
			ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE,
			ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE,
			ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE,
		];
	}

	/**
	 * Map a Theme Builder layout post type to its et_template meta key.
	 *
	 * @since ??
	 *
	 * @param string $layout_post_type Layout post type.
	 *
	 * @return string Meta key, or empty string when unknown.
	 */
	private static function _meta_key_for_layout_post_type( string $layout_post_type ): string {
		$map = [];

		if ( defined( 'ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE' ) ) {
			$map[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ] = '_et_header_layout_id';
		}

		if ( defined( 'ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE' ) ) {
			$map[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ] = '_et_body_layout_id';
		}

		if ( defined( 'ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE' ) ) {
			$map[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ] = '_et_footer_layout_id';
		}

		return $map[ $layout_post_type ] ?? '';
	}

	/**
	 * Walk parsed blocks and assign per-layout loop ID remaps for collisions.
	 *
	 * Only remaps IDs that already appeared in a previous layout. Within-layout
	 * duplicates of a newly introduced ID are left unchanged so save-time dedup
	 * does not rewrite layouts that have no cross-layout collision.
	 *
	 * @since ??
	 *
	 * @param array                 $blocks              Parsed blocks.
	 * @param array<string, true>   $seen_loop_ids       Globally seen loop IDs (by reference).
	 * @param array<string, true>   $seen_before_layout  Loop IDs seen before the current layout.
	 * @param array<string, string> $remapped_in_layout  Old-to-new loop ID map for the current layout (by reference).
	 *
	 * @return void
	 */
	private static function _collect_layout_loop_id_remaps( array $blocks, array &$seen_loop_ids, array $seen_before_layout, array &$remapped_in_layout ): void {
		foreach ( $blocks as $block ) {
			if ( empty( $block ) || ! is_array( $block ) ) {
				continue;
			}

			$attrs = $block['attrs'] ?? [];

			if ( self::_is_loop_enabled_block( $attrs ) ) {
				$current_id = $attrs['module']['advanced']['loop']['desktop']['value']['loopId'] ?? '';

				if ( is_string( $current_id ) && '' !== $current_id ) {
					if ( isset( $seen_before_layout[ $current_id ] ) ) {
						if ( ! isset( $remapped_in_layout[ $current_id ] ) ) {
							$new_id = self::generate_loop_id();

							while ( isset( $seen_loop_ids[ $new_id ] ) ) {
								$new_id = self::generate_loop_id();
							}

							$remapped_in_layout[ $current_id ] = $new_id;
							$seen_loop_ids[ $new_id ]          = true;
						}
					} else {
						$seen_loop_ids[ $current_id ] = true;
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::_collect_layout_loop_id_remaps( $block['innerBlocks'], $seen_loop_ids, $seen_before_layout, $remapped_in_layout );
			}
		}
	}

	/**
	 * Patch loopId and targetLoop values in layout content without re-serializing blocks.
	 *
	 * Re-serializing Divi blocks can corrupt dynamic-content attrs, so only targeted
	 * string replacements are performed for remapped IDs in this layout.
	 *
	 * @since ??
	 *
	 * @param string                $content          Layout Gutenberg content.
	 * @param array<string, string> $remapped_in_tree Old-to-new loop ID map for this layout.
	 *
	 * @return string Patched layout content.
	 */
	private static function _patch_loop_ids_in_content( string $content, array $remapped_in_tree ): string {
		foreach ( $remapped_in_tree as $old_id => $new_id ) {
			$content = str_replace(
				'"loopId":"' . $old_id . '"',
				'"loopId":"' . $new_id . '"',
				$content
			);

			$content = str_replace(
				'"targetLoop":{"desktop":{"value":"' . $old_id . '"}}',
				'"targetLoop":{"desktop":{"value":"' . $new_id . '"}}',
				$content
			);
		}

		return $content;
	}

	/**
	 * Whether block attrs indicate loop is enabled.
	 *
	 * @since ??
	 *
	 * @param array $attrs Block attrs.
	 *
	 * @return bool
	 */
	private static function _is_loop_enabled_block( array $attrs ): bool {
		$enable = $attrs['module']['advanced']['loop']['desktop']['value']['enable'] ?? 'off';

		return 'on' === $enable;
	}
}
