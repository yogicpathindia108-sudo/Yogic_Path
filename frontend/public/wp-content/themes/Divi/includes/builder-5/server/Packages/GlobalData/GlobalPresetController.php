<?php
/**
 * REST: GlobalPresetController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\GlobalData;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GlobalPreset REST Controller class.
 *
 * @since ??
 */
class GlobalPresetController extends RESTController {

	/**
	 * Transient expiration time in seconds (5 minutes).
	 *
	 * @since ??
	 */
	const CHUNK_TRANSIENT_EXPIRATION = 300;

	/**
	 * Sync global preset data with the server.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error Returns the REST response object or WP_Error if validation fails.
	 */
	public static function sync( WP_REST_Request $request ) {
		$is_chunked    = $request->get_param( 'isChunked' );
		$chunk_index   = $request->get_param( 'chunkIndex' );
		$total_chunks  = $request->get_param( 'totalChunks' );
		$is_last_chunk = $request->get_param( 'isLastChunk' );
		$request_id    = $request->get_param( 'requestId' );
		$action_type   = $request->get_param( 'actionType' ) ?? '';

		// Validate chunked request: if isChunked is true, all chunking parameters must be present.
		if ( true === $is_chunked ) {
			if ( null === $chunk_index || null === $total_chunks || null === $is_last_chunk || null === $request_id ) {
				return RESTController::response_error(
					'invalid_chunked_request',
					esc_html__( 'Invalid chunked request: missing required chunking parameters.', 'et_builder_5' ),
					[
						'status' => 400,
					]
				);
			}
		}

		// Validate non-chunked request: if isChunked is false (or null/defaults to false), chunking parameters must not be present.
		if ( false === $is_chunked || null === $is_chunked ) {
			if ( null !== $chunk_index || null !== $total_chunks || null !== $is_last_chunk || null !== $request_id ) {
				return RESTController::response_error(
					'invalid_non_chunked_request',
					esc_html__( 'Invalid non-chunked request: chunking parameters should not be present.', 'et_builder_5' ),
					[
						'status' => 400,
					]
				);
			}
		}

		// Handle chunked requests: accumulate chunks before processing.
		// Check explicit isChunked flag first, then verify all required chunking parameters are present.
		if ( true === $is_chunked ) {
			// Get transient key for this request ID.
			$transient_key = self::_get_chunk_transient_key( $request_id );

			// Get existing accumulated chunks from transient.
			$accumulated_data = get_transient( $transient_key );

			// Initialize accumulation for this request ID if not exists.
			if ( false === $accumulated_data ) {
				$accumulated_data = [
					'chunks'      => [],
					'actionType'  => $action_type,
					'totalChunks' => $total_chunks,
				];
			}

			// Store this chunk.
			$chunk_presets                              = $request->get_param( 'presets' );
			$accumulated_data['chunks'][ $chunk_index ] = $chunk_presets;

			// Update transient with new chunk data.
			set_transient( $transient_key, $accumulated_data, self::CHUNK_TRANSIENT_EXPIRATION );

			// If this is not the last chunk, return early with success (chunk received).
			if ( ! $is_last_chunk ) {
				return RESTController::response_success( (object) [] );
			}

			// This is the last chunk - verify we received all expected chunks before merging.
			$accumulated_chunks   = $accumulated_data['chunks'];
			$received_chunk_count = count( $accumulated_chunks );

			// Verify we have all chunks (chunk indices are 0-based, so total should match).
			if ( $received_chunk_count !== $total_chunks ) {
				// Clean up transient on error.
				delete_transient( $transient_key );

				return RESTController::response_error(
					'incomplete_chunks',
					sprintf(
						// translators: %1$d: received chunks, %2$d: expected chunks.
						esc_html__( 'Incomplete chunk data received. Expected %1$d chunks, received %2$d chunks.', 'et_builder_5' ),
						$total_chunks,
						$received_chunk_count
					),
					[
						'status'          => 400,
						'expectedChunks'  => $total_chunks,
						'receivedChunks'  => $received_chunk_count,
						'receivedIndices' => array_keys( $accumulated_chunks ),
					]
				);
			}

			// Merge all chunks and process.
			$merged_presets = self::_merge_chunks( $accumulated_chunks );

			// Clean up transient after successful merge.
			delete_transient( $transient_key );

			// Use merged presets for processing.
			$prepared_data = GlobalPreset::prepare_data( $merged_presets );
		} else {
			// Non-chunked request - process normally.
			$prepared_data = GlobalPreset::prepare_data( $request->get_param( 'presets' ) );
		}

		// CRITICAL SAFETY CHECK: Validate that we're not accidentally losing presets during sync.
		// Only DELETE actions should reduce the preset count. All other operations (save, add, update)
		// should preserve or increase the preset count.
		// This prevents bugs where incomplete restore points or data corruption causes preset loss.
		// We compare against the current database state (source of truth), not client-side state.
		$current_presets  = GlobalPreset::get_data();
		$validation_error = GlobalPreset::validate_preset_count( $current_presets, $prepared_data, $action_type );

		if ( is_wp_error( $validation_error ) ) {
			// Clean up transient on validation error.
			if ( null !== $request_id ) {
				$transient_key = self::_get_chunk_transient_key( $request_id );
				delete_transient( $transient_key );
			}

			return $validation_error;
		}

		// Handle deletion cleanup for D4 legacy presets.
		// When a preset is deleted in D5, we need to also remove it from the D4 legacy option
		// to prevent re-migration on page refresh.
		if ( 'DELETE_MODULE_PRESET' === $action_type || 'DELETE_OPTION_GROUP_PRESET' === $action_type ) {
			$preset_type = 'DELETE_MODULE_PRESET' === $action_type ? 'module' : 'group';

			// Get deleted preset IDs by comparing current (before deletion) vs incoming (after deletion).
			$deleted_presets = GlobalPreset::get_deleted_preset_ids( $current_presets, $prepared_data, $preset_type );

			// Remove each deleted preset from the D4 legacy option.
			foreach ( $deleted_presets as $deleted_preset ) {
				GlobalPreset::remove_preset_from_legacy_option(
					$deleted_preset['id'],
					$deleted_preset['moduleName']
				);
			}
		}

		$saved_data = GlobalPreset::save_data( $prepared_data );

		return RESTController::response_success( (object) $saved_data );
	}

