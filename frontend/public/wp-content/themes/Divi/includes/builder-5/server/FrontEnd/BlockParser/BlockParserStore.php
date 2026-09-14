<?php
/**
 * Class BlockParserStore
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\FrontEnd\BlockParser;

// phpcs:disable ET.ValidVariableName.PropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\BlockParser\BlockParserBlock;
use ET\Builder\FrontEnd\BlockParser\OrderIndexResetManager;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Packages\Conversion\Conversion;
use ET\Builder\Packages\Module\Options\Conditions\ConditionsRenderer;
use WP_Block;

/**
 * Class BlockParserStore
 *
 * Holds the block structure in memory as flatten associative array. This class is counterparts of EditPostStore in VB, with a slight
 * difference that this class can have multiple instances. A new store instance will be created when `do_blocks` function is invoked. This is intended to prevent
 * the data for previous call of `do_blocks` get overridden by a later call of `do_blocks`.
 * Each item stored in the store will have a `storeInstance` property that hold the data to which store instance is the item belongs to.
 *
 * @since ??
 */
class BlockParserStore {

	/**
	 * Flag indicating whether the block parser is currently rendering inner content.
	 *
	 * This static property tracks the parsing state to help prevent infinite loops
	 * and ensure proper behavior when processing nested blocks. It's used by
	 * the block parser to know when it's in the middle of processing inner content
	 * so it can avoid reprocessing blocks that are already being handled.
	 *
	 * @since ??
	 * @var bool
	 */
	private static $_rendering_inner_content = false;

	/**
	 * Flag indicating whether the order index has been reset during this page render.
	 *
	 * This static property ensures that order indexes are only reset once per page render,
	 * preventing multiple resets that could cause incorrect order index numbering.
	 *
	 * @since ??
	 * @var bool
	 */
	private static $_has_reset_order_index = false;

	/**
	 * Check if currently rendering inner content.
	 *
	 * Returns the current state of the inner content rendering flag. This is used
	 * to determine whether the block parser is in the middle of processing inner
	 * content, which helps prevent infinite loops and ensures proper parsing behavior.
	 *
	 * @since ??
	 * @return bool True if currently rendering inner content, false otherwise.
	 */
	public static function is_rendering_inner_content(): bool {
		return self::$_rendering_inner_content;
	}

	/**
	 * Set the inner content rendering state.
	 *
	 * Controls whether the block parser is currently rendering inner content. This flag
	 * is used to track parsing state and help prevent infinite loops or incorrect parsing
	 * behavior when processing nested blocks.
	 *
	 * @since ??
	 * @param bool $state True if currently rendering inner content, false otherwise.
	 * @return void
	 */
	public static function set_rendering_inner_content( bool $state ): void {
		self::$_rendering_inner_content = $state;
	}

	/**
	 * Renders inner content by applying WordPress the_content filter while setting appropriate internal state.
	 *
	 * This method temporarily enables inner content rendering state, applies the 'the_content' filter
	 * to the provided content, then disables the inner content rendering state. This is used for
	 * rendering content within blocks while ensuring that the rendering state is properly tracked
	 * by the BlockParserStore instance.
	 *
	 * The inner content rendering state is used by other methods in this class to determine
	 * whether they are currently processing content that's inside a block, which may affect
	 * how content is parsed or rendered.
	 *
	 * @since ??
	 *
	 * @param string $content The content to render. This content will be processed through
	 *                       WordPress's 'the_content' filter chain.
	 *
	 * @return string The rendered content after applying WordPress content filters.
	 *
	 * @example
	 * ```php
	 * $raw_content   = 'Some content with shortcodes and HTML';
	 * $rendered_html = BlockParserStore::render_inner_content( $raw_content );
	 * // $rendered_html now contains processed HTML with shortcodes expanded
	 * ```
	 */
	public static function render_inner_content( string $content ): string {
		self::set_rendering_inner_content( true );

		try {
			$content = apply_filters( 'the_content', $content );
		} finally {
			self::set_rendering_inner_content( false );
		}

		return $content;
	}

	/**
	 * Add root block.
	 *
	 * Root is a read-only and unique block. It can only to be added using this method.
	 * The `innerBlocks` data will be populated when calling `BlockParserStore::get('divi/root')`.
	 *
	 * @since ??
	 *
	 * @param int $instance Optional. The instance of the store you want to use. Default `null`.
	 *
	 * @return void
	 */
	protected static function _add_root( $instance = null ) {
		$use_instance = self::_use_instance( $instance );

		self::$_data[ $use_instance ]['divi/root'] = new BlockParserBlockRoot( $use_instance );
	}


	/**
	 * Add item to store.
	 *
	 * @since ??
	 *
	 * @param BlockParserBlock $block The block object.
	 *
	 * @return BlockParserBlock
	 */
	public static function add( BlockParserBlock $block ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
		if ( ! $block->blockName ) {
			return $block;
		}

		if ( self::_is_root( $block->id ) || self::has( $block->id, $block->storeInstance ) ) {
			return $block;
		}

		self::$_data[ self::_use_instance( $block->storeInstance ) ][ $block->id ] = $block;

		return $block;
	}


	/**
	 * Find the ancestor of existing item in the store.
	 *
	 * @since ??
	 *
	 * @param string   $child_id The unique ID of the child block.
	 * @param callable $matcher  Callable function that will be invoked to determine if the ancestor is match.
	 * @param int      $instance Optional. The instance of the store you want to use. Default `null`.
	 *
	 * @return BlockParserBlock|null
	 */
	public static function find_ancestor( $child_id, $matcher, $instance = null ) {
		return ArrayUtility::find( self::get_ancestors( $child_id, $instance ), $matcher );
	}


	/**
	 * Get all of existing items in the store.
	 *
	 * @since ??
	 *
	 * @param int $instance The instance of the store you want to use.
	 *
	 * @return BlockParserBlock[]
	 */
	public static function get_all( $instance = null ) {
		$all_blocks = self::$_data[ self::_use_instance( $instance ) ] ?? [];

		if ( isset( $all_blocks['divi/root'] ) ) {
			$all_blocks['divi/root'] = self::get( 'divi/root', $instance );
		}

		return $all_blocks;
	}

	/**
	 * Get the ancestors of existing item in the store.
	 *
	 * @since ??
	 *
	 * @param string $child_id The unique ID of the child block.
	 * @param int    $instance Optional. The instance of the store you want to use. Default `null`.
	 *
	 * @return BlockParserBlock[] An array of ancestors sorted from bottom to the very top level of the structure tree.
	 */
	public static function get_ancestors( $child_id, $instance = null ) {
		$ancestors = [];
		$parent    = self::get_parent( $child_id, $instance );

		while ( $parent ) {
			$ancestors[] = $parent;

			$parent = self::get_parent( $parent->id, $instance );
		}

		return $ancestors;
	}

	/**
	 * Get the ancestor ids of existing item in the store.
	 *
	 * @since ??
	 *
	 * @param string   $child_id The unique ID of the child block.
	 * @param int|null $instance Optional. The instance of the store you want to use. Default `null`.
	 *
	 * @return BlockParserBlock[] An array of ancestors sorted from bottom to the very top level of the structure tree.
	 */
	public static function get_ancestor_ids( string $child_id, $instance = null ): array {
		$ancestors = [];
		$parent    = self::get_parent( $child_id, $instance );

		while ( $parent ) {
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
			if ( isset( $parent->blockName ) && 'divi/placeholder' !== $parent->blockName ) {
				$ancestors[] = $parent->id;
			}

			$parent = self::get_parent( $parent->id, $instance );
		}

		return $ancestors;
	}

	/**
	 * Get the ancestor of existing item in the store.
	 *
	 * @since ??
	 *
	 * @param string   $child_id The unique ID of the child block.
	 * @param callable $matcher  Optional.
	 *                           Callable function that will be invoked to determine to return early if it returns a `true`.
	 *                           If not provided, it will match up to the very top level of the structure tree.
	 *                           Default `null`.
	 * @param int      $instance Optional. The instance of the store you want to use. Default `null`.
	 *
	 * @return BlockParserBlock|null
	 */
	public static function get_ancestor( $child_id, $matcher = null, $instance = null ) {
		$ancestor = null;
		$parent   = self::get_parent( $child_id, $instance );

		while ( $parent ) {
			$ancestor = $parent;

			if ( is_callable( $matcher ) && true === call_user_func( $matcher, $ancestor ) ) {
				return $ancestor;
			}

			$parent = self::get_parent( $ancestor->id, $instance );
		}

		return $ancestor;
	}


