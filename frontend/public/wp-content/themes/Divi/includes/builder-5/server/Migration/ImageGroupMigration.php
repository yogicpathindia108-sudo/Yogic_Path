<?php
/**
 * Image Group Migration.
 *
 * Migrates legacy image group attrs:
 * - `image.advanced.forceFullwidth` to `image.decoration.sizing.width`
 *   for Woo Product Images modules.
 * - `portrait.innerContent.*.value.url` to `portrait.innerContent.*.value.src`
 *   for Testimonial modules.
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
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\FrontEnd\BlockParser\BlockParser;
use ET\Builder\Migration\MigrationContentBase;
use ET\Builder\Migration\MigrationContext;
use ET\Builder\Migration\Utils\MigrationUtils;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;

/**
 * Image Group Migration class.
 *
 * @since ??
 */
class ImageGroupMigration extends MigrationContentBase {
	/**
	 * Migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'imageGroup.v1';

	/**
	 * Migration release version.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.4.2';

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
		add_filter( 'divi_framework_portability_import_migrated_post_content', [ __CLASS__, 'migrate_import_content' ] );
		add_action( 'wp', [ __CLASS__, 'migrate_fe_content' ] );
		add_filter( 'et_fb_load_raw_post_content', [ __CLASS__, 'migrate_vb_content' ], 10, 2 );
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
	 * Migrate import content.
	 *
	 * @since ??
	 *
	 * @param string $content Content string.
	 *
	 * @return string
	 */
	public static function migrate_import_content( $content ) {
		return self::_migrate_the_content( $content );
	}

	/**
	 * Migrate frontend content.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function migrate_fe_content(): void {
		if ( ! Conditions::is_fe_and_should_migrate_content() ) {
			return;
		}

		$content = MigrationUtils::get_current_content();

		if ( $content ) {
			add_filter(
				'the_content',
				function ( $content ) {
					return self::_migrate_block_content( $content );
				},
				8
			);
		}

		$tb_template_ids = DynamicAssetsUtils::get_theme_builder_template_ids();

		if ( ! empty( $tb_template_ids ) ) {
			add_filter(
				'et_builder_render_layout',
				function ( $rendered_content ) {
					return self::_migrate_block_content( $rendered_content );
				},
				8
			);
		}
	}

	/**
	 * Migrate Visual Builder content.
	 *
	 * @since ??
	 *
	 * @param string $content Content string.
	 *
	 * @return string
	 */
	public static function migrate_vb_content( $content ) {
		return self::_migrate_the_content( $content );
	}

	/**
	 * Migrate content block.
	 *
	 * @since ??
	 *
	 * @param string $content Serialized block content.
	 *
	 * @return string
	 */
	public static function migrate_content_block( string $content ): string {
		if ( ! self::has_divi_block( $content ) ) {
			return $content;
		}

		return self::_migrate_block_content( $content );
	}

	/**
	 * Migrate shortcode content.
	 *
	 * This migration only applies to block content.
	 *
	 * @since ??
	 *
	 * @param string $content Content string.
	 *
	 * @return string
	 */
	public static function migrate_content_shortcode( string $content ): string {
		return $content;
	}