	/**
	 * Gets the transient key for chunk accumulation.
	 *
	 * @since ??
	 *
	 * @param string $request_id The unique request ID.
	 * @return string Transient key.
	 */
	private static function _get_chunk_transient_key( string $request_id ): string {
		return 'et_global_preset_chunks_' . get_current_user_id() . '_' . $request_id;
	}

	/**
	 * Merges accumulated chunks into a single preset dataset.
	 *
	 * Chunks are split sequentially by module/group name, so each module/group appears in exactly one chunk.
	 * We simply concatenate arrays directly without deduplication since chunks don't overlap.
	 *
	 * @since ??
	 *
	 * @param array<int, array> $chunks Array of chunk presets, keyed by chunk index.
	 * @return array Merged preset data structure.
	 */
	private static function _merge_chunks( array $chunks ): array {
		$merged = [
			'module' => [],
			'group'  => [],
		];

		// Sort chunks by index to ensure correct order.
		ksort( $chunks );

		// Merge each chunk by directly concatenating arrays (memory efficient).
		foreach ( $chunks as $chunk ) {
			// Ensure chunk is an array.
			if ( ! is_array( $chunk ) ) {
				continue;
			}

			// Concatenate module presets arrays directly.
			if ( isset( $chunk['module'] ) && is_array( $chunk['module'] ) ) {
				foreach ( $chunk['module'] as $module_schema ) {
					$merged['module'][] = $module_schema;
				}
			}

			// Concatenate group presets arrays directly.
			if ( isset( $chunk['group'] ) && is_array( $chunk['group'] ) ) {
				foreach ( $chunk['group'] as $group_schema ) {
					$merged['group'][] = $group_schema;
				}
			}
		}

		return $merged;
	}

