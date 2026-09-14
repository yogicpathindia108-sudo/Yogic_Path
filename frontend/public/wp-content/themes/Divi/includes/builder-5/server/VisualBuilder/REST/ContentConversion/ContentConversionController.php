<?php
/**
 * REST: ContentConversionController class.
 *
 * @package Builder\VisualBuilder\REST
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\ContentConversion;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Packages\Conversion\Conversion as BuilderConversion;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Content Conversion REST API Controller class.
 *
 * Provides REST endpoint for converting D4 shortcode content to D5 blocks format
 * using the server-side PHP conversion system.
 *
 * @since ??
 */
class ContentConversionController extends RESTController {

	/**
	 * Convert D4 shortcode content to D5 blocks format and apply D5 migrations.
	 *
	 * This endpoint accepts raw content and returns the fully migrated D5 blocks format.
	 * It handles both:
	 * - D4-to-D5 conversion (shortcode to blocks format)
	 * - D5-to-D5 migrations (AttributeMigration, FlexboxMigration, etc.)
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object containing the content to convert.
	 *
	 * @return WP_REST_Response|WP_Error Returns converted and migrated content or error response.
	 *
	 * @example:
	 * ```php
	 * $request = new WP_REST_Request( 'POST', '/divi/v1/content-conversion' );
	 * $request->set_param( 'content', '[et_pb_section]...[/et_pb_section]' );
	 * $request->set_param( 'post_id', 123 ); // Optional, for global module templates
	 *
	 * $response = ContentConversionController::convert_content( $request );
	 * ```
	 */
	public static function convert_content( WP_REST_Request $request ) {
		$content = $request->get_param( 'content' );
		$post_id = $request->get_param( 'post_id' ); // Optional: for global module template context.

		if ( empty( $content ) ) {
			return self::response_error( 'empty_content', esc_html__( 'Content cannot be empty.', 'et_builder_5' ) );
		}

		try {
			$has_shortcode = Conditions::has_shortcode( '', $content );

			// Check if content needs D4-to-D5 conversion.
			if ( $has_shortcode ) {

				// Initialize shortcode framework and prepare for conversion.
				BuilderConversion::initialize_shortcode_framework();

				// Prepare for D4 to D5 conversion by ensuring module definitions are available.
				do_action( 'divi_visual_builder_before_d4_conversion' );

				// Apply full D4-to-D5 conversion (includes migration + format conversion).
				// Pass post_id for global module template selective sync conversion.
				$converted_content = BuilderConversion::maybeConvertContent( $content, true, $post_id );
			} else {
				// Content is already in D5 format or doesn't contain shortcodes.
				$converted_content = $content;
			}

			// Apply D5-to-D5 migrations (AttributeMigration, FlexboxMigration, etc.).
			$converted_content = apply_filters( 'divi_framework_portability_import_migrated_post_content', $converted_content );

			$response_data = [
				'original_content'  => $content,
				'converted_content' => $converted_content,
			];

			return self::response_success( $response_data );

		} catch ( Exception $e ) {
			return self::response_error(
				'conversion_failed',
				sprintf(
					esc_html__( 'Content conversion failed: %s', 'et_builder_5' ),
					$e->getMessage()
				)
			);
		} catch ( Error $e ) {
			return self::response_error(
				'conversion_fatal_error',
				esc_html__( 'Content conversion encountered a fatal error.', 'et_builder_5' )
			);
		}
	}

	/**
	 * Get arguments for convert_content action.
	 *
	 * Defines an array of arguments for the convert_content action used in `register_rest_route()`.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for convert_content action.
	 */
	public static function convert_content_args(): array {
		return [
			'content' => [
				'required'          => true,
				'sanitize_callback' => [ __CLASS__, 'sanitize_content' ],
				'validate_callback' => [ __CLASS__, 'validate_content' ],
			],
			'post_id' => [
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'description'       => esc_html__( 'Optional post ID for global module template context (enables selective sync conversion).', 'et_builder' ),
			],
		];
	}

