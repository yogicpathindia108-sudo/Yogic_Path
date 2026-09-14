<?php
/**
 * Image Group Preset Migration.
 *
 * Migrates legacy image group preset attrs:
 * - `image.advanced.forceFullwidth` to `image.decoration.sizing.width`
 *   for Woo Product Images presets.
 * - `portrait.innerContent.*.value.url` to `portrait.innerContent.*.value.src`
 *   for Testimonial presets.
 * - `dynamicOptionGroups.designImage` to `dynamicOptionGroups.image`
 *   for modules that moved to `divi/image` image groups, with module-specific
 *   host keys preserved (for example `portrait` and `imageIcon`).
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Migration;

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;

/**
 * Image Group Preset Migration class.
 *
 * @since ??
 */
class ImageGroupPresetMigration extends MigrationPresetsBase {
	/**
	 * Migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'imageGroupPreset.v1';

	/**
	 * Migration release version.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.4.2';

	/**
	 * Attr groups that may include preset attrs.
	 *
	 * @since ??
	 *
	 * @var array<int, string>
	 */
	private const PRESET_ATTR_GROUPS = [ 'attrs', 'renderAttrs', 'styleAttrs' ];

	/**
	 * Module names supported by this migration.
	 *
	 * @since ??
	 *
	 * @var array<int, string>
	 */
	private const SUPPORTED_MODULES = [
		'divi/audio',
		'divi/blog',
		'divi/blurb',
		'divi/comments',
		'divi/filterable-portfolio',
		'divi/fullwidth-header',
		'divi/fullwidth-portfolio',
		'divi/gallery',
		'divi/portfolio',
		'divi/post-content',
		'divi/post-slider',
		'divi/slide',
		'divi/slider',
		'divi/team-member',
		'divi/woocommerce-product-images',
		'divi/woocommerce-cart-products',
		'divi/woocommerce-product-reviews',
		'divi/woocommerce-product-upsell',
		'divi/woocommerce-products',
		'divi/woocommerce-related-products',
		'divi/post-title',
		'divi/testimonial',
	];

	/**
	 * Dynamic image subgroups now provided by `divi/image`.
	 *
	 * @since ??
	 *
	 * @var array<int, string>
	 */
	private const BUILT_IN_DYNAMIC_IMAGE_SUBGROUPS = [
		'sizing',
		'border',
		'box-shadow',
		'boxShadow',
		'fit',
		'filters',
	];

	/**
	 * Register migration hooks.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function load(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_migrate_presets' ], 1 );
	}

	/**
	 * Get migration name.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public static function get_name() {
		return self::$_name;
	}

	/**
	 * Get release version.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public static function get_release_version(): string {
		return self::$_release_version;
	}

	/**
	 * Run migration only in VB contexts.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function maybe_migrate_presets(): void {
		if ( ! (
			Conditions::is_vb_enabled()
			|| Conditions::is_vb_app_window()
			|| Conditions::is_rest_api_request()
		) ) {
			return;
		}

		self::migrate_presets();
	}

	/**
	 * Migrate all preset data.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function migrate_presets(): void {
		$presets_data = GlobalPreset::get_data();

		if ( empty( $presets_data ) || ! self::_has_supported_module_preset_candidates( $presets_data ) ) {
			return;
		}

		if ( ! self::_has_presets_needing_migration( $presets_data ) ) {
			return;
		}

		$changes_made    = false;
		$updated_presets = $presets_data;

		if ( isset( $presets_data['module'] ) && is_array( $presets_data['module'] ) ) {
			foreach ( $presets_data['module'] as $module_name => $module_presets ) {
				if ( empty( $module_presets['items'] ) ) {
					continue;
				}

				foreach ( $module_presets['items'] as $preset_id => $preset_item ) {
					$effective_module_name = self::_resolve_preset_item_module_name( $preset_item, $module_name );
					if ( ! self::_should_migrate_module( $effective_module_name ) ) {
						continue;
					}

					$migrated_preset = self::_migrate_preset_item( $preset_item, $effective_module_name );
					if ( $migrated_preset !== $preset_item ) {
						$updated_presets['module'][ $module_name ]['items'][ $preset_id ] = $migrated_preset;
						$changes_made = true;
					}
				}
			}
		}

		if ( isset( $presets_data['group'] ) && is_array( $presets_data['group'] ) ) {
			foreach ( $presets_data['group'] as $group_name => $group_data ) {
				if ( empty( $group_data['items'] ) ) {
					continue;
				}

				foreach ( $group_data['items'] as $preset_id => $preset_item ) {
					$module_name = $preset_item['moduleName'] ?? '';
					if ( ! is_string( $module_name ) ) {
						$module_name = '';
					}

					$effective_module_name = self::_resolve_preset_item_module_name( $preset_item, $module_name );
					if ( ! self::_should_migrate_module( $effective_module_name ) ) {
						continue;
					}

					$migrated_preset = self::_migrate_preset_item( $preset_item, $effective_module_name );
					if ( $migrated_preset !== $preset_item ) {
						$updated_presets['group'][ $group_name ]['items'][ $preset_id ] = $migrated_preset;
						$changes_made = true;
					}
				}
			}
		}

		if ( $changes_made ) {
			GlobalPreset::save_data( $updated_presets );
		}
	}

	/**
	 * Migrate a single preset item.
	 *
	 * @since ??
	 *
	 * @param array  $preset_item Preset item.
	 * @param string $module_name Module name.
	 *
	 * @return array
	 */
	public static function migrate_preset_item( array $preset_item, string $module_name ): array {
		return self::_migrate_preset_item( $preset_item, $module_name );
	}