	/**
	 * Get an array of all the children of a given block.
	 *
	 * @since ??
	 *
	 * @param string $id       The id of the block you want to get.
	 * @param int    $instance Optional. The instance of the store you want to use. Default `null`.
	 *
	 * @return BlockParserBlock[] An array of the children of the block.
	 */
	public static function get_children( $id, $instance = null ) {
		$current = self::get( $id, $instance );

		if ( ! $current ) {
			return [];
		}

		$inner_blocks = [];
		$all_blocks   = self::$_data[ self::_use_instance( $instance ) ];

		foreach ( $all_blocks as $block ) {
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
			if ( $block->parentId === $current->id ) {
				$inner_blocks[] = self::get( $block->id, $instance );
			}
		}

		if ( 1 < count( $inner_blocks ) ) {
			usort(
				$inner_blocks,
				function ( BlockParserBlock $a, BlockParserBlock $b ) {
					if ( $a->index === $b->index ) {
						return 0;
					}

					return ( $a->index < $b->index ) ? -1 : 1;
				}
			);
		}

		return $inner_blocks;
	}

	/**
	 * Apply selective filtering based on localAttrsMap.
	 *
	 * This implements the new snapshot-based architecture where:
	 * 1. localAttrs contains ALL attributes (complete snapshot)
	 * 2. Template's localAttrsMap determines which attributes to use from snapshot
	 * 3. Provides temporal stability - pages preserve state even if localAttrsMap changes
	 *
	 * @since ??
	 *
	 * @param array $template_attrs Template attributes (base), including localAttrsMap.
	 * @param array $local_attrs    Complete attribute snapshot from serialized block.
	 *
	 * @return array Merged attributes with selective filtering applied.
	 */
	public static function apply_local_attrs_filtering( array $template_attrs, array $local_attrs ): array {
		// Start with template attributes as base.
		$merged_attrs = $template_attrs;

		// Get localAttrsMap from template (source of truth).
		$local_attrs_map = $template_attrs['localAttrsMap'] ?? [];

		// If no localAttrsMap, return template attributes unchanged.
		if ( empty( $local_attrs_map ) ) {
			return $merged_attrs;
		}

		// Apply selective filtering based on localAttrsMap.
		foreach ( $local_attrs_map as $map_entry ) {
			$attr_name = $map_entry['attrName'] ?? '';
			if ( empty( $attr_name ) ) {
				continue;
			}

			// Convert dot notation to array path.
			$attr_path = explode( '.', $attr_name );

			// Handle subName (granular filtering).
			if ( isset( $map_entry['subName'] ) && ! empty( $map_entry['subName'] ) ) {
				// attrName represents path UP TO breakpoint level (e.g., "module.decoration.background")
				// subName represents path WITHIN desktop.value structure (e.g., "color")
				// Full path in localAttrs is: attrName.desktop.value.subName.
				$sub_name_parts = explode( '.', $map_entry['subName'] );

				// Get the attribute object from localAttrs (includes breakpoint/mode structure).
				$attr_object = ArrayUtility::get_value_by_array_path( $local_attrs, $attr_path );

				if ( $attr_object && is_array( $attr_object ) ) {
					// Iterate through breakpoints and modes to apply subName value.
					foreach ( $attr_object as $breakpoint => $breakpoint_value ) {
						if ( $breakpoint_value && is_array( $breakpoint_value ) ) {
							foreach ( $breakpoint_value as $mode => $mode_value ) {
								if ( $mode_value && is_array( $mode_value ) ) {
									// Get value at subName path within this breakpoint/mode.
									$sub_value = ArrayUtility::get_value_by_array_path( $mode_value, $sub_name_parts );

									if ( null !== $sub_value ) {
										// Set in merged attrs at: attrPath.breakpoint.mode.subPath.
										$full_path    = array_merge( $attr_path, [ $breakpoint, $mode ], $sub_name_parts );
										$merged_attrs = ArrayUtility::set_value( $merged_attrs, $full_path, $sub_value );
									}
								}
							}
						}
					}
				}
			} else {
				// Use entire attribute from snapshot.
				$snapshot_value = ArrayUtility::get_value_by_array_path( $local_attrs, $attr_path );

				if ( null !== $snapshot_value ) {
					$merged_attrs = ArrayUtility::set_value( $merged_attrs, $attr_path, $snapshot_value );
				}
			}
		}

		return $merged_attrs;
	}

	/**
	 * Simple recursive merge of local attributes into template attributes.
	 *
	 * Used specifically for loop processing where ALL localAttrs need to be merged
	 * before parsing, not just those in localAttrsMap.
	 *
	 * @since ??
	 *
	 * @param array $attrs       Template attributes to merge into.
	 * @param array $local_attrs Local attributes to merge.
	 *
	 * @return array Merged attributes.
	 */
	private static function _recursive_merge_attrs( array $attrs, array $local_attrs ): array {
		foreach ( $local_attrs as $key => $value ) {
			if ( is_array( $value ) && isset( $attrs[ $key ] ) && is_array( $attrs[ $key ] ) ) {
				$attrs[ $key ] = self::_recursive_merge_attrs( $attrs[ $key ], $value );
			} else {
				$attrs[ $key ] = $value;
			}
		}

		return $attrs;
	}

	/**
	 * Filter out whitespace-only blocks that WordPress parse_blocks() creates.
	 *
	 * WordPress parse_blocks() creates blocks with null blockName for whitespace/newlines between blocks.
	 * When these get normalized and serialized, they become empty <!-- wp: --> blocks in rendered output.
	 * This method filters them out to prevent rendering artifacts.
	 *
	 * @since ??
	 *
	 * @param array $blocks Array of parsed blocks from parse_blocks().
	 * @return array Filtered blocks with whitespace-only blocks removed.
	 */
	public static function _filter_whitespace_blocks( array $blocks ): array {
		return array_values(
			array_filter(
				$blocks,
				function ( $block ) {
					// Keep blocks that have a blockName.
					if ( ! empty( $block['blockName'] ) ) {
						return true;
					}

					// Remove blocks with no blockName that only contain whitespace.
					if ( empty( $block['blockName'] ) && isset( $block['innerHTML'] ) ) {
						return ! empty( trim( $block['innerHTML'] ) );
					}

					return false;
				}
			)
		);
	}

	/**
	 * Parse blocks with error recovery to handle malformed block structures.
	 *
	 * WordPress parse_blocks() can fail when encountering malformed blocks,
	 * causing all subsequent blocks to be lost. This method attempts to recover
	 * by extracting valid block comments individually and parsing them separately.
	 *
	 * @since ??
	 *
	 * @param string $content The content to parse.
	 * @return array Parsed blocks array, with recovery for malformed blocks.
	 */
	private static function _parse_blocks_with_error_recovery( string $content ): array {
		// First try normal parsing.
		$blocks = self::_filter_whitespace_blocks( parse_blocks( $content ) );

		// If we got multiple valid blocks, return them.
		if ( count( $blocks ) > 1 ) {
			// Filter out any empty blocks.
			return array_filter(
				$blocks,
				function ( $block ) {
					return ! empty( $block['blockName'] ) || ! empty( trim( $block['innerHTML'] ?? '' ) );
				}
			);
		}

		// If we have one valid named block, return it.
		if ( count( $blocks ) === 1 && ! empty( $blocks[0]['blockName'] ) ) {
			return $blocks;
		}

		// If parsing failed, use original blocks as-is.
		// Malformed blocks naturally break parsing, which is expected behavior.
		return $blocks;
	}

	/**
	 * Collapse consecutive duplicate placeholder wrappers into a single wrapper.
	 *
	 * Some imported content can contain:
	 * - `<!-- wp:divi/placeholder --><!-- wp:divi/placeholder -->`
	 * - `<!-- /wp:divi/placeholder --><!-- /wp:divi/placeholder -->`
	 *
	 * This method reduces these duplicated open/close markers while preserving the
	 * wrapped module content.
	 *
	 * @since ??
	 *
	 * @param string $content Serialized block content.
	 * @return string Content with duplicate placeholder wrappers collapsed.
	 */
	private static function _collapse_duplicate_placeholder_wrappers( string $content ): string {
		$normalized = $content;

		do {
			$previous            = $normalized;
			$normalized_openings = preg_replace(
				'/(<!--\s*wp:divi\/placeholder\b[^>]*-->)(?:\s*<!--\s*wp:divi\/placeholder\b[^>]*-->)+/s',
				'$1',
				$normalized
			);
			$normalized_closings = null;

			// Keep original content if preg_replace fails to avoid dropping global module content.
			if ( null === $normalized_openings ) {
				return $content;
			}

			$normalized_closings = preg_replace(
				'/(<!--\s*\/wp:divi\/placeholder\s*-->)(?:\s*<!--\s*\/wp:divi\/placeholder\s*-->)+/s',
				'$1',
				$normalized_openings
			);

			// Keep original content if preg_replace fails to avoid dropping global module content.
			if ( null === $normalized_closings ) {
				return $content;
			}

			$normalized = $normalized_closings;
		} while ( $previous !== $normalized );

		return $normalized;
	}

