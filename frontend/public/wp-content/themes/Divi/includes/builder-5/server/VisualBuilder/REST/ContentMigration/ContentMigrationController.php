<?php
/**
 * REST: ContentMigrationController class.
 *
 * @package Builder\VisualBuilder\REST
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\ContentMigration;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleLibrary\Svg\SvgSanitizer;
use ET\Builder\VisualBuilder\Saving\SavingUtility;
use Exception;
use Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Content Migration REST API Controller class.
 *
 * Provides REST endpoint for applying D5-to-D5 migrations to content that is already
 * in D5 blocks format, without performing any D4-to-D5 conversion.
 *
 * @since ??
 */
class ContentMigrationController extends RESTController {

	/**
	 * Apply D5 migrations to content without performing D4-to-D5 conversion.
	 *
	 * This endpoint accepts content that is already in D5 blocks format and applies
	 * only D5-to-D5 migrations (AttributeMigration, FlexboxMigration, etc.) without
	 * performing any D4 shortcode conversion.
	 *
	 * Use this when you have D5 blocks that need migration but don't require conversion
	 * from D4 shortcodes.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object containing the content to migrate.
	 *
	 * @return WP_REST_Response|WP_Error Returns migrated content or error response.
	 *
	 * @example:
	 * ```php
	 * $request = new WP_REST_Request( 'POST', '/divi/v1/content-migration' );
	 * $request->set_param( 'content', '<!-- wp:divi/section -->...<!-- /wp:divi/section -->' );
	 *
	 * $response = ContentMigrationController::migrate_content( $request );
	 * ```
	 */
	public static function migrate_content( WP_REST_Request $request ) {
		$content = $request->get_param( 'content' );

		if ( empty( $content ) ) {
			return self::response_error( 'empty_content', esc_html__( 'Content cannot be empty.', 'et_builder_5' ) );
		}

		try {
			// Apply only D5-to-D5 migrations (AttributeMigration, FlexboxMigration, etc.).
			// No D4-to-D5 conversion is performed.
			$migrated_content = apply_filters( 'divi_framework_portability_import_migrated_post_content', $content );

			$response_data = [
				'original_content' => $content,
				'migrated_content' => $migrated_content,
			];

			return self::response_success( $response_data );

		} catch ( Exception $e ) {
			return self::response_error(
				'migration_failed',
				sprintf(
					esc_html__( 'Content migration failed: %s', 'et_builder_5' ),
					$e->getMessage()
				)
			);
		} catch ( Error $e ) {
			return self::response_error(
				'migration_fatal_error',
				esc_html__( 'Content migration encountered a fatal error.', 'et_builder_5' )
			);
		}
	}

	/**
	 * Get arguments for migrate_content action.
	 *
	 * Defines an array of arguments for the migrate_content action used in `register_rest_route()`.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for migrate_content action.
	 */
	public static function migrate_content_args(): array {
		return [
			'content' => [
				'required'          => true,
				'sanitize_callback' => [ __CLASS__, 'sanitize_content' ],
				'validate_callback' => [ __CLASS__, 'validate_content' ],
			],
		];
	}

	/**
	 * Sanitize content parameter.
	 *
	 * Preserves script tags for attributes that meet either condition:
	 * 1. Attribute has `allowHtml: true` in module metadata AND user has `unfiltered_html` capability (e.g., Code Module)
	 * 2. Attribute has `elementType: 'content'` AND user has `unfiltered_html` capability (e.g., Text Module)
	 *
	 * SVG module `svg.innerContent.*.value.code` strings use `SvgSanitizer::sanitize_markup()` (strict SVG allowlist, all roles).
	 *
	 * All other content is sanitized using `wp_kses_post()` to strip script tags.
	 *
	 * @since ??
	 *
	 * @param string $content The content to sanitize.
	 *
	 * @return string The sanitized content.
	 */
	public static function sanitize_content( string $content ): string {
		// Parse blocks to access individual block attributes.
		$blocks = parse_blocks( $content );

		if ( empty( $blocks ) ) {
			// If parsing fails or no blocks found, apply standard sanitization.
			return wp_kses_post( $content );
		}

		// Process blocks recursively to preserve script tags for allowHtml attributes.
		$processed_blocks = self::_sanitize_blocks_with_allow_html( $blocks );

		// Re-serialize blocks maintaining preserved script tags.
		return serialize_blocks( $processed_blocks );
	}

