<?php
/**
 * PostUtility class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Utility;

// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames -- Reserved keywords used intentionally for clarity in utility functions.
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentUtils;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\BlockParser\SimpleBlockParser;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * PostUtility class.
 *
 * This class contains methods to work with Post.
 *
 * @since ??
 */
class PostUtility {

	/**
	 * Truncate post content to generate post excerpt.
	 *
	 * @since ??
	 *
	 * @param integer $amount           Amount of text that should be kept.
	 * @param boolean $echo             Whether to print the output or not.
	 * @param object  $post             Post object.
	 * @param boolean $strip_shortcodes Whether to strip the shortcodes or not.
	 * @param boolean $is_words_length  Whether to cut the text based on words length or not.
	 *
	 * @return string Generated post post excerpt.
	 */
	public static function truncate_post( $amount, $echo = true, $post = '', $strip_shortcodes = false, $is_words_length = false ) {
		global $shortname;

		if ( empty( $post ) ) {
			global $post;
		}

		if ( post_password_required( $post ) ) {
			$post_excerpt = get_the_password_form();

			if ( $echo ) {
				echo et_core_intentionally_unescaped( $post_excerpt, 'html' );
				return;
			}

			return $post_excerpt;
		}

		$post_excerpt = apply_filters( 'the_excerpt', $post->post_excerpt );

		if ( 'on' === et_get_option( $shortname . '_use_excerpt' ) && ! empty( $post_excerpt ) ) {
			if ( $echo ) {
				echo et_core_intentionally_unescaped( $post_excerpt, 'html' );
			} else {
				return $post_excerpt;
			}
		} else {
			// get the post content.
			$truncate = $post->post_content;

			// remove caption shortcode from the post content.
			$truncate = preg_replace( '@\[caption[^\]]*?\].*?\[\/caption]@si', '', $truncate );

			// remove post nav shortcode from the post content.
			// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
			// TODO fix(D5, PostUtility) Replace the et_pb_post_nav with D5's post navigation module.
			$truncate = preg_replace( '@\[et_pb_post_nav[^\]]*?\].*?\[\/et_pb_post_nav]@si', '', $truncate );

			// Remove audio shortcode from post content to prevent unwanted audio file on the excerpt
			// due to unparsed audio shortcode.
			$truncate = preg_replace( '@\[audio[^\]]*?\].*?\[\/audio]@si', '', $truncate );

			// Remove embed shortcode from post content.
			$truncate = preg_replace( '@\[embed[^\]]*?\].*?\[\/embed]@si', '', $truncate );

			if ( $strip_shortcodes ) {
				// Lightweight excerpt path: extract plain text without full builder render (D4 parity).
				$truncate = self::_extract_plain_text_from_content( $truncate, (int) $amount );
				$truncate = wp_strip_all_tags( $truncate );
				$truncate = et_strip_shortcodes( $truncate );
				$truncate = DynamicContentUtils::get_strip_dynamic_content( $truncate );
			} else {
				// Full render path for callers that need rendered module output.
				$truncate = BlockParserStore::render_inner_content( $truncate );

				// Remove icon markup from rendered content to prevent icon characters in excerpts.
				$truncate = self::remove_icon_markup( $truncate );

				// Ensure adjacent tags preserve whitespace when tags are stripped.
				$truncate = self::ensure_space_between_tags( $truncate );

				// Remove script and style tags from the post content.
				$truncate = wp_strip_all_tags( $truncate );

				// Check if content should be overridden with a custom value.
				$custom = apply_filters( 'et_truncate_post_use_custom_content', false, $truncate, $post );
				// apply content filters.
				$truncate = false === $custom ? BlockParserStore::render_inner_content( $truncate ) : $custom;
			}

			/**
			 * Filter automatically generated post excerpt before it gets truncated.
			 *
			 * @since 3.17.2
			 *
			 * @param string $excerpt
			 * @param integer $post_id
			 */
			$truncate = apply_filters( 'et_truncate_post', $truncate, $post->ID );

			// decide if we need to append dots at the end of the string.
			if ( strlen( $truncate ) <= $amount ) {
				$echo_out = '';
			} else {
				$echo_out = '...';
			}

			$trim_words = '';

			if ( $is_words_length ) {
				// Reset `$echo_out` text because it will be added by wp_trim_words() with
				// default WordPress `excerpt_more` text.
				$echo_out     = '';
				$excerpt_more = apply_filters( 'excerpt_more', ' [&hellip;]' );
				$trim_words   = wp_trim_words( $truncate, $amount, $excerpt_more );
			} else {
				$trim_words = et_wp_trim_words( $truncate, $amount, '' );
			}

			// trim text to a certain number of characters, also remove spaces from the end of a string ( space counts as a character ).
			$truncate = rtrim( $trim_words );

			// remove the last word to make sure we display all words correctly.
			if ( ! empty( $echo_out ) ) {
				$new_words_array = (array) explode( ' ', $truncate );
				// Remove last word if word count is more than 1.
				if ( count( $new_words_array ) > 1 ) {
					array_pop( $new_words_array );
				}

				$truncate = implode( ' ', $new_words_array );

				// Dots should not add to empty string.
				if ( '' !== $truncate ) {
					// append dots to the end of the string.
					$truncate .= $echo_out;
				}
			}

			if ( $echo ) {
				echo et_core_intentionally_unescaped( $truncate, 'html' );
			} else {
				return $truncate;
			}
		}
	}

