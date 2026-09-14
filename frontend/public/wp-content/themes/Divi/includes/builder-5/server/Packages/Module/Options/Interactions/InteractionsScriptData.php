<?php
/**
 * Module Options: Interactions Script Data Class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Interactions;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\ScriptData;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Packages\Module\Options\Attributes\AttributeUtils;
use ET\Builder\Packages\Module\Options\Interactions\InteractionUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

/**
 * Module Options: Interactions script data
 *
 * @since ??
 */
class InteractionsScriptData {

	/**
	 * Flag to track if preset selector registration is in progress.
	 *
	 * Used to prevent infinite recursion when `do_blocks()` is called during
	 * preset selector registration, which triggers script data collection again.
	 *
	 * @since ??
	 *
	 * @var bool
	 */
	private static $_registering_preset_selectors = false;

	/**
	 * Set the interactions data item.
	 *
	 * This function sets the interactions data item by registering it with the ScriptData class.
	 * The interactions data item contains information such as the module ID, selector, and interaction configurations.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of arguments.
	 *
	 *     @type string $id            Optional. The module ID. Default empty string.
	 *     @type string $selector      Optional. The selector for the module element. Default empty string.
	 *     @type array  $attr          Optional. The interactions attributes. Default `[]`.
	 *     @type int    $storeInstance Optional. The ID of instance where this block stored in BlockParserStore.
	 *                                 Default `null`.
	 * }
	 *
	 * @return void
	 *
	 * @example:
	 * ```php
	 * InteractionsScriptData::set( [
	 *     'id'            => 'divi/blurb-0',
	 *     'selector'      => '.et_pb_blurb_0',
	 *     'attr'          => [
	 *       'desktop' => [
	 *         'value' => [
	 *           'interactions' => [...]
	 *         ]
	 *       ]
	 *     ],
	 *     'storeInstance' => 123,
	 * ] );
	 * ```
	 */
	public static function set( array $args ): void {
		$args = wp_parse_args(
			$args,
			[
				'id'            => '',
				'selector'      => '',
				'attr'          => [],
				'storeInstance' => null,
			]
		);

		if ( ! InteractionUtils::has_interactions( $args['attr'] ) ) {
			return;
		}

		// Generate interactions data.
		$processed_interactions = self::generate_data( $args );

		// Generate globally unique ID for interactions script data to prevent collisions
		// across Theme Builder areas and regular page content.
		$unique_id = ModuleUtils::get_unique_module_id( $args['id'], $args['storeInstance'] );

		// Register script data item.
		ScriptData::add_data_item(
			[
				'data_name'    => 'interactions',
				'data_item_id' => $unique_id,
				'data_item'    => $processed_interactions,
			]
		);

		// Register preset selectors as used so their CSS gets rendered.
		// Skip if already registering to prevent infinite recursion.
		if ( ! empty( $processed_interactions ) && ! self::$_registering_preset_selectors ) {
			self::_register_preset_selectors_as_used( $processed_interactions );
		}
	}



	/**
	 * Generate interactions data for the frontend script.
	 *
	 * @since ??
	 *
	 * @param array $args The arguments containing module info and attributes.
	 *
	 * @return array The generated interactions data.
	 */
	public static function generate_data( array $args ): array {
		$id   = $args['id'] ?? '';
		$attr = $args['attr'] ?? [];

		if ( empty( $attr ) ) {
			return [];
		}

		// Get interactions from desktop value (interactions don't support responsive breakpoints).
		$interactions_value = $attr['desktop']['value'] ?? [];
		$interactions       = $interactions_value['interactions'] ?? [];

		if ( empty( $interactions ) ) {
			return [];
		}

		// Process interactions for frontend consumption.
		$processed_interactions = [];
		foreach ( $interactions as $interaction ) {

			// Use stored trigger class or generate one as fallback (for element that gets interacted with).
			$trigger_class = $interaction['triggerClass'] ?? 'et-interaction-trigger-' . substr( md5( $id . '-trigger' ), 0, 8 );

			$processed_interaction = [
				'id'                    => $interaction['id'] ?? '',
				'triggerClass'          => $trigger_class, // Element that gets interacted with.
				'trigger'               => $interaction['trigger'] ?? '',
				'effect'                => $interaction['effect'] ?? '',
				'target'                => [ // Element that receives the effect.
					'targetClass' => $interaction['target']['targetClass'] ?? '',
					'label'       => $interaction['target']['label'] ?? '',
					'targetType'  => $interaction['target']['targetType'] ?? 'module',
				],
				'attributeName'         => $interaction['attributeName'] ?? '',
				'attributeValue'        => $interaction['attributeValue'] ?? '',
				'cookieName'            => $interaction['cookieName'] ?? '',
				'cookieValue'           => $interaction['cookieValue'] ?? '',
				'timeDelay'             => $interaction['timeDelay'] ?? '0ms',
				'presetId'              => $interaction['presetId'] ?? '',
				'replaceExistingPreset' => $interaction['replaceExistingPreset'] ?? false,
				'sensitivity'           => $interaction['sensitivity'] ?? 50, // Sensitivity for mirror mouse movement effect (0-100).
				'mouseMovementType'     => $interaction['mouseMovementType'] ?? 'translate', // Type of mouse movement effect (translate, scale, opacity, tilt, rotate).
				'breakpointName'        => $interaction['breakpointName'] ?? '', // Breakpoint name for breakpointEnter and breakpointExit triggers.
				'enabled'               => true,
			];

			// Extract custom attributes from preset if this is a preset-related effect.
			$effect = $interaction['effect'] ?? '';
			if ( str_contains( $effect, 'Preset' ) && ! empty( $interaction['presetId'] ) ) {
				$custom_attributes = self::_extract_preset_custom_attributes( $interaction['presetId'] );
				if ( ! empty( $custom_attributes ) ) {
					$processed_interaction['customAttributes'] = $custom_attributes;
				}

				$filtered_interaction = apply_filters(
					'divi_module_options_interactions_processed_preset_interaction',
					$processed_interaction,
					$interaction,
					$interaction['presetId']
				);

				// Guard against invalid filter return values to keep interactions processing stable.
				if ( is_array( $filtered_interaction ) ) {
					$processed_interaction = $filtered_interaction;
				}
			}

			$processed_interactions[] = $processed_interaction;
		}

		if ( empty( $processed_interactions ) ) {
			return [];
		}

		// Return just the interactions array since ScriptData will use module ID as key.
		return $processed_interactions;
	}