	/**
	 * Generates the properties for a preset type.
	 *
	 * @param string $preset_type The type of the preset.
	 * @param array  $extra_items_properties Additional properties to merge with the default item properties.
	 *
	 * @return array The array structure defining the properties of the preset type.
	 */
	public static function preset_type_properties( string $preset_type, array $extra_items_properties = [] ): array {
		$items_properties = array_merge(
			[
				'type'         => [
					'required' => true,
					'type'     => 'string',
					'enum'     => [ $preset_type ],
				],
				'id'           => [
					'required'  => true,
					'type'      => 'string',
					'format'    => 'text-field', // Set format to 'text-field' to get the value sanitized using sanitize_text_field.
					'minLength' => 1, // Prevent empty string.
				],
				'name'         => [
					'required'  => true,
					'type'      => 'string',
					'format'    => 'text-field', // Set format to 'text-field' to get the value sanitized using sanitize_text_field.
					'minLength' => 1, // Prevent empty string.
				],
				'priority'     => [
					'required' => false,
					'type'     => 'integer',
					'default'  => 10, // Default priority value.
				],
				'order'        => [
					'required' => false,
					'type'     => 'integer',
				],
				'created'      => [
					'required' => true,
					'type'     => 'integer',
				],
				'updated'      => [
					'required' => true,
					'type'     => 'integer',
				],
				'version'      => [
					'required'  => true,
					'type'      => 'string',
					'format'    => 'text-field', // Set format to 'text-field' to get the value sanitized using sanitize_text_field.
					'minLength' => 1, // Prevent empty string.
				],
				'attrs'        => [
					'required' => false,
					'type'     => 'object', // Will be sanitized using GlobalPreset::prepare_data().
				],
				'renderAttrs'  => [
					'required' => false,
					'type'     => 'object', // Will be sanitized using GlobalPreset::prepare_data().
				],
				'styleAttrs'   => [
					'required' => false,
					'type'     => 'object', // Will be sanitized using GlobalPreset::prepare_data().
				],
				'groupPresets' => [
					'required'             => false,
					'type'                 => 'object', // Object containing group preset references keyed by group ID.
					'properties'           => [],
					'additionalProperties' => [
						'type'                 => 'object',
						'properties'           => [
							'presetId'  => [
								'required' => true,
								'type'     => 'array',
								'items'    => [
									'type'      => 'string',
									'format'    => 'text-field',
									'minLength' => 1,
								],
								'minItems' => 1, // At least one preset ID required.
							],
							'groupName' => [
								'required'  => true,
								'type'      => 'string',
								'format'    => 'text-field',
								'minLength' => 1,
							],
							'segmentBoundary' => [
								'required' => false,
								'type'     => 'integer',
								'minimum'  => 0,
							],
						],
						'additionalProperties' => false,
					],
				],
			],
			$extra_items_properties
		);

		return [
			'required' => false,
			'type'     => 'array',
			'items'    => [
				'type'                 => 'object',
				'properties'           => [
					'default' => [
						'required' => true,
						'type'     => 'string',
						'format'   => 'text-field', // Set format to 'text-field' to get the value sanitized using sanitize_text_field.
					],
					'items'   => [
						'required' => true,
						'type'     => 'array',
						'items'    => [
							'type'                 => 'object',
							'properties'           => $items_properties,
							'additionalProperties' => false,
						],
					],
				],
				'additionalProperties' => false,
			],
		];
	}

	/**
	 * Get the arguments for the sync action.
	 *
	 * This function returns an array that defines the arguments for the sync action,
	 * which is used in the `register_rest_route()` function.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the sync action. The array should aligns with the GlobalData.Presets.RestSchemaItems TS interface.
	 */
	public static function sync_args(): array {
		return [
			'presets'     => [
				'required'             => true,
				'type'                 => 'object',
				'properties'           => [
					'module' => self::preset_type_properties(
						'module',
						[
							'moduleName' => [
								'required'  => true,
								'type'      => 'string',
								'format'    => 'text-field', // Set format to 'text-field' to get the value sanitized using sanitize_text_field.
								'minLength' => 1, // Prevent empty string.
							],
						]
					),
					'group'  => self::preset_type_properties(
						'group',
						[
							'groupId'         => [
								'required'  => true,
								'type'      => 'string',
								'format'    => 'text-field', // Set format to 'text-field' to get the value sanitized using sanitize_text_field.
								'minLength' => 1, // Prevent empty string.
							],
							'groupName'       => [
								'required'  => true,
								'type'      => 'string',
								'format'    => 'text-field', // Set format to 'text-field' to get the value sanitized using sanitize_text_field.
								'minLength' => 1, // Prevent empty string.
							],
							'moduleName'      => [
								'required'  => true,
								'type'      => 'string',
								'format'    => 'text-field', // Set format to 'text-field' to get the value sanitized using sanitize_text_field.
								'minLength' => 1, // Prevent empty string.
							],
							'primaryAttrName' => [
								'type'   => 'string',
								'format' => 'text-field', // Set format to 'text-field' to get the value sanitized using sanitize_text_field.
							],
						]
					),
				],
				'additionalProperties' => false,
			],
			'actionType'  => [
				'required' => false,
				'type'     => 'string',
				'format'   => 'text-field', // Set format to 'text-field' to get the value sanitized using sanitize_text_field.
			],
			'isChunked'   => [
				'required' => false,
				'type'     => 'boolean',
				'default'  => false,
			],
			'chunkIndex'  => [
				'required' => false,
				'type'     => 'integer',
			],
			'totalChunks' => [
				'required' => false,
				'type'     => 'integer',
			],
			'isLastChunk' => [
				'required' => false,
				'type'     => 'boolean',
			],
			'requestId'   => [
				'required' => false,
				'type'     => 'string',
				'format'   => 'text-field', // Set format to 'text-field' to get the value sanitized using sanitize_text_field.
			],
		];
	}

	/**
	 * Provides the permission status for the sync action.
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has the permission to use the Visual Builder, `false` otherwise.
	 */
	public static function sync_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'edit_theme_options' );
	}
}
