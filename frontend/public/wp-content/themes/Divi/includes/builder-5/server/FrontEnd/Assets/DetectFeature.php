<?php
/**
 * Feature Detection class.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets;

use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\ModuleLibrary\SocialMediaFollowItem\SocialMediaFollowItemModule;
use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\FrontEnd\BlockParser\SimpleBlockParser;
use ET\Builder\FrontEnd\BlockParser\SimpleBlock;
use ET\Builder\Packages\GlobalData\GlobalPreset;

/**
 * Detects Feature based on content.
 *
 * @since ??
 */
class DetectFeature {

	/**
	 * Retrieves the block names from the given content.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for block names.
	 *
	 * @return array The array of block names.
	 */
	public static function get_block_names( string $content ): array {
		// Perform a quick check to see if "wp:" is in the content at all.
		if ( empty( $content ) || ! str_contains( $content, 'wp:' ) ) {
			// Bail early if no relevant blocks or shortcodes are found.
			return [];
		}

		/*
		 * This pattern is used to detect block names in the content.
		 * test regex: https://regex101.com/r/tvl4FK/1
		 */
		static $pattern = '/<!--\s+wp:([^<>\s]+)\s+/';

		// Perform regex search for block names in Gutenberg content.
		preg_match_all( $pattern, $content, $matches );

		$verified_blocks = DynamicAssetsUtils::get_divi_block_names();
		$blocks          = array_unique( $matches[1] );

		// Return unique block names against verified block names.
		return array_values( array_intersect( $verified_blocks, $blocks ) );
	}

	/**
	 * Retrieves the shortcode names from the given content.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for shortcode names.
	 *
	 * @return array The array of shortcode names.
	 */
	public static function get_shortcode_names( string $content ): array {
		// Perform a quick check to see "[" (for shortcodes) is in the content at all.
		if ( empty( $content ) || ! str_contains( $content, '[' ) ) {
			return [];
		}

		/*
		 * This pattern is used to detect shortcode in the content.
		 * test regex: https://regex101.com/r/b6kgSm/1
		 */
		static $pattern = '@\[([^<>&/\[\]\x00-\x20=]++)@';

		// Perform regex search for shortcodes in the content.
		preg_match_all( $pattern, $content, $matches );

		$verified_shortcodes = apply_filters( 'divi_frontend_assets_shortcode_whitelist', DynamicAssetsUtils::get_divi_shortcode_slugs() );
		$shortcodes          = array_unique( $matches[1] );

		// Return unique shortcode names against verified shortcode names.
		return array_values( array_intersect( $verified_shortcodes, $shortcodes ) );
	}

	/**
	 * Parse block content once per unique content string.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to parse.
	 *
	 * @return array Parsed block results.
	 */
	private static function _get_cached_parsed_blocks( string $content ): array {
		static $cache = [];

		$cache_key = md5( $content );
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$cache[ $cache_key ] = SimpleBlockParser::parse( $content )->results();

		return $cache[ $cache_key ];
	}

	/**
	 * Extract unique global IDs by prefix from raw content.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to scan.
	 * @param string $prefix  Global id prefix, e.g. `gcid-` or `gvid-`.
	 *
	 * @return array Unique matching IDs.
	 */
	private static function _get_global_ids_by_prefix( string $content, string $prefix ): array {
		static $cache = [];

		$cache_key = md5( $prefix . '|' . $content );
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		// Perform a quick check before regex.
		if ( empty( $content ) || ! str_contains( $content, $prefix ) ) {
			$cache[ $cache_key ] = [];
			return [];
		}

		/*
		 * The pattern to search for global ids in the content.
		 * regex test: https://regex101.com/r/Yba3oz/1.
		 */
		$pattern = '(' . preg_quote( $prefix, '~' ) . '[0-9a-z-]*)';

		// Perform regex search.
		preg_match_all( "~$pattern~", $content, $matches );

		$matched_ids         = $matches[1] ?? [];
		$cache[ $cache_key ] = ! empty( $matched_ids ) ? array_values( array_unique( $matched_ids ) ) : [];

		return $cache[ $cache_key ];
	}

	/**
	 * Build preset attrs payload used for global id extraction.
	 *
	 * @since ??
	 *
	 * @param string $content Content to inspect.
	 *
	 * @return array Payload containing module and group attrs.
	 */
	private static function _get_used_preset_attrs_payload( string $content ): array {
		static $cache = [];

		$cache_key = md5( $content );
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		// Preset extraction applies to Divi block content only.
		if ( empty( $content ) || ! str_contains( $content, 'wp:divi/' ) ) {
			$cache[ $cache_key ] = [];
			return [];
		}

		$module_preset_ids = self::get_block_preset_ids( $content );
		$group_preset_ids  = self::get_group_preset_ids( $content );

		$module_attrs = DynamicAssetsUtils::get_module_preset_attributes( $module_preset_ids );
		$group_attrs  = DynamicAssetsUtils::get_group_preset_attributes( $group_preset_ids );

		if ( empty( $module_attrs ) && empty( $group_attrs ) ) {
			$cache[ $cache_key ] = [];
			return [];
		}

		$cache[ $cache_key ] = [
			'module_attrs' => $module_attrs,
			'group_attrs'  => $group_attrs,
		];

		return $cache[ $cache_key ];
	}

	/**
	 * Retrieves the block names and preset IDs from a given content in Gutenberg format.
	 *
	 * D4 attribute names: `_module_preset`.
	 * D5 attribute paths: `modulePreset`.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for block names and preset IDs.
	 * @param array  $preset_data The preset data.
	 *
	 * @return array {
	 *     An array of block names and preset IDs.
	 *
	 *     @type string $block_name The name of the block.
	 *     @type string $preset_id  The ID of the preset.
	 * }
	 */
	public static function get_block_preset_ids( string $content, ?array $preset_data = null ): array {
		// Perform a quick check wether content is empty or not contains 'wp:'.
		if ( empty( $content ) || ! str_contains( $content, 'wp:' ) ) {
			return [];
		}

		// Cache results per content to avoid re-parsing on multiple calls.
		static $cache = [];
		$cache_key    = $preset_data ? md5( $content . wp_json_encode( $preset_data ) ) : md5( $content );
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$results = [];
		$parseds = self::_get_cached_parsed_blocks( $content );

		foreach ( $parseds as $block ) {
			if ( $block->error() ) {
				continue;
			}

			// Get module preset value (could be string or array for stacked presets).
			$module_preset_value = $block->attrs()['modulePreset'] ?? '';

			// Normalize to array to handle stacked presets.
			$preset_ids = GlobalPreset::normalize_preset_stack( $module_preset_value );

			// If no preset IDs after normalization (empty, null, 'default', '_initial'), still process the block to get its default preset.
			if ( empty( $preset_ids ) ) {
				$selected_preset = GlobalPreset::get_selected_preset(
					[
						'moduleAttrs' => $block->attrs(),
						'moduleName'  => $block->name(),
						'allData'     => $preset_data,
					]
				);

				if ( $selected_preset->has_data_attrs() ) {
					$results[] = [
						'block_name' => $selected_preset->get_data_module_name(),
						'preset_id'  => $selected_preset->get_data_id(),
					];
				}
			} else {
				// Process each preset in the stack.
				foreach ( $preset_ids as $preset_id ) {
					$selected_preset = GlobalPreset::get_selected_preset(
						[
							'moduleAttrs' => [ 'modulePreset' => [ $preset_id ] ],
							'moduleName'  => $block->name(),
							'allData'     => $preset_data,
						]
					);

					if ( ! $selected_preset->has_data_attrs() ) {
						continue;
					}

					$results[] = [
						'block_name' => $selected_preset->get_data_module_name(),
						'preset_id'  => $selected_preset->get_data_id(),
					];
				}
			}
		}

		$cache[ $cache_key ] = $results;
		return $results;
	}

	/**
	 * Retrieves group preset IDs from content containing groupPreset attributes.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for group presets.
	 * @param array  $preset_data The preset data.
	 *
	 * @return array {
	 *     An array of group preset information.
	 *
	 *     @type string $preset_id  The ID of the preset.
	 *     @type string $group_name The name of the group.
	 * }
	 */
	public static function get_group_preset_ids( string $content, ?array $preset_data = null ): array {
		// Perform a quick check wether content is empty or not contains 'wp:'.
		if ( empty( $content ) || ! str_contains( $content, 'wp:' ) ) {
			return [];
		}

		// Cache results per content to avoid re-parsing on multiple calls.
		static $cache = [];
		$cache_key    = $preset_data ? md5( $content . wp_json_encode( $preset_data ) ) : md5( $content );
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$results = [];

		$parseds = self::_get_cached_parsed_blocks( $content );

		foreach ( $parseds as $block ) {
			if ( $block->error() ) {
				continue;
			}

			$selected_presets = GlobalPreset::get_selected_group_presets(
				[
					'moduleAttrs' => $block->attrs(),
					'moduleName'  => $block->name(),
					'allData'     => $preset_data,
				]
			);

			foreach ( $selected_presets as $selected_preset ) {
				if ( ! $selected_preset->has_data_attrs() ) {
					continue;
				}

				$results[] = [
					'preset_id'  => $selected_preset->get_data_id(),
					'group_name' => $selected_preset->get_data_group_name(),
					'type'       => 'group',
				];
			}
		}

		$cache[ $cache_key ] = $results;
		return $results;
	}