	/**
	 * Prepare block post content for excerpt stripping without full builder rendering.
	 *
	 * Replaces each parsed block comment with readable innerContent text while
	 * preserving legacy shortcodes, plain HTML, and block innerHTML. Remaining
	 * block markers are removed so D4-style tag/shortcode stripping can run on
	 * the full body.
	 *
	 * @since ??
	 *
	 * @param string $content    Post content.
	 * @param int    $max_length Target excerpt character length. Zero disables early exit.
	 *
	 * @return string Content prepared for excerpt stripping.
	 */
	private static function _extract_plain_text_from_content( string $content, int $max_length = 0 ): string {
		if ( '' === $content || ! str_contains( $content, '<!-- wp:' ) ) {
			return $content;
		}

		if ( ! str_contains( $content, '"innerContent"' ) ) {
			return $content;
		}

		$parsed_blocks = SimpleBlockParser::parse( $content );

		if ( 0 === count( $parsed_blocks ) ) {
			return $content;
		}

		$prepared_content    = $content;
		$length_limit        = $max_length;
		$extracted_fragments = [];

		foreach ( $parsed_blocks as $block ) {
			if ( $block->error() ) {
				continue;
			}

			$block_raw = $block->raw();

			if ( '' === $block_raw || ! str_contains( $prepared_content, $block_raw ) ) {
				continue;
			}

			$block_fragments = [];
			$attrs           = $block->attrs();

			if ( ! empty( $attrs ) ) {
				self::_collect_plain_text_from_attrs( $attrs, $block_fragments );
			}

			$replacement = empty( $block_fragments )
				? ' '
				: ' ' . implode( ' ', $block_fragments ) . ' ';

			$prepared_content = str_replace( $block_raw, $replacement, $prepared_content );

			if ( ! empty( $block_fragments ) ) {
				$extracted_fragments = array_merge( $extracted_fragments, $block_fragments );
			}

			if ( $length_limit > 0 && strlen( implode( ' ', $extracted_fragments ) ) >= $length_limit ) {
				break;
			}
		}

		// Remove closing block comments and any remaining unparsed block markers.
		$prepared_content = preg_replace( '/<!--\s*\/?wp:[^>]*-->/', ' ', $prepared_content );

		return $prepared_content;
	}

	/**
	 * Collect plain text fragments recursively from block attrs.
	 *
	 * Walks nested attrs for innerContent desktop values. Supports string values
	 * and object-shaped values (e.g. button text).
	 *
	 * @since ??
	 *
	 * @param array $attrs          Attrs node.
	 * @param array $text_fragments Text fragment accumulator.
	 *
	 * @return void
	 */
	private static function _collect_plain_text_from_attrs( array $attrs, array &$text_fragments ): void {
		foreach ( $attrs as $attr_value ) {
			if ( ! is_array( $attr_value ) ) {
				continue;
			}

			$desktop_value = $attr_value['innerContent']['desktop']['value'] ?? null;

			if ( null !== $desktop_value ) {
				$text = '';

				if ( is_string( $desktop_value ) ) {
					$text = HTMLUtility::decode_wordpress_block_entities( $desktop_value );
				} elseif ( is_array( $desktop_value ) ) {
					$text_keys = [ 'text', 'title', 'label', 'subtitle', 'author' ];

					foreach ( $text_keys as $text_key ) {
						if ( isset( $desktop_value[ $text_key ] ) && is_string( $desktop_value[ $text_key ] ) ) {
							$text = HTMLUtility::decode_wordpress_block_entities( $desktop_value[ $text_key ] );
							break;
						}
					}
				}

				$plain_text = wp_strip_all_tags( $text );

				if ( '' !== $plain_text ) {
					$text_fragments[] = $plain_text;
				}
			}

			self::_collect_plain_text_from_attrs( $attr_value, $text_fragments );
		}
	}

	/**
	 * Remove icon markup from rendered content.
	 *
	 * @since ??
	 *
	 * @param string $content Rendered content HTML.
	 *
	 * @return string Content with icon markup removed.
	 */
	public static function remove_icon_markup( $content ) {
		// Test regex: https://regex101.com/r/v7E3sA/1.
		$icon_markup_pattern = '@<(?:span|i)[^>]*class="[^"]*\b(?:et-pb-icon|et_pb_icon)\b[^"]*"[^>]*>.*?<\/(?:span|i)>@si';

		return preg_replace( $icon_markup_pattern, '', $content );
	}