	/**
	 * Recursively sanitize blocks while preserving script tags for attributes with allowHtml.
	 *
	 * @since ??
	 *
	 * @param array $blocks Array of parsed blocks to process.
	 *
	 * @return array Processed blocks with script tags preserved for allowHtml attributes.
	 */
	private static function _sanitize_blocks_with_allow_html( array $blocks ): array {
		$processed_blocks = [];

		foreach ( $blocks as $block ) {
			$block_name = $block['blockName'] ?? null;

			// Process inner blocks recursively first.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::_sanitize_blocks_with_allow_html( $block['innerBlocks'] );
			}

			// Get module metadata to check for allowHtml attributes.
			// This works for all registered modules (core Divi modules and third-party modules).
			if ( ! empty( $block_name ) && ! empty( $block['attrs'] ) ) {
				// Try to get metadata from core modules first.
				$metadata = ModuleRegistration::get_core_module_metadata( $block_name );

				// If not found in core modules, try to get from registered block type (includes third-party modules).
				if ( empty( $metadata ) ) {
					$module_settings = ModuleRegistration::get_module_settings( $block_name );
					if ( $module_settings ) {
						// WP_Block_Type stores the full metadata passed during registration.
						// Access the attributes from the block type's stored metadata.
						$block_attrs = $module_settings->get_attributes();
						if ( ! empty( $block_attrs ) ) {
							// Convert block attributes schema to module metadata format.
							// Block attributes may have different structure, so we check for allowHtml directly.
							$metadata = [ 'attributes' => $block_attrs ];
						}
					}
				}

				if ( ! empty( $metadata['attributes'] ) ) {
					// Process block attributes to preserve script tags for allowHtml attributes.
					$block['attrs'] = self::_sanitize_block_attrs_with_allow_html(
						$block['attrs'],
						$metadata['attributes'],
						$block_name
					);
				}
			}

			$processed_blocks[] = $block;
		}