	/**
	 * Check whether preset data has migratable items.
	 *
	 * @since ??
	 *
	 * @param array $presets_data Preset data.
	 *
	 * @return bool
	 */
	private static function _has_presets_needing_migration( array $presets_data ): bool {
		$has_legacy_attr = static function ( array $preset_item, string $module_name ): bool {
			$effective_module_name = self::_resolve_preset_item_module_name( $preset_item, $module_name );
			if ( ! self::_should_migrate_module( $effective_module_name ) ) {
				return false;
			}

			$preset_version = $preset_item['version'] ?? '0.0.0';
			if ( ! StringUtility::version_compare( $preset_version, self::$_release_version, '<' ) ) {
				return false;
			}

			foreach ( self::PRESET_ATTR_GROUPS as $attr_group ) {
				$attrs = $preset_item[ $attr_group ] ?? [];
				if ( ! is_array( $attrs ) ) {
					continue;
				}

				if ( self::_has_legacy_image_group_attr( $attrs, $effective_module_name ) ) {
					return true;
				}
			}

			return false;
		};

		foreach ( $presets_data['module'] ?? [] as $module_name => $module_data ) {
			foreach ( $module_data['items'] ?? [] as $preset_item ) {
				if ( $has_legacy_attr( $preset_item, $module_name ) ) {
					return true;
				}
			}
		}

		foreach ( $presets_data['group'] ?? [] as $group_data ) {
			foreach ( $group_data['items'] ?? [] as $preset_item ) {
				$module_name = $preset_item['moduleName'] ?? '';
				if ( ! is_string( $module_name ) ) {
					$module_name = '';
				}

				if ( $has_legacy_attr( $preset_item, $module_name ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Fast guard: check whether preset payload includes any supported module preset candidates.
	 *
	 * This avoids scanning attr payloads when the relevant module presets are not present.
	 *
	 * @since ??
	 *
	 * @param array $presets_data Preset data.
	 *
	 * @return bool
	 */
	private static function _has_supported_module_preset_candidates( array $presets_data ): bool {
		$module_presets = $presets_data['module'] ?? [];
		if ( is_array( $module_presets ) ) {
			foreach ( self::SUPPORTED_MODULES as $module_name ) {
				$items = $module_presets[ $module_name ]['items'] ?? null;
				if ( is_array( $items ) && ! empty( $items ) ) {
					return true;
				}
			}
		}

		$group_presets = $presets_data['group'] ?? [];
		if ( ! is_array( $group_presets ) ) {
			return false;
		}

		foreach ( $group_presets as $group_data ) {
			$items = $group_data['items'] ?? null;
			if ( ! is_array( $items ) || empty( $items ) ) {
				continue;
			}

			foreach ( $items as $preset_item ) {
				$module_name = $preset_item['moduleName'] ?? '';
				if ( is_string( $module_name ) && self::_should_migrate_module( $module_name ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Migrate a single preset item.
	 *
	 * @since ??
	 *
	 * @param array  $preset_item Preset item.
	 * @param string $module_name Module name.
	 *
	 * @return array
	 */
	private static function _migrate_preset_item( array $preset_item, string $module_name ): array {
		if ( ! self::_should_migrate_module( $module_name ) ) {
			return $preset_item;
		}

		$preset_version = $preset_item['version'] ?? '0.0.0';
		if ( ! StringUtility::version_compare( $preset_version, self::$_release_version, '<' ) ) {
			return $preset_item;
		}

		$migrated_preset = $preset_item;
		$changes_made    = false;

		foreach ( self::PRESET_ATTR_GROUPS as $attr_group ) {
			$attrs = $preset_item[ $attr_group ] ?? [];
			if ( ! is_array( $attrs ) ) {
				continue;
			}

			$migrated_attrs = self::_migrate_image_group_attr( $attrs, $module_name );
			if ( $migrated_attrs !== $attrs ) {
				$migrated_preset[ $attr_group ] = $migrated_attrs;
				$changes_made                   = true;
			}
		}

		if ( $changes_made ) {
			$migrated_preset['version'] = self::$_release_version;
		}

		return $migrated_preset;
	}

	/**
	 * Resolve effective module name for preset item.
	 *
	 * @since ??
	 *
	 * @param array  $preset_item Preset item.
	 * @param string $fallback    Fallback module name.
	 *
	 * @return string
	 */
	private static function _resolve_preset_item_module_name( array $preset_item, string $fallback ): string {
		$module_name = $preset_item['moduleName'] ?? null;
		if ( is_string( $module_name ) && '' !== $module_name ) {
			return $module_name;
		}

		return $fallback;
	}

	/**
	 * Determine whether module should be migrated.
	 *
	 * @since ??
	 *
	 * @param string $module_name Module name.
	 *
	 * @return bool
	 */
	private static function _should_migrate_module( string $module_name ): bool {
		return in_array( $module_name, self::SUPPORTED_MODULES, true );
	}

	/**
	 * Check whether attrs include legacy image-group values.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Attr tree.
	 * @param string $module_name Module name.
	 *
	 * @return bool
	 */
	private static function _has_legacy_image_group_attr( array $attrs, string $module_name ): bool {
		return self::_has_legacy_force_fullwidth_attr( $attrs )
			|| self::_has_legacy_post_title_image_sizing_attr( $attrs, $module_name )
			|| self::_has_legacy_testimonial_portrait_url_attr( $attrs )
			|| self::_has_legacy_dynamic_option_group_image_attr( $attrs, $module_name );
	}

	/**
	 * Migrate image-group attrs for the target module.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Attr tree.
	 * @param string $module_name Module name.
	 *
	 * @return array
	 */
	private static function _migrate_image_group_attr( array $attrs, string $module_name ): array {
		$migrated_attrs = $attrs;

		if ( 'divi/woocommerce-product-images' === $module_name ) {
			$migrated_attrs = self::_migrate_force_fullwidth_attr( $migrated_attrs );
		}

		if ( 'divi/testimonial' === $module_name ) {
			$migrated_attrs = self::_migrate_testimonial_portrait_url_attr( $migrated_attrs );
		}

		if ( 'divi/post-title' === $module_name ) {
			$migrated_attrs = self::_migrate_post_title_image_sizing_attr( $migrated_attrs );
		}

		if ( self::_has_legacy_dynamic_option_group_image_attr( $migrated_attrs, $module_name ) ) {
			$migrated_attrs = self::_migrate_dynamic_option_group_image_attr( $migrated_attrs, $module_name );
		}

		return $migrated_attrs;
	}

	/**
	 * Check whether attrs include legacy force fullwidth values.
	 *
	 * @since ??
	 *
	 * @param array $attrs Attr tree.
	 *
	 * @return bool
	 */
	private static function _has_legacy_force_fullwidth_attr( array $attrs ): bool {
		foreach ( self::_get_breakpoints_states() as $breakpoint => $states ) {
			foreach ( $states as $state ) {
				$legacy_toggle = self::_get_legacy_force_fullwidth_value( $attrs, $breakpoint, $state );
				if ( is_string( $legacy_toggle ) && '' !== $legacy_toggle ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check whether attrs include legacy Post Title featured image sizing values.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Attr tree.
	 * @param string $module_name Module name.
	 *
	 * @return bool
	 */
	private static function _has_legacy_post_title_image_sizing_attr( array $attrs, string $module_name ): bool {
		if ( 'divi/post-title' !== $module_name ) {
			return false;
		}

		foreach ( self::_get_breakpoints_states() as $breakpoint => $states ) {
			foreach ( $states as $state ) {
				$legacy_force_fullwidth = self::_get_legacy_featured_image_force_fullwidth_value( $attrs, $breakpoint, $state );
				if ( is_string( $legacy_force_fullwidth ) && '' !== $legacy_force_fullwidth ) {
					return true;
				}

				foreach ( [ 'enabled', 'placement' ] as $advanced_key ) {
					$legacy_advanced_value = self::_get_legacy_featured_image_advanced_value( $attrs, $breakpoint, $state, $advanced_key );
					if ( is_string( $legacy_advanced_value ) && '' !== $legacy_advanced_value ) {
						return true;
					}
				}

				foreach ( [ 'width', 'maxWidth', 'height', 'maxHeight', 'alignment' ] as $sizing_key ) {
					$legacy_sizing_value = self::_get_legacy_featured_image_sizing_value( $attrs, $breakpoint, $state, $sizing_key );
					if ( is_string( $legacy_sizing_value ) && '' !== $legacy_sizing_value ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Check whether attrs include legacy testimonial portrait url values.
	 *
	 * @since ??
	 *
	 * @param array $attrs Attr tree.
	 *
	 * @return bool
	 */
	private static function _has_legacy_testimonial_portrait_url_attr( array $attrs ): bool {
		foreach ( self::_get_breakpoints_states() as $breakpoint => $_states ) {
			$portrait_value = $attrs['portrait']['innerContent'][ $breakpoint ]['value'] ?? null;
			if ( is_array( $portrait_value ) && array_key_exists( 'url', $portrait_value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Migrate force fullwidth attr into sizing width.
	 *
	 * @since ??
	 *
	 * @param array $attrs Attr tree.
	 *
	 * @return array
	 */
	private static function _migrate_force_fullwidth_attr( array $attrs ): array {
		$migrated_attrs = $attrs;
		$changes_made   = false;

		foreach ( self::_get_breakpoints_states() as $breakpoint => $states ) {
			foreach ( $states as $state ) {
				$legacy_toggle = self::_get_legacy_force_fullwidth_value( $attrs, $breakpoint, $state );

				if ( 'on' !== $legacy_toggle ) {
					continue;
				}

				$existing_width = $attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ]['width'] ?? null;
				if ( ! is_string( $existing_width ) || '' === $existing_width ) {
					$migrated_attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ]['width'] = '100%';
					$changes_made = true;
				}
			}
		}

		if ( isset( $attrs['image']['advanced']['forceFullwidth'] ) ) {
			unset( $migrated_attrs['image']['advanced']['forceFullwidth'] );
			$changes_made = true;
		}

		if ( empty( $migrated_attrs['image']['advanced'] ?? null ) ) {
			unset( $migrated_attrs['image']['advanced'] );
		}

		if ( empty( $migrated_attrs['image'] ?? null ) ) {
			unset( $migrated_attrs['image'] );
		}

		return $changes_made ? $migrated_attrs : $attrs;
	}

	/**
	 * Migrate Post Title legacy featured image sizing attrs to image-group sizing attrs.
	 *
	 * @since ??
	 *
	 * @param array $attrs Attr tree.
	 *
	 * @return array
	 */
	private static function _migrate_post_title_image_sizing_attr( array $attrs ): array {
		$migrated_attrs       = $attrs;
		$changes_made         = false;
		$has_legacy_alignment = self::_has_legacy_featured_image_alignment_attr( $attrs );

		foreach ( self::_get_breakpoints_states() as $breakpoint => $states ) {
			foreach ( $states as $state ) {
				$legacy_force_fullwidth = self::_get_legacy_featured_image_force_fullwidth_value( $attrs, $breakpoint, $state );
				if ( 'on' === $legacy_force_fullwidth ) {
					$migrated_attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ]['width'] = '100%';
					$changes_made = true;
				} else {
					foreach ( [ 'width', 'maxWidth' ] as $sizing_key ) {
						$legacy_sizing_value = self::_get_legacy_featured_image_sizing_value( $attrs, $breakpoint, $state, $sizing_key );
						if ( ! is_string( $legacy_sizing_value ) || '' === $legacy_sizing_value ) {
							continue;
						}

						$existing_sizing_value = $attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ][ $sizing_key ] ?? null;
						if ( ! is_string( $existing_sizing_value ) || '' === $existing_sizing_value ) {
							$migrated_attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ][ $sizing_key ] = $legacy_sizing_value;
							$changes_made = true;
						}
					}

					if ( 'off' === $legacy_force_fullwidth ) {
						$legacy_width   = self::_get_legacy_featured_image_sizing_value( $attrs, $breakpoint, $state, 'width' );
						$existing_width = $attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ]['width'] ?? null;
						if ( ( ! is_string( $legacy_width ) || '' === $legacy_width ) && ( ! is_string( $existing_width ) || '' === $existing_width ) ) {
							$migrated_attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ]['width'] = 'auto';
							$changes_made = true;
						}
					}
				}

				foreach ( [ 'height', 'maxHeight' ] as $sizing_key ) {
					$legacy_sizing_value = self::_get_legacy_featured_image_sizing_value( $attrs, $breakpoint, $state, $sizing_key );
					if ( ! is_string( $legacy_sizing_value ) || '' === $legacy_sizing_value ) {
						continue;
					}

					$existing_sizing_value = $attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ][ $sizing_key ] ?? null;
					if ( ! is_string( $existing_sizing_value ) || '' === $existing_sizing_value ) {
						$migrated_attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ][ $sizing_key ] = $legacy_sizing_value;
						$changes_made = true;
					}
				}

				$legacy_alignment = self::_get_legacy_featured_image_sizing_value( $attrs, $breakpoint, $state, 'alignment' );
				if ( is_string( $legacy_alignment ) && '' !== $legacy_alignment ) {
					$align_self = self::_get_align_self_from_alignment( $legacy_alignment );
					if ( null !== $align_self ) {
						$existing_align_self = $attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ]['alignSelf'] ?? null;
						if ( ! is_string( $existing_align_self ) || '' === $existing_align_self ) {
							$migrated_attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ]['alignSelf'] = $align_self;
							$changes_made = true;
						}
					}
				} elseif ( 'desktop' === $breakpoint && 'value' === $state && ! $has_legacy_alignment ) {
					$existing_align_self = $attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ]['alignSelf'] ?? null;
					if ( ! is_string( $existing_align_self ) || '' === $existing_align_self ) {
						// Legacy Post Title image alignment defaulted to center.
						$migrated_attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ]['alignSelf'] = 'center';
						$changes_made = true;
					}
				}

				foreach ( [ 'enabled', 'placement' ] as $advanced_key ) {
					$legacy_advanced_value = self::_get_legacy_featured_image_advanced_value( $attrs, $breakpoint, $state, $advanced_key );
					if ( ! is_string( $legacy_advanced_value ) || '' === $legacy_advanced_value ) {
						continue;
					}

					$existing_advanced_value = $attrs['image']['advanced'][ $advanced_key ][ $breakpoint ]['value'] ?? null;
					if ( ! is_string( $existing_advanced_value ) || '' === $existing_advanced_value ) {
						$migrated_attrs['image']['advanced'][ $advanced_key ][ $breakpoint ]['value'] = $legacy_advanced_value;
						$changes_made = true;
					}
				}

				foreach ( [ 'width', 'maxWidth', 'height', 'maxHeight', 'alignment' ] as $sizing_key ) {
					if ( isset( $attrs['featuredImage']['decoration']['sizing'][ $breakpoint ][ $state ][ $sizing_key ] ) ) {
						unset( $migrated_attrs['featuredImage']['decoration']['sizing'][ $breakpoint ][ $state ][ $sizing_key ] );
						$changes_made = true;
					}
				}
			}
		}

		if ( isset( $attrs['featuredImage']['advanced']['forceFullwidth'] ) ) {
			unset( $migrated_attrs['featuredImage']['advanced']['forceFullwidth'] );
			$changes_made = true;
		}
		if ( isset( $attrs['featuredImage']['advanced']['enabled'] ) ) {
			unset( $migrated_attrs['featuredImage']['advanced']['enabled'] );
			$changes_made = true;
		}
		if ( isset( $attrs['featuredImage']['advanced']['placement'] ) ) {
			unset( $migrated_attrs['featuredImage']['advanced']['placement'] );
			$changes_made = true;
		}

		self::_cleanup_empty_featured_image_sizing_branches( $migrated_attrs );

		if ( empty( $migrated_attrs['featuredImage']['advanced'] ?? null ) ) {
			unset( $migrated_attrs['featuredImage']['advanced'] );
		}

		if ( empty( $migrated_attrs['featuredImage']['decoration']['sizing'] ?? null ) ) {
			unset( $migrated_attrs['featuredImage']['decoration']['sizing'] );
		}

		if ( empty( $migrated_attrs['featuredImage']['decoration'] ?? null ) ) {
			unset( $migrated_attrs['featuredImage']['decoration'] );
		}

		if ( empty( $migrated_attrs['featuredImage'] ?? null ) ) {
			unset( $migrated_attrs['featuredImage'] );
		}

		return $changes_made ? $migrated_attrs : $attrs;
	}

	/**
	 * Migrate testimonial portrait innerContent url to src.
	 *
	 * @since ??
	 *
	 * @param array $attrs Attr tree.
	 *
	 * @return array
	 */
	private static function _migrate_testimonial_portrait_url_attr( array $attrs ): array {
		$migrated_attrs = $attrs;
		$changes_made   = false;

		foreach ( self::_get_breakpoints_states() as $breakpoint => $_states ) {
			$portrait_value = $attrs['portrait']['innerContent'][ $breakpoint ]['value'] ?? null;
			if ( ! is_array( $portrait_value ) || ! array_key_exists( 'url', $portrait_value ) ) {
				continue;
			}

			$legacy_url   = $portrait_value['url'] ?? null;
			$existing_src = $portrait_value['src'] ?? null;

			if ( is_string( $legacy_url ) && ( ! is_string( $existing_src ) || '' === $existing_src ) ) {
				$migrated_attrs['portrait']['innerContent'][ $breakpoint ]['value']['src'] = $legacy_url;
				$changes_made = true;
			}

			unset( $migrated_attrs['portrait']['innerContent'][ $breakpoint ]['value']['url'] );
			$changes_made = true;
		}

		return $changes_made ? $migrated_attrs : $attrs;
	}

	/**
	 * Get legacy dynamicOptionGroups image-host key mappings for module.
	 *
	 * @since ??
	 *
	 * @param string $module_name Module name.
	 *
	 * @return array<string, string>
	 */
	private static function _get_legacy_dynamic_option_group_image_mappings( string $module_name ): array {
		if ( 'divi/testimonial' === $module_name ) {
			return [
				'designImage' => 'portrait',
			];
		}

		if ( 'divi/blurb' === $module_name ) {
			return [
				'designImageIcon' => 'imageIcon',
				'designImage'     => 'imageIcon',
			];
		}

		return [
			'designImage' => 'image',
		];
	}

	/**
	 * Check whether attrs include legacy dynamicOptionGroups image-host values.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Attr tree.
	 * @param string $module_name Module name.
	 *
	 * @return bool
	 */
	private static function _has_legacy_dynamic_option_group_image_attr( array $attrs, string $module_name ): bool {
		$dynamic_option_groups = $attrs['dynamicOptionGroups'] ?? null;
		if ( ! is_array( $dynamic_option_groups ) ) {
			return false;
		}

		foreach ( self::_get_legacy_dynamic_option_group_image_mappings( $module_name ) as $legacy_key => $_target_key ) {
			if ( isset( $dynamic_option_groups[ $legacy_key ] ) && is_array( $dynamic_option_groups[ $legacy_key ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Migrate legacy dynamicOptionGroups image-host branches to current host keys.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Attr tree.
	 * @param string $module_name Module name.
	 *
	 * @return array
	 */
	private static function _migrate_dynamic_option_group_image_attr( array $attrs, string $module_name ): array {
		if ( ! self::_has_legacy_dynamic_option_group_image_attr( $attrs, $module_name ) ) {
			return $attrs;
		}

		$migrated_attrs = $attrs;
		foreach ( self::_get_legacy_dynamic_option_group_image_mappings( $module_name ) as $legacy_key => $target_key ) {
			$legacy_group = $attrs['dynamicOptionGroups'][ $legacy_key ] ?? null;
			if ( ! is_array( $legacy_group ) ) {
				continue;
			}

			$legacy_group   = self::_strip_built_in_dynamic_image_subgroups( $legacy_group );
			$existing_group = $migrated_attrs['dynamicOptionGroups'][ $target_key ] ?? [];
			$merged_group   = self::_merge_dynamic_option_groups( is_array( $existing_group ) ? $existing_group : [], $legacy_group );

			$migrated_attrs['dynamicOptionGroups'][ $target_key ] = $merged_group;
			unset( $migrated_attrs['dynamicOptionGroups'][ $legacy_key ] );
		}

		if ( empty( $migrated_attrs['dynamicOptionGroups'] ?? null ) ) {
			unset( $migrated_attrs['dynamicOptionGroups'] );
		}

		return $migrated_attrs;
	}

	/**
	 * Remove dynamic image subgroups already provided by divi/image.
	 *
	 * @since ??
	 *
	 * @param array $group Legacy dynamic option group.
	 *
	 * @return array
	 */
	private static function _strip_built_in_dynamic_image_subgroups( array $group ): array {
		foreach ( $group as $key => $value ) {
			if ( in_array( $key, self::BUILT_IN_DYNAMIC_IMAGE_SUBGROUPS, true ) && true === $value ) {
				unset( $group[ $key ] );
				continue;
			}

			if ( is_array( $value ) ) {
				$group[ $key ] = self::_strip_built_in_dynamic_image_subgroups( $value );
			}
		}

		return $group;
	}

	/**
	 * Merge legacy dynamicOptionGroups settings into target branch.
	 *
	 * @since ??
	 *
	 * @param array $target Existing target branch.
	 * @param array $source Legacy source branch.
	 *
	 * @return array
	 */
	private static function _merge_dynamic_option_groups( array $target, array $source ): array {
		foreach ( $source as $key => $source_value ) {
			if ( ! array_key_exists( $key, $target ) ) {
				$target[ $key ] = $source_value;
				continue;
			}

			$target_value = $target[ $key ];
			if ( is_array( $target_value ) && is_array( $source_value ) ) {
				$target[ $key ] = self::_merge_dynamic_option_groups( $target_value, $source_value );
				continue;
			}

			if ( true === $source_value ) {
				$target[ $key ] = true;
			}
		}

		return $target;
	}

	/**
	 * Resolve legacy force fullwidth value from either stateful or plain shape.
	 *
	 * @since ??
	 *
	 * @param array  $attrs      Attr tree.
	 * @param string $breakpoint Breakpoint key.
	 * @param string $state      State key.
	 *
	 * @return string|null
	 */
	private static function _get_legacy_force_fullwidth_value( array $attrs, string $breakpoint, string $state ): ?string {
		$legacy_toggle = $attrs['image']['advanced']['forceFullwidth'][ $breakpoint ][ $state ]['value'] ?? null;
		if ( is_string( $legacy_toggle ) && '' !== $legacy_toggle ) {
			return $legacy_toggle;
		}

		$legacy_toggle = $attrs['image']['advanced']['forceFullwidth'][ $breakpoint ]['value'] ?? null;
		if ( is_string( $legacy_toggle ) && '' !== $legacy_toggle ) {
			return $legacy_toggle;
		}

		return null;
	}

	/**
	 * Resolve legacy Post Title featured-image force fullwidth value.
	 *
	 * @since ??
	 *
	 * @param array  $attrs      Attr tree.
	 * @param string $breakpoint Breakpoint key.
	 * @param string $state      State key.
	 *
	 * @return string|null
	 */
	private static function _get_legacy_featured_image_force_fullwidth_value( array $attrs, string $breakpoint, string $state ): ?string {
		$legacy_toggle = $attrs['featuredImage']['advanced']['forceFullwidth'][ $breakpoint ][ $state ]['value'] ?? null;
		if ( is_string( $legacy_toggle ) && '' !== $legacy_toggle ) {
			return $legacy_toggle;
		}

		if ( 'value' !== $state ) {
			return null;
		}

		$legacy_toggle = $attrs['featuredImage']['advanced']['forceFullwidth'][ $breakpoint ]['value'] ?? null;
		if ( is_string( $legacy_toggle ) && '' !== $legacy_toggle ) {
			return $legacy_toggle;
		}

		return null;
	}

	/**
	 * Resolve legacy Post Title featured-image advanced value.
	 *
	 * @since ??
	 *
	 * @param array  $attrs        Attr tree.
	 * @param string $breakpoint   Breakpoint key.
	 * @param string $state        State key.
	 * @param string $advanced_key Advanced key.
	 *
	 * @return string|null
	 */
	private static function _get_legacy_featured_image_advanced_value( array $attrs, string $breakpoint, string $state, string $advanced_key ): ?string {
		$legacy_value = $attrs['featuredImage']['advanced'][ $advanced_key ][ $breakpoint ][ $state ]['value'] ?? null;
		if ( is_string( $legacy_value ) && '' !== $legacy_value ) {
			return $legacy_value;
		}

		$legacy_value = $attrs['featuredImage']['advanced'][ $advanced_key ][ $breakpoint ][ $state ] ?? null;
		if ( is_string( $legacy_value ) && '' !== $legacy_value ) {
			return $legacy_value;
		}

		if ( 'value' !== $state ) {
			return null;
		}

		$legacy_value = $attrs['featuredImage']['advanced'][ $advanced_key ][ $breakpoint ]['value'] ?? null;
		if ( is_string( $legacy_value ) && '' !== $legacy_value ) {
			return $legacy_value;
		}

		return null;
	}

	/**
	 * Resolve legacy Post Title featured-image sizing value.
	 *
	 * @since ??
	 *
	 * @param array  $attrs      Attr tree.
	 * @param string $breakpoint Breakpoint key.
	 * @param string $state      State key.
	 * @param string $sizing_key Sizing key.
	 *
	 * @return string|null
	 */
	private static function _get_legacy_featured_image_sizing_value( array $attrs, string $breakpoint, string $state, string $sizing_key ): ?string {
		$legacy_value = $attrs['featuredImage']['decoration']['sizing'][ $breakpoint ][ $state ][ $sizing_key ] ?? null;
		if ( is_string( $legacy_value ) && '' !== $legacy_value ) {
			return $legacy_value;
		}

		if ( 'value' !== $state ) {
			return null;
		}

		$legacy_value = $attrs['featuredImage']['decoration']['sizing'][ $breakpoint ]['value'][ $sizing_key ] ?? null;
		if ( is_string( $legacy_value ) && '' !== $legacy_value ) {
			return $legacy_value;
		}

		return null;
	}

	/**
	 * Determine whether any legacy featured image alignment value exists.
	 *
	 * @since ??
	 *
	 * @param array $attrs Attr tree.
	 *
	 * @return bool
	 */
	private static function _has_legacy_featured_image_alignment_attr( array $attrs ): bool {
		foreach ( self::_get_breakpoints_states() as $breakpoint => $states ) {
			foreach ( $states as $state ) {
				$legacy_alignment = self::_get_legacy_featured_image_sizing_value( $attrs, $breakpoint, $state, 'alignment' );
				if ( is_string( $legacy_alignment ) && '' !== $legacy_alignment ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Convert legacy left/center/right alignment to sizing.alignSelf value.
	 *
	 * @since ??
	 *
	 * @param string $alignment Legacy alignment value.
	 *
	 * @return string|null
	 */
	private static function _get_align_self_from_alignment( string $alignment ): ?string {
		switch ( $alignment ) {
			case 'left':
				return 'flex-start';
			case 'center':
				return 'center';
			case 'right':
				return 'end';
			default:
				return null;
		}
	}

	/**
	 * Cleanup empty Post Title featuredImage sizing branches.
	 *
	 * @since ??
	 *
	 * @param array $attrs Attr tree.
	 *
	 * @return void
	 */
	private static function _cleanup_empty_featured_image_sizing_branches( array &$attrs ): void {
		$sizing_attrs = $attrs['featuredImage']['decoration']['sizing'] ?? null;
		if ( ! is_array( $sizing_attrs ) ) {
			return;
		}

		foreach ( $sizing_attrs as $breakpoint => $states ) {
			if ( ! is_array( $states ) ) {
				continue;
			}

			foreach ( $states as $state => $state_attrs ) {
				if ( ! is_array( $state_attrs ) ) {
					continue;
				}

				if ( empty( $state_attrs ) ) {
					unset( $attrs['featuredImage']['decoration']['sizing'][ $breakpoint ][ $state ] );
				}
			}

			if ( empty( $attrs['featuredImage']['decoration']['sizing'][ $breakpoint ] ?? null ) ) {
				unset( $attrs['featuredImage']['decoration']['sizing'][ $breakpoint ] );
			}
		}
	}

	/**
	 * Get canonical breakpoint/state pairs.
	 *
	 * @since ??
	 *
	 * @return array<string, array<int, string>>
	 */
	private static function _get_breakpoints_states(): array {
		return MultiViewUtils::get_breakpoints_states();
	}
}