	/**
	 * Retrieves the shortcode names and preset IDs from a given content in shortcode format.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for shortcode names and preset IDs.
	 *
	 * @return array {
	 *     An array of shortcode names and preset IDs.
	 *
	 *     @type string $shortcode_name The name of the shortcode.
	 *     @type string $preset_id  The ID of the preset.
	 * }
	 */
	public static function get_shortcode_preset_ids( string $content ): array {
		// Perform a quick check to see if "_module_preset=" is in the content at all.
		if ( empty( $content ) || ! str_contains( $content, '_module_preset=' ) ) {
			return [];
		}

		/*
		 * This pattern is used to detect shortcode name and preset ID in the content.
		 * test regex: https://regex101.com/r/evHAVd/1
		 */
		static $pattern = '/\[(?P<shortcode_name>[^\s\]]+)[^\[\]]*_module_preset="(?P<preset_id>[^"]+)"[^\[\]]*/';

		// Perform regex search.
		preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER );

		// Initialize the array to store results.
		$results = [];

		// Process each match to extract shortcode names and preset IDs.
		foreach ( $matches as $match ) {
			$shortcode_name = $match['shortcode_name'];

			// Check if it's a section and handle special cases for 'fullwidth' and 'specialty'.
			if ( 'et_pb_section' === $shortcode_name ) {
				if ( str_contains( $match[0], 'fullwidth="on"' ) ) {
					$shortcode_name = 'et_pb_fullwidth_section';
				} elseif ( str_contains( $match[0], 'specialty="on"' ) ) {
					$shortcode_name = 'et_pb_specialty_section';
				}
			}

			// Add the match to results if a preset ID is found.
			if ( isset( $match['preset_id'] ) ) {
				$results[] = [
					'shortcode_name' => $shortcode_name,
					'preset_id'      => $match['preset_id'],
				];
			}
		}