	/**
	 * Expand placeholder-wrapped global module content for WordPress block parsing compatibility.
	 *
	 * Global modules are intentionally stored with placeholder wrappers around content blocks.
	 * However, WordPress parse_blocks() treats this as a single block with innerHTML instead
	 * of separate blocks, preventing proper selective sync attribute extraction.
	 *
	 * Transforms: <!-- wp:divi/placeholder --><!-- wp:divi/text {...} /--><!-- /wp:divi/placeholder -->
	 * Into:       <!-- wp:divi/placeholder /-->
	 *
	 *             <!-- wp:divi/text {...} -->
	 *             <p>Content here</p>
	 *             <!-- /wp:divi/text -->
	 *
	 * @since ??
	 *
	 * @param string $content The placeholder-wrapped block content.
	 * @return string The expanded block content with separate blocks.
	 */
	public static function _expand_placeholder_wrapped_blocks( string $content ): string {
		$content = self::_collapse_duplicate_placeholder_wrappers( $content );

		// Pattern to match placeholder wrapper with any inner content.
		$pattern = '/<!--\s*wp:divi\/placeholder\b[^>]*-->(.+?)<!--\s*\/wp:divi\/placeholder\s*-->/s';

		if ( preg_match( $pattern, $content, $matches ) ) {
			$inner_content = trim( $matches[1] );

			// FIX: Handle malformed blocks by trying to recover and continue parsing.
			$parsed_blocks = self::_parse_blocks_with_error_recovery( $inner_content );

			// Start with placeholder block.
			$fixed_content = "<!-- wp:divi/placeholder /-->\n\n";

			// Process each block found inside the placeholder.
			foreach ( $parsed_blocks as $block ) {
				if ( empty( $block['blockName'] ) ) {
					// Handle free-form content (HTML between blocks).
					if ( ! empty( trim( $block['innerHTML'] ) ) ) {
						$fixed_content .= trim( $block['innerHTML'] ) . "\n";
					}
					continue;
				}

				// Reconstruct block with proper WordPress block format.
				$block_name  = $block['blockName'];
				$block_attrs = ! empty( $block['attrs'] ) ? wp_json_encode( $block['attrs'] ) : '{}';

				// Extract innerHTML content for blocks that have it.
				$inner_html = '';
				if ( isset( $block['attrs']['content']['innerContent']['desktop']['value'] ) ) {
					$inner_content_value = $block['attrs']['content']['innerContent']['desktop']['value'];
					if ( is_array( $inner_content_value ) ) {
						$inner_content_value = $inner_content_value['text'] ?? '';
					}
					if ( is_string( $inner_content_value ) ) {
						// Use safe entity decoding to prevent XSS security vulnerability.
						// This decodes WordPress block entities (&quot;, &amp;, etc.) but NOT dangerous ones (&lt;, &gt;).
						// See security review: https://github.com/elegantthemes/submodule-builder-5/pull/6776#discussion_r2434267097.
						// See: https://github.com/elegantthemes/Divi/issues/9664.
						$inner_html = HTMLUtility::decode_wordpress_block_entities( $inner_content_value );
					}
				}
				// If the attribute value was present but could not be normalized to a usable string
				// (e.g. an object with no 'text' key), fall back to the block's parsed innerHTML so
				// that blocks already stored in non-self-closing format are not silently dropped.
				if ( '' === $inner_html && ! empty( $block['innerHTML'] ) ) {
					$inner_html = $block['innerHTML'];
				}

				// Trim and check if innerHTML is actually meaningful content.
				$has_content = ! empty( trim( $inner_html ) );

				// Check if this block has child modules (inner blocks).
				if ( ! empty( $block['innerBlocks'] ) ) {
					// Parent block with child modules - use full block format.
					$fixed_content .= "<!-- wp:{$block_name} {$block_attrs} -->\n";
					// Serialize child blocks with proper formatting.
					$serialized_children = serialize_blocks( $block['innerBlocks'] );
					// Ensure each child block is on its own line.
					$serialized_children = str_replace( '/--><!-- wp:', "/-->\n<!-- wp:", $serialized_children );
					// Add children without trailing newline to avoid innerHTML pollution.
					$fixed_content .= rtrim( $serialized_children );
					$fixed_content .= "\n<!-- /wp:{$block_name} -->";
				} elseif ( $has_content ) {
					// Block with meaningful innerHTML content - use full block format.
					$fixed_content .= "<!-- wp:{$block_name} {$block_attrs} -->\n";
					$fixed_content .= $inner_html . "\n";
					$fixed_content .= "<!-- /wp:{$block_name} -->";
				} else {
					// Self-closing block (no content).
					$fixed_content .= "<!-- wp:{$block_name} {$block_attrs} /-->";
				}
			}

			return $fixed_content;
		}

		// Return original content if no placeholder wrapper pattern found.
		return $content;
	}


	/**
	 * Check if a variable syntax string contains a loop variable (not a global variable).
	 *
	 * Loop variables are like: loop_post_title, loop_post_excerpt, loop_post_content, etc.
	 * Global variables are like: gcid-xxx (global colors), gvid-xxx (global variables), etc.
	 *
	 * @since ??
	 *
	 * @param string $value The value to check.
	 * @return bool True if the value contains a loop variable, false otherwise.
	 */
	private static function _contains_loop_variable( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		// Check if value contains $variable() syntax with loop variable name.
		if ( ! str_contains( $value, '$variable(' ) ) {
			return false;
		}

		// Extract the variable name from JSON structure.
		// The pattern matches variable names in $variable() syntax, such as loop_post_title or gcid-xxx.
		if ( preg_match( '/"name"\s*:\s*"([^"]+)"/', $value, $matches ) ) {
			$variable_name = $matches[1] ?? '';
			// Check if it's a loop variable (starts with "loop_").
			return str_starts_with( $variable_name, 'loop_' );
		}

		return false;
	}

	/**
	 * Find attributes in localAttrs that override loop variables in template (not global variables).
	 *
	 * When loop is disabled, we need to inject attributes that replace loop variables
	 * (like loop_post_title) with static content, but we should NOT inject attributes
	 * that override global variables (like global colors).
	 *
	 * @since ??
	 *
	 * @param array $template_attrs Template attributes to check for loop variables.
	 * @param array $local_attrs    Local attributes that may override template attributes.
	 * @param array $current_path   Current path in the attribute tree (for recursion).
	 * @return array Array of paths to attributes that should be included, keyed by dot-notation path.
	 */
	private static function _find_loop_variable_overrides( array $template_attrs, array $local_attrs, array $current_path = [] ): array {
		$result = [];

		foreach ( $local_attrs as $key => $local_value ) {
			// Skip loop builder (already handled separately).
			if ( 'module' === $key && isset( $local_value['advanced']['loop'] ) ) {
				continue;
			}

			$template_value = $template_attrs[ $key ] ?? null;
			$new_path       = array_merge( $current_path, [ $key ] );

			if ( null === $template_value ) {
				// Template doesn't have this key, skip.
				continue;
			}

			if ( is_array( $local_value ) && is_array( $template_value ) ) {
				// Both are arrays, recurse into nested structure.
				$nested_result = self::_find_loop_variable_overrides( $template_value, $local_value, $new_path );
				$result        = array_merge( $result, $nested_result );
			} elseif ( self::_contains_loop_variable( $template_value ) ) {
				// Template has loop variable syntax, include localAttrs override at this path.
				$path_key            = implode( '.', $new_path );
				$result[ $path_key ] = $local_value;
			}
		}

		return $result;
	}