	/**
	 * Ensure adjacent tags preserve whitespace once tags are stripped.
	 *
	 * @since ??
	 *
	 * @param string $content Rendered content HTML.
	 *
	 * @return string Content with tag boundaries separated by spaces.
	 */
	public static function ensure_space_between_tags( $content ) {
		return preg_replace( '/>\s*</', '> <', $content );
	}

	/**
	 * Get the first video URL and embed code from the post content.
	 *
	 * This fixes the issue with thumbnail video player not working, when video url is added to content without
	 * shortcode.
	 *
	 * This function is the replacement of Divi 4 function `et_get_first_video`.
	 *
	 * @since ??
	 *
	 * @return string|false First video embed code if found, false otherwise.
	 */
	public static function get_first_video() {
		$first_url    = '';
		$first_video  = '';
		$video_width  = (int) apply_filters( 'et_blog_video_width', 1080 );
		$video_height = (int) apply_filters( 'et_blog_video_height', 630 );

		$i       = 0;
		$content = get_the_content();

		preg_match_all( '|^\s*https?://[^\s"]+\s*$|im', $content, $urls );

		foreach ( $urls[0] as $url ) {
			++$i;

			if ( 1 === $i ) {
				$first_url = trim( $url );
			}

			$oembed = wp_oembed_get( esc_url( $url ) );

			if ( ! $oembed ) {
				continue;
			}

			$first_video = $oembed;
			$first_video = preg_replace( '/<embed /', '<embed wmode="transparent" ', $first_video );
			$first_video = preg_replace( '/<\/object>/', '<param name="wmode" value="transparent" /></object>', $first_video );

			// If the url comes from a GB embed block.
			if ( preg_match( '|wp-block-embed.+?' . preg_quote( $url, null ) . '|s', $content ) ) {
				// We need to remove some useless markup later.
				add_filter( 'the_content', 'et_delete_post_video' );
			}
			break;
		}

		if ( '' === $first_video ) {
			// Gutenberg compatibility.
			if ( ! has_shortcode( $content, 'video' ) && empty( $first_url ) ) {
				preg_match( '/<!-- wp:video[^\]]+?class="wp-block-video"><video[^\]]+?src="([^\]]+?)"[^\]]+?<!-- \/wp:video -->/', $content, $gb_video );
				$first_url = isset( $gb_video[1] ) ? $gb_video[1] : false;
			}

			if ( ! has_shortcode( $content, 'video' ) && ! empty( $first_url ) ) {
				$video_shortcode = sprintf( '[video src="%1$s" /]', esc_attr( $first_url ) );

				if ( ! empty( $gb_video ) ) {
					$content = str_replace( $gb_video[0], $video_shortcode, $content );
				} else {
					$content = str_replace( $first_url, $video_shortcode, $content );
				}
			}

			if ( has_shortcode( $content, 'video' ) ) {
				$regex = get_shortcode_regex();
				preg_match( "/{$regex}/s", $content, $match );

				// In D5 block content, shortcodes inside block comment JSON have special
				// chars encoded as \uXXXX by serialize_block_attributes(). Decode the
				// two escapes that can appear inside shortcode tags: " and &.
				$match[0] = strtr(
					$match[0],
					[
						'\u0022' => '"',
						'\u0026' => '&',
					]
				);

				$first_video = preg_replace( '/width="[0-9]*"/', "width=\"{$video_width}\"", $match[0] );
				$first_video = preg_replace( '/height="[0-9]*"/', "height=\"{$video_height}\"", $first_video );

				add_filter( 'the_content', 'et_delete_post_video' );

				$first_video = do_shortcode( HTMLUtility::fix_shortcodes( $first_video ) );
			}
		}

		return ( '' !== $first_video ) ? $first_video : false;
	}

	/**
	 * Delete the first video url from the post content.
	 *
	 * This function is the replacement of Divi 4 function `et_delete_post_first_video`.
	 *
	 * @since ??
	 *
	 * @param string $content post content.
	 */
	public static function delete_post_first_video( $content ) {
		if ( 'video' !== et_pb_post_format() ) {
			return $content;
		}

		$first_video = self::get_first_video();
		if ( false !== $first_video ) {
			preg_match_all( '|^\s*https?:\/\/[^\s"]+\s*|im', $content, $urls );

			if ( ! empty( $urls[0] ) ) {
				$content = str_replace( $urls[0], '', $content );
			}
		}

		return $content;
	}

	/**
	 * Get current post ID from editor context.
	 *
	 * Retrieves the post ID from the global $post object or $_GET['post'] parameter.
	 * This is intended for use in admin editor contexts (Classic Editor and Gutenberg).
	 *
	 * @since ??
	 *
	 * @return int|null Post ID if found, null otherwise.
	 */
	public static function get_editor_post_id(): ?int {
		global $post;

		$post_id = null;

		if ( $post && isset( $post->ID ) ) {
			$post_id = $post->ID;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is in admin enqueue hooks context with WordPress permission checks.
		} elseif ( isset( $_GET['post'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is in admin enqueue hooks context with WordPress permission checks.
			$post_id = absint( $_GET['post'] );
		}

		return $post_id;
	}
}