		return $results;
	}

	/**
	 * Retrieves the global color IDs from a given content in Gutenberg/Shortcode format.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search.
	 *
	 * @return array The array of global color IDs (empty if none found).
	 */
	public static function get_global_color_ids( string $content ): array {
		return self::_get_global_ids_by_prefix( $content, 'gcid-' );
	}

	/**
	 * Retrieves the global variable IDs from a given content in Gutenberg/Shortcode format.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search.
	 *
	 * @return array The array of global variable IDs (empty if none found).
	 */
	public static function get_global_variable_ids( string $content ): array {
		return self::_get_global_ids_by_prefix( $content, 'gvid-' );
	}

	/**
	 * Retrieves the global module Post IDs from a given content in Gutenberg/Shortcode format.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search.
	 *
	 * @return array The array of global module Post IDs (empty if none found).
	 */
	public static function get_global_module_ids( string $content ): array {
		// Perform a quick check to see if "globalModule" or "global_module=" is in the content at all.
		if ( empty( $content ) || ( ! str_contains( $content, 'globalModule' ) && ! str_contains( $content, 'global_module=' ) ) ) {
			return [];
		}

		/*
		 * The pattern to search for global modules in Gutenberg content.
		 * A global module is indicated by the "globalModule" attribute with a PostID value.
		 * regex test: https://regex101.com/r/nxsrTU/1
		 */
		static $pattern_gutenberg = '"globalModule":\s*"(\d+)"';

		/*
		 * The pattern to search for global modules in Shortcode content.
		 * A global module is indicated by the "global_module" attribute with a PostID value.
		 * regex test: https://regex101.com/r/wC4AN0/1
		 */
		static $pattern_shortcode = 'global_module="(\d+)"';

		// Combine both patterns as it's too early to determine whether we have shortcode or not.
		// Perform regex search.
		preg_match_all( "~$pattern_gutenberg|$pattern_shortcode~", $content, $matches );

		// Merge matches from both capture groups if both patterns were used.
		$module_ids = array_filter(
			array_merge( $matches[1], $matches[2] ?? [] )
		);

		// Return unique module IDs and reset array keys.
		return ! empty( $module_ids ) ? array_values( array_unique( $module_ids ) ) : [];
	}

	/**
	 * Retrieves referenced Divi Library module IDs from Gutenberg/Shortcode content.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for library references.
	 *
	 * @return array The referenced library module IDs.
	 */
	public static function get_library_module_ids( string $content ): array {
		// Bail early when there are no library reference markers.
		if ( empty( $content ) || ( ! str_contains( $content, '"library"' ) && ! str_contains( $content, 'divi_library=' ) ) ) {
			return [];
		}

		/*
		 * Pattern to capture D5 block-based library references.
		 * This intentionally allows flexible spacing and key order changes around nested objects.
		 * regex test: https://regex101.com/r/N8xa1B/1
		 */
		static $pattern_divi5 = '"library"\s*:\s*{.*?"desktop"\s*:\s*{.*?"value"\s*:\s*"(\d+)"';

		/*
		 * Pattern to capture D4 shortcode-based library references.
		 * regex test: https://regex101.com/r/y6nID7/1
		 */
		static $pattern_divi4 = 'divi_library="(\d+)"';

		// Run a combined search to support both content formats.
		preg_match_all( "~$pattern_divi5|$pattern_divi4~", $content, $matches );

		$module_ids = array_filter(
			array_merge( $matches[1] ?? [], $matches[2] ?? [] )
		);

		// Return unique module IDs as integers with reset indexes.
		return ! empty( $module_ids )
			? array_values( array_unique( array_map( 'intval', $module_ids ) ) )
			: [];
	}

	/**
	 * Retrieves the gutter widths for a given content in Gutenberg/Shortcode format.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for gutter widths.
	 * @param array  $options Whether to check shortode use.
	 *
	 * @return array  The gutter width values.
	 */
	public static function get_gutter_widths( string $content, array $options = [] ): array {
		// Bail early, if needed..
		if (
			empty( $content )
			|| ( ! $options['has_block'] && ! $options['has_shortcode'] )
			|| ( ! str_contains( $content, '"gutter"' ) && ! str_contains( $content, 'use_custom_gutter=' ) )
		) {
			return [];
		}

		// Handle both old content (with "enable" field) and new content (without "enable" field) for backward compatibility.
		// Pattern for old content format with "enable" field.
		// Matches: "gutter":{"desktop":{"value":{"enable":"on","width":"4"}}}
		// Also:    "gutter":{"desktop":{"value":{"width":"4","enable":"on"}}}
		// The width capture is bounded to the flat "value" object via [^}]* (no nested braces),
		// making it order-agnostic for "enable" and "width" keys while preventing over-capture
		// from sibling attributes (e.g. sizing.width:"100%") on single-line serialized content.
		// Test regex: https://regex101.com/r/S6MPuW/7.
		static $pattern_gutenberg_old = '("gutter":{.*?"value":{(?=[^}]*"enable":"on")(?:(?=[^}]*"width":"(?<gutenberg_width_old>[^"]*)"))?[^}]*}[^}]*}[^}]*})';

		// Pattern for new D5 content format (without "enable" field) - uses negative lookahead to prevent old disabled gutters.
		// Test regex: https://regex101.com/r/S6MPuW/4.
		static $pattern_gutenberg_new = '("gutter":{(?!.*"enable":"off").*?"width":"(?<gutenberg_width_new>[^\"]*)".*?}}}})';

		// Combined pattern to match both old and new gutter formats for backward compatibility across D4→D5 migrations.
		$pattern_gutenberg = "($pattern_gutenberg_old|$pattern_gutenberg_new)";

		// Pattern to get gutter width in Shortcode content.
		// Test regex: https://regex101.com/r/BFl7oi/3.
		static $pattern_shortcode = '(use_custom_gutter="on"(?:[^\[\]]*?gutter_width="(?<shortcode_width>[^"]*)")?)';

		// Conditionally build the pattern based.
		if ( $options['has_block'] && $options['has_shortcode'] ) {
			$pattern = "~$pattern_gutenberg|$pattern_shortcode~";
		} elseif ( $options['has_block'] ) {
			$pattern = "~$pattern_gutenberg~";
		} elseif ( $options['has_shortcode'] ) {
			$pattern = "~$pattern_shortcode~";
		}

		// Perform a single regex search for gutter widths.
		preg_match_all( $pattern, $content, $matches );

		// Initialize an array to store the gutter widths.
		$widths = [];

		// Process both Gutenberg (old and new format) and shortcode gutter width matches.
		$gutenberg_widths_old = $matches['gutenberg_width_old'] ?? [];
		$gutenberg_widths_new = $matches['gutenberg_width_new'] ?? [];
		$shortcode_widths     = $matches['shortcode_width'] ?? [];

		// Combine all found widths from both old and new Gutenberg formats plus shortcodes.
		$found_widths = array_filter(
			array_merge( $gutenberg_widths_old, $gutenberg_widths_new, $shortcode_widths )
		);

		// If total found instances > found widths, inject default gutter width of 3 (handles missing width values).
		$total_found = 0;
		if ( ! empty( $gutenberg_widths_old ) ) {
			$total_found = count( $gutenberg_widths_old );
		} elseif ( ! empty( $gutenberg_widths_new ) ) {
			$total_found = count( $gutenberg_widths_new );
		} elseif ( ! empty( $shortcode_widths ) ) {
			$total_found = count( $shortcode_widths );
		}

		if ( $total_found > count( $found_widths ) ) {
			$widths[] = 3;
		}

		foreach ( $found_widths as $match ) {
			// Add the value to the array if it's not already included.
			if ( ! in_array( $match, $widths, true ) ) {
				$widths[] = $match;
			}
		}

		// Return unique gutter widths as integers.
		return array_map( 'intval', $widths );
	}

	/**
	 * Checks if the content has animation style.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for animation style.
	 * @param array  $options Whether to check shortode use.
	 *
	 * @return bool  True if the content has animation style, false otherwise.
	 */
	public static function has_animation_style( string $content, array $options = [] ): bool {
		// Bail early if content is empty or we are not checking for either blocks or shortcodes.
		if ( empty( $content ) || ( empty( $options['has_block'] ) && empty( $options['has_shortcode'] ) ) ) {
			return false;
		}

		$animation_styles = [
			'fade',
			'slide',
			'bounce',
			'zoom',
			'flip',
			'fold',
			'roll',
		];

		$animation_styles_pattern = implode( '|', $animation_styles );

		if ( ! empty( $options['has_block'] ) ) {
			// A single regex to find any of the specified block animation styles.
			// test regex: https://regex101.com/r/ohOKVe/1.
			$block_pattern = '/"animation":\s*{[^{}]*"desktop":\s*{[^{}]*"value":\s*{[^{}]*"style":\s*"(' . $animation_styles_pattern . ')"/';

			if ( preg_match( $block_pattern, $content ) ) {
				return true;
			}

			if ( self::_has_legacy_blurb_animation_style( $content ) ) {
				return true;
			}
		}

		if ( ! empty( $options['has_shortcode'] ) ) {
			// A single regex to find either a shortcode with a specified animation style
			// or the `content_animation` attribute.
			// test regex: https://regex101.com/r/cGzMw9/2.
			$shortcode_pattern = '/animation_style="(' . $animation_styles_pattern . ')"|content_animation=/';
			if ( preg_match( $shortcode_pattern, $content ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detects legacy Blurb animation values used before composable migration.
	 *
	 * Legacy path:
	 * imageIcon.innerContent.{breakpoint}.value.animation.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search.
	 *
	 * @return bool True when legacy Blurb animation is enabled.
	 */
	private static function _has_legacy_blurb_animation_style( string $content ): bool {
		// Early return when no Blurb block exists.
		if ( ! str_contains( $content, 'wp:divi/blurb' ) ) {
			return false;
		}

		$blurb_block_pattern = '/<!--\s+wp:divi\/blurb\s+(\{.*?\})\s*\/?\s*-->/s';
		$matches             = [];

		if ( 1 > preg_match_all( $blurb_block_pattern, $content, $matches ) ) {
			return false;
		}

		/*
		 * Dynamic assets detection runs on raw content and may execute before runtime
		 * migration mutates legacy paths to decoration.animation.*.style.
		 *
		 * Legacy behavior for Blurb image animation:
		 * - "animation":"off" => animation disabled.
		 * - "animation":"<direction>" => animation enabled.
		 * - missing animation key => default animation is applied by migration.
		 *
		 * Therefore this detector should return true unless animation is explicitly "off".
		 *
		 * Test regex: https://regex101.com/r/E46T28/1.
		 */
		$legacy_animation_off_pattern = '/"imageIcon"\s*:\s*\{.*?"innerContent"\s*:\s*\{.*?"animation"\s*:\s*"off"/s';

		foreach ( $matches[1] as $blurb_payload ) {
			if ( ! is_string( $blurb_payload ) ) {
				continue;
			}

			if ( 1 !== preg_match( $legacy_animation_off_pattern, $blurb_payload ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Retrieves the status of content length for a given content block in Gutenberg/Shortcode format.
	 *
	 * Modules that use this attribute: Blog.
	 *
	 * D4 attribute name: `show_content`
	 * D5 attribute path: `post.advanced.excerptContent.*`
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search.
	 * @param array  $options Whether to check shortode use.
	 *
	 * @return bool  The content length status.
	 */
	public static function has_excerpt_content_on( string $content, array $options = [] ): bool {
		// Bail early, if needed..
		if (
			empty( $content )
			|| ( ! $options['has_block'] && ! $options['has_shortcode'] )
			|| ( ! str_contains( $content, '"excerptContent"' ) && ! str_contains( $content, 'show_content=' ) )
		) {
			return false;
		}

		/*
		 * Look for a JSON object containing an "on" `excerptContent` attribute.
		 *
		 * The excerptContent attribute is a nested object. The value we're looking
		 * for will be found within a breakpoint->state structure.
		 *
		 * When this attribute is 'on', the Content Length setting is set to "Show
		 * Content"; if 'off', the Content Length setting is set to "Show Excerpt".
		 *
		 * Typically, if Content Length uses the default value, a value will not be
		 * set in the JSON object. If it is set to anything other than 'on', it is
		 * not considered enabled.
		 *
		 * Test Regex: https://regex101.com/r/s90yfc/1
		 */
		static $pattern_gutenberg = '"excerptContent"(?:(?!}}).)*"on"';

		/*
		 * The pattern to search for animation style in Shortcode content.
		 * regex test: https://regex101.com/r/a7VwWm/1
		 */
		static $pattern_shortcode = 'show_content="on"';

		// Conditionally build the pattern based.
		if ( $options['has_block'] && $options['has_shortcode'] ) {
			$pattern = "~$pattern_gutenberg|$pattern_shortcode~";
		} elseif ( $options['has_block'] ) {
			$pattern = "~$pattern_gutenberg~";
		} elseif ( $options['has_shortcode'] ) {
			$pattern = "~$pattern_shortcode~";
		}

		// Perform a single regex search based on the combined pattern.
		preg_match( $pattern, $content, $matches );

		// Return true if any excerpt content status was detected, false otherwise.
		return ! empty( $matches );
	}

	/**
	 * Checks if the content contains a Divi icon using raw HTML entity format.
	 *
	 * The regex looks for hexadecimal HTML entities (e.g., &#xe00a;) followed by ||divi||
	 * This pattern is typical for Divi font icons embedded in content.
	 * Example it matches: &#xe00a;||divi||
	 * Note: Any trailing numbers (like font weight e.g., 400 or 500) are intentionally ignored.
	 * Regex101 test: https://regex101.com/r/VnmNG2/1.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for Divi icons.
	 *
	 * @return bool True if Divi icons are found in raw HTML entity format, false otherwise.
	 */
	private static function _has_divi_icon_raw_html_entity( string $content ): bool {
		return preg_match( '/&#x[0-9a-fA-F]+;\|\|divi\|\|/', $content );
	}

	/**
	 * Checks if Divi and/or Font Awesome icons are used in the given content in Gutenberg format.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search.
	 * @param string $type    Type of font detection. Valid type: fa | divi.
	 * @param array  $options Whether to check shortode use.
	 *
	 * @return bool True if Font Awesome ('fa') or Divi ('divi') icons are used in the content.
	 */
	public static function has_icon_font( string $content, string $type, array $options = [] ): bool {
		static $cached = [];

		$cache_key = md5( $content . implode( '', $options ) );

		// If cached result exists, return it early.
		if ( isset( $cached[ $cache_key ] ) ) {
			return $cached[ $cache_key ][ $type ] ?? false;
		}

		// Bail early, if needed..
		// Regex101 test: https://regex101.com/r/VnmNG2/1.
		if (
			empty( $content )
			|| ( ! $options['has_block'] && ! $options['has_shortcode'] )
			|| ( ! str_contains( $content, '"unicode"' ) && ! str_contains( $content, '_icon' ) && ! preg_match( '/&#x[0-9a-fA-F]+;\|\|divi\|\|/', $content ) )
		) {
			$cached[ $cache_key ] = [
				'fa'   => false,
				'divi' => false,
			];

			return false;
		}

		/*
		 * There are cases where we should check "enabled":"on" value of a button/element to consider a font type use
		 * for icons, for example buttonOne|buttonTwo attributes of the fullwidth-header module; however many other
		 * modules' attribute doesn't have enabled value. Also, the enabled value could be present in `desktop` or
		 * `tablet` breakpoint but then `tablet` / `phone` breakpoint will not have the enabled value as it would be
		 * inherited from the higher breakpoint. Given the complexity, and for performance reason, ignored the enabled
		 * value to detect the fa|divi font type use.
		 *
		 * Optimized pattern: Simplified to find "type":"divi" or "type":"fa" near "unicode" to avoid complex backtracking.
		 * Uses a more efficient pattern that doesn't require matching the full object structure.
		 * Regex test: https://regex101.com/r/vKj3C3/1.
		 */
		static $pattern = '/"unicode"[^}]{0,256}?"type":"(?<type>divi|fa)"/';

		$font_types = [];

		if ( $options['has_block'] ) {
			// Early bail-out: If checking for a specific type and it doesn't exist in content,
			// we can skip the expensive regex. But we need to check both types to cache results.
			// So we only bail if BOTH types are absent.
			$has_divi_type = str_contains( $content, '"type":"divi"' );
			$has_fa_type   = str_contains( $content, '"type":"fa"' );

			// If neither type exists, bail early without regex.
			// Only cache and return false if shortcode checking is not enabled, since shortcodes
			// use different patterns and may still contain icons.
			if ( ! $has_divi_type && ! $has_fa_type && ! $options['has_shortcode'] ) {
				$cached[ $cache_key ] = [
					'fa'   => false,
					'divi' => false,
				];
				return false;
			}

			// Perform regex search to find icon types in the content.
			preg_match_all( $pattern, $content, $matches );

			// Initialize the results array to track the detected font types.
			$font_types = array_values( $matches['type'] ?? [] );

			// Check if the content contains a Divi icon using raw HTML entity format.
			if ( self::_has_divi_icon_raw_html_entity( $content ) ) {
				// If matched, we assume it's using a Divi font icon and add 'divi' to the font types.
				$font_types[] = 'divi';
			}
		}

		if ( $options['has_shortcode'] ) {
			// Checks all the divi icons use based on shortcode content.
			if ( et_pb_check_if_post_contains_fa_font_icon( $content ) ) {
				$font_types[] = 'fa';
			}

			// Checks all the fa icons use based on shortcode content.
			if ( et_pb_check_if_post_contains_divi_font_icon( $content ) ) {
				$font_types[] = 'divi';
			}

			// Check if the content contains a Divi icon using raw HTML entity format.
			// Only check if 'divi' is not already in the font_types array.
			if ( ! in_array( 'divi', $font_types, true ) && self::_has_divi_icon_raw_html_entity( $content ) ) {
				// If matched, we assume it's using a Divi font icon and add 'divi' to the font types.
				$font_types[] = 'divi';
			}
		}

		// Track the presence of Font Awesome and Divi icons and cache the result to avoid repeated checks.
		$cached[ $cache_key ] = [
			'fa'   => in_array( 'fa', $font_types, true ),
			'divi' => in_array( 'divi', $font_types, true ),
		];

		// Return the result indicating the presence of Font Awesome or Divi icons.
		return $cached[ $cache_key ][ $type ] ?? false;
	}

	/**
	 * Retrieves the lightbox status for a given content in Gutenberg/Shortcode format.
	 *
	 * Modules that use this attribute: Image, Fullwidth Image, Instagram Feed.
	 *
	 * D4 attribute name: `show_in_lightbox`
	 * D5 attribute paths: `image.advanced.lightbox.*`, `feed.advanced.config.*.value.lightbox`.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for lightbox status.
	 * @param array  $options Whether to check shortode use.
	 *
	 * @return bool  The lightbox status.
	 */
	public static function has_lightbox( string $content, array $options = [] ): bool {
		// Check for third-party lightbox plugins first (doesn't require blocks/shortcodes).
		if ( self::has_3p_lightbox( $content, $options ) ) {
			return true;
		}

		// Bail early, if needed..
		if (
			empty( $content )
			|| ( ! $options['has_block'] && ! $options['has_shortcode'] )
			|| ( ! str_contains( $content, '"lightbox"' ) && ! str_contains( $content, 'show_in_lightbox=' ) && ! str_contains( $content, 'wp:divi/instagram-feed' ) )
		) {
			return false;
		}

		/*
		 * Look for a JSON object containing a `lightbox` attribute with a nested object.
		 *
		 * The lightbox attribute is a nested object. The value we are looking
		 * for is in the `desktop.value` key.
		 *
		 * Typically, if the lightbox is not enabled, the value will not be set.
		 * If it is set to anything other than 'on', it is not considered enabled.
		 *
		 * Test Regex: https://regex101.com/r/oBYH80/1
		 */
		static $pattern_gutenberg = '"lightbox":{"(?:\bdesktop|tablet|phone\b)":{"(?:\bvalue|sticky|hover\b)":"on"}}';

		/*
		 * The pattern to search for show_in_lightbox in Shortcode content.
		 * regex test: https://regex101.com/r/zZv4ZY/1
		 */
		static $pattern_shortcode = 'show_in_lightbox="on"';

		// Instagram Feed has default lightbox enabled, and default values may not be persisted in content.
		// Extract each Instagram Feed block payload with a targeted regex and check for lightbox state via
		// fast substring matching — avoiding a full block parse in this performance-critical path.
		if ( $options['has_block'] && str_contains( $content, 'wp:divi/instagram-feed' ) ) {
			/*
			 * Extract each Instagram Feed block's JSON payload from its block comment.
			 * Instagram Feed is self-closing, so the comment ends with /-->.
			 * Test regex: https://regex101.com/r/GcIMwI/1
			 */
			static $pattern_ig_block = '/<!--\s+wp:divi\/instagram-feed\s+(\{.+?\})\s*\/?-->/s';

			if ( ! preg_match_all( $pattern_ig_block, $content, $ig_matches ) ) {
				// Block comment present but not parseable by the pattern: assume lightbox defaults are active.
				return true;
			}

			foreach ( $ig_matches[1] as $block_json ) {
				/*
				 * Return true when any block has lightbox enabled.
				 * Instagram Feed stores lightbox as a plain key inside feed.advanced.config.*.value,
				 * e.g. "lightbox":"on" or "lightbox":"off" — unlike other modules that store booleans
				 * as {"desktop":{"value":"on|off"}}.
				 *
				 * Because lightbox defaults to "on" and that default is not persisted in content, we
				 * treat the absence of "lightbox":"off" as lightbox being enabled.
				 */
				if ( str_contains( $block_json, '"lightbox":"on"' ) || ! str_contains( $block_json, '"lightbox":"off"' ) ) {
					return true;
				}
			}
		}

		// Conditionally build the pattern based.
		if ( $options['has_block'] && $options['has_shortcode'] ) {
			$pattern = "~$pattern_gutenberg|$pattern_shortcode~";
		} elseif ( $options['has_block'] ) {
			$pattern = "~$pattern_gutenberg~";
		} elseif ( $options['has_shortcode'] ) {
			$pattern = "~$pattern_shortcode~";
		}

		// Perform a single regex search based on the combined pattern.
		preg_match( $pattern, $content, $matches );

		// Return true if any lightbox attribute was detected, false otherwise.
		return ! empty( $matches );
	}

	/**
	 * Detects third-party magnific popup usage for backwards compatibility.
	 *
	 * This method detects magnific popup usage from third-party plugins that
	 * aren't recognized by the standard D5 detection system. It checks for
	 * known third-party plugin shortcodes actually used in content.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for third-party magnific popup usage.
	 * @param array  $options Detection options (unused for now but maintained for consistency).
	 *
	 * @return bool True if third-party magnific popup usage is detected, false otherwise.
	 */
	public static function has_3p_lightbox( string $content = '', array $options = [] ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Parameter reserved for future use.
		// Check for known third-party plugin shortcodes that use magnific popup.
		if ( ! empty( $content ) ) {
			$known_3p_lightbox_shortcodes = [
				// WPTools Masonry Gallery shortcodes.
				'et_pb_wpt_masonry_image_gallery',
				'wpt_masonry_gallery',
				// Other known third-party shortcodes that use magnific popup.
				'divi_masonry_gallery',
				'ds_masonry_gallery',
				'divi_gallery_extended',
			];

			foreach ( $known_3p_lightbox_shortcodes as $shortcode ) {
				if ( str_contains( $content, '[' . $shortcode ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Retrieves the fullscreen section status for a given content in Gutenberg format.
	 *
	 * Modules that use this attribute: Fullwidth Header.
	 *
	 * D5 attribute path: `module.advanced.headerFullscreen.*`
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for fullscreen section status.
	 * @param array  $options Whether to check shortode use.
	 *
	 * @return bool  The fullscreen section status.
	 */
	public static function has_fullscreen_section_enabled( string $content, array $options = [] ): bool {
		// Bail early, if needed..
		if (
			empty( $content )
			|| ( ! $options['has_block'] && ! $options['has_shortcode'] )
			|| ( ! str_contains( $content, '"headerFullscreen"' ) && ! str_contains( $content, 'header_fullscreen=' ) )
		) {
			return false;
		}

		/*
		 * Look for a JSON object containing a `headerFullscreen` in Gutenberg content.
		 *
		 * If it is set to anything other than 'on', it is not considered enabled.
		 *
		 * Test Regex: https://regex101.com/r/U4ZpBL/1
		 */
		static $pattern_gutenberg = '"headerFullscreen":{"(?:\bdesktop|tablet|phone\b)":{"(?:\bvalue|sticky|hover\b)":"on"}}';

		/*
		 * The pattern to search for header_fullscreen in Shortcode content.
		 * regex test: https://regex101.com/r/qJGeeN/1
		 */
		static $pattern_shortcode = 'header_fullscreen="on"';

		// Conditionally build the pattern based.
		if ( $options['has_block'] && $options['has_shortcode'] ) {
			$pattern = "~$pattern_gutenberg|$pattern_shortcode~";
		} elseif ( $options['has_block'] ) {
			$pattern = "~$pattern_gutenberg~";
		} elseif ( $options['has_shortcode'] ) {
			$pattern = "~$pattern_shortcode~";
		}

		// Perform a single regex search based on the combined pattern.
		preg_match( $pattern, $content, $matches );

		// Return true if any fullscreen section attribute was detected, false otherwise.
		return ! empty( $matches );
	}

	/**
	 * Checks if any of the module has scroll effects enabled.
	 *
	 * Scroll effects matched include `rotating`, `scaling`, `verticalMotion`, `horizontalMotion`, `blur`, and `fade`.
	 * If any one of these is enabled, the function returns true.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for scroll effects.
	 * @param array  $options Whether to check shortode use.
	 *
	 * @return bool True if the content has scroll effect, false otherwise.
	 */
	public static function has_scroll_effects_enabled( string $content, array $options = [] ): bool {
		// Bail early, if needed..
		if (
			empty( $content )
			|| ( ! $options['has_block'] && ! $options['has_shortcode'] )
			|| ( ! str_contains( $content, '"scroll"' ) && ! str_contains( $content, 'scroll_' ) )
		) {
			return false;
		}

		/*
		 * this patterns matches any of the scroll effects that may be enabled enabled.
		 * these include rotating, scaling, verticalMotion, horizontalMotion, blur, fade.
		 * test regex 101: https://regex101.com/r/ZFCPup/1
		 * test regex 101: https://regex101.com/r/ZFCPup/2
		 */
		static $pattern_gutenberg = '"(?:\brotating|scaling|verticalMotion|horizontalMotion|blur|fade\b)":{.*?"enable":"on"[^}]*?}';

		/*
		 * The pattern to search for show_in_lightbox in Shortcode content.
		 * regex test: https://regex101.com/r/bYbq6x/1
		 */
		static $pattern_shortcode = 'scroll_(?:rotating|scaling|vertical_motion|horizontal_motion|blur|fade)_enable="on"';

		// Conditionally build the pattern based.
		if ( $options['has_block'] && $options['has_shortcode'] ) {
			$pattern = "~$pattern_gutenberg|$pattern_shortcode~";
		} elseif ( $options['has_block'] ) {
			$pattern = "~$pattern_gutenberg~";
		} elseif ( $options['has_shortcode'] ) {
			$pattern = "~$pattern_shortcode~";
		}

		// Perform a single regex search based on the combined pattern.
		preg_match( $pattern, $content, $matches );

		// Return true if any scroll effect was detected, false otherwise.
		return ! empty( $matches );
	}

	/**
	 * Checks if any of the page has split testing active.
	 *
	 * Split testing sets ab_goal to "on" when a test is active.
	 * If any element has ab_goal set to "on" this will return true.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for split testing.
	 * @param array  $options Whether to check shortode use.
	 *
	 * @return bool True if the content has split testing, false otherwise.
	 */
	public static function has_split_testing_enabled( string $content, array $options = [] ): bool {
		// Bail early, if needed..
		if (
			empty( $content )
			|| ( ! $options['has_block'] && ! $options['has_shortcode'] )
			|| ! str_contains( $content, 'ab_goal' )
		) {
			return false;
		}

		/*
		 * The patterns matches any ab_goal attribute that is "on".
		 */
		static $pattern_gutenberg = '"ab_goal":"on"';
		static $pattern_shortcode = 'ab_goal="on"';

		// Conditionally build the pattern based.
		if ( $options['has_block'] && $options['has_shortcode'] ) {
			$pattern = "~$pattern_gutenberg|$pattern_shortcode~";
		} elseif ( $options['has_block'] ) {
			$pattern = "~$pattern_gutenberg~";
		} elseif ( $options['has_shortcode'] ) {
			$pattern = "~$pattern_shortcode~";
		}

		// Perform a single regex search based on the combined pattern.
		preg_match( $pattern, $content, $matches );

		// Return true if any split testing attribute was detected, false otherwise.
		return ! empty( $matches );
	}

	/**
	 * Checks if any of the page has section dividers in use.
	 *
	 * Sections will have a dividers attribiute with a style attribute that contains a string.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for section dividers.
	 * @param array  $options Whether to check shortode use.
	 *
	 * @return bool True if the content has section dividers, false otherwise.
	 */
	public static function has_section_dividers_enabled( string $content, array $options = [] ): bool {
		// Bail early, if needed..
		if (
			empty( $content )
			|| ( ! $options['has_block'] && ! $options['has_shortcode'] )
			|| ( ! str_contains( $content, '"dividers"' ) && ! str_contains( $content, '_divider_style=' ) )
		) {
			return false;
		}

		/*
		 * The pattern matches dividers.(top|bottom).[breakpoint].[state].style with any contents.
		 * test regex: https://regex101.com/r/1yWyP5/1
		 */
		static $pattern_gutenberg = '"dividers":{.*?"(?:\btop|bottom\b)":{.*?"style":\s*"(.*?)".*?}}';

		/*
		 * The patterns matches any top_divider_style/bottom_divider_style attribute with any contents.
		 * test regex: https://regex101.com/r/PKBUF5/1
		 */
		static $pattern_shortcode = '(?:top|bottom)_divider_style="[^"]+"';

		// Conditionally build the pattern based.
		if ( $options['has_block'] && $options['has_shortcode'] ) {
			$pattern = "~$pattern_gutenberg|$pattern_shortcode~";
		} elseif ( $options['has_block'] ) {
			$pattern = "~$pattern_gutenberg~";
		} elseif ( $options['has_shortcode'] ) {
			$pattern = "~$pattern_shortcode~";
		}

		// Perform a single regex search based on the combined pattern.
		preg_match( $pattern, $content, $matches );

		// Return true if any section divider was detected, false otherwise.
		return ! empty( $matches );
	}

	/**
	 * Checks if any modules on the page use the link option.
	 *
	 * Modules will have a link attribute containing a string.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for section dividers.
	 * @param array  $options Whether to check shortode use.
	 *
	 * @return bool True if the content has section dividers, false otherwise.
	 */
	public static function has_link_enabled( string $content, array $options = [] ): bool {
		// Bail early, if needed..
		if (
			empty( $content )
			|| ( ! $options['has_block'] && ! $options['has_shortcode'] )
		) {
			return false;
		}

		// Quick check before expensive regex - bail early if link pattern doesn't exist.
		// For block format, we need both "link" and "url" to ensure there's an actual link URL configured.
		$has_link_block     = str_contains( $content, '"link"' ) && str_contains( $content, '"url"' );
		$has_link_shortcode = str_contains( $content, 'link_option_url=' );

		if ( ! $has_link_block && ! $has_link_shortcode ) {
			return false;
		}

		/*
		 * Optimized pattern: Limits quantifier to prevent catastrophic backtracking.
		 * This patterns matches link.[breakpoint].[state].url with contents, and skip the blank URL: "".
		 * Limits matching to 20KB per section to prevent exponential backtracking on large strings.
		 * Using smaller limits (20KB) since link structure is typically smaller than 50KB.
		 * test regex: https://regex101.com/r/N5wBWC/1.
		 */
		static $pattern_gutenberg = '"link":\{.{0,20480}?"url":\s*"(?<url_gutenberg>[^"]+)".{0,20480}?\}\}\}';

		/*
		 * The patterns matches any link_option_url attribute with contents, and skip the blank URL: "".
		 * test regex: https://regex101.com/r/cJgW8K/1
		 */
		static $pattern_shortcode = 'link_option_url="(?<url_shortcode>[^"]+)"';

		// Conditionally build the pattern based.
		if ( $options['has_block'] && $options['has_shortcode'] ) {
			$pattern = "~$pattern_gutenberg|$pattern_shortcode~s";
		} elseif ( $options['has_block'] ) {
			$pattern = "~$pattern_gutenberg~s";
		} elseif ( $options['has_shortcode'] ) {
			$pattern = "~$pattern_shortcode~";
		}

		// Perform a single regex search based on the combined pattern.
		preg_match( $pattern, $content, $matches );

		// Return true if any link URL was detected in either Gutenberg or shortcode content.
		return ! empty( $matches['url_gutenberg'] ) || ! empty( $matches['url_shortcode'] );
	}

	/**
	 * Checks if Divi and/or Font Awesome icons are used in the Social Media Follow Network blocks.
	 *
	 * This function searches the provided content string for social media follow network blocks
	 * and determines if any Font Awesome or Divi icons are used within those blocks.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for social media follow network blocks.
	 * @param string $type    Type of font detection. Valid type: fa | divi.
	 * @param array  $options Whether to check shortode use.
	 *
	 * @return bool True if Font Awesome ('fa') or Divi ('divi') icons are used in the content.
	 */
	public static function has_social_follow_icon_font( string $content, string $type, array $options = [] ): bool {
		static $cached = [];

		$cache_key = md5( $content . intval( $options['has_block'] ) . intval( $options['has_shortcode'] ) );

		// If cached result exists, return it early.
		if ( isset( $cached[ $cache_key ] ) ) {
			return $cached[ $cache_key ][ $type ] ?? false;
		}

		// Bail early, if needed..
		if (
			empty( $content )
			|| ( ! $options['has_block'] && ! $options['has_shortcode'] )
			|| ( ! str_contains( $content, '"socialNetwork"' ) && ! str_contains( $content, 'social_network=' ) )
		) {
			$cached[ $cache_key ] = [
				'fa'   => false,
				'divi' => false,
			];

			return false;
		}

		/*
		 * Define the regex pattern to match the icon names within the social media follow network blocks.
		 * The icon names are expected to appear in the `"title"` key within the nested JSON structure.
		 *
		 * Test regex: https://regex101.com/r/kdfF1I/1
		 */
		static $pattern_gutenberg = '"socialNetwork":{.*?"innerContent":.*?"title":\s*"(?<gutenberg_icon>.*?)".*?}';

		/*
		 * The patterns matches social_network attribute with any contents.
		 * Test regex: https://regex101.com/r/349wYc/1
		 */
		static $pattern_shortcode = 'social_network="(?<shortcode_icon>.*?)"';

		// Conditionally build the pattern based.
		if ( $options['has_block'] && $options['has_shortcode'] ) {
			$pattern = "~$pattern_gutenberg|$pattern_shortcode~";
		} elseif ( $options['has_block'] ) {
			$pattern = "~$pattern_gutenberg~";
		} elseif ( $options['has_shortcode'] ) {
			$pattern = "~$pattern_shortcode~";
		}

		// Perform a single regex search for both Gutenberg and shortcode gutter widths.
		preg_match_all( $pattern, $content, $matches );

		// Process both Gutenberg and shortcode matches.
		$gutenberg_icons = $matches['gutenberg_icon'] ?? [];
		$shortcode_icons = $matches['shortcode_icon'] ?? [];
		$found_icons     = array_filter(
			array_merge( $gutenberg_icons, $shortcode_icons )
		);

		// Retrieve the list of Font Awesome icons.
		$font_awesome_icons = SocialMediaFollowItemModule::font_awesome_icons();

		// Track the presence of Font Awesome and Divi icons.
		$cached[ $cache_key ] = [
			'fa'   => ! empty( array_intersect( $found_icons, $font_awesome_icons ) ),
			'divi' => ! empty( array_diff( $found_icons, $font_awesome_icons ) ),
		];

		// Return the result indicating the presence of Font Awesome or Divi icons.
		return $cached[ $cache_key ][ $type ];
	}

	/**
	 * Checks if the content has a specialty section.
	 *
	 * A specialty section has type set to "specialty", defined in the module -> advanced settings.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for specialty section.
	 * @param array  $options Whether to check shortode use.
	 *
	 * @return bool True if the content has a specialty section, false otherwise.
	 */
	public static function has_specialty_section( string $content, array $options = [] ): bool {
		// Bail early, if needed..
		if (
			empty( $content )
			|| ( ! $options['has_block'] && ! $options['has_shortcode'] )
			|| ( ! str_contains( $content, '"specialty"' ) && ! str_contains( $content, 'specialty=' ) )
		) {
			return false;
		}

		/*
		 * The pattern to search for a specialty section.
		 * A specialty section has type set to "specialty", defined in the module -> advanced settings.
		 * regex test: https://regex101.com/r/PSbkrI/1
		 */
		static $pattern_gutenberg = '{"module":{.*?"advanced":{.*?"type":{"(?:\bdesktop|tablet|phone\b)":{"value":"specialty".*?}';

		/*
		 * The pattern to search for a specialty section in shortcode content.
		 * regex test: https://regex101.com/r/vHj89y/1
		 */
		static $pattern_shortcode = 'specialty="on"';

		// Conditionally build the pattern based.
		if ( $options['has_block'] && $options['has_shortcode'] ) {
			$pattern = "~$pattern_gutenberg|$pattern_shortcode~";
		} elseif ( $options['has_block'] ) {
			$pattern = "~$pattern_gutenberg~";
		} elseif ( $options['has_shortcode'] ) {
			$pattern = "~$pattern_shortcode~";
		}

		// Perform a single regex search based on the combined pattern.
		preg_match( $pattern, $content, $matches );

		// Return true if any specialty section was detected, false otherwise.
		return ! empty( $matches );
	}

	/**
	 * Checks if sticky position is enabled for a given content in Gutenberg format.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for sticky position.
	 * @param array  $options Whether to check shortode use.
	 *
	 * @return bool True if sticky position is enabled, false otherwise.
	 */
	public static function has_sticky_position_enabled( string $content, array $options = [] ): bool {
		// Bail early, if needed..
		if (
			empty( $content )
			|| ( ! $options['has_block'] && ! $options['has_shortcode'] )
			|| ( ! str_contains( $content, '"sticky"' ) && ! str_contains( $content, 'sticky_position=' ) )
		) {
			return false;
		}

		/*
		 * This pattern is used to detect sticky position in the content.
		 * The pattern matches for position "top", "bottom" or "topBottom".
		 * If any of these positions are found, it means sticky position is enabled.
		 * test regex: https://regex101.com/r/zCeJhv/1
		 */
		static $pattern_gutenberg = '"sticky":{.*?"position":"(?:\btop|bottom|topBottom\b)".*?}';

		/*
		 * The patterns matches any sticky_position attribute with any contents.
		 * test regex: https://regex101.com/r/wfsOGn/1
		 */
		static $pattern_shortcode = 'sticky_position="[^"]+"';

		// Conditionally build the pattern based.
		if ( $options['has_block'] && $options['has_shortcode'] ) {
			$pattern = "~$pattern_gutenberg|$pattern_shortcode~";
		} elseif ( $options['has_block'] ) {
			$pattern = "~$pattern_gutenberg~";
		} elseif ( $options['has_shortcode'] ) {
			$pattern = "~$pattern_shortcode~";
		}

		// Perform a single regex search based on the combined pattern.
		preg_match( $pattern, $content, $matches );

		// Return true if any scroll effect was detected, false otherwise.
		return ! empty( $matches );
	}

	/**
	 * Checks if Woo module exists for a given content in shortcode format.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for Woo module shortcode names.
	 *
	 * @return bool True if content has WooCommerce shortcode module, false otherwise.
	 */
	public static function has_woocommerce_module_shortcode( string $content ): bool {
		// Perform a quick check to see "[" (for shortcodes) is in the content at all.
		if ( empty( $content ) || ( ! str_contains( $content, '[et_pb_wc_' ) && ! str_contains( $content, '[et_pb_shop' ) ) ) {
			return false;
		}

		// This pattern is used to detect Woo module shortcode in the content.
		// Test regex: https://regex101.com/r/gfRO6P/1.
		static $pattern = '@\[ *(et_pb_wc_[^ ]*|et_pb_shop)(?: [^]]*)?]@';

		// Perform a single regex search based on the combined pattern.
		preg_match( $pattern, $content, $matches );

		// Return true if any Woo modules was detected, false otherwise.
		return ! empty( $matches );
	}

	/**
	 * Determines whether the provided content contains a Divi WooCommerce module block.
	 *
	 * This method efficiently scans the content for Divi block comments that are either
	 * dedicated WooCommerce modules (e.g., `wp:divi/woocommerce-cart-notice`) or the
	 * general shop module (`wp:divi/shop`).
	 *
	 * @since ??
	 *
	 * @param string $content The content to search within.
	 *
	 * @return bool True if a WooCommerce or Shop module block is found, false otherwise.
	 */
	public static function has_woocommerce_module_block( string $content = '' ): bool {
		// Bail early if the content is empty.
		if ( empty( $content ) ) {
			return false;
		}

		/*
		 * This pattern detects the start of a Divi WooCommerce or Shop block comment.
		 * Using a single, efficient regex is better than multiple `strpos` calls followed by a regex.
		 * Test regex: https://regex101.com/r/QbPYzW/2
		 */
		static $pattern = '/<!--\s+wp:divi\/(woocommerce-|shop)/';

		// `preg_match` stops on the first match and is faster than `preg_match_all`.
		// It returns 1 on match, 0 on no match, and false on error. We strictly check for 1.
		return 1 === preg_match( $pattern, $content );
	}

	/**
	 * Determines whether the provided content contains a Divi Post Content module block.
	 *
	 * This method efficiently scans the content for Divi block comments that are either
	 * Post Content module (`wp:divi/post-content`) or Fullwidth Post Content module
	 * (`wp:divi/fullwidth-post-content`).
	 *
	 * @since ??
	 *
	 * @param string $content The content to search within.
	 *
	 * @return bool True if a Post Content or Fullwidth Post Content module block is found, false otherwise.
	 */
	public static function has_post_content_module_block( string $content = '' ): bool {
		// Bail early if the content is empty.
		if ( empty( $content ) ) {
			return false;
		}

		/*
		 * This pattern detects the start of a Divi Post Content or Fullwidth Post Content block comment.
		 * Using a single, efficient regex is better than multiple `strpos` calls followed by a regex.
		 * Test regex: https://regex101.com/r/QbPYzW/2
		 */
		static $pattern = '/<!--\s+wp:divi\/(post-content|fullwidth-post-content)/';

		// `preg_match` stops on the first match and is faster than `preg_match_all`.
		// It returns 1 on match, 0 on no match, and false on error. We strictly check for 1.
		return 1 === preg_match( $pattern, $content );
	}

	/**
	 * Helper method to extract font families from preset attributes.
	 *
	 * @since ??
	 *
	 * @param array $attrs Preset attributes.
	 * @param array $fonts Array to collect found fonts.
	 */
	public static function extract_font_from_preset_attrs( array $attrs, array &$fonts ) {
		$global_variables = null;

		// Recursively check for font family values.
		foreach ( $attrs as $key => $value ) {
			if ( is_array( $value ) ) {
				// Recurse into nested arrays.
				self::extract_font_from_preset_attrs( $value, $fonts );
			} elseif ( is_string( $value ) && str_contains( $key, 'family' ) ) {
				// Direct font family value.
				if ( ! str_starts_with( $value, '$variable(' ) ) {
					$fonts[] = $value;
				} elseif ( preg_match( '/"name"\s*:\s*"([^"]+)"/', $value, $matches ) ) {
					// Process variable reference - extract the ID directly using regex.
					// It matches the following structure: `"$variable({"type":"content","value":{"name":"gvid-bfhzpqo17e","settings":{}}})$"`.
					// Regex101 link: https://regex101.com/r/RIrokW/1.
					$target_id = $matches[1];

					if ( null === $global_variables ) {
						$global_variables = GlobalData::get_global_variables();
					}

					$global_fonts = (array) ( $global_variables['fonts'] ?? [] );

					if ( isset( $global_fonts[ $target_id ]['value'] ) && ( 'active' === $global_fonts[ $target_id ]['status'] ?? '' ) ) {
						$fonts[] = $global_fonts[ $target_id ]['value'];
					}
				}
			}
		}
	}

	/**
	 * Checks if the content has interactions enabled.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for interactions.
	 * @param array  $options {
	 *     Detection options.
	 *
	 *     @type bool $has_block     Whether has Gutenberg blocks.
	 *     @type bool $has_shortcode Whether has shortcodes.
	 * }
	 *
	 * @return bool  True if the content has interactions enabled, false otherwise.
	 */
	public static function has_interactions_enabled( string $content, array $options = [] ): bool {
		// Bail early if content is empty or we are not checking for either blocks or shortcodes.
		if ( empty( $content ) || ( empty( $options['has_block'] ) ) ) {
			return false;
		}

		// Quick check before expensive regex - bail early if interactions pattern doesn't exist.
		if ( ! str_contains( $content, '"interactions"' ) || ! str_contains( $content, '"decoration"' ) ) {
			return false;
		}

		if ( ! empty( $options['has_block'] ) ) {
			// Look for interactions in the decoration group with non-empty interactions array.
			// Optimized pattern: Limits quantifier to prevent catastrophic backtracking.
			// The pattern matches up to 50KB between decoration and interactions
			// (more than enough for realistic nested structures) but prevents unlimited backtracking.
			// Pattern checks for decoration context and non-empty interactions array.
			// test regex: https://regex101.com/r/94wP0n/1.
			// The {0,51200}? limit prevents exponential backtracking while still allowing
			// matching across nested JSON structures. The 's' flag allows . to match newlines.
			$block_pattern = '/"decoration"\s*:\s*\{.{0,51200}?"interactions"\s*:\s*\[\s*\{/s';
			if ( preg_match( $block_pattern, $content ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks if the content has block layout enabled.
	 *
	 * Block layout is enabled when the layout display attribute is set to "block"
	 * in the decoration settings. This feature is only available in Gutenberg blocks.
	 * Also enabled for content with builder versions less than 5.0.0-public-alpha.18.2.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for block layout.
	 * @param array  $options {
	 *     Detection options.
	 *
	 *     @type bool $has_block     Whether has Gutenberg blocks.
	 *     @type bool $has_shortcode Whether has shortcodes.
	 * }
	 *
	 * @return bool True if the content has block layout enabled, false otherwise.
	 */
	public static function has_block_layout_enabled( string $content, array $options = [] ): bool {
		// If there are shortcodes, we know this layout uses the block grid.
		if ( $options['has_shortcode'] ) {
			return true;
		}

		// Next we check for block layouts in Divi 5 blocks.
		// If there are no blocks or content, we can return false and skip the check.
		if ( empty( $content ) || ( empty( $options['has_block'] ) ) ) {
			return false;
		}

		// If this is a version prior to the introducing of flex layouts, we need to enable it.
		// Versions prior to this have unmigrated blocks that use the old layout system.
		if ( self::has_old_builder_version( $content, $options, '5.0.0-public-alpha.18.2' ) ) {
			return true;
		}

		// Quick check before expensive regex - bail early if layout pattern doesn't exist.
		if ( ! str_contains( $content, '"block"' ) ) {
			return false;
		}

		/*
		 * The pattern to search for block layout in Gutenberg content.
		 * Block layout is indicated by "display":"block" within the layout attribute.
		 * Optimized pattern: Limits quantifier to prevent catastrophic backtracking.
		 * We only need to check if "display":"block" exists within "layout", so we don't
		 * need to match what comes after it.
		 * test regex: https://regex101.com/r/A8RnzR/1.
		 */
		static $pattern = '"layout":\{.{0,51200}?"display":"block"';

		// Perform a single regex search for block layout.
		preg_match( "~$pattern~s", $content, $matches );

		// Return true if any block layout was detected, false otherwise.
		return ! empty( $matches );
	}

	/**
	 * Checks if the content has flex layout enabled.
	 *
	 * Flex layout is enabled when flexType attributes are present AND the element
	 * does not have display:block set. Both flexType and display settings can exist
	 * in the same element, so we need to check that display is NOT set to block.
	 *
	 * flexType location: module.decoration.sizing.{breakpoint}.value.flexType
	 * display location: module.decoration.layout.{breakpoint}.value.display
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for flex layout.
	 * @param array  $options {
	 *     Detection options.
	 *
	 *     @type bool $has_block     Whether has Gutenberg blocks.
	 *     @type bool $has_shortcode Whether has shortcodes.
	 * }
	 *
	 * @return bool True if the content has flex layout enabled, false otherwise.
	 */
	public static function has_flex_layout_enabled( string $content, array $options = [] ): bool {
		// If there are no blocks or content, we can return false and skip the check.
		if ( empty( $content ) || ( empty( $options['has_block'] ) ) ) {
			return false;
		}

		// Quick check: if no flexType exists at all, return false early.
		if ( ! str_contains( $content, '"flexType"' ) ) {
			return false;
		}

		/*
		 * We need to check each block individually to see if it has flexType
		 * WITHOUT display:block at the desktop breakpoint.
		 *
		 * If desktop has display:block, the element is using block layout and
		 * flexType attributes should be ignored, even if they exist.
		 *
		 * Pattern to check: "layout":{"desktop":{"value":{"display":"block"...
		 */

		// Parse each block and check if it has flexType without desktop display:block.
		// Pattern explanation: <!-- wp:divi/WORD {JSON} -->.
		// We capture everything between the opening { and closing } before -->.
		preg_match_all( '/<!-- wp:divi\/\w+\s+(\{.+?\})\s*-->/s', $content, $block_matches );

		foreach ( $block_matches[1] as $block_json ) {
			// Check if this block has flexType.
			if ( ! str_contains( $block_json, '"flexType"' ) ) {
				continue;
			}

			// Check if desktop breakpoint has display:block.
			// Expected JSON pattern to match: layout object with desktop.value.display set to "block".
			// Use .*? to handle nested objects (e.g., other breakpoints before desktop).
			if ( preg_match( '/"layout":\{.*?"desktop":\{"value":\{"display"\s*:\s*"block"/s', $block_json ) ) {
				continue;
			}

			// Found a block with flexType and desktop is NOT set to block!
			return true;
		}

		return false;
	}

	/**
	 * Retrieves the responsive breakpoints that have custom flexType defined.
	 *
	 * This method detects which breakpoints (excluding desktop) have custom
	 * flexType values defined in the content.
	 *
	 * flexType location: module.decoration.sizing.{breakpoint}.value.flexType
	 *
	 * Note: If desktop has display:block set, we return an empty array because
	 * the element is using block layout and flexType should be ignored for all breakpoints.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for responsive breakpoints.
	 * @param array  $options {
	 *     Detection options.
	 *
	 *     @type bool $has_block     Whether has Gutenberg blocks.
	 *     @type bool $has_shortcode Whether has shortcodes.
	 * }
	 *
	 * @return array Array of breakpoint names that have custom flexType.
	 */
	public static function get_flex_grid_responsive_breakpoints( string $content, array $options = [] ): array {
		// Bail early if content is empty or no blocks are present.
		if ( empty( $content ) || empty( $options['has_block'] ) ) {
			return [];
		}

		// Check if pricing-tables module is present.
		// Pricing tables have phone breakpoint flexType defaults that aren't written to content,
		// so we need to always include 'phone' breakpoint for CSS loading even if no flexType is in content.
		$has_pricing_tables = str_contains( $content, 'wp:divi/pricing-tables' );

		// If no flexType in content and no pricing-tables, bail early.
		if ( ! str_contains( $content, '"flexType"' ) && ! $has_pricing_tables ) {
			return [];
		}

		// Parse blocks to check if any have flexType with desktop display:block.
		// If desktop has display:block, we should ignore flexType for all breakpoints.
		preg_match_all( '/<!-- wp:divi\/\w+\s+(\{.+?\})\s*-->/s', $content, $block_matches );

		$has_valid_flex_block = false;
		foreach ( $block_matches[1] as $block_json ) {
			// Check if this block has flexType.
			if ( ! str_contains( $block_json, '"flexType"' ) ) {
				continue;
			}

			// Check if desktop breakpoint has display:block.
			// Use .*? to handle nested objects (e.g., other breakpoints before desktop).
			if ( preg_match( '/"layout":\{.*?"desktop":\{"value":\{"display"\s*:\s*"block"/s', $block_json ) ) {
				// This block has flexType but desktop is set to block, skip it.
				continue;
			}

			// Found at least one block with flexType where desktop is NOT set to block.
			$has_valid_flex_block = true;
			break;
		}

		// If no valid flex blocks found and no pricing-tables, return empty array.
		if ( ! $has_valid_flex_block && ! $has_pricing_tables ) {
			return [];
		}

		$breakpoints = [];

		// The order of breakpoints is crucial, as it determines which styles should override others across breakpoints.
		$default_style_breakpoint_order = Breakpoint::get_default_style_breakpoint_order();

		foreach ( $default_style_breakpoint_order as $breakpoint ) {
			// Skip desktop breakpoint (we only check responsive breakpoints).
			if ( 'desktop' === $breakpoint ) {
				continue;
			}

			// Skip disabled breakpoints that aren't configured for styling.
			if ( ! Breakpoint::is_enabled_for_style( $breakpoint ) ) {
				continue;
			}

			// Always include 'phone' breakpoint for pricing-tables modules.
			// Pricing tables have phone flexType defaults (24_24) that aren't written to content,
			// so the regex detection below won't find them, but we still need the CSS loaded.
			if ( 'phone' === $breakpoint && $has_pricing_tables ) {
				$breakpoints[] = $breakpoint;
				continue;
			}

			// This regex pattern matches JSON structures where `flexType`
			// defines custom values for specific breakpoints (e.g., tablet, phone, etc.).
			//
			// Old pattern: "flexType":{..."phoneWide":{"value":"12_24"...
			// New pattern: "sizing":{..."phoneWide":{"value":{"flexType":"12_24"...
			//
			// We need two patterns:
			// 1. Old format: "flexType" appears BEFORE breakpoint name
			// 2. New format: "flexType" appears AFTER breakpoint name (nested in value)
			//
			// test regex (tabletWide): https://regex101.com/r/jy9SlL/1.
			// test regex (tablet): https://regex101.com/r/jy9SlL/2.
			// test regex (phoneWide): https://regex101.com/r/jy9SlL/3.
			// test regex (phone): https://regex101.com/r/jy9SlL/4.
			// test regex (widescreen): https://regex101.com/r/jy9SlL/5.
			// test regex (ultrawide): https://regex101.com/r/jy9SlL/6.

			// Pattern 1: Old format - "flexType":{..."breakpoint":{"value":"...
			// Use non-greedy .*? to match any content without character limits.
			$regex_old = '/"flexType":\{.*?"' . $breakpoint . '":\{"value":/s';

			// Pattern 2: New format - "breakpoint":{"value":{"flexType":"...
			// Use non-greedy .*? to match any content without character limits.
			$regex_new = '/"' . $breakpoint . '":\{"value":\{.*?"flexType":/s';

			// If this breakpoint has custom flexType in either format, add it to our collection.
			if ( preg_match( $regex_old, $content ) || preg_match( $regex_new, $content ) ) {
				$breakpoints[] = $breakpoint;
			}
		}

		return $breakpoints;
	}

	/**
	 * Checks if the content has CSS Grid layout enabled.
	 *
	 * CSS Grid layout is enabled when display:"grid" is present in the layout
	 * attribute of any Divi 5 block. This detection looks for the grid layout
	 * setting in Gutenberg block content. When called via the feature detection
	 * map, it also automatically detects grid layout in module presets and
	 * option group presets (via JSON-encoded preset content).
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for CSS Grid layout.
	 * @param array  $options {
	 *     Detection options.
	 *
	 *     @type bool $has_block     Whether has Gutenberg blocks.
	 *     @type bool $has_shortcode Whether has shortcodes.
	 * }
	 *
	 * @return bool True if the content has CSS Grid layout enabled, false otherwise.
	 */
	public static function has_css_grid_layout_enabled( string $content, array $options = [] ): bool {
		// If there are no blocks or content, we can return false and skip the check.
		if ( empty( $content ) || ( empty( $options['has_block'] ) ) ) {
			return false;
		}

		/*
		 * The pattern to search for CSS Grid layout in Gutenberg content.
		 * CSS Grid layout is indicated by "display":"grid" within the layout attribute.
		 * Optimized pattern: Limits quantifier to prevent catastrophic backtracking.
		 * We only need to check if "display":"grid" exists within "layout", so we don't
		 * need to match what comes after it.
		 *
		 * This pattern works for both direct content and JSON-encoded preset content
		 * (when called via the feature detection map).
		 * test regex: https://regex101.com/r/ESOgOR/1.
		 */
		static $pattern = '"layout":\{.{0,51200}?"display":"grid"';

		// Perform a single regex search for CSS Grid layout.
		preg_match( "~$pattern~s", $content, $matches );

		// Return true if grid layout found in content (including preset content when called via feature detection map).
		return ! empty( $matches );
	}

	/**
	 * Check if content has builder versions less than the specified threshold.
	 *
	 * @since ??
	 *
	 * @param string $content The content to search in.
	 * @param array  $options {
	 *     Detection options.
	 *
	 *     @type bool $has_block     Whether has Gutenberg blocks.
	 *     @type bool $has_shortcode Whether has shortcodes.
	 * }
	 * @param string $threshold_version The version threshold to compare against.
	 * @return bool True if any builder version is less than the threshold or if no versions are found (predates versioning).
	 */
	public static function has_old_builder_version( string $content, array $options = [], string $threshold_version = '5.0.0-public-alpha.18.2' ): bool {
		// Bail early if content is empty or we are not checking for either blocks.
		if ( empty( $content ) || ( empty( $options['has_block'] ) ) ) {
			return false;
		}

		// Regex to match builderVersion attributes in JSON format.
		// test regex: https://regex101.com/r/ZLC299/1.
		$pattern = '/"builderVersion"\s*:\s*"([^"]+)"/';

		if ( preg_match_all( $pattern, $content, $matches ) ) {
			foreach ( $matches[1] as $version ) {
				if ( version_compare( $version, $threshold_version, '<' ) ) {
					return true;
				}
			}
			// Found versions but none were less than threshold.
			return false;
		}

		// No builderVersion found at all - content predates versioning system.
		return true;
	}

	/**
	 * Checks if the content has block mode blog enabled.
	 *
	 * Block mode blog is enabled when a blog module block has:
	 * - blogGrid layout display set to "block"
	 * - fullwidth set to "off"
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for block mode blog.
	 * @param array  $options {
	 *     Detection options.
	 *
	 *     @type bool $has_block     Whether has Gutenberg blocks.
	 *     @type bool $has_shortcode Whether has shortcodes.
	 * }
	 *
	 * @return bool True if the content has block mode blog enabled, false otherwise.
	 */
	public static function has_block_mode_blog_enabled( string $content, array $options = [] ): bool {
		// Bail early if content is empty or we are not checking for blocks.
		if ( empty( $content ) || ( empty( $options['has_block'] ) ) ) {
			return false;
		}

		// Quick check for blog module blocks.
		if ( ! str_contains( $content, 'wp:divi/blog' ) ) {
			return false;
		}

		$results = SimpleBlockParser::parse(
			$content,
			[
				'limit'        => 1,
				'excludeError' => true,
				'blockName'    => 'divi/blog',
				'filter'       => function ( SimpleBlock $block ) {
					$attrs        = $block->attrs();
					$grid_layout  = 'off' === ( $attrs['fullwidth']['advanced']['enable']['desktop']['value'] ?? 'on' );
					$block_layout = 'block' === ( $attrs['blogGrid']['decoration']['layout']['desktop']['value']['display'] ?? 'grid' );

					return $grid_layout && $block_layout;
				},
			]
		);

		return $results->count() > 0;
	}

	/**
	 * Retrieves global color IDs from module presets used on the current page.
	 *
	 * This method extracts global color IDs from module presets that are actually
	 * used on the current page to ensure preset-only global colors are included
	 * in CSS generation.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for preset usage.
	 *
	 * @return array Array of global color IDs found in page-specific presets.
	 */
	public static function get_preset_global_color_ids( string $content = '' ): array {
		static $cache = [];

		$cache_key = md5( $content );
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$preset_attrs_payload = self::_get_used_preset_attrs_payload( $content );
		if ( empty( $preset_attrs_payload ) ) {
			$cache[ $cache_key ] = [];
			return [];
		}

		// Extract global colors from the specific presets used on this page.
		$cache[ $cache_key ] = self::get_global_color_ids(
			wp_json_encode( $preset_attrs_payload )
		);

		return $cache[ $cache_key ];
	}

	/**
	 * Retrieves global variable IDs from module presets used on the current page.
	 *
	 * This method extracts global variable IDs from module presets that are actually
	 * used on the current page to ensure preset-only global variables are included
	 * in CSS generation.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for preset usage.
	 *
	 * @return array Array of global variable IDs found in page-specific presets.
	 */
	public static function get_preset_global_variable_ids( string $content = '' ): array {
		static $cache = [];

		$cache_key = md5( $content );
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$preset_attrs_payload = self::_get_used_preset_attrs_payload( $content );
		if ( empty( $preset_attrs_payload ) ) {
			$cache[ $cache_key ] = [];
			return [];
		}

		// Extract global variables from the specific presets used on this page.
		$cache[ $cache_key ] = self::get_global_variable_ids(
			wp_json_encode( $preset_attrs_payload )
		);

		return $cache[ $cache_key ];
	}

	/**
	 * Retrieves all page-level global variable IDs from content and used presets.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search.
	 *
	 * @return array Array of unique global variable IDs.
	 */
	public static function get_page_global_variable_ids( string $content = '' ): array {
		static $cache = [];

		$cache_key = md5( $content );
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		if ( empty( $content ) ) {
			$cache[ $cache_key ] = [];
			return [];
		}

		$content_variable_ids = self::get_global_variable_ids( $content );

		// Preset-level detection is only meaningful for Divi block content.
		if ( ! str_contains( $content, 'wp:divi/' ) ) {
			$cache[ $cache_key ] = $content_variable_ids;
			return $cache[ $cache_key ];
		}

		$preset_variable_ids = self::get_preset_global_variable_ids( $content );

		$cache[ $cache_key ] = array_values( array_unique( array_merge( $content_variable_ids, $preset_variable_ids ) ) );

		return $cache[ $cache_key ];
	}

	/**
	 * Check if content contains Contact Form or Signup modules with reCaptcha enabled.
	 *
	 * This checks for spam protection settings in both D5 JSON format and D4 shortcode format.
	 * For D5, it uses SimpleBlockParser to find contact-form and signup blocks, then validates
	 * that reCAPTCHA is enabled with a valid account:
	 * - enabled="on" AND provider="recaptcha" AND account != "0|none" AND useBasicCaptcha != "on"
	 *
	 * Note: Basic captcha and reCAPTCHA are mutually exclusive - if useBasicCaptcha="on",
	 * reCAPTCHA detection will not trigger even if other conditions are met.
	 *
	 * @since ??
	 *
	 * @param string $content The content string to search for reCaptcha-enabled modules.
	 * @param array  $options Optional parameters for detection.
	 *
	 * @return bool True if reCaptcha-enabled modules found, false otherwise.
	 */
	public static function has_recaptcha_enabled( string $content, array $options = [] ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Parameter reserved for future use.
		// Bail early if content is empty or doesn't contain relevant patterns.
		if ( empty( $content ) ) {
			return false;
		}

		// Quick check for common patterns before doing expensive parsing operations.
		if ( ! str_contains( $content, 'spamProtection' ) && ! str_contains( $content, 'use_spam_service' ) ) {
			return false;
		}

		// Check for D5 blocks with reCAPTCHA enabled using SimpleBlockParser.
		// Parse contact-form and signup blocks, then filter for valid reCAPTCHA configuration.
		$recaptcha_blocks = SimpleBlockParser::parse(
			$content,
			[
				'limit'        => 1,
				'excludeError' => true,
				'blockName'    => 'divi/contact-form,divi/signup',
				'filter'       => function ( SimpleBlock $block ) {
					$attrs = $block->attrs();

					// Get spam protection settings from the desktop breakpoint.
					$spam_protection = $attrs['module']['advanced']['spamProtection']['desktop']['value'] ?? [];

					// Check all required conditions for reCAPTCHA detection:
					// 1. enabled must be "on"
					// 2. provider must be "recaptcha" (defaults to "recaptcha" when not specified)
					// 3. account must be set and not "0|none" (0|none is the default account).
					$enabled  = $spam_protection['enabled'] ?? 'off';
					$provider = $spam_protection['provider'] ?? 'recaptcha'; // Default to recaptcha when not specified.
					$account  = $spam_protection['account'] ?? '0|none'; // Default to 0|none when not specified.

					return 'on' === $enabled
						&& 'recaptcha' === $provider
						&& ! empty( $account )
						&& '0|none' !== $account;
				},
			]
		);

		if ( $recaptcha_blocks->count() > 0 ) {
			return true;
		}

		// Check for D4 shortcode format: use_spam_service="on".
		if ( str_contains( $content, 'use_spam_service="on"' ) ) {
			return true;
		}

		return false;
	}
}