	/**
	 * Filter localAttrs based on localAttrsMap, returning only attributes that should be injected.
	 *
	 * This implements selective filtering similar to apply_local_attrs_filtering(), but returns
	 * a filtered subset of localAttrs instead of merged attributes. Used before injection to
	 * ensure only mapped attributes are injected into template content.
	 *
	 * Loop builder settings (module.advanced.loop) are always included, even when localAttrsMap
	 * is empty, because they must be merged before parsing to affect loop execution.
	 *
	 * When localAttrsMap is empty (fully synced), only loop builder settings are returned.
	 * Template values should be used exclusively for all other attributes.
	 *
	 * @since ??
	 *
	 * @param array $local_attrs       Complete attribute snapshot from serialized block.
	 * @param array $local_attrs_map   localAttrsMap from template (source of truth).
	 * @param array $template_attrs   Template attributes (unused, kept for backward compatibility).
	 *
	 * @return array Filtered localAttrs containing attributes in localAttrsMap plus loop builder settings.
	 */
	private static function _filter_local_attrs_by_map( array $local_attrs, array $local_attrs_map, array $template_attrs = [] ): array {
		$filtered_attrs = [];

		// Always include loop builder settings, even when localAttrsMap is empty.
		// Loop builder settings must be injected before parsing to affect loop execution.
		$loop_attrs = ArrayUtility::get_value_by_array_path( $local_attrs, [ 'module', 'advanced', 'loop' ] );
		if ( null !== $loop_attrs ) {
			$filtered_attrs = ArrayUtility::set_value( $filtered_attrs, [ 'module', 'advanced', 'loop' ], $loop_attrs );
		}

		// If no localAttrsMap, check if loop is disabled and inject attributes that override loop variables.
		// When fully synced, template values should be used exclusively (no injection from localAttrs),
		// except when loop is disabled - in that case, we need to inject attributes that replace loop variables.
		if ( empty( $local_attrs_map ) ) {
			// Check if loop is disabled in localAttrs.
			if ( null !== $loop_attrs && is_array( $loop_attrs ) ) {
				$loop_enable = ArrayUtility::get_value_by_array_path( $loop_attrs, [ 'desktop', 'value', 'enable' ] );
				if ( 'off' === $loop_enable ) {
					// When loop is disabled, inject attributes that override loop variables in template.
					// This allows static content to replace loop variables when loop is turned off.
					// But we should NOT inject attributes that override global variables (like global colors).
					$loop_variable_overrides = self::_find_loop_variable_overrides( $template_attrs, $local_attrs );
					foreach ( $loop_variable_overrides as $path_str => $value ) {
						$path           = explode( '.', $path_str );
						$filtered_attrs = ArrayUtility::set_value( $filtered_attrs, $path, $value );
					}
				}
			}

			return $filtered_attrs;
		}

		// Apply selective filtering based on localAttrsMap.
		foreach ( $local_attrs_map as $map_entry ) {
			$attr_name = $map_entry['attrName'] ?? '';
			if ( empty( $attr_name ) ) {
				continue;
			}

			// Convert dot notation to array path.
			$attr_path = explode( '.', $attr_name );

			// Handle subName (granular filtering).
			if ( isset( $map_entry['subName'] ) && ! empty( $map_entry['subName'] ) ) {
				// attrName represents path UP TO breakpoint level (e.g., "module.decoration.background")
				// subName represents path WITHIN desktop.value structure (e.g., "color")
				// Full path in localAttrs is: attrName.desktop.value.subName.
				$sub_name_parts = explode( '.', $map_entry['subName'] );

				// Get the attribute object from localAttrs (includes breakpoint/mode structure).
				$attr_object = ArrayUtility::get_value_by_array_path( $local_attrs, $attr_path );

				if ( $attr_object && is_array( $attr_object ) ) {
					// Iterate through breakpoints and modes to extract subName value.
					foreach ( $attr_object as $breakpoint => $breakpoint_value ) {
						if ( $breakpoint_value && is_array( $breakpoint_value ) ) {
							foreach ( $breakpoint_value as $mode => $mode_value ) {
								if ( $mode_value && is_array( $mode_value ) ) {
									// Get value at subName path within this breakpoint/mode.
									$sub_value = ArrayUtility::get_value_by_array_path( $mode_value, $sub_name_parts );

									if ( null !== $sub_value ) {
										// Set in filtered attrs at: attrPath.breakpoint.mode.subPath.
										$full_path      = array_merge( $attr_path, [ $breakpoint, $mode ], $sub_name_parts );
										$filtered_attrs = ArrayUtility::set_value( $filtered_attrs, $full_path, $sub_value );
									}
								}
							}
						}
					}
				}
			} else {
				// Use entire attribute from snapshot.
				$snapshot_value = ArrayUtility::get_value_by_array_path( $local_attrs, $attr_path );

				if ( null !== $snapshot_value ) {
					$filtered_attrs = ArrayUtility::set_value( $filtered_attrs, $attr_path, $snapshot_value );
				}
			}
		}

		return $filtered_attrs;
	}