		return $processed_blocks;
	}

	/**
	 * Sanitize block attributes while preserving script tags for specific attributes.
	 *
	 * Script tags are preserved when either condition is met:
	 * 1. Attribute has `allowHtml: true` in module metadata AND user has `unfiltered_html` capability (e.g., Code Module)
	 * 2. Attribute has `elementType: 'content'` AND user has `unfiltered_html` capability (e.g., Text Module)
	 *
	 * SVG module `svg.innerContent.*.value.code` strings are sanitized with `SvgSanitizer::sanitize_markup()`.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Block attributes to sanitize.
	 * @param array  $metadata    Module metadata attributes structure.
	 * @param string $block_name  Block name (e.g. `divi/svg`).
	 * @param array  $path        Current attribute path for nested processing.
	 *
	 * @return array Sanitized attributes with script tags preserved for qualifying attributes.
	 */
	private static function _sanitize_block_attrs_with_allow_html( array $attrs, array $metadata, string $block_name, array $path = [] ): array {
		$sanitized_attrs = [];

		foreach ( $attrs as $key => $value ) {
			$current_path = array_merge( $path, [ $key ] );

			if ( is_array( $value ) ) {
				// Recursively process nested arrays.
				$sanitized_attrs[ $key ] = self::_sanitize_block_attrs_with_allow_html( $value, $metadata, $block_name, $current_path );
			} elseif ( is_string( $value ) ) {
				// Extract base attribute name from path (e.g., 'content.innerContent.desktop.value' → 'content').
				$base_attr_name = self::_extract_base_attribute_name( $current_path );

				// Check if this attribute has allowHtml enabled in metadata.
				$has_allow_html = ! empty( $base_attr_name )
					&& isset( $metadata[ $base_attr_name ]['allowHtml'] )
					&& true === $metadata[ $base_attr_name ]['allowHtml']
					&& current_user_can( 'unfiltered_html' );

				// Check if this attribute is a richtext field (elementType: 'content') and user has unfiltered_html capability.
				$is_richtext_with_capability = ! empty( $base_attr_name )
					&& isset( $metadata[ $base_attr_name ]['elementType'] )
					&& 'content' === $metadata[ $base_attr_name ]['elementType']
					&& current_user_can( 'unfiltered_html' );

				// Preserve script tags if either condition is met.
				if ( $has_allow_html || $is_richtext_with_capability ) {
					// Preserve script tags for:
					// 1. Attributes with allowHtml: true when user has unfiltered_html capability (e.g., Code Module).
					// 2. Richtext attributes when user has unfiltered_html capability (e.g., Text Module).
					// Skip sanitization entirely for these attributes to allow scripts.
					$sanitized_attrs[ $key ] = $value;
				} elseif ( SavingUtility::is_module_css_free_form_attr_path( $current_path ) ) {
					// Custom CSS free-form must not use wp_kses_post() — it encodes `>` as `&gt;` and breaks selectors
					// (for example when the client runs content through the `/divi/v1/content-migration` sanitize callback).
					$sanitized_attrs[ $key ] = SavingUtility::sanitize_css( $value, false, false, true );
				} elseif ( self::_is_svg_module_code_attr_path( $block_name, $current_path ) ) {
					$sanitized_attrs[ $key ] = SvgSanitizer::sanitize_markup( $value );
				} else {
					// Apply standard sanitization for all other attributes.
					$sanitized_attrs[ $key ] = wp_kses_post( $value );
				}
			} else {
				// Preserve non-string values as-is.
				$sanitized_attrs[ $key ] = $value;
			}
		}

		return $sanitized_attrs;
	}

	/**
	 * Whether the attribute path targets SVG module pasted markup (`svg.innerContent.*.value.code`).
	 *
	 * @since ??
	 *
	 * @param string $block_name Block name (e.g. `divi/svg`).
	 * @param array  $path       Attribute path array.
	 *
	 * @return bool
	 */
	private static function _is_svg_module_code_attr_path( string $block_name, array $path ): bool {
		if ( 'divi/svg' !== $block_name || empty( $path ) || 'svg' !== $path[0] ) {
			return false;
		}

		if ( 'code' !== end( $path ) ) {
			return false;
		}

		return in_array( 'innerContent', $path, true );
	}

	/**
	 * Extract base attribute name from nested attribute path.
	 *
	 * For paths like 'content.innerContent.desktop.value', extracts 'content'.
	 * The base attribute name is the first element in the path.
	 *
	 * @since ??
	 *
	 * @param array $path Attribute path array (e.g., ['content', 'innerContent', 'desktop', 'value']).
	 *
	 * @return string|null Base attribute name or null if path is empty.
	 */
	private static function _extract_base_attribute_name( array $path ) {
		if ( empty( $path ) ) {
			return null;
		}

		// The first element in the path is the base attribute name.
		return $path[0];
	}

	/**
	 * Validate content parameter.
	 *
	 * @since ??
	 *
	 * @param string $content The content to validate.
	 *
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_content( string $content ) {
		if ( empty( trim( $content ) ) ) {
			return new WP_Error( 'empty_content', esc_html__( 'Content cannot be empty.', 'et_builder_5' ) );
		}

		return true;
	}

	/**
	 * Check if user has permission for migrate_content action.
	 *
	 * Checks if the current user has the permission to edit posts, used in `register_rest_route()`.
	 *
	 * @since ??
	 *
	 * @return bool|WP_Error Returns `true` if the user has permission, or `WP_Error` if the user does not have permission.
	 */
	public static function migrate_content_permission() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return self::response_error_permission();
		}

		return true;
	}
}