	/**
	 * Sanitize content parameter.
	 *
	 * @since ??
	 *
	 * @param string $content The content to sanitize.
	 *
	 * @return string The sanitized content.
	 */
	public static function sanitize_content( string $content ): string {
		// Allow HTML and shortcodes in content.
		return wp_kses_post( $content );
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
	 * Check if user has permission for convert_content action.
	 *
	 * Checks if the current user has the permission to edit posts, used in `register_rest_route()`.
	 *
	 * @since ??
	 *
	 * @return bool|WP_Error Returns `true` if the user has permission, or `WP_Error` if the user does not have permission.
	 */
	public static function convert_content_permission() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return self::response_error_permission();
		}

		return true;
	}

	/**
	 * Convert presets from D4 format to D5 format and apply D5 migrations.
	 *
	 * This method handles the conversion of D4 presets to D5 format using the PHP conversion system
	 * and applies D5 preset migrations. It takes D4 preset data and returns the fully converted and
	 * migrated D5 preset data, following the same pattern as the portability system.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object containing the presets to convert.
	 *
	 * @return WP_REST_Response|WP_Error Returns converted and migrated presets or error response.
	 *
	 * @example:
	 * ```php
	 * $request = new WP_REST_Request( 'POST', '/divi/v1/preset-conversion' );
	 * $request->set_param( 'presets', $d4_presets_data );
	 * $response = ContentConversionController::convert_presets( $request );
	 * ```
	 */
	public static function convert_presets( WP_REST_Request $request ) {
		$presets = $request->get_param( 'presets' );

		if ( empty( $presets ) ) {
			return self::response_error( 'empty_presets', esc_html__( 'Presets cannot be empty.', 'et_builder_5' ) );
		}

		try {
			// Convert D4 presets to D5, apply migrations, process IDs, and create default presets.
			// This handles the complete preset conversion workflow without saving to database.
			$processed_result                = GlobalPreset::process_presets_for_import( $presets, false );
			$processed_presets               = $processed_result['presets'];
			$preset_id_mappings              = $processed_result['preset_id_mappings'];
			$default_imported_module_presets = $processed_result['defaultImportedModulePresetIds'] ?? [];
			$default_imported_group_presets  = $processed_result['defaultImportedGroupPresetIds'] ?? [];

			$response_data = [
				'original_presets'               => $presets,
				'converted_presets'              => $processed_presets,
				'preset_id_mappings'             => $preset_id_mappings,
				'defaultImportedModulePresetIds' => $default_imported_module_presets,
				'defaultImportedGroupPresetIds'  => $default_imported_group_presets,
			];

			return self::response_success( $response_data );

		} catch ( Exception $e ) {
			return self::response_error(
				'presets_conversion_failed',
				sprintf(
					esc_html__( 'Presets conversion failed: %s', 'et_builder_5' ),
					$e->getMessage()
				)
			);
		} catch ( Error $e ) {
			return self::response_error(
				'presets_conversion_fatal_error',
				esc_html__( 'Presets conversion encountered a fatal error.', 'et_builder_5' )
			);
		}
	}

	/**
	 * Defines the arguments for the convert_presets endpoint.
	 *
	 * This method specifies the required and optional parameters for the preset conversion endpoint.
	 *
	 * @since ??
	 *
	 * @return array An array of argument definitions for the REST endpoint.
	 */
	public static function convert_presets_args(): array {
		return [
			'presets' => [
				'required'    => true,
				'type'        => [ 'object', 'array' ],
				'description' => esc_html__( 'The D4 presets data to convert to D5 format and apply migrations.', 'et_builder_5' ),
			],
		];
	}

	/**
	 * Check if user has permission for convert_presets action.
	 *
	 * Checks if the current user has the permission to edit posts, used in `register_rest_route()`.
	 *
	 * @since ??
	 *
	 * @return bool|WP_Error Returns `true` if the user has permission, or `WP_Error` if the user does not have permission.
	 */
	public static function convert_presets_permission() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return self::response_error_permission();
		}

		return true;
	}
}