	/**
	 * Inject local attributes into template content by modifying the JSON in block comments.
	 * This must happen BEFORE parsing so loop settings are merged before loop execution.
	 *
	 * Filters localAttrs based on template's localAttrsMap before injection. When localAttrsMap
	 * is empty (fully synced), skips injection entirely to prevent old attribute values from
	 * being injected when they shouldn't be.
	 *
	 * @param string $content Template content.
	 * @param array  $local_attrs Local attributes to merge.
	 * @return string Modified content with localAttrs injected, or original content if no injection needed.
	 */
	private static function _inject_local_attrs_into_content( string $content, array $local_attrs ) {
		// Find ALL blocks and pick the first non-placeholder one.
		if ( ! preg_match_all( '/<!-- wp:divi\/([a-z]+)\s+/', $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
			return $content;
		}

		$target_match = null;
		foreach ( $matches as $match ) {
			$block_name = $match[1][0];
			if ( 'placeholder' !== $block_name ) {
				$target_match = $match;
				break;
			}
		}

		if ( ! $target_match ) {
			return $content;
		}

		$start_pos = $target_match[0][1] + strlen( $target_match[0][0] );

		$content_length = strlen( $content );

		// Extract the existing JSON.
		if ( $start_pos >= $content_length || '{' !== $content[ $start_pos ] ) {
			return $content;
		}

		$brace_count = 0;
		$json_start  = $start_pos;
		$in_string   = false;
		$escape_next = false;

		for ( $i = $start_pos; $i < $content_length; $i++ ) {
			$char = $content[ $i ];

			if ( $escape_next ) {
				$escape_next = false;
				continue;
			}

			if ( '\\' === $char ) {
				$escape_next = true;
				continue;
			}

			if ( '"' === $char ) {
				$in_string = ! $in_string;
			}

			if ( ! $in_string ) {
				if ( '{' === $char ) {
					++$brace_count;
				} elseif ( '}' === $char ) {
					--$brace_count;
					if ( 0 === $brace_count ) {
						// Found the JSON.
						$json_str = substr( $content, $json_start, $i - $json_start + 1 );
						$attrs    = json_decode( $json_str, true );

						if ( ! is_array( $attrs ) ) {
							return $content;
						}

						// Extract localAttrsMap from template attributes to determine which attributes to inject.
						$local_attrs_map = $attrs['localAttrsMap'] ?? [];

						// Filter localAttrs to only include attributes in localAttrsMap.
						// Loop builder settings are always included (even when localAttrsMap is empty)
						// because they must be merged before parsing to affect loop execution.
						// When localAttrsMap is empty, attributes that override template attributes
						// containing $variable() syntax are also included.
						$filtered_local_attrs = self::_filter_local_attrs_by_map( $local_attrs, $local_attrs_map, $attrs );

						// Only inject if there are filtered attributes to inject.
						// This preserves the original intent (fixing loop builder with selective sync)
						// while preventing unintended attribute injection for fully synced Global Sections.
						if ( empty( $filtered_local_attrs ) ) {
							return $content;
						}

						// Merge filtered attributes with template attributes.
						$merged_attrs     = self::_recursive_merge_attrs( $attrs, $filtered_local_attrs );
						$new_json         = wp_json_encode( $merged_attrs );
						$modified_content = substr( $content, 0, $json_start ) . $new_json . substr( $content, $i + 1 );
						return $modified_content;
					}
				}
			}
		}

		return $content;
	}

	/**
	 * Reset order indexes data
	 *
	 * Resets all module order indexes using the central module order manager.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function reset_order_index() {
		$layout_type = self::get_layout_type();

		// Use the central module order manager.
		\ET_Builder_Module_Order::reset_indexes( $layout_type );
	}

	/**
	 * Check if blocks have already been parsed in any store instance.
	 *
	 * This method checks if there are any blocks (other than root blocks) in any
	 * store instance, indicating that blocks have already been parsed.
	 *
	 * @since ??
	 *
	 * @return bool True if blocks have been parsed, false otherwise.
	 */
	public static function has_parsed_blocks() {
		// PERFORMANCE: Optimize for the common case - pages typically have blocks,
		// so we want to return true as fast as possible.
		foreach ( self::$_data as $instance_blocks ) {
			// Skip empty instances immediately (common after instance creation).
			if ( empty( $instance_blocks ) ) {
				continue;
			}

			// PERFORMANCE: Quick check - if there's more than one block, at least one must be non-root.
			// This is the most common case and avoids iterating through blocks.
			$block_count = count( $instance_blocks );
			if ( $block_count > 1 ) {
				return true;
			}

			// Only one block - check if it's non-root.
			// PERFORMANCE: Use foreach directly to avoid array_keys() overhead.
			foreach ( $instance_blocks as $block_id => $block ) {
				if ( 'divi/root' !== $block_id ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if order index has been reset during this page render.
	 *
	 * @since ??
	 *
	 * @return bool True if order index has been reset, false otherwise.
	 */
	public static function has_reset_order_index() {
		return self::$_has_reset_order_index;
	}

	/**
	 * Set the order index reset flag.
	 *
	 * This is used internally by OrderIndexResetManager to track reset state.
	 *
	 * @since ??
	 *
	 * @param bool $value The value to set for the reset flag.
	 * @return void
	 */
	public static function set_has_reset_order_index( $value ) {
		self::$_has_reset_order_index = $value;
	}

	/**
	 * Check if blocks have been parsed for a specific layout type in the current instance.
	 *
	 * This is used by OrderIndexResetManager to determine if blocks were parsed
	 * after a reset occurred in the same store instance.
	 *
	 * PERFORMANCE: This method loops through all blocks in the current instance.
	 * Results should be cached when called multiple times for the same layout type.
	 *
	 * @since ??
	 *
	 * @param string $layout_type The layout type to check.
	 * @return bool True if blocks exist for this layout type in current instance, false otherwise.
	 */
	public static function has_blocks_for_layout_type( $layout_type ) {
		// PERFORMANCE: Early return if no current instance or it's empty.
		if ( ! isset( self::$_data[ self::$_instance ] ) || empty( self::$_data[ self::$_instance ] ) ) {
			return false;
		}

		$instance_blocks = self::$_data[ self::$_instance ];

		// PERFORMANCE: Quick check - if there's only one block and it's root, return false immediately.
		$block_count = count( $instance_blocks );
		if ( 1 === $block_count && isset( $instance_blocks['divi/root'] ) ) {
			return false;
		}

		// Iterate through blocks to find a match.
		// PERFORMANCE: Use foreach directly instead of array operations to avoid creating intermediate arrays.
		foreach ( $instance_blocks as $block_id => $block ) {
			// Skip root blocks early (most common case).
			if ( 'divi/root' === $block_id ) {
				continue;
			}

			// Check if this block has the layout type we're looking for.
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Matches WordPress block parser conventions.
			if ( isset( $block->layout_type ) && $layout_type === $block->layout_type ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if blocks have been parsed for a specific layout type across all store instances.
	 *
	 * This is used by OrderIndexResetManager to detect if blocks were parsed during CSS generation
	 * in a previous store instance, which would require a reset before HTML rendering.
	 *
	 * PERFORMANCE: This method is expensive as it loops through all store instances and all blocks.
	 * It should only be called when absolutely necessary, and results should be cached.
	 *
	 * @since ??
	 *
	 * @param string $layout_type The layout type to check.
	 * @return bool True if blocks exist for this layout type in any instance, false otherwise.
	 */
	public static function has_blocks_for_layout_type_anywhere( $layout_type ) {
		// PERFORMANCE: Early termination if no instances exist.
		if ( empty( self::$_data ) ) {
			return false;
		}

		// PERFORMANCE: Iterate through instances and blocks with early returns.
		// This is the most expensive operation in the OrderIndexResetManager,
		// so we optimize for the common case: finding a match quickly or
		// determining no match exists with minimal iterations.
		foreach ( self::$_data as $instance_blocks ) {
			// Skip empty instances immediately (common case after instance creation).
			if ( empty( $instance_blocks ) ) {
				continue;
			}

			// PERFORMANCE: For most instances, we can skip if there are only root blocks.
			// Check the count first to avoid iterating if there's only one block (the root).
			$block_count = count( $instance_blocks );
			if ( 1 === $block_count && isset( $instance_blocks['divi/root'] ) ) {
				continue;
			}

			// Iterate through blocks to find a match.
			// PERFORMANCE: Use foreach directly instead of array_keys() to avoid creating an intermediate array.
			foreach ( $instance_blocks as $block_id => $block ) {
				// Skip root blocks early (most common case).
				if ( 'divi/root' === $block_id ) {
					continue;
				}

				// PERFORMANCE: Check layout_type property directly without isset() check first.
				// The property should always exist on non-root blocks, so the isset() is redundant overhead.
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Matches WordPress block parser conventions.
				if ( $layout_type === $block->layout_type ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Reset order indexes data once per page render.
	 *
	 * This method ensures that order indexes are only reset once per page render,
	 * preventing multiple resets that could cause incorrect order index numbering.
	 * The reset flag is automatically managed internally using a static property.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function reset_order_index_once() {
		if ( ! self::$_has_reset_order_index ) {
			self::reset_order_index();
			self::$_has_reset_order_index = true;
		}
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
	 * @param array   $capabilities Optional. An array of user capability to match against the current user. Default `[]`.
	 * @param boolean $mask_post_password Optional. Whether to mask `post_password` field. Default `true`.
	 * @param string  $inner_html Optional. The innerHTML content from global-layout block for local children. Default `''`.
	 *
	 * @return string|null The post content or null on failure.
	 */
	public static function get_global_layout_content( string $content, string $post_id, array $fields = [], array $capabilities = [], bool $mask_post_password = true, string $inner_html = '' ) {
		global $_is_parsing_global_layout;

		// Prevent re-entry during global layout parsing to avoid duplication.
		if ( $_is_parsing_global_layout ) {
			return $content; // Return original content if already parsing a global layout.
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return null;
		}

		try {
			self::set_layout(
				[
					'id'   => 'global_layout_' . $post_id,
					'type' => 'global_layout',
				]
			);
			// Set $_is_parsing_global_layout so parser knows that it's parsing a global layout.
			$_is_parsing_global_layout = true;

			// FIX: Manually extract localAttrs from the global-layout block using brace-matching.
			// parse_blocks() may fail with double-escaped attributes, so we extract directly from block comment.
			$local_attrs    = [];
			$content_length = strlen( $content );

			if ( preg_match( '/<!-- wp:divi\/global-layout\s+/', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
				$start_pos = $matches[0][1] + strlen( $matches[0][0] );

				// Manually extract JSON by counting braces.
				if ( $start_pos < $content_length && '{' === $content[ $start_pos ] ) {
					$brace_count = 0;
					$json_start  = $start_pos;
					$in_string   = false;
					$escape_next = false;

					for ( $i = $start_pos; $i < $content_length; $i++ ) {
						$char = $content[ $i ];

						if ( $escape_next ) {
							$escape_next = false;
							continue;
						}

						if ( '\\' === $char ) {
							$escape_next = true;
							continue;
						}

						if ( '"' === $char ) {
							$in_string = ! $in_string;
						}

						if ( ! $in_string ) {
							if ( '{' === $char ) {
								++$brace_count;
							} elseif ( '}' === $char ) {
								--$brace_count;
								if ( 0 === $brace_count ) {
									$attrs_json          = substr( $content, $json_start, $i - $json_start + 1 );
									$global_layout_attrs = json_decode( $attrs_json, true );
									$local_attrs         = $global_layout_attrs['localAttrs'] ?? [];
									break;
								}
							}
						}
					}
				}
			}

			// Convert D4 shortcode to D5 blocks on-the-fly when the global module template
			// has not yet been persisted as D5 in the database. Without this, the raw D4
			// shortcode is fed directly into parse_blocks() which cannot parse it, producing
			// a blank render. The rest of the rendering pipeline handles D4-on-the-fly for
			// regular page content; this makes global module templates consistent with that.
			$raw_post_content = $post->post_content;
			if ( false !== strpos( $raw_post_content, '[et_pb_' ) ) {
				// initialize_shortcode_framework() is idempotent — no-op if already called.
				Conversion::initialize_shortcode_framework();
				// Only fire once per request; in migration contexts it was already fired upstream.
				if ( ! did_action( 'divi_visual_builder_before_d4_conversion' ) ) {
					do_action( 'divi_visual_builder_before_d4_conversion' );
				}
				$raw_post_content = Conversion::maybeConvertContent( $raw_post_content, true, (int) $post_id );
			}

			// Decode HTML entities before processing (WordPress encodes block comments in database).
			// Use safe, whitelist-based entity decoding to prevent XSS vulnerabilities.
			// This decodes only WordPress block structure entities (&quot;, &amp;, brackets)
			// and does NOT decode dangerous entities like &lt; and &gt; that could enable script injection.
			// @see: https://github.com/elegantthemes/submodule-builder-5/pull/6776#discussion_r2434267097.
			// @see: https://github.com/elegantthemes/Divi/issues/9664.
			$decoded_content = HTMLUtility::decode_wordpress_block_entities( $raw_post_content );
			$decoded_content = self::_collapse_duplicate_placeholder_wrappers( $decoded_content );

			// CRITICAL: Inject localAttrs into template content BEFORE parsing.
			//
			// WHY HERE: This must happen before parse_blocks() because loop builder settings
			// (which use $variable() syntax) need to be merged into template content before
			// the loop is executed during parsing. Post-parse merging would be too late - the
			// loop would already have been executed with template values. This also applies to
			// other attributes containing $variable() syntax (Global Colors, dynamic content).
			//
			// WHAT IT DOES: The _inject_local_attrs_into_content() method handles double-escaped
			// attributes that break WordPress parse_blocks() after database save/load cycles with
			// wp_slash(). It does this by:
			// - Extracting JSON manually using brace-matching (handles double-escaping)
			// - json_decode() processes the double-escaped JSON correctly
			// - Extracts template's localAttrsMap from the decoded attributes
			// - Filters localAttrs to only include attributes specified in localAttrsMap
			// - If localAttrsMap is empty (fully synced), skips injection entirely
			// - Otherwise, merges filtered localAttrs with template attributes
			// - wp_json_encode() creates fresh, properly-escaped JSON
			// - Replaces the old JSON in content
			// Result: Content no longer has double-escaped attributes after this step.
			//
			// WHY FILTER: localAttrs contains a complete snapshot of all attributes from when
			// the page was saved. However, only attributes listed in localAttrsMap should be
			// used from the snapshot. Attributes not in localAttrsMap should come from the
			// template instead. Without filtering, old snapshot values would overwrite template
			// values for attributes that should be synced (like Global Colors, dynamic content).
			//
			// OPTIMIZATION: Only call when content contains $variable() syntax.
			// This method is expensive (manual JSON parsing), but double-escaping issues
			// only occur with $variable() syntax. Without it, we can rely on post-parse
			// attribute merging via apply_local_attrs_filtering() instead.
			if ( ! empty( $local_attrs ) && str_contains( $decoded_content, '$variable(' ) ) {
				$decoded_content = self::_inject_local_attrs_into_content( $decoded_content, $local_attrs );
			}

			// Preprocess placeholder-wrapped global module content before parsing.
			// The _expand_placeholder_wrapped_blocks() method also fixes double-escaping by
			// reconstructing blocks with wp_json_encode(), creating fresh JSON that parse_blocks() can handle.
			$preprocessed_content = self::_expand_placeholder_wrapped_blocks( $decoded_content );

			// Parse the preprocessed content. At this point, any double-escaped attributes have been
			// fixed by the re-encoding process above, so parse_blocks() works correctly.
			$parsed_actual_post = self::_filter_whitespace_blocks( parse_blocks( $preprocessed_content ) );

			// Unset $_is_parsing_global_layout so parser can continue working normally.
			$_is_parsing_global_layout = false;

			// Find the first non-placeholder block and update its attributes.
			foreach ( $parsed_actual_post as $index => $block ) {
				if (
				isset( $block['blockName'] ) &&
				'divi/placeholder' !== $block['blockName'] &&
				! empty( $block['attrs'] )
				) {
					// Apply selective filtering based on template's localAttrsMap.
					// The localAttrs from serialized block contains a complete snapshot.
					// Template's localAttrsMap determines which attributes to use from snapshot.
					// Note: Loop attributes are already applied before parsing via _inject_local_attrs_into_content().
					if ( ! empty( $local_attrs ) ) {
						$parsed_actual_post[ $index ]['attrs'] = self::apply_local_attrs_filtering( $block['attrs'], $local_attrs );
					}
					break;
				}
			}

			// Handle children snapshot filtering based on template's localChildren.
			// innerHTML (innerBlocks) always contains children snapshot (complete snapshot).
			// Template's localChildren boolean determines whether to use snapshot or template children.
			if ( ! empty( $inner_html ) ) {
				// Parse innerHTML to get children snapshot and filter whitespace blocks.
				$parsed_inner_html = self::_filter_whitespace_blocks( parse_blocks( $inner_html ) );

				// Filter out placeholder blocks and get actual child modules.
				// Use array_values to re-index the array and avoid sparse indices that break serialize_blocks.
				$children_snapshot = array_values(
					array_filter(
						$parsed_inner_html,
						function ( $block ) {
							return ! empty( $block['blockName'] ) && 'divi/placeholder' !== $block['blockName'];
						}
					)
				);

				// Ensure all blocks have the required properties for WordPress serialization.
				foreach ( $children_snapshot as $index => $block ) {
					$children_snapshot[ $index ] = array_merge(
						[
							'blockName'    => '',
							'attrs'        => [],
							'innerBlocks'  => [],
							'innerHTML'    => '',
							'innerContent' => [],
						],
						$block
					);
				}

				// Find the main module in parsed_actual_post (first non-placeholder block).
				// Note: Don't require ! empty( $block['attrs'] ) as complex JSON may not parse correctly.
				$main_module_index = null;
				foreach ( $parsed_actual_post as $index => $block ) {
					if (
					isset( $block['blockName'] ) &&
					! empty( $block['blockName'] ) &&
					'divi/placeholder' !== $block['blockName']
					) {
						$main_module_index = $index;
						break;
					}
				}

				if ( null !== $main_module_index ) {
					// Get template's localChildren value (source of truth).
					$template_local_children = $parsed_actual_post[ $main_module_index ]['attrs']['localChildren'] ?? false;

					// Template's localChildren determines which children to use.
					if ( true === $template_local_children && ! empty( $children_snapshot ) ) {
						// Use children from snapshot (serialized block).
						$parsed_actual_post[ $main_module_index ]['innerBlocks'] = $children_snapshot;
					}
					// Else: Use children from template (already in parsed_actual_post).

					// Note: localChildren is already in template attributes, no need to set it again.
				}
			}

			// Filter out placeholder blocks before final serialization - they're only needed for internal processing.
			$parsed_actual_post = array_values(
				array_filter(
					$parsed_actual_post,
					function ( $block ) {
						return isset( $block['blockName'] ) && 'divi/placeholder' !== $block['blockName'];
					}
				)
			);

			// Recursively ensure all blocks have complete structure before serialization with depth limit.
			$parsed_actual_post = self::_normalize_block_structure( $parsed_actual_post );

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
		} finally {
			self::reset_order_index();
			self::reset_layout();
		}
	}


	/**
	 * Get the ID of the currently active store instance.
	 *
	 * @since ??
	 *
	 * @return int|null The active store instance ID. Will return `null` when no instance has been created.
	 */
	public static function get_instance() {
		return self::$_instance;
	}

	/**
	 * Get all active store instance IDs.
	 *
	 * @since ??
	 *
	 * @return array<int, int>
	 */
	public static function get_instance_ids(): array {
		return array_map( 'intval', array_keys( self::$_data ) );
	}


	/**
	 * Get the parent of existing item in the store.
	 *
	 * @since ??
	 *
	 * @param string $child_id The unique ID of the child block.
	 * @param int    $instance Optional. The instance of the store you want to use. Default `null`.
	 *
	 * @return BlockParserBlock|null
	 */
	public static function get_parent( $child_id, $instance = null ) {
		$current = self::$_data[ self::_use_instance( $instance ) ][ $child_id ] ?? null;

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
		if ( ! $current || ! $current->parentId || ! self::has( $current->parentId, $instance ) ) {
			return null;
		}

		return self::get( $current->parentId, $instance );
	}


	/**
	 * Get the siblings of existing item in the store.
	 *
	 * @since ??
	 *
	 * @param string $id       The ID of the block you want to get the sibling of.
	 * @param string $location Sibling location. Can be either `before` or `after`.
	 * @param int    $instance Optional. The instance of the store you want to use. Default `null`.
	 *
	 * @return BlockParserBlock[] Array of siblings sorted from the closest sibling. Will return empty array on failure.
	 */
	public static function get_siblings( $id, $location, $instance = null ) {
		$parent = self::get_parent( $id, $instance );

		if ( ! $parent ) {
			return [];
		}

		$inner_blocks = [];
		$all_blocks   = self::$_data[ self::_use_instance( $instance ) ];

		foreach ( $all_blocks as $block ) {
			// phpcs:disable WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
			if ( $block->parentId === $parent->id ) {
				$inner_blocks[] = $block;
			}
		}

		$inner_blocks_count = count( $inner_blocks );

		if ( 1 < $inner_blocks_count ) {
			usort(
				$inner_blocks,
				function ( $a, $b ) {
					if ( $a->index === $b->index ) {
						return 0;
					}

					return ( $a->index < $b->index ) ? -1 : 1;
				}
			);
		}

		$siblings    = [];
		$index_found = null;
		$index_last  = $inner_blocks_count - 1;

		foreach ( $inner_blocks as $index => $inner_block ) {
			if ( $id === $inner_block->id ) {
				$index_found = $index;
				break;
			}
		}

		if ( null !== $index_found ) {
			if ( 'before' === $location && 0 < $index_found ) {
				$inner_blocks_before = array_reverse( array_slice( $inner_blocks, 0, $index_found ) );

				foreach ( $inner_blocks_before as $inner_block ) {
					$siblings[] = self::get( $inner_block->id, $instance );
				}
			}

			if ( 'after' === $location && $index_last > $index_found ) {
				$inner_blocks_after = array_slice( $inner_blocks, ( $index_found + 1 ) );

				foreach ( $inner_blocks_after as $inner_block ) {
					$siblings[] = self::get( $inner_block->id, $instance );
				}
			}
		}

		return $siblings;
	}

	/**
	 * Get the direct sibling of existing item in the store.
	 *
	 * @since ??
	 *
	 * @param string $id       The ID of the block you want to get the sibling of.
	 * @param string $location Sibling location. Can be either `before` or `after`.
	 * @param int    $instance Optional. The instance of the store you want to use. Default null.
	 *
	 * @return BlockParserBlock|null
	 */
	public static function get_sibling( $id, $location, $instance = null ): ?BlockParserBlock {
		$siblings = self::get_siblings( $id, $location, $instance );

		if ( ! $siblings ) {
			return null;
		}

		return $siblings[0] ?? null;
	}


	/**
	 * Get existing item in the store.
	 *
	 * @since ??
	 *
	 * @param string $id       The unique ID of the block.
	 * @param int    $instance The instance of the store you want to use.
	 *
	 * @return BlockParserBlock|null
	 */
	public static function get( $id, $instance = null ) {
		if ( ! self::has( $id, $instance ) ) {
			return null;
		}

		$use_instance = self::_use_instance( $instance );

		$item = self::$_data[ $use_instance ][ $id ];

		// Populate `innerBlocks` data for root block.
		if ( self::_is_root( $id ) ) {
			$inner_blocks = [];

			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Matches WordPress block parser conventions.
			foreach ( self::$_data[ $use_instance ] as $block ) {
				if ( ! self::_is_root( $block->parentId ) ) {
					continue;
				}

				$inner_blocks[] = (array) $block;
			}

			$item->innerBlocks = $inner_blocks;
		}

		return $item;
	}

	/**
	 * Block Parser Store: Instance check.
	 *
	 * Check if a store ID exists in the current instance's `$_data`.
	 *
	 * @since ??
	 *
	 * @param int $instance The instance ID of the store.
	 *
	 * @return bool
	 */
	public static function has_instance( $instance ) {
		if ( null === $instance ) {
			return false;
		}

		return isset( self::$_data[ $instance ] );
	}


	/**
	 * Block Parser Store: Block check.
	 *
	 * Check if a particular block exists in the instance store.
	 *
	 * @since ??
	 *
	 * @param string $id       The unique ID of the block.
	 * @param int    $instance Optional. The instance of the store you want to use. Default `null`.
	 *
	 * @return bool
	 */
	public static function has( $id, $instance = null ) {
		return isset( self::$_data[ self::_use_instance( $instance ) ][ $id ] );
	}

	/**
	 * Block Parser Store: Is First check.
	 *
	 * Check if the given block is the first block in the parent block.
	 *
	 * @since ??
	 *
	 * @param string $id       The ID of the block you want to check.
	 * @param int    $instance The instance of the store you want to use.
	 *
	 * @return bool
	 */
	public static function is_first( $id, $instance = null ) {
		if ( self::_is_root( $id ) ) {
			return true;
		}

		$parent = self::get_parent( $id, $instance );

		if ( ! $parent ) {
			return false;
		}

		$children = self::get_children( $parent->id, $instance );

		$first_child_id = $children[0]->id ?? null;

		// Checking if children blocks is renderable or not.
		foreach ( $children as $index => $child_block ) {
			$child_block_arr = get_object_vars( $child_block ) ?? [];
			// First parameter `Block` is for the fake block_content, if condition doesn't meet, it will return empty string.
			// We need to check ConditionsRenderer::should_render() to ensure the child block is renderable.
			// If the child block is not renderable, we need to remove this block from the $children.
			if ( ConditionsRenderer::should_render( true, new WP_Block( $child_block_arr ), $child_block_arr['attrs'] ?? [] ) ) {
				$first_child_id = $child_block->id ?? null;
				break;
			}
		}

		return $id === $first_child_id;
	}

	/**
	 * Block Parser Store: Is Last check.
	 *
	 * Check if the given block is the last block in the parent block.
	 *
	 * @since ??
	 *
	 * @param string $id       The ID of the block you want to check.
	 * @param int    $instance The instance of the store you want to use.
	 *
	 * @return bool
	 */
	public static function is_last( $id, $instance = null ) {
		if ( self::_is_root( $id ) ) {
			return true;
		}

		$parent = self::get_parent( $id, $instance );

		if ( ! $parent ) {
			return false;
		}

		$children = self::get_children( $parent->id, $instance );

		if ( ! $children ) {
			return false;
		}

		$last_index = count( $children ) - 1;

		return isset( $children[ $last_index ]->id ) && $id === $children[ $last_index ]->id;
	}

	/**
	 * Block Parser Store: Is Nested Module.
	 *
	 * Check if the given block is a nested module (eg. row inside row module).
	 *
	 * @since ??
	 *
	 * @param string $id       The ID of the block you want to check.
	 * @param int    $instance The instance of the store you want to use.
	 *
	 * @return bool
	 */
	public static function is_nested_module( $id, $instance = null ) {
		$module = self::get( $id, $instance );

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Matches WordPress block parser conventions.
		$ancestor_module_name = array_map(
			function ( $module ) {
				return $module->blockName;
			},
			self::get_ancestors(
				$id,
				$instance
			)
		);

		return in_array( $module->blockName, $ancestor_module_name, true );
	}

	/**
	 * Check if given block is root block.
	 *
	 * Checks if the given ID is equal to `divi/root`.
	 *
	 * @since ??
	 *
	 * @param string $id The ID of the block you want to check.
	 */
	protected static function _is_root( $id ) {
		return 'divi/root' === $id;
	}

	/**
	 * Set layout area before parsing module / block.
	 * This allows module to know which area it is being rendered in.
	 *
	 * @since ??
	 *
	 * @param array $layout The layout area. The format is matched to layout array passed by `et_theme_builder_begin_layout` filter.
	 */
	public static function set_layout( $layout ) {

		// Set the param as current layout.
		self::$_layout = [
			'id'   => $layout['id'],
			'type' => $layout['type'] ?? 'default',
		];

		// Append the given layout to array of layouts. This will be used when resetting layout.
		self::$_layouts[] = self::$_layout;
	}

	/**
	 * Reset layout area.
	 * After any (theme builder) layout is done rendered, its layout should be reset.
	 *
	 * @since ??
	 */
	public static function reset_layout() {
		// Remove the last (to be reset) layout from array of layouts.
		array_pop( self::$_layouts );

		// Get the previous (last layout after the to be reset layout is removed) layout from array of layouts.
		$last_layout = end( self::$_layouts );

		// Set previous (or default) layout as current layout.
		self::$_layout = $last_layout ? $last_layout : [
			'id'   => '',
			'type' => 'default',
		];
	}

	/**
	 * Get layout type.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public static function get_layout_type() {
		return self::$_layout['type'];
	}

	/**
	 * Get layout types.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function get_layout_types() {
		return apply_filters(
			'et_theme_builder_layout_types',
			[
				'default',
				'et_header_layout',
				'et_body_layout',
				'et_footer_layout',
				'migration',
				'global_layout',
				'saving_content',
			]
		);
	}

		/**
		 * Create or return existing instance.
		 *
		 * Create new store instance when no instance has created yet.
		 * Otherwise returns existing latest instance.
		 *
		 * @since ??
		 *
		 * @internal Do not use this method outside the `BlockParser::parse()`.
		 *
		 * @return int The store instance ID.
		 */
	public static function maybe_new_instance() {
		if ( null !== self::$_instance ) {
			return self::$_instance;
		}

		return self::new_instance();
	}

	/**
	 * Create new store instance and switch to the new instance instantly.
	 *
	 * @since ??
	 *
	 * @internal Do not use this method outside the `BlockParser::parse()`.
	 *
	 * @return int The new store instance ID.
	 */
	public static function new_instance() {
		$previous_instance = self::$_instance;
		self::$_instance   = null === self::$_instance ? 0 : count( self::$_data );

		// Reset orderIndex when creating a new store instance (but not the first one).
		// This ensures each new rendering phase starts with orderIndex 0.
		// Use the unified OrderIndexResetManager to handle reset logic consistently
		// with other reset points (handles off-canvas, inner content, parsed blocks checks).
		if ( null !== $previous_instance ) {
			OrderIndexResetManager::maybe_reset( OrderIndexResetManager::PHASE_NEW_STORE_INSTANCE );
			// Reset the flag so reset_order_index_once can work for the new instance.
			// This is always safe to reset here since we're creating a new store instance.
			self::$_has_reset_order_index = false;
		}

		self::_add_root( self::$_instance );

		return self::$_instance;
	}

		/**
		 * Reset specific store instance.
		 *
		 * Will reset the store to an empty array `[]`.
		 *
		 * @since ??
		 *
		 * @param int $instance The instance of the store you want to reset.
		 *
		 * @return int|null The given store instance ID or `null` if the given ID is not found.
		 */
	public static function reset_instance( $instance ) {
		if ( self::has_instance( $instance ) ) {
			self::$_data[ $instance ] = [];

			self::_add_root( $instance );

			return $instance;
		}

		return null;
	}

	/**
	 * Store active instance
	 *
	 * @since ??
	 *
	 * @var int
	 */
	protected static $_instance = null;

	/**
	 * Store data
	 *
	 * @since ??
	 *
	 * @var BlockParserBlock[]
	 */
	protected static $_data = [];

	/**
	 * Current layout area.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	protected static $_layout = [
		'id'   => '',
		'type' => 'default',
	];

	/**
	 * Array of currently used layouts.
	 *
	 * Collect all currently used layout so when there are nested layout like body > post content, correct previous
	 * layout gets restored correctly when the layout is being reset.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	protected static $_layouts = [];

		/**
		 * Reset whole store data.
		 *
		 * @since ??
		 */
	public static function reset() {
		self::$_data     = [];
		self::$_instance = null;
	}

	/**
	 * Set property of existing block item in the store.
	 *
	 * @since ??
	 *
	 * @param string $id       The ID of the block you want to set the property for.
	 * @param string $property The property/key you want to set.
	 * @param mixed  $value    The value to set.
	 * @param int    $instance Optional. The instance of the store you want to use. Default `null`.
	 */
	public static function set_property( $id, $property, $value, $instance = null ) {
		$use_instance = self::_use_instance( $instance );

		if (
			self::_is_root( $id )
			|| ! self::has( $id, $instance )
			|| ! property_exists( self::$_data[ $use_instance ][ $id ], $property )
		) {
			return;
		}

		self::$_data[ $use_instance ][ $id ]->{$property} = $value;
	}

	/**
	 * Switch to specific store instance.
	 *
	 * @since ??
	 *
	 * @param int $instance The instance you want to switch to.
	 *
	 * @return int|null The previous instance before the switch. Will return null on failure or when no instance created yet.
	 */
	public static function switch_instance( int $instance ) {
		if ( self::$_instance !== $instance && self::has_instance( $instance ) ) {
			$previous_instance = self::$_instance;
			self::$_instance   = $instance;

			return $previous_instance;
		}

		return null;
	}


	/**
	 * Get the store instance that will be used.
	 *
	 * @since ??
	 *
	 * @param int $instance The instance of the store you want to use.
	 *
	 * @return int The instance of the store that will be used.
	 */
	private static function _use_instance( $instance ) {
		return self::has_instance( $instance ) ? $instance : self::$_instance;
	}

	/**
	 * Recursively normalize block structure to ensure compatibility with serialize_blocks().
	 *
	 * @since ??
	 *
	 * @param array $blocks Array of blocks to normalize.
	 * @param int   $depth  Current recursion depth for preventing infinite loops.
	 *
	 * @return array Normalized blocks.
	 */
	private static function _normalize_block_structure( $blocks, $depth = 0 ) {
		if ( ! is_array( $blocks ) ) {
			return $blocks;
		}

		/**
		 * Filter the maximum depth allowed for global module block structure normalization.
		 *
		 * This filter allows modification of the maximum depth limit for processing
		 * nested block structures in global modules. Prevents infinite recursion and
		 * stack overflow errors when processing deeply nested or circular structures.
		 *
		 * @since ??
		 *
		 * @param int $max_depth Maximum allowed depth for block structure normalization.
		 *                       Default is 20 levels deep.
		 */
		$max_depth = apply_filters( 'divi_frontend_block_parser_global_module_max_depth', 20 );

		// Prevent infinite recursion by limiting depth.
		if ( $depth >= $max_depth ) {
			return $blocks;
		}

		foreach ( $blocks as $index => $block ) {
			if ( is_array( $block ) ) {
				// Ensure all required properties exist.
				$blocks[ $index ]['blockName']   = $block['blockName'] ?? '';
				$blocks[ $index ]['attrs']       = isset( $block['attrs'] ) && is_array( $block['attrs'] ) && ! empty( $block['attrs'] ) ? $block['attrs'] : null;
				$blocks[ $index ]['innerBlocks'] = $block['innerBlocks'] ?? [];
				$blocks[ $index ]['innerHTML']   = $block['innerHTML'] ?? '';

				// Recursively normalize innerBlocks first with depth tracking.
				if ( ! empty( $blocks[ $index ]['innerBlocks'] ) ) {
					$blocks[ $index ]['innerBlocks'] = self::_normalize_block_structure( $blocks[ $index ]['innerBlocks'], $depth + 1 );
				}

				// Fix innerContent based on block type - this is critical for serialize_blocks().
				if ( ! empty( $blocks[ $index ]['innerBlocks'] ) ) {
					// Parent block with children - needs null placeholders for each child.
					$inner_content = [ '' ]; // Opening content.
					foreach ( $blocks[ $index ]['innerBlocks'] as $child ) {
						$inner_content[] = null; // Placeholder for each child.
					}
					$inner_content[]                  = ''; // Closing content.
					$blocks[ $index ]['innerContent'] = $inner_content;
				} elseif ( ! empty( $blocks[ $index ]['innerHTML'] ) ) {
					// FIX: For Divi blocks without innerBlocks, clear innerHTML to prevent duplication.
					// When a module's content comes from attributes (not child blocks), WordPress's,
					// serialize_blocks() should not include innerHTML. The innerContent array is used,
					// by WordPress to determine what HTML to include between block comment tags.
					//
					// CONTEXT: For modules like Text, Button, etc., content is stored in attributes,
					// (e.g., content.innerContent.desktop.value), not as actual WordPress innerBlocks.
					// However, when WordPress parses the saved content, it populates the innerHTML,
					// with the rendered HTML. When we re-serialize for global modules, this causes,
					// the content to appear both in attributes AND as innerHTML, leading to,
					// duplication on the frontend.
					//
					// SOLUTION: For Divi blocks without actual innerBlocks, produce a self-closing,
					// block comment without innerHTML. Non-Divi blocks keep their innerHTML as-is.
					$is_divi_block = ! empty( $blocks[ $index ]['blockName'] ) && str_starts_with( $blocks[ $index ]['blockName'], 'divi/' );
					if ( $is_divi_block ) {
						// Self-closing Divi block - clear innerHTML to prevent duplication.
						$blocks[ $index ]['innerContent'] = [];
					} else {
						// Non-Divi block with innerHTML content - keep as-is.
						$blocks[ $index ]['innerContent'] = [ $blocks[ $index ]['innerHTML'] ];
					}
				} else {
					// Self-closing or empty block.
					$blocks[ $index ]['innerContent'] = [];
				}
			}
		}

		return $blocks;
	}
}