	/**
	 * Migrate all content entry points.
	 *
	 * @since ??
	 *
	 * @param string $content Content string.
	 *
	 * @return string
	 */
	private static function _migrate_the_content( $content ) {
		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version, self::SUPPORTED_MODULES ) ) {
			return $content;
		}

		if ( ! self::_content_has_legacy_image_group_attr( $content ) ) {
			return $content;
		}

		return self::_migrate_block_content( $content );
	}

	/**
	 * Migrate block content.
	 *
	 * @since ??
	 *
	 * @param string $content Serialized block content.
	 *
	 * @return string
	 */
	private static function _migrate_block_content( $content ) {
		if ( ! BlockParser::has_any_divi_block( $content ) || '<!-- wp:divi/placeholder -->' === $content ) {
			return $content;
		}

		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version, self::SUPPORTED_MODULES ) ) {
			return $content;
		}

		if ( ! self::_content_has_legacy_image_group_attr( $content ) ) {
			return $content;
		}

		$content = MigrationUtils::ensure_placeholder_wrapper( $content );

		MigrationContext::start();

		try {
			$flat_objects = MigrationUtils::parse_serialized_post_into_flat_module_object( $content, self::$_name );
			$changes_made = false;

			foreach ( $flat_objects as $module_id => $module_data ) {
				$module_name = $module_data['name'] ?? '';
				if ( ! in_array( $module_name, self::SUPPORTED_MODULES, true ) ) {
					continue;
				}

				$current_version = $module_data['props']['attrs']['builderVersion'] ?? '0.0.0';
				if ( ! StringUtility::version_compare( $current_version, self::$_release_version, '<' ) ) {
					continue;
				}

				$attrs = $module_data['props']['attrs'] ?? [];
				if ( ! is_array( $attrs ) ) {
					continue;
				}

				if ( ! self::_has_legacy_image_group_attr( $attrs, $module_name ) ) {
					continue;
				}

				$module_changed = self::_migrate_module_attrs( $flat_objects, $module_id, $module_name, $attrs );

				if ( $module_changed ) {
					$flat_objects[ $module_id ]['props']['attrs']['builderVersion'] = self::$_release_version;
					$changes_made = true;
				}
			}

			if ( ! $changes_made ) {
				return $content;
			}

			return MigrationUtils::serialize_flat_objects( $flat_objects );
		} finally {
			MigrationContext::end();
		}
	}

	/**
	 * Check content signature for legacy image-group attrs.
	 *
	 * @since ??
	 *
	 * @param string $content Serialized block content.
	 *
	 * @return bool
	 */
	private static function _content_has_legacy_image_group_attr( string $content ): bool {
		return str_contains( $content, '"image":{"advanced":{"forceFullwidth"' )
			|| str_contains( $content, '"featuredImage":{"advanced":{"forceFullwidth"' )
			|| str_contains( $content, '"featuredImage":{"advanced":{"enabled"' )
			|| str_contains( $content, '"featuredImage":{"advanced":{"placement"' )
			|| str_contains( $content, '"featuredImage":{"advanced":{' )
			|| str_contains( $content, '"featuredImage":{"decoration":{"sizing"' )
			|| str_contains( $content, '"dynamicOptionGroups":{"designImage"' )
			|| str_contains( $content, '"dynamicOptionGroups":{"designImageIcon"' )
			|| str_contains( $content, '"portrait":{"innerContent":{"desktop":{"value":{"url"' )
			|| str_contains( $content, '"portrait":{"innerContent":{"tablet":{"value":{"url"' )
			|| str_contains( $content, '"portrait":{"innerContent":{"phone":{"value":{"url"' );
	}

	/**
	 * Check attrs for legacy image-group values.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Module attrs.
	 * @param string $module_name Module name.
	 *
	 * @return bool
	 */
	private static function _has_legacy_image_group_attr( array $attrs, string $module_name ): bool {
		return self::_has_legacy_force_fullwidth_attr( $attrs )
			|| self::_has_legacy_post_title_image_sizing_attr( $attrs, $module_name )
			|| self::_has_legacy_testimonial_portrait_url_attr( $attrs )
			|| self::_has_legacy_dynamic_option_group_image_attrs( $attrs, $module_name );
	}

	/**
	 * Check attrs for legacy force fullwidth values.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attrs.
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
	 * Migrate module attrs for image-group changes.
	 *
	 * @since ??
	 *
	 * @param array  $flat_objects Flat object map.
	 * @param string $module_id    Current module ID.
	 * @param string $module_name  Current module name.
	 * @param array  $attrs        Current module attrs.
	 *
	 * @return bool
	 */
	private static function _migrate_module_attrs( array &$flat_objects, string $module_id, string $module_name, array $attrs ): bool {
		$module_changed = false;

		if ( 'divi/woocommerce-product-images' === $module_name ) {
			$module_changed = self::_migrate_woocommerce_product_images_attrs( $flat_objects, $module_id, $attrs ) || $module_changed;
		}

		if ( 'divi/testimonial' === $module_name ) {
			$module_changed = self::_migrate_testimonial_attrs( $flat_objects, $module_id, $attrs ) || $module_changed;
		}

		if ( 'divi/post-title' === $module_name ) {
			$module_changed = self::_migrate_post_title_attrs( $flat_objects, $module_id, $attrs ) || $module_changed;
		}

		if ( self::_has_legacy_dynamic_option_group_image_attrs( $attrs, $module_name ) ) {
			$module_changed = self::_migrate_dynamic_option_group_image_attrs( $flat_objects, $module_id, $module_name, $attrs ) || $module_changed;
		}

		return $module_changed;
	}

	/**
	 * Migrate Woo Product Images attrs from forceFullwidth toggle to sizing width.
	 *
	 * @since ??
	 *
	 * @param array  $flat_objects Flat object map.
	 * @param string $module_id    Current module ID.
	 * @param array  $attrs        Current module attrs.
	 *
	 * @return bool
	 */
	private static function _migrate_woocommerce_product_images_attrs( array &$flat_objects, string $module_id, array $attrs ): bool {
		$module_changed = false;

		foreach ( self::_get_breakpoints_states() as $breakpoint => $states ) {
			foreach ( $states as $state ) {
				$legacy_toggle = self::_get_legacy_force_fullwidth_value( $attrs, $breakpoint, $state );

				if ( 'on' !== $legacy_toggle ) {
					continue;
				}

				$existing_width = $attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ]['width'] ?? null;
				if ( ! is_string( $existing_width ) || '' === $existing_width ) {
					$flat_objects[ $module_id ]['props']['attrs']['image']['decoration']['sizing'][ $breakpoint ][ $state ]['width'] = '100%';
					$module_changed = true;
				}
			}
		}

		if ( isset( $attrs['image']['advanced']['forceFullwidth'] ) ) {
			unset( $flat_objects[ $module_id ]['props']['attrs']['image']['advanced']['forceFullwidth'] );
			$module_changed = true;
		}

		if ( empty( $flat_objects[ $module_id ]['props']['attrs']['image']['advanced'] ?? null ) ) {
			unset( $flat_objects[ $module_id ]['props']['attrs']['image']['advanced'] );
		}

		if ( empty( $flat_objects[ $module_id ]['props']['attrs']['image'] ?? null ) ) {
			unset( $flat_objects[ $module_id ]['props']['attrs']['image'] );
		}

		return $module_changed;
	}

	/**
	 * Check attrs for legacy Post Title featured image sizing values.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Module attrs.
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
	 * Migrate Post Title legacy featured-image sizing attrs to image-group sizing attrs.
	 *
	 * @since ??
	 *
	 * @param array  $flat_objects Flat object map.
	 * @param string $module_id    Current module ID.
	 * @param array  $attrs        Current module attrs.
	 *
	 * @return bool
	 */
	private static function _migrate_post_title_attrs( array &$flat_objects, string $module_id, array $attrs ): bool {
		$module_changed       = false;
		$has_legacy_alignment = self::_has_legacy_featured_image_alignment_attr( $attrs );

		foreach ( self::_get_breakpoints_states() as $breakpoint => $states ) {
			foreach ( $states as $state ) {
				$legacy_force_fullwidth = self::_get_legacy_featured_image_force_fullwidth_value( $attrs, $breakpoint, $state );
				if ( 'on' === $legacy_force_fullwidth ) {
					$flat_objects[ $module_id ]['props']['attrs']['image']['decoration']['sizing'][ $breakpoint ][ $state ]['width'] = '100%';
					$module_changed = true;
				} else {
					foreach ( [ 'width', 'maxWidth' ] as $sizing_key ) {
						$legacy_sizing_value = self::_get_legacy_featured_image_sizing_value( $attrs, $breakpoint, $state, $sizing_key );
						if ( ! is_string( $legacy_sizing_value ) || '' === $legacy_sizing_value ) {
							continue;
						}

						$existing_sizing_value = $attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ][ $sizing_key ] ?? null;
						if ( ! is_string( $existing_sizing_value ) || '' === $existing_sizing_value ) {
							$flat_objects[ $module_id ]['props']['attrs']['image']['decoration']['sizing'][ $breakpoint ][ $state ][ $sizing_key ] = $legacy_sizing_value;
							$module_changed = true;
						}
					}

					if ( 'off' === $legacy_force_fullwidth ) {
						$legacy_width   = self::_get_legacy_featured_image_sizing_value( $attrs, $breakpoint, $state, 'width' );
						$existing_width = $attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ]['width'] ?? null;
						if ( ( ! is_string( $legacy_width ) || '' === $legacy_width ) && ( ! is_string( $existing_width ) || '' === $existing_width ) ) {
							$flat_objects[ $module_id ]['props']['attrs']['image']['decoration']['sizing'][ $breakpoint ][ $state ]['width'] = 'auto';
							$module_changed = true;
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
						$flat_objects[ $module_id ]['props']['attrs']['image']['decoration']['sizing'][ $breakpoint ][ $state ][ $sizing_key ] = $legacy_sizing_value;
						$module_changed = true;
					}
				}

				$legacy_alignment = self::_get_legacy_featured_image_sizing_value( $attrs, $breakpoint, $state, 'alignment' );
				if ( is_string( $legacy_alignment ) && '' !== $legacy_alignment ) {
					$align_self = self::_get_align_self_from_alignment( $legacy_alignment );
					if ( null !== $align_self ) {
						$existing_align_self = $attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ]['alignSelf'] ?? null;
						if ( ! is_string( $existing_align_self ) || '' === $existing_align_self ) {
							$flat_objects[ $module_id ]['props']['attrs']['image']['decoration']['sizing'][ $breakpoint ][ $state ]['alignSelf'] = $align_self;
							$module_changed = true;
						}
					}
				} elseif ( 'desktop' === $breakpoint && 'value' === $state && ! $has_legacy_alignment ) {
					$existing_align_self = $attrs['image']['decoration']['sizing'][ $breakpoint ][ $state ]['alignSelf'] ?? null;
					if ( ! is_string( $existing_align_self ) || '' === $existing_align_self ) {
						// Legacy Post Title image alignment defaulted to center.
						$flat_objects[ $module_id ]['props']['attrs']['image']['decoration']['sizing'][ $breakpoint ][ $state ]['alignSelf'] = 'center';
						$module_changed = true;
					}
				}

				foreach ( [ 'enabled', 'placement' ] as $advanced_key ) {
					$legacy_advanced_value = self::_get_legacy_featured_image_advanced_value( $attrs, $breakpoint, $state, $advanced_key );
					if ( ! is_string( $legacy_advanced_value ) || '' === $legacy_advanced_value ) {
						continue;
					}

					$existing_advanced_value = $attrs['image']['advanced'][ $advanced_key ][ $breakpoint ]['value'] ?? null;
					if ( ! is_string( $existing_advanced_value ) || '' === $existing_advanced_value ) {
						$flat_objects[ $module_id ]['props']['attrs']['image']['advanced'][ $advanced_key ][ $breakpoint ]['value'] = $legacy_advanced_value;
						$module_changed = true;
					}
				}

				foreach ( [ 'width', 'maxWidth', 'height', 'maxHeight', 'alignment' ] as $sizing_key ) {
					if ( isset( $attrs['featuredImage']['decoration']['sizing'][ $breakpoint ][ $state ][ $sizing_key ] ) ) {
						unset( $flat_objects[ $module_id ]['props']['attrs']['featuredImage']['decoration']['sizing'][ $breakpoint ][ $state ][ $sizing_key ] );
						$module_changed = true;
					}
				}
			}
		}

		if ( isset( $attrs['featuredImage']['advanced']['forceFullwidth'] ) ) {
			unset( $flat_objects[ $module_id ]['props']['attrs']['featuredImage']['advanced']['forceFullwidth'] );
			$module_changed = true;
		}
		if ( isset( $attrs['featuredImage']['advanced']['enabled'] ) ) {
			unset( $flat_objects[ $module_id ]['props']['attrs']['featuredImage']['advanced']['enabled'] );
			$module_changed = true;
		}
		if ( isset( $attrs['featuredImage']['advanced']['placement'] ) ) {
			unset( $flat_objects[ $module_id ]['props']['attrs']['featuredImage']['advanced']['placement'] );
			$module_changed = true;
		}

		self::_cleanup_empty_featured_image_sizing_branches( $flat_objects[ $module_id ]['props']['attrs'] );

		if ( empty( $flat_objects[ $module_id ]['props']['attrs']['featuredImage']['advanced'] ?? null ) ) {
			unset( $flat_objects[ $module_id ]['props']['attrs']['featuredImage']['advanced'] );
		}

		if ( empty( $flat_objects[ $module_id ]['props']['attrs']['featuredImage']['decoration']['sizing'] ?? null ) ) {
			unset( $flat_objects[ $module_id ]['props']['attrs']['featuredImage']['decoration']['sizing'] );
		}

		if ( empty( $flat_objects[ $module_id ]['props']['attrs']['featuredImage']['decoration'] ?? null ) ) {
			unset( $flat_objects[ $module_id ]['props']['attrs']['featuredImage']['decoration'] );
		}

		if ( empty( $flat_objects[ $module_id ]['props']['attrs']['featuredImage'] ?? null ) ) {
			unset( $flat_objects[ $module_id ]['props']['attrs']['featuredImage'] );
		}

		return $module_changed;
	}

	/**
	 * Check attrs for legacy testimonial portrait url values.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attrs.
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
	 * Migrate Testimonial portrait innerContent url to src.
	 *
	 * @since ??
	 *
	 * @param array  $flat_objects Flat object map.
	 * @param string $module_id    Current module ID.
	 * @param array  $attrs        Current module attrs.
	 *
	 * @return bool
	 */
	private static function _migrate_testimonial_attrs( array &$flat_objects, string $module_id, array $attrs ): bool {
		$module_changed = false;

		foreach ( self::_get_breakpoints_states() as $breakpoint => $_states ) {
			$portrait_value = $attrs['portrait']['innerContent'][ $breakpoint ]['value'] ?? null;
			if ( ! is_array( $portrait_value ) || ! array_key_exists( 'url', $portrait_value ) ) {
				continue;
			}

			$legacy_url   = $portrait_value['url'] ?? null;
			$existing_src = $portrait_value['src'] ?? null;

			if ( is_string( $legacy_url ) && ( ! is_string( $existing_src ) || '' === $existing_src ) ) {
				$flat_objects[ $module_id ]['props']['attrs']['portrait']['innerContent'][ $breakpoint ]['value']['src'] = $legacy_url;
				$module_changed = true;
			}

			unset( $flat_objects[ $module_id ]['props']['attrs']['portrait']['innerContent'][ $breakpoint ]['value']['url'] );
			$module_changed = true;
		}

		return $module_changed;
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
	 * Check attrs for legacy dynamicOptionGroups image host values.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Module attrs.
	 * @param string $module_name Module name.
	 *
	 * @return bool
	 */
	private static function _has_legacy_dynamic_option_group_image_attrs( array $attrs, string $module_name ): bool {
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
	 * @param array  $flat_objects Flat object map.
	 * @param string $module_id    Current module ID.
	 * @param string $module_name  Current module name.
	 * @param array  $attrs        Current module attrs.
	 *
	 * @return bool
	 */
	private static function _migrate_dynamic_option_group_image_attrs( array &$flat_objects, string $module_id, string $module_name, array $attrs ): bool {
		if ( ! self::_has_legacy_dynamic_option_group_image_attrs( $attrs, $module_name ) ) {
			return false;
		}

		$module_changed = false;
		foreach ( self::_get_legacy_dynamic_option_group_image_mappings( $module_name ) as $legacy_key => $target_key ) {
			$legacy_group = $attrs['dynamicOptionGroups'][ $legacy_key ] ?? null;
			if ( ! is_array( $legacy_group ) ) {
				continue;
			}

			$legacy_group   = self::_strip_built_in_dynamic_image_subgroups( $legacy_group );
			$existing_group = $flat_objects[ $module_id ]['props']['attrs']['dynamicOptionGroups'][ $target_key ] ?? [];
			$merged_group   = self::_merge_dynamic_option_groups( is_array( $existing_group ) ? $existing_group : [], $legacy_group );

			$flat_objects[ $module_id ]['props']['attrs']['dynamicOptionGroups'][ $target_key ] = $merged_group;
			unset( $flat_objects[ $module_id ]['props']['attrs']['dynamicOptionGroups'][ $legacy_key ] );
			$module_changed = true;
		}

		if ( empty( $flat_objects[ $module_id ]['props']['attrs']['dynamicOptionGroups'] ?? null ) ) {
			unset( $flat_objects[ $module_id ]['props']['attrs']['dynamicOptionGroups'] );
		}

		return $module_changed;
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
	 * @param array  $attrs      Module attrs.
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
	 * @param array  $attrs      Module attrs.
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
	 * @param array  $attrs        Module attrs.
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
	 * @param array  $attrs       Module attrs.
	 * @param string $breakpoint  Breakpoint key.
	 * @param string $state       State key.
	 * @param string $sizing_key  Sizing key.
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
	 * @param array $attrs Module attrs.
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
