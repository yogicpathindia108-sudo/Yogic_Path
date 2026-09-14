<?php
/**
 * Class that handles conversion for Divi 5 Readiness.
 *
 * @since ??
 *
 * @package D5_Readiness
 */

namespace Divi\D5_Readiness\Server;

use ET\Builder\Packages\Conversion\Conversion as D5BuilderConversion;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Packages\GlobalData\Utils\PresetContentUtils;
use Divi\D5_Readiness\Helpers;
use ET_Core_PageResource;
use ET\Builder\VisualBuilder\Saving\SavingUtility;

/**
 * Class that handles conversion for Divi 5 Readiness.
 *
 * @since ??
 *
 * @package D5_Readiness
 */
class Conversion {
	/**
	 * For a D4 formatted post, converts to D5 format. For a D5 formatted post, checks for newly
	 * convertible modules within D5 shortcode-module and converts them to D5 format.
	 *
	 * @param string $post_id The post ID.
	 *
	 * @return array
	 */
	public static function convert_single_post( $post_id ) {
		// Get current post content (may be D4 or D5 format).
		$post         = get_post( $post_id );
		$post_content = $post->post_content;
		$is_d5_post   = get_post_meta( $post_id, '_et_pb_use_divi_5', true ) === 'on';

		$converted_content = D5BuilderConversion::maybeConvertContent( $post_content, true, (int) $post_id );

		if ( ! $is_d5_post ) {
			$converted_content = "<!-- wp:divi/placeholder -->{$converted_content}<!-- /wp:divi/placeholder -->";
		}

		$legacy_import_result = GlobalPreset::get_last_legacy_preset_import_result();
		$default_imported     = $legacy_import_result['defaultImportedModulePresetIds'] ?? [];
		if ( ! empty( $default_imported ) ) {
			$converted_content = PresetContentUtils::apply_default_imported_presets_to_content(
				$converted_content,
				$default_imported
			);
		}

		// Apply D5-to-D5 migrations (NestedModuleMigration, AttributeMigration, etc.).
		// This is critical for migrating attributes to their correct locations,
		// e.g., flexType from module.advanced.flexType to module.decoration.sizing.flexType.
		$converted_content = apply_filters( 'divi_framework_portability_import_migrated_post_content', $converted_content );

		$normalized_custom_css = null;
		if ( ! $is_d5_post ) {
			$normalized_conversion = self::maybe_normalize_legacy_search_page_custom_css( $post_id, $converted_content );
			$converted_content     = $normalized_conversion['postContent'];
			$normalized_custom_css = $normalized_conversion['pageCustomCss'];
		}

		$d5_content = $converted_content;

		$conversion_status = [
			'status'          => 'success',
			'error'           => '',
			'builder_version' => ET_BUILDER_VERSION,
		];

		// Serialize the remaining data.
		$serialized_conversion_status = wp_json_encode( $conversion_status );

		// Update post.
		$update = wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => wp_slash( $d5_content ),
				'post_status'  => $post->post_status,
			]
		);

		if ( $update ) {
			// Get saved post, verify its content against the one that is being sent.
			$saved_post             = get_post( $update );
			$saved_post_content     = $saved_post->post_content;
			$converted_post_content = stripslashes( $d5_content );

			// If `post_content` column on wp_posts table doesn't use `utf8mb4` charset, the saved post
			// content's emoji will be encoded which means the check of saved post_content vs
			// builder's post_content will be false; Thus check the charset of `post_content` column
			// first then encode the builder's post_content if needed
			// @see https://make.wordpress.org/core/2015/04/02/omg-emoji-%f0%9f%98%8e/
			// @see https://make.wordpress.org/core/2015/04/02/the-utf8mb4-upgrade/.
			global $wpdb;

			if ( 'utf8' === $wpdb->get_col_charset( $wpdb->posts, 'post_content' ) ) {
				$converted_post_content = wp_encode_emoji( $converted_post_content );
			}

			$saved_verification = $saved_post_content === $converted_post_content;

			/**
			 * Hook triggered when the Post is updated.
			 *
			 * @param int $post_id Post ID.
			 *
			 * @since 3.29
			 */
			do_action( 'et_update_post', $post_id );

			update_post_meta( $post_id, '_et_pb_divi_5_conversion_status', $serialized_conversion_status );
			update_post_meta( $post_id, '_et_pb_use_divi_5', 'on' );

			// Handle meta updates based on conversion type.
			if ( $is_d5_post ) {
				delete_post_meta( $post_id, '_et_pb_has_newly_convertible_modules' );
			} else {
				// Store original D4 content for first-time conversions.
				update_post_meta( $post_id, '_et_pb_divi_4_content', $post_content );

				if ( is_string( $normalized_custom_css ) ) {
					if ( '' === trim( $normalized_custom_css ) ) {
						delete_post_meta( $post_id, '_et_pb_custom_css' );
					} else {
						update_post_meta( $post_id, '_et_pb_custom_css', wp_slash( $normalized_custom_css ) );
					}
				}
			}

			// Clear the modules conversation cache so the UI refreshes.
			Helpers\clear_modules_conversation_cache();

			// Remove all static resources (CSS/JS) to force regeneration with Divi 5 assets.
			// This ensures the frontend displays correctly after conversion from Divi 4 to Divi 5.
			ET_Core_PageResource::remove_static_resources( $post_id, 'all' );

			// Clear dynamic assets cached metadata to prevent stale feature detection.
			// After migration, cached metadata may have empty preset tracking arrays,
			// causing features like scroll effects to not be detected on first frontend load.
			delete_post_meta( $post_id, '_divi_dynamic_assets_cached_modules' );
			delete_post_meta( $post_id, '_divi_dynamic_assets_cached_feature_used' );

			// Prime the page cache to pre-generate and cache the converted content.
			// This improves frontend performance by having the converted page ready to serve.
			SavingUtility::prime_page_cache_on_save( $post_id );

			return [
				'postId'           => $post_id,
				'postTitle'        => $post->post_title,
				'postType'         => $post->post_type,
				'postStatus'       => get_post_status( $update ),
				'postUrl'          => get_permalink( $post_id ),
				'saveVerification' => apply_filters( 'et_fb_ajax_save_verification_result', $saved_verification ),
				'status'           => 'success',
			];
		} else {
			return [
				'postId'     => $post_id,
				'postTitle'  => $post->post_title,
				'postType'   => $post->post_type,
				'postStatus' => get_post_status( $update ),
				'postUrl'    => get_permalink( $post_id ),
				'status'     => 'error',
			];
		}
	}

	/**
	 * Move legacy Search page-level custom CSS into the matching converted Search block.
	 *
	 * Only simple top-level rules with a standalone `.et_pb_search_<n>` token are normalized here.
	 * This keeps the fix scoped to the known legacy Search portability gap without rewriting generic
	 * page-level custom CSS.
	 *
	 * @since ??
	 *
	 * @param int    $post_id      The post ID.
	 * @param string $post_content Converted D5 post content.
	 *
	 * @return array{postContent:string,pageCustomCss:?string}
	 */
	private static function maybe_normalize_legacy_search_page_custom_css( int $post_id, string $post_content ): array {
		$page_custom_css = get_post_meta( $post_id, '_et_pb_custom_css', true );

		if ( ! is_string( $page_custom_css ) || '' === trim( $page_custom_css ) || false === strpos( $page_custom_css, '.et_pb_search_' ) ) {
			return [
				'postContent'   => $post_content,
				'pageCustomCss' => is_string( $page_custom_css ) ? $page_custom_css : null,
			];
		}

		$legacy_rules_by_index = self::get_legacy_search_page_custom_css_rules_by_index( $page_custom_css );
		if ( empty( $legacy_rules_by_index ) ) {
			return [
				'postContent'   => $post_content,
				'pageCustomCss' => $page_custom_css,
			];
		}

		$blocks           = parse_blocks( $post_content );
		$search_index     = -1;
		$moved_rule_spans = [];
		$updated_blocks   = self::move_legacy_search_page_custom_css_to_blocks( $blocks, $legacy_rules_by_index, $search_index, $moved_rule_spans );

		if ( empty( $moved_rule_spans ) ) {
			return [
				'postContent'   => $post_content,
				'pageCustomCss' => $page_custom_css,
			];
		}

		return [
			'postContent'   => serialize_blocks( $updated_blocks ),
			'pageCustomCss' => self::remove_legacy_search_page_custom_css_rules( $page_custom_css, $moved_rule_spans ),
		];
	}

	/**
	 * Collect simple top-level Search page-level CSS rules keyed by their legacy Search index.
	 *
	 * @since ??
	 *
	 * @param string $page_custom_css Page-level custom CSS.
	 *
	 * @return array<int, array{css:array<int,string>,spans:array<int,array{offset:int,length:int}>}>
	 */
	private static function get_legacy_search_page_custom_css_rules_by_index( string $page_custom_css ): array {
		$rules_by_index = [];
		$matches        = [];

		// Regex test: https://regex101.com/r/5rx166/1.
		$pattern = '/(?P<rule>(?P<selectors>[^{}]+)\{(?P<body>[^{}]*)\})/m';

		preg_match_all( $pattern, $page_custom_css, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

		foreach ( $matches as $match ) {
			$rule       = $match['rule'][0];
			$offset     = $match['rule'][1];
			$selectors  = trim( $match['selectors'][0] );
			$body       = $match['body'][0];
			$rule_index = [];

			if ( '' === $selectors || '@' === $selectors[0] ) {
				continue;
			}

			if ( 0 !== self::get_css_nesting_depth( $page_custom_css, $offset ) ) {
				continue;
			}

			if ( false === strpos( $selectors, '.et_pb_search_' ) ) {
				continue;
			}

			// Regex test: https://regex101.com/r/rd3ghF/1.
			preg_match_all(
				'/(?<![A-Za-z0-9_-])\.et_pb_search_(\d+)(?![A-Za-z0-9_-])/',
				$selectors,
				$rule_index
			);

			$matching_indexes = array_values( array_unique( $rule_index[1] ?? [] ) );

			if ( 1 !== count( $matching_indexes ) || '' === trim( $body ) ) {
				continue;
			}

			$search_index    = (int) $matching_indexes[0];
			$normalized_rule = D5BuilderConversion::valueSanitization( trim( $rule ), 'custom_css_free_form', 'divi/search' );

			if ( '' === trim( $normalized_rule ) ) {
				continue;
			}

			if ( ! isset( $rules_by_index[ $search_index ] ) ) {
				$rules_by_index[ $search_index ] = [
					'css'   => [],
					'spans' => [],
				];
			}

			$rules_by_index[ $search_index ]['css'][]   = $normalized_rule;
			$rules_by_index[ $search_index ]['spans'][] = [
				'offset' => $offset,
				'length' => strlen( $rule ),
			];
		}

		return $rules_by_index;
	}

	/**
	 * Count top-level brace depth before the given CSS offset.
	 *
	 * @since ??
	 *
	 * @param string $css    CSS to inspect.
	 * @param int    $offset Offset to inspect up to.
	 *
	 * @return int
	 */
	private static function get_css_nesting_depth( string $css, int $offset ): int {
		$prefix = substr( $css, 0, $offset );

		return substr_count( $prefix, '{' ) - substr_count( $prefix, '}' );
	}

	/**
	 * Move indexed Search rules into the matching Search block free-form CSS field.
	 *
	 * @since ??
	 *
	 * @param array $blocks                Parsed block tree.
	 * @param array $legacy_rules_by_index Indexed Search CSS rules.
	 * @param int   $search_index          Running Search module index.
	 * @param array $moved_rule_spans      Collected spans that were moved.
	 *
	 * @return array
	 */
	private static function move_legacy_search_page_custom_css_to_blocks( array $blocks, array $legacy_rules_by_index, int &$search_index, array &$moved_rule_spans ): array {
		foreach ( $blocks as &$block ) {
			if ( 'divi/search' === ( $block['blockName'] ?? null ) ) {
				++$search_index;

				if ( isset( $legacy_rules_by_index[ $search_index ] ) ) {
					$existing_free_form_css = $block['attrs']['css']['desktop']['value']['freeForm'] ?? '';
					$legacy_free_form_css   = implode( "\n\n", $legacy_rules_by_index[ $search_index ]['css'] );
					$combined_free_form_css = trim( implode( "\n\n", array_filter( [ $existing_free_form_css, $legacy_free_form_css ] ) ) );

					$block['attrs']['css']['desktop']['value']['freeForm'] = $combined_free_form_css;
					$moved_rule_spans                                       = array_merge( $moved_rule_spans, $legacy_rules_by_index[ $search_index ]['spans'] );
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::move_legacy_search_page_custom_css_to_blocks( $block['innerBlocks'], $legacy_rules_by_index, $search_index, $moved_rule_spans );
			}
		}
		unset( $block );

		return $blocks;
	}

	/**
	 * Remove CSS rules that were moved into Search block free-form CSS.
	 *
	 * @since ??
	 *
	 * @param string $page_custom_css Page-level custom CSS.
	 * @param array  $moved_rule_spans Moved CSS spans.
	 *
	 * @return string
	 */
	private static function remove_legacy_search_page_custom_css_rules( string $page_custom_css, array $moved_rule_spans ): string {
		usort(
			$moved_rule_spans,
			static function ( array $left, array $right ): int {
				return $right['offset'] <=> $left['offset'];
			}
		);

		foreach ( $moved_rule_spans as $span ) {
			$page_custom_css = substr_replace( $page_custom_css, '', $span['offset'], $span['length'] );
		}

		// Regex test: https://regex101.com/r/RdhWFG/1.
		$page_custom_css = preg_replace( "/(\r?\n[ \t]*){3,}/", "\n\n", $page_custom_css );

		return is_string( $page_custom_css ) ? trim( $page_custom_css ) : '';
	}


	/**
	 * Get the meta query for posts for which conversion failed or was only partially successful.
	 *
	 * @since ??
	 *
	 * @param string $post_type The post type.
	 * @param bool   $use_meta  Whether to use meta.
	 *
	 * @return array
	 */
	private static function _get_conversion_failed_meta_query( $post_type, $use_meta ) {
		$additional = [];

		$meta_query = [];

		// Get only posts for which conversion is partial or failed.
		if ( $use_meta ) {
			$meta_query[] = [
				[
					'relation' => 'AND',
					[
						'key'     => '_et_pb_use_builder',
						'value'   => 'on',
						'compare' => '=',
					],
					[
						'relation' => 'OR',
						[
							'key'     => '_et_pb_use_divi_5',
							'value'   => 'partial',
							'compare' => '=',
						],
						[
							'key'     => '_et_pb_use_divi_5',
							'value'   => 'off',
							'compare' => '=',
						],
					],
				],
			];
		}

		// Add additional condition if it exists.
		if ( ! empty( $additional ) ) {
			$meta_query[] = $additional;
		}

		return $meta_query;
	}


	/**
	 * Get posts for which conversion failed or was partially successful.
	 *
	 * @since ??
	 *
	 * @param string $post_type The post type.
	 * @param bool   $use_meta  Whether to use meta.
	 *
	 * @return array
	 */
	public static function get_posts_conversion_failed( $post_type, $use_meta ) {
		global $wpdb;

		// Add temporary filter to select only ID and post_title.
		$fields_filter = function ( $_fields ) use ( $wpdb ) {
			return "{$wpdb->posts}.ID, {$wpdb->posts}.post_title";
		};
		add_filter( 'posts_fields', $fields_filter );

		try {
			$args = [
				'post_type'      => $post_type,
				'posts_per_page' => 100,
				'post_status'    => 'publish',
				'meta_query'     => self::_get_conversion_failed_meta_query( $post_type, $use_meta ),
			];

			$query = new \WP_Query( $args );

			$index   = 0;
			$results = [];
			foreach ( $query->posts as $post ) {
				$results[ $index ] = [
					'ID'         => $post->ID,
					'post_title' => $post->post_title,
				];

				if ( 'et_template' === $post_type ) {
					$results[ $index ]['meta'] = [
						'_et_theme_builder_marked_as_unused' => get_post_meta( $post->ID, '_et_theme_builder_marked_as_unused', true ),
					];
				}

				++$index;
			}

			return $query->have_posts() ? $results : [];
		} finally {
			// Always remove filter, even if an exception occurs.
			remove_filter( 'posts_fields', $fields_filter );
		}
	}

	/**
	 * Get the meta query for posts that have not been converted.
	 *
	 * @param string $post_type The post type.
	 * @param bool   $use_meta  Whether to use meta.
	 *
	 * @return array
	 * @since ??
	 */
	public static function _get_pending_conversion_meta_query( $post_type, $use_meta ) {
		// Initialize an empty meta query array.
		$meta_query = [];

		if ( $use_meta ) {
			// If using meta, add an 'AND' relation and both conditions.
			$meta_query = [
				'relation' => 'AND',
				[
					'key'     => '_et_pb_use_builder',
					'value'   => 'on',
					'compare' => '=',
				],
				[
					'relation' => 'OR',
					[
						'key'     => '_et_pb_use_divi_5',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => '_et_pb_has_newly_convertible_modules',
						'compare' => 'EXISTS',
					],
				],
			];

			if ( in_array( $post_type, [ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE, ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE, ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ], true ) ) {
				$meta_query[] = [
					'key'     => '_et_theme_builder_marked_as_unused',
					'compare' => 'NOT EXISTS',
				];
			}
		} elseif ( in_array( $post_type, [ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE, ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE, ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ], true ) ) {
				$meta_query[] = [
					'relation' => 'AND',
					[
						'key'     => '_et_theme_builder_marked_as_unused',
						'compare' => 'NOT EXISTS',
					],
					[
						'relation' => 'OR',
						[
							'key'     => '_et_pb_use_divi_5',
							'compare' => 'NOT EXISTS',
						],
						[
							'key'     => '_et_pb_has_newly_convertible_modules',
							'compare' => 'EXISTS',
						],
					],
				];
		} else {
			// If not using meta, only add the _et_pb_use_divi_5 condition.
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => '_et_pb_use_divi_5',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_et_pb_has_newly_convertible_modules',
					'compare' => 'EXISTS',
				],
			];
		}

		return $meta_query;
	}

	/**
	 * Get posts to convert.
	 *
	 * @since ??
	 *
	 * @param string  $post_type The post type.
	 * @param boolean $use_meta Default FALSE.
	 *
	 * @return array
	 */
	public static function get_posts_pending_conversion( $post_type, $use_meta = false ) {
		$args = [
			'post_type'      => $post_type,
			'posts_per_page' => 100,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'meta_query'     => self::_get_pending_conversion_meta_query( $post_type, $use_meta ),
		];

		$query = new \WP_Query( $args );

		return $query->have_posts() ? $query->posts : [];
	}
}