	/**
	 * Extract custom attributes from preset data for interactions.
	 *
	 * @since ??
	 *
	 * @param string $preset_id The preset ID.
	 *
	 * @return array Associative array of custom attributes for the main container (attribute name => value).
	 */
	private static function _extract_preset_custom_attributes( string $preset_id ): array {
		$preset_data = GlobalPreset::find_preset_data_by_id( $preset_id );

		if ( ! $preset_data || empty( $preset_data['renderAttrs'] ) ) {
			return [];
		}

		$render_attrs = $preset_data['renderAttrs'];

		// Extract custom attributes from module.decoration.attributes.
		$custom_attributes_data = $render_attrs['module']['decoration']['attributes'] ?? [];

		if ( empty( $custom_attributes_data ) ) {
			return [];
		}

		// Separate attributes by target element and get main container attributes.
		$separated_attributes = AttributeUtils::separate_attributes_by_target_element( $custom_attributes_data );

		return $separated_attributes['main'] ?? [];
	}

	/**
	 * Register preset selectors as used so their CSS gets rendered by the existing preset system.
	 *
	 * This method uses a re-entrancy guard to prevent infinite recursion when `do_blocks()` is called.
	 * The recursion occurs because `do_blocks()` triggers the WordPress block rendering pipeline,
	 * which calls script data collection again, leading to nested calls to this method.
	 *
	 * @since ??
	 *
	 * @param array $interactions Array of processed interactions.
	 *
	 * @return void
	 */
	private static function _register_preset_selectors_as_used( array $interactions ): void {
		// If already registering, skip to prevent infinite recursion.
		if ( self::$_registering_preset_selectors ) {
			return;
		}

		$fake_blocks = [];

		foreach ( $interactions as $interaction ) {
			if ( ! str_contains( $interaction['effect'], 'Preset' ) || empty( $interaction['presetId'] ) ) {
				continue;
			}

			$preset_id = $interaction['presetId'];

			// Find the preset data to determine its module type.
			$preset_data = GlobalPreset::find_preset_data_by_id( $preset_id );

			if ( ! $preset_data ) {
				continue;
			}

			// Get module name and ensure it has the divi/ prefix.
			$module_name = $preset_data['moduleName'] ?? 'text';
			$block_name  = str_starts_with( $module_name, 'divi/' ) ? $module_name : 'divi/' . $module_name;

			// Create a fake block with the preset assigned.
			$fake_blocks[] = sprintf(
				'<!-- wp:%s {"modulePreset":"%s"} --><!-- /wp:%s -->',
				$block_name,
				$preset_id,
				$block_name
			);
		}

		if ( ! empty( $fake_blocks ) ) {
			$fake_content = implode( "\n", $fake_blocks );

			// Set flag before calling do_blocks() to prevent nested execution.
			self::$_registering_preset_selectors = true;

			try {
				// Process the fake content to trigger preset CSS generation.
				// This will call the normal module rendering pipeline which handles preset CSS.
				do_blocks( $fake_content );
			} finally {
				// Always reset flag, even if an exception occurs.
				self::$_registering_preset_selectors = false;
			}
		}
	}
}
