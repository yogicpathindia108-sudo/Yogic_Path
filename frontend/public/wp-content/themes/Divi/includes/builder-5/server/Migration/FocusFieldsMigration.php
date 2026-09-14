<?php
/**
 * Focus Fields Migration
 *
 * Migrates legacy form field focus color attributes to the new focus state-aware
 * decoration attribute paths.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\FrontEnd\BlockParser\BlockParser;
use ET\Builder\Migration\MigrationContentBase;
use ET\Builder\Migration\MigrationContext;
use ET\Builder\Migration\Utils\MigrationUtils;

/**
 * Focus Fields Migration Class.
 *
 * @since ??
 */
class FocusFieldsMigration extends MigrationContentBase {
	/**
	 * Modules that use the premade form-field group.
	 *
	 * @since ??
	 *
	 * @var array<int, string>
	 */
	private const FORM_FIELD_GROUP_MODULES = [
		'divi/comments',
		'divi/contact-field',
		'divi/contact-form',
		'divi/login',
		'divi/signup',
		'divi/signup-custom-field',
		'divi/woocommerce-cart-notice',
		'divi/woocommerce-cart-products',
		'divi/woocommerce-cart-totals',
		'divi/woocommerce-checkout-billing',
		'divi/woocommerce-checkout-information',
		'divi/woocommerce-checkout-shipping',
		'divi/woocommerce-product-add-to-cart',
		'divi/woocommerce-product-reviews',
	];

	/**
	 * Extra modules with focus-field legacy markers outside premade form-field group list.
	 *
	 * @since ??
	 *
	 * @var array<int, string>
	 */
	private const EXTRA_FOCUS_FIELD_CONTENT_SIGNATURE_MODULES = [
		'divi/woocommerce-checkout-additional-info',
	];

	/**
	 * Modules that share contact-form family field behavior.
	 *
	 * @since ??
	 *
	 * @var array<int, string>
	 */
	private const CONTACT_FORM_FAMILY_MODULES = [
		'divi/contact-form',
		'divi/contact-field',
		'divi/signup',
		'divi/signup-custom-field',
	];

	/**
	 * Attr groups where legacy background color migration is allowed.
	 *
	 * @since ??
	 *
	 * @var array<int, string>
	 */
	private const FORM_RELATED_BACKGROUND_ATTR_GROUPS = [
		'field',
		'checkbox',
		'radio',
		'fieldLabels',
	];


	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'focus-fields.v1';

	/**
	 * The migration release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.3';

	/**
	 * Run the migration.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function load(): void {
		add_filter( 'divi_framework_portability_import_migrated_post_content', [ __CLASS__, 'migrate_import_content' ] );
		add_action( 'wp', [ __CLASS__, 'migrate_fe_content' ] );
		add_action( 'et_fb_load_raw_post_content', [ __CLASS__, 'migrate_vb_content' ], 10, 2 );
	}

	/**
	 * Get the migration name.
	 *
	 * @since ??
	 *
	 * @return string The migration name.
	 */
	public static function get_name() {
		return self::$_name;
	}

	/**
	 * Get the release version for this migration.
	 *
	 * @since ??
	 *
	 * @return string The release version.
	 */
	public static function get_release_version(): string {
		return self::$_release_version;
	}

	/**
	 * Migrate import content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
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

		// Return if this is not frontend context that should migrate content.
		if ( ! Conditions::is_fe_and_should_migrate_content() ) {
			return;
		}

		$content = MigrationUtils::get_current_content();

		// Handle regular post content.
		if ( $content ) {
			add_filter(
				'the_content',
				function ( $the_content ) {
					return self::_migrate_block_content( $the_content );
				},
				8 // BEFORE do_blocks().
			);
		}

		// Handle Theme Builder templates with filters.
		$tb_template_ids = DynamicAssetsUtils::get_theme_builder_template_ids();

		if ( ! empty( $tb_template_ids ) ) {
			add_filter(
				'et_builder_render_layout',
				function ( $rendered_content ) {
					return self::_migrate_block_content( $rendered_content );
				},
				8 // BEFORE do_blocks().
			);
		}
	}

	/**
	 * Migrate Visual Builder content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	public static function migrate_vb_content( $content ) {
		return self::_migrate_the_content( $content );
	}

	/**
	 * Migrate block content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
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
	 * @param string $content The content to migrate.
	 *
	 * @return string Unchanged content.
	 */
	public static function migrate_content_shortcode( string $content ): string {
		return $content;
	}

	/**
	 * Migrate content entry point.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	private static function _migrate_the_content( $content ) {
		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version ) ) {
			return $content;
		}

		if ( ! self::_content_needs_migration( $content ) ) {
			return $content;
		}

		return self::_migrate_block_content( $content );
	}

	/**
	 * Migrate block-based content (D5 blocks).
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 *
	 * @throws \RuntimeException When block-content migration fails.
	 */
	private static function _migrate_block_content( $content ) {
		if ( ! BlockParser::has_any_divi_block( $content ) || '<!-- wp:divi/placeholder -->' === $content ) {
			return $content;
		}

		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version ) ) {
			return $content;
		}

		if ( ! self::_content_needs_migration( $content ) ) {
			return $content;
		}

		$content = MigrationUtils::ensure_placeholder_wrapper( $content );

		MigrationContext::start();

		try {
			$flat_objects = MigrationUtils::parse_serialized_post_into_flat_module_object( $content, self::$_name );
			$changes_made = false;

			foreach ( $flat_objects as $module_id => $module_data ) {
				if ( ! StringUtility::version_compare(
					$module_data['props']['attrs']['builderVersion'] ?? '0.0.0',
					self::$_release_version,
					'<'
				)
				) {
					continue;
				}

				$attrs = $module_data['props']['attrs'] ?? null;

				if ( ! is_array( $attrs ) ) {
					continue;
				}

				$module_name = $module_data['name'] ?? '';
				$module_name = is_string( $module_name ) ? $module_name : '';
				if ( ! self::should_migrate_module( $module_name ) ) {
					continue;
				}

				$migration_flags = self::_get_module_migration_flags( $module_name );

				if (
					! self::_has_legacy_form_field_attrs_tree( $attrs, $module_name )
					&& ! self::_attrs_tree_needs_module_specific_migration( $attrs, $module_name, $migration_flags )
				) {
					continue;
				}

				$migrated_attrs = self::_migrate_attrs( $attrs, $module_name, '', $migration_flags );

				if ( $migrated_attrs !== $attrs ) {
					$flat_objects[ $module_id ]['props']['attrs']                   = $migrated_attrs;
					$flat_objects[ $module_id ]['props']['attrs']['builderVersion'] = self::$_release_version;
					$changes_made = true;
				}
			}

			if ( ! $changes_made ) {
				return $content;
			}

			return MigrationUtils::serialize_flat_objects( $flat_objects );
		} catch ( \Throwable $exception ) {
			throw new \RuntimeException( 'FocusFieldsMigration failed while migrating block content.', 0, $exception ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception chaining preserves root cause context; no output is rendered.
		} finally {
			MigrationContext::end();
		}
	}

	/**
	 * Fast pre-check for migration signature in content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to check.
	 *
	 * @return bool True when migration signature exists.
	 */
	private static function _content_needs_migration( string $content ): bool {
		$module_content_signatures = array_merge(
			self::_get_supported_form_field_group_modules(),
			self::EXTRA_FOCUS_FIELD_CONTENT_SIGNATURE_MODULES
		);

		foreach ( $module_content_signatures as $module_name ) {
			if ( str_contains( $content, '<!-- wp:' . $module_name . ' ' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively migrate attributes.
	 *
	 * @since ??
	 *
	 * @param array  $attrs            Attributes tree.
	 * @param string $module_name      Current module name.
	 * @param string $current_attr_key Current attr key.
	 * @param array  $migration_flags  Module migration flags.
	 * @param array  $options          Migration options.
	 *
	 * @return array Migrated attributes tree.
	 */
	private static function _migrate_attrs( array $attrs, string $module_name = '', string $current_attr_key = '', array $migration_flags = [], array $options = [] ): array {
		if ( empty( $attrs ) ) {
			return $attrs;
		}

		if ( empty( $migration_flags ) ) {
			$migration_flags = self::_get_module_migration_flags( $module_name );
		}

		if ( empty( $migration_flags['should_run_focus_field_migration'] ) ) {
			return $attrs;
		}

		$migrated_attrs = $attrs;

		foreach ( $migrated_attrs as $key => $value ) {
			if ( is_array( $value ) ) {
				$migrated_attrs[ $key ] = self::_migrate_attrs( $value, $module_name, (string) $key, $migration_flags, $options );
			}
		}

		if ( empty( $options['skip_contact_form_border_and_shadow_migration'] ) ) {
			$migrated_attrs = self::_migrate_contact_form_border_and_shadow_attrs( $migrated_attrs, $module_name );
		}

		if ( ! empty( $migration_flags['needs_contact_form_field_variant_copy'] ) ) {
			$migrated_attrs = self::_migrate_contact_form_field_variant_attrs( $migrated_attrs, $module_name );
		}

		if ( ! empty( $migration_flags['needs_contact_form_child_field_label_text_copy'] ) ) {
			$migrated_attrs = self::_migrate_contact_form_child_field_label_text_attrs( $migrated_attrs, $module_name );
		}

		if ( ! empty( $migration_flags['needs_woocommerce_field_labels_font_copy'] ) ) {
			$migrated_attrs = self::_migrate_woocommerce_field_labels_font_attrs( $migrated_attrs, $module_name );
		}

		if ( ! empty( $migration_flags['needs_woocommerce_required_field_indicator_color_move'] ) ) {
			$migrated_attrs = self::_migrate_woocommerce_required_field_indicator_color_attrs( $migrated_attrs, $module_name );
		}

		if ( ! empty( $migration_flags['needs_woocommerce_field_labels_group_preset_move'] ) ) {
			$migrated_attrs = self::_migrate_woocommerce_field_labels_group_preset_refs( $migrated_attrs, $module_name );
		}

		return self::_migrate_field_node( $migrated_attrs, $current_attr_key );
	}

	/**
	 * Migrate an attributes tree for focus fields.
	 *
	 * This method is shared by content and preset migrations.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Attributes tree.
	 * @param string $module_name Current module name.
	 * @param array  $options     Migration options.
	 *
	 * @return array Migrated attributes tree.
	 */
	public static function migrate_attrs_tree( array $attrs, string $module_name = '', array $options = [] ): array {
		if ( '' !== $module_name && ! self::should_migrate_module( $module_name ) ) {
			return $attrs;
		}

		return self::_migrate_attrs( $attrs, $module_name, '', [], $options );
	}

	/**
	 * Check whether module uses premade form-field group migration paths.
	 *
	 * @since ??
	 *
	 * @param string $module_name Module name.
	 *
	 * @return bool True when module should run focus-fields migration.
	 */
	public static function is_supported_form_field_module( string $module_name ): bool {
		return in_array( $module_name, self::_get_supported_form_field_group_modules(), true );
	}

	/**
	 * Get supported form-field modules, including third-party extensions.
	 *
	 * @since ??
	 *
	 * @return array<int, string> Supported module names.
	 */
	private static function _get_supported_form_field_group_modules(): array {
		/**
		 * Filters modules supported by focus-fields migration.
		 *
		 * @since ??
		 *
		 * @param array<int, string> $modules Module names to allowlist for migration.
		 */
		$filtered_modules = apply_filters( 'divi_migration_focus_fields_modules', self::FORM_FIELD_GROUP_MODULES );

		return self::_normalize_module_names( $filtered_modules );
	}

	/**
	 * Normalize module name list to unique non-empty strings.
	 *
	 * @since ??
	 *
	 * @param mixed $module_names Module names candidate list.
	 *
	 * @return array<int, string> Normalized module names.
	 */
	private static function _normalize_module_names( $module_names ): array {
		if ( ! is_array( $module_names ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $module_names as $module_name ) {
			if ( ! is_string( $module_name ) || '' === $module_name ) {
				continue;
			}

			$normalized[] = $module_name;
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Check whether module should run focus-fields migration.
	 *
	 * @since ??
	 *
	 * @param string $module_name Module name.
	 *
	 * @return bool True when module should run focus-fields migration.
	 */
	public static function should_migrate_module( string $module_name ): bool {
		if ( '' === $module_name ) {
			// Keep direct utility calls compatible when module context is unavailable.
			return true;
		}

		return self::is_supported_form_field_module( $module_name )
			|| MigrationUtils::is_woocommerce_field_labels_legacy_module( $module_name )
			|| MigrationUtils::is_woocommerce_required_field_indicator_color_legacy_module( $module_name );
	}

	/**
	 * Resolve module-specific migration flags once per module.
	 *
	 * @since ??
	 *
	 * @param string $module_name Current module name.
	 *
	 * @return array<string, bool> Migration flags.
	 */
	private static function _get_module_migration_flags( string $module_name ): array {
		$should_run_focus_field_migration = self::should_migrate_module( $module_name );
		$is_contact_form_family           = in_array( $module_name, self::CONTACT_FORM_FAMILY_MODULES, true );
		$is_contact_form_child            = in_array( $module_name, [ 'divi/contact-field', 'divi/signup-custom-field' ], true );
		$is_woo_field_labels              = MigrationUtils::is_woocommerce_field_labels_legacy_module( $module_name );
		$is_woo_required_field            = MigrationUtils::is_woocommerce_required_field_indicator_color_legacy_module( $module_name );

		return [
			'should_run_focus_field_migration'         => $should_run_focus_field_migration,
			'needs_contact_form_field_variant_copy'    => $is_contact_form_family,
			'needs_contact_form_child_field_label_text_copy' => $is_contact_form_child,
			'needs_woocommerce_field_labels_font_copy' => $is_woo_field_labels,
			'needs_woocommerce_required_field_indicator_color_move' => $is_woo_required_field,
			'needs_woocommerce_field_labels_group_preset_move' => $is_woo_field_labels,
		];
	}

	/**
	 * Check whether attrs tree needs any module-specific migration.
	 *
	 * @since ??
	 *
	 * @param array              $attrs           Attributes tree.
	 * @param string             $module_name     Current module name.
	 * @param array<string,bool> $migration_flags Module migration flags.
	 *
	 * @return bool True when migration is needed.
	 */
	private static function _attrs_tree_needs_module_specific_migration( array $attrs, string $module_name, array $migration_flags ): bool {
		if (
			! empty( $migration_flags['needs_contact_form_field_variant_copy'] )
			&& self::_attrs_tree_needs_contact_form_field_variant_copy( $attrs, $module_name )
		) {
			return true;
		}

		if (
			! empty( $migration_flags['needs_contact_form_child_field_label_text_copy'] )
			&& self::_attrs_tree_needs_contact_form_child_field_label_text_copy( $attrs, $module_name )
		) {
			return true;
		}

		if (
			! empty( $migration_flags['needs_woocommerce_field_labels_font_copy'] )
			&& self::_attrs_tree_needs_woocommerce_field_labels_font_copy( $attrs, $module_name )
		) {
			return true;
		}

		if (
			! empty( $migration_flags['needs_woocommerce_required_field_indicator_color_move'] )
			&& self::_attrs_tree_needs_woocommerce_required_field_indicator_color_move( $attrs, $module_name )
		) {
			return true;
		}

		if (
			! empty( $migration_flags['needs_woocommerce_field_labels_group_preset_move'] )
			&& self::_attrs_tree_needs_woocommerce_field_labels_group_preset_move( $attrs, $module_name )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Migrate contact form field attrs to checkbox and radio attrs.
	 *
	 * Copies all field attrs into `checkbox` and `radio` groups while excluding
	 * placeholder text attrs and spacing margin/padding attrs, because those groups
	 * do not expose those options.
	 *
	 * @since ??
	 *
	 * @param array  $node        Attr node.
	 * @param string $module_name Current module name.
	 *
	 * @return array Migrated node.
	 */
	private static function _migrate_contact_form_field_variant_attrs( array $node, string $module_name ): array {
		if ( ! self::_is_contact_form_family_module_node( $node, $module_name ) ) {
			return $node;
		}

		$field_attrs = $node['field'] ?? null;

		if ( ! is_array( $field_attrs ) || ! self::_has_meaningful_attr_values( $field_attrs ) ) {
			return $node;
		}

		$field_attrs_without_placeholder = self::_remove_placeholder_text_attrs( $field_attrs );
		$field_attrs_for_variant_copy    = self::_remove_spacing_padding_and_margin_attrs( $field_attrs_without_placeholder );
		$field_attrs_for_variant_copy    = self::_remove_signup_variant_text_color_and_alignment_attrs(
			$field_attrs_for_variant_copy,
			$module_name
		);

		foreach ( [ 'checkbox', 'radio' ] as $target_attr_name ) {
			$existing_target_attrs     = $node[ $target_attr_name ] ?? [];
			$node[ $target_attr_name ] = self::_merge_missing_attr_values(
				is_array( $existing_target_attrs ) ? $existing_target_attrs : [],
				$field_attrs_for_variant_copy
			);
		}

		return $node;
	}

	/**
	 * Check whether node belongs to contact form module attrs.
	 *
	 * @since ??
	 *
	 * @param array  $node        Attr node.
	 * @param string $module_name Current module name.
	 *
	 * @return bool True when node is contact form attrs node.
	 */
	private static function _is_contact_form_family_module_node( array $node, string $module_name ): bool {
		return in_array( $module_name, self::CONTACT_FORM_FAMILY_MODULES, true );
	}

	/**
	 * Remove placeholder text attrs from field attrs.
	 *
	 * @since ??
	 *
	 * @param array $attrs Field attrs.
	 *
	 * @return array Field attrs without placeholder-specific attrs.
	 */
	private static function _remove_placeholder_text_attrs( array $attrs ): array {
		$migrated_attrs = $attrs;

		unset( $migrated_attrs['decoration']['placeholderFont'] );
		unset( $migrated_attrs['advanced']['placeholder'] );

		return $migrated_attrs;
	}

	/**
	 * Remove spacing margin and padding attrs from field attrs.
	 *
	 * @since ??
	 *
	 * @param array $attrs Field attrs.
	 *
	 * @return array Field attrs without spacing margin/padding attrs.
	 */
	private static function _remove_spacing_padding_and_margin_attrs( array $attrs ): array {
		$migrated_attrs = $attrs;

		if ( ! is_array( $migrated_attrs['decoration']['spacing'] ?? null ) ) {
			return $migrated_attrs;
		}

		foreach ( $migrated_attrs['decoration']['spacing'] as $breakpoint => $spacing_values ) {
			if ( ! is_array( $spacing_values ) ) {
				continue;
			}

			foreach ( $spacing_values as $state => $state_values ) {
				if ( ! is_array( $state_values ) ) {
					continue;
				}

				unset( $migrated_attrs['decoration']['spacing'][ $breakpoint ][ $state ]['padding'] );
				unset( $migrated_attrs['decoration']['spacing'][ $breakpoint ][ $state ]['margin'] );

				if ( ! is_array( $state_values['value'] ?? null ) ) {
					continue;
				}

				unset( $migrated_attrs['decoration']['spacing'][ $breakpoint ][ $state ]['value']['padding'] );
				unset( $migrated_attrs['decoration']['spacing'][ $breakpoint ][ $state ]['value']['margin'] );
			}
		}

		return $migrated_attrs;
	}

	/**
	 * Recursively merge source attrs into target only for missing keys.
	 *
	 * @since ??
	 *
	 * @param array $target Target attrs.
	 * @param array $source Source attrs.
	 *
	 * @return array Merged attrs.
	 */
	private static function _merge_missing_attr_values( array $target, array $source ): array {
		foreach ( $source as $key => $source_value ) {
			if ( ! array_key_exists( $key, $target ) ) {
				$target[ $key ] = $source_value;
				continue;
			}

			if ( is_array( $target[ $key ] ) && is_array( $source_value ) ) {
				$target[ $key ] = self::_merge_missing_attr_values( $target[ $key ], $source_value );
			}
		}

		return $target;
	}

	/**
	 * Migrate contact child field module text attrs to label text attrs.
	 *
	 * Legacy contact-field and signup-custom-field modules apply field text attrs
	 * to option-title labels as well. Copy field text attrs into labelFont while
	 * excluding text color, and preserve existing explicit labelFont values.
	 *
	 * @since ??
	 *
	 * @param array  $node        Attr node.
	 * @param string $module_name Current module name.
	 *
	 * @return array Migrated node.
	 */
	private static function _migrate_contact_form_child_field_label_text_attrs( array $node, string $module_name ): array {
		if ( ! in_array( $module_name, [ 'divi/contact-field', 'divi/signup-custom-field' ], true ) ) {
			return $node;
		}

		$field_font_attrs = $node['field']['decoration']['font'] ?? null;

		if ( ! is_array( $field_font_attrs ) || ! self::_has_meaningful_attr_values( $field_font_attrs ) ) {
			return $node;
		}

		$field_label_font_attrs = self::_remove_text_color_attrs_from_font_group( $field_font_attrs );

		if ( ! self::_has_meaningful_attr_values( $field_label_font_attrs ) ) {
			return $node;
		}

		$existing_label_font_attrs                = $node['field']['decoration']['labelFont'] ?? [];
		$node['field']['decoration']['labelFont'] = self::_merge_missing_attr_values(
			is_array( $existing_label_font_attrs ) ? $existing_label_font_attrs : [],
			$field_label_font_attrs
		);

		return $node;
	}

	/**
	 * Remove text color attrs from a font group attr tree.
	 *
	 * @since ??
	 *
	 * @param array $font_group_attrs Font group attrs.
	 *
	 * @return array Font group attrs without color keys in font values.
	 */
	private static function _remove_text_color_attrs_from_font_group( array $font_group_attrs ): array {
		$migrated_font_group_attrs = $font_group_attrs;
		$font_attr_tree            = $migrated_font_group_attrs['font'] ?? null;

		if ( ! is_array( $font_attr_tree ) ) {
			return $migrated_font_group_attrs;
		}

		foreach ( $font_attr_tree as $breakpoint => $state_values ) {
			if ( ! is_array( $state_values ) ) {
				continue;
			}

			foreach ( $state_values as $state => $font_values ) {
				if ( ! is_array( $font_values ) ) {
					continue;
				}

				unset( $migrated_font_group_attrs['font'][ $breakpoint ][ $state ]['color'] );
				unset( $migrated_font_group_attrs['font'][ $breakpoint ][ $state ]['textColor'] );
			}
		}

		return $migrated_font_group_attrs;
	}

	/**
	 * Migrate Woo field-label font attrs to field labelFont attrs.
	 *
	 * @since ??
	 *
	 * @param array  $node        Attr node.
	 * @param string $module_name Current module name.
	 *
	 * @return array Migrated node.
	 */
	private static function _migrate_woocommerce_field_labels_font_attrs( array $node, string $module_name ): array {
		if ( ! MigrationUtils::is_woocommerce_field_labels_legacy_module( $module_name ) ) {
			return $node;
		}

		$field_labels_font_attrs = $node['fieldLabels']['decoration']['font'] ?? null;

		if ( ! is_array( $field_labels_font_attrs ) ) {
			return $node;
		}

		if ( self::_has_meaningful_attr_values( $field_labels_font_attrs ) ) {
			$existing_label_font_attrs                = $node['field']['decoration']['labelFont'] ?? [];
			$node['field']['decoration']['labelFont'] = self::_merge_missing_attr_values(
				is_array( $existing_label_font_attrs ) ? $existing_label_font_attrs : [],
				$field_labels_font_attrs
			);
		}

		unset( $node['fieldLabels']['decoration']['font'] );

		if ( empty( $node['fieldLabels']['decoration'] ) ) {
			unset( $node['fieldLabels']['decoration'] );
		}

		if ( empty( $node['fieldLabels'] ) ) {
			unset( $node['fieldLabels'] );
		}

		return $node;
	}

	/**
	 * Migrate Woo required indicator color attr from fieldLabels to field.
	 *
	 * @since ??
	 *
	 * @param array  $node        Attr node.
	 * @param string $module_name Current module name.
	 *
	 * @return array Migrated node.
	 */
	private static function _migrate_woocommerce_required_field_indicator_color_attrs( array $node, string $module_name ): array {
		if ( ! MigrationUtils::is_woocommerce_required_field_indicator_color_legacy_module( $module_name ) ) {
			return $node;
		}

		$legacy_attr = $node['fieldLabels']['advanced']['requiredFieldIndicatorColor'] ?? null;

		if ( ! is_array( $legacy_attr ) ) {
			return $node;
		}

		if ( self::_has_meaningful_attr_values( $legacy_attr ) ) {
			$existing_target_attr                                     = $node['field']['advanced']['requiredFieldIndicatorColor'] ?? [];
			$node['field']['advanced']['requiredFieldIndicatorColor'] = self::_merge_missing_attr_values(
				is_array( $existing_target_attr ) ? $existing_target_attr : [],
				$legacy_attr
			);
		}

		unset( $node['fieldLabels']['advanced']['requiredFieldIndicatorColor'] );

		if ( empty( $node['fieldLabels']['advanced'] ) ) {
			unset( $node['fieldLabels']['advanced'] );
		}

		if ( empty( $node['fieldLabels'] ) ) {
			unset( $node['fieldLabels'] );
		}

		return $node;
	}

	/**
	 * Migrate Woo field-label group preset refs to field labelFont group refs.
	 *
	 * @since ??
	 *
	 * @param array  $node        Attr node.
	 * @param string $module_name Current module name.
	 *
	 * @return array Migrated node.
	 */
	private static function _migrate_woocommerce_field_labels_group_preset_refs( array $node, string $module_name ): array {
		if ( ! MigrationUtils::is_woocommerce_field_labels_legacy_module( $module_name ) ) {
			return $node;
		}

		$group_presets = $node['groupPreset'] ?? null;

		if ( ! is_array( $group_presets ) ) {
			return $node;
		}

		$legacy_group_preset_key = '';
		$legacy_group_preset     = null;

		foreach ( self::_get_woocommerce_legacy_field_labels_group_preset_keys() as $legacy_key ) {
			$maybe_legacy_group_preset = $group_presets[ $legacy_key ] ?? null;

			if ( is_array( $maybe_legacy_group_preset ) ) {
				$legacy_group_preset_key = $legacy_key;
				$legacy_group_preset     = $maybe_legacy_group_preset;
				break;
			}
		}

		if ( '' === $legacy_group_preset_key || ! is_array( $legacy_group_preset ) ) {
			return $node;
		}

		$target_group_preset = $group_presets['field.decoration.labelFont'] ?? null;

		if ( ! is_array( $target_group_preset ) ) {
			$group_presets['field.decoration.labelFont'] = $legacy_group_preset;
		} else {
			$legacy_preset_ids = MigrationUtils::normalize_preset_stack_value( $legacy_group_preset['presetId'] ?? '' );
			$target_preset_ids = MigrationUtils::normalize_preset_stack_value( $target_group_preset['presetId'] ?? '' );
			$merged_preset_ids = array_values( array_unique( array_merge( $legacy_preset_ids, $target_preset_ids ) ) );

			$group_presets['field.decoration.labelFont'] = [
				'presetId'  => $merged_preset_ids,
				'groupName' => $target_group_preset['groupName'] ?? $legacy_group_preset['groupName'] ?? '',
			];
		}

		foreach ( self::_get_woocommerce_legacy_field_labels_group_preset_keys() as $legacy_key ) {
			unset( $group_presets[ $legacy_key ] );
		}
		$node['groupPreset'] = $group_presets;

		return $node;
	}

	/**
	 * Check whether module has legacy Woo fieldLabels font attrs.
	 *
	 * @since ??
	 *
	 * @param string $module_name Current module name.
	 *
	 * @return bool True when module should migrate fieldLabels font attrs.
	 */

	/**
	 * Check whether attrs tree contains legacy form-field attributes.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Attributes tree.
	 * @param string $module_name Current module name.
	 *
	 * @return bool True when legacy form-field attrs are found.
	 */
	public static function has_legacy_form_field_attrs_tree( array $attrs, string $module_name = '' ): bool {
		if ( '' !== $module_name && ! self::should_migrate_module( $module_name ) ) {
			return false;
		}

		return self::_has_legacy_form_field_attrs_tree( $attrs, $module_name );
	}

	/**
	 * Recursively check attrs tree for legacy form-field attributes.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Attributes tree.
	 * @param string $module_name Current module name.
	 * @param string $current_attr_key Current attr key.
	 *
	 * @return bool True when legacy attrs are found.
	 */
	private static function _has_legacy_form_field_attrs_tree( array $attrs, string $module_name = '', string $current_attr_key = '' ): bool {
		if ( self::_node_has_legacy_form_field_attrs( $attrs, $module_name, $current_attr_key ) ) {
			return true;
		}

		foreach ( $attrs as $key => $value ) {
			if ( is_array( $value ) && self::_has_legacy_form_field_attrs_tree( $value, $module_name, (string) $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively check attrs tree for missing contact form field variant attrs.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Attributes tree.
	 * @param string $module_name Current module name.
	 *
	 * @return bool True when checkbox/radio copy is needed.
	 */
	private static function _attrs_tree_needs_contact_form_field_variant_copy( array $attrs, string $module_name ): bool {
		if ( self::_node_needs_contact_form_field_variant_copy( $attrs, $module_name ) ) {
			return true;
		}

		foreach ( $attrs as $value ) {
			if ( is_array( $value ) && self::_attrs_tree_needs_contact_form_field_variant_copy( $value, $module_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively check attrs tree for missing child-field label text copy.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Attributes tree.
	 * @param string $module_name Current module name.
	 *
	 * @return bool True when label text copy is needed.
	 */
	private static function _attrs_tree_needs_contact_form_child_field_label_text_copy( array $attrs, string $module_name ): bool {
		if ( self::_node_needs_contact_form_child_field_label_text_copy( $attrs, $module_name ) ) {
			return true;
		}

		foreach ( $attrs as $value ) {
			if ( is_array( $value ) && self::_attrs_tree_needs_contact_form_child_field_label_text_copy( $value, $module_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively check attrs tree for missing Woo fieldLabels font copy.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Attributes tree.
	 * @param string $module_name Current module name.
	 *
	 * @return bool True when Woo fieldLabels font copy is needed.
	 */
	private static function _attrs_tree_needs_woocommerce_field_labels_font_copy( array $attrs, string $module_name ): bool {
		if ( self::_node_needs_woocommerce_field_labels_font_copy( $attrs, $module_name ) ) {
			return true;
		}

		foreach ( $attrs as $value ) {
			if ( is_array( $value ) && self::_attrs_tree_needs_woocommerce_field_labels_font_copy( $value, $module_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively check attrs tree for Woo required indicator color migration.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Attributes tree.
	 * @param string $module_name Current module name.
	 *
	 * @return bool True when required indicator color migration is needed.
	 */
	private static function _attrs_tree_needs_woocommerce_required_field_indicator_color_move( array $attrs, string $module_name ): bool {
		if ( self::_node_needs_woocommerce_required_field_indicator_color_move( $attrs, $module_name ) ) {
			return true;
		}

		foreach ( $attrs as $value ) {
			if ( is_array( $value ) && self::_attrs_tree_needs_woocommerce_required_field_indicator_color_move( $value, $module_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively check attrs tree for Woo group-preset key migration.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Attributes tree.
	 * @param string $module_name Current module name.
	 *
	 * @return bool True when group-preset remap is needed.
	 */
	private static function _attrs_tree_needs_woocommerce_field_labels_group_preset_move( array $attrs, string $module_name ): bool {
		if ( self::_node_needs_woocommerce_field_labels_group_preset_move( $attrs, $module_name ) ) {
			return true;
		}

		foreach ( $attrs as $value ) {
			if ( is_array( $value ) && self::_attrs_tree_needs_woocommerce_field_labels_group_preset_move( $value, $module_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check current node for missing child-field label text copy.
	 *
	 * @since ??
	 *
	 * @param array  $node        Attr node.
	 * @param string $module_name Current module name.
	 *
	 * @return bool True when label text copy is needed.
	 */
	private static function _node_needs_contact_form_child_field_label_text_copy( array $node, string $module_name ): bool {
		if ( ! in_array( $module_name, [ 'divi/contact-field', 'divi/signup-custom-field' ], true ) ) {
			return false;
		}

		$field_font_attrs = $node['field']['decoration']['font'] ?? null;

		if ( ! is_array( $field_font_attrs ) || ! self::_has_meaningful_attr_values( $field_font_attrs ) ) {
			return false;
		}

		$field_label_font_attrs = self::_remove_text_color_attrs_from_font_group( $field_font_attrs );

		if ( ! self::_has_meaningful_attr_values( $field_label_font_attrs ) ) {
			return false;
		}

		$existing_label_font_attrs = $node['field']['decoration']['labelFont'] ?? [];
		$merged_label_font_attrs   = self::_merge_missing_attr_values(
			is_array( $existing_label_font_attrs ) ? $existing_label_font_attrs : [],
			$field_label_font_attrs
		);

		return $merged_label_font_attrs !== $existing_label_font_attrs;
	}

	/**
	 * Check current node for missing Woo fieldLabels font copy.
	 *
	 * @since ??
	 *
	 * @param array  $node        Attr node.
	 * @param string $module_name Current module name.
	 *
	 * @return bool True when copy is needed.
	 */
	private static function _node_needs_woocommerce_field_labels_font_copy( array $node, string $module_name ): bool {
		if ( ! MigrationUtils::is_woocommerce_field_labels_legacy_module( $module_name ) ) {
			return false;
		}

		$field_labels_font_attrs = $node['fieldLabels']['decoration']['font'] ?? null;

		if ( ! is_array( $field_labels_font_attrs ) ) {
			return false;
		}

		if ( ! self::_has_meaningful_attr_values( $field_labels_font_attrs ) ) {
			return true;
		}

		$existing_label_font_attrs = $node['field']['decoration']['labelFont'] ?? [];
		$merged_label_font_attrs   = self::_merge_missing_attr_values(
			is_array( $existing_label_font_attrs ) ? $existing_label_font_attrs : [],
			$field_labels_font_attrs
		);

		return $merged_label_font_attrs !== $existing_label_font_attrs
			|| array_key_exists( 'font', $node['fieldLabels']['decoration'] ?? [] );
	}

	/**
	 * Check current node for missing Woo required indicator color migration.
	 *
	 * @since ??
	 *
	 * @param array  $node        Attr node.
	 * @param string $module_name Current module name.
	 *
	 * @return bool True when migration is needed.
	 */
	private static function _node_needs_woocommerce_required_field_indicator_color_move( array $node, string $module_name ): bool {
		if ( ! MigrationUtils::is_woocommerce_required_field_indicator_color_legacy_module( $module_name ) ) {
			return false;
		}

		$legacy_required_indicator_attr = $node['fieldLabels']['advanced']['requiredFieldIndicatorColor'] ?? null;

		if ( ! is_array( $legacy_required_indicator_attr ) ) {
			return false;
		}

		if ( ! self::_has_meaningful_attr_values( $legacy_required_indicator_attr ) ) {
			return true;
		}

		$target_required_indicator_attr = $node['field']['advanced']['requiredFieldIndicatorColor'] ?? [];
		$merged_required_indicator_attr = self::_merge_missing_attr_values(
			is_array( $target_required_indicator_attr ) ? $target_required_indicator_attr : [],
			$legacy_required_indicator_attr
		);

		return $merged_required_indicator_attr !== $target_required_indicator_attr
			|| array_key_exists( 'requiredFieldIndicatorColor', $node['fieldLabels']['advanced'] ?? [] );
	}

	/**
	 * Check current node for Woo group-preset key migration.
	 *
	 * @since ??
	 *
	 * @param array  $node        Attr node.
	 * @param string $module_name Current module name.
	 *
	 * @return bool True when group-preset remap is needed.
	 */
	private static function _node_needs_woocommerce_field_labels_group_preset_move( array $node, string $module_name ): bool {
		if ( ! MigrationUtils::is_woocommerce_field_labels_legacy_module( $module_name ) ) {
			return false;
		}

		$group_presets = $node['groupPreset'] ?? null;
		if ( ! is_array( $group_presets ) ) {
			return false;
		}

		foreach ( self::_get_woocommerce_legacy_field_labels_group_preset_keys() as $legacy_key ) {
			$legacy_group_preset = $group_presets[ $legacy_key ] ?? null;

			if ( is_array( $legacy_group_preset ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get cached WooCommerce legacy field-label group preset keys.
	 *
	 * @since ??
	 *
	 * @return array<int, string> Legacy group preset keys.
	 */
	private static function _get_woocommerce_legacy_field_labels_group_preset_keys(): array {
		static $keys = null;

		if ( ! is_array( $keys ) ) {
			$keys = MigrationUtils::get_woocommerce_legacy_field_labels_group_preset_keys();
		}

		return $keys;
	}

	/**
	 * Check current node for missing contact form field variant attrs.
	 *
	 * @since ??
	 *
	 * @param array  $node        Attr node.
	 * @param string $module_name Current module name.
	 *
	 * @return bool True when checkbox/radio copy is needed.
	 */
	private static function _node_needs_contact_form_field_variant_copy( array $node, string $module_name ): bool {
		if ( ! self::_is_contact_form_family_module_node( $node, $module_name ) ) {
			return false;
		}

		$field_attrs = $node['field'] ?? null;

		if ( ! is_array( $field_attrs ) || ! self::_has_meaningful_attr_values( $field_attrs ) ) {
			return false;
		}

		$field_attrs_without_placeholder = self::_remove_placeholder_text_attrs( $field_attrs );
		$field_attrs_for_variant_copy    = self::_remove_spacing_padding_and_margin_attrs( $field_attrs_without_placeholder );
		$field_attrs_for_variant_copy    = self::_remove_signup_variant_text_color_and_alignment_attrs(
			$field_attrs_for_variant_copy,
			$module_name
		);

		foreach ( [ 'checkbox', 'radio' ] as $target_attr_name ) {
			$existing_target_attrs = $node[ $target_attr_name ] ?? [];
			$merged_target_attrs   = self::_merge_missing_attr_values(
				is_array( $existing_target_attrs ) ? $existing_target_attrs : [],
				$field_attrs_for_variant_copy
			);

			if ( $merged_target_attrs !== $existing_target_attrs ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove text and decoration attrs for signup variant copy targets.
	 *
	 * Legacy signup and signup-custom-field modules do not apply field text color,
	 * text alignment, border, or box-shadow styles to checkbox/radio fields.
	 * Keep contact-form behavior unchanged by only stripping these keys/groups
	 * for signup family modules.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Field attrs prepared for variant copy.
	 * @param string $module_name Current module name.
	 *
	 * @return array Field attrs without signup-only text/decoration keys.
	 */
	private static function _remove_signup_variant_text_color_and_alignment_attrs( array $attrs, string $module_name ): array {
		if ( ! in_array( $module_name, [ 'divi/signup', 'divi/signup-custom-field' ], true ) ) {
			return $attrs;
		}

		$migrated_attrs = $attrs;
		$font_attr_keys = [
			'color',
			'textColor',
			'textAlign',
		];

		foreach ( $migrated_attrs['decoration']['font']['font'] ?? [] as $breakpoint => $state_values ) {
			if ( ! is_array( $state_values ) ) {
				continue;
			}

			foreach ( $state_values as $state => $font_values ) {
				if ( ! is_array( $font_values ) ) {
					continue;
				}

				foreach ( $font_attr_keys as $font_attr_key ) {
					unset( $migrated_attrs['decoration']['font']['font'][ $breakpoint ][ $state ][ $font_attr_key ] );
				}
			}
		}

		foreach ( $migrated_attrs['advanced']['focus']['font']['font'] ?? [] as $breakpoint => $state_values ) {
			if ( ! is_array( $state_values ) ) {
				continue;
			}

			foreach ( $state_values as $state => $font_values ) {
				if ( ! is_array( $font_values ) ) {
					continue;
				}

				foreach ( $font_attr_keys as $font_attr_key ) {
					unset( $migrated_attrs['advanced']['focus']['font']['font'][ $breakpoint ][ $state ][ $font_attr_key ] );
				}
			}
		}

		unset( $migrated_attrs['decoration']['border'] );
		unset( $migrated_attrs['decoration']['boxShadow'] );
		unset( $migrated_attrs['advanced']['focus']['border'] );
		unset( $migrated_attrs['advanced']['focusUseBorder'] );

		return $migrated_attrs;
	}

	/**
	 * Check current node for legacy form-field attributes.
	 *
	 * @since ??
	 *
	 * @param array  $node        Attr node.
	 * @param string $module_name Current module name.
	 * @param string $current_attr_key Current attr key.
	 *
	 * @return bool True when legacy attrs are present.
	 */
	private static function _node_has_legacy_form_field_attrs( array $node, string $module_name = '', string $current_attr_key = '' ): bool {
		$legacy_background            = $node['advanced']['focus']['background'] ?? null;
		$legacy_focus_font            = $node['advanced']['focus']['font']['font'] ?? null;
		$legacy_placeholder_font      = $node['advanced']['placeholder']['font']['font'] ?? null;
		$legacy_decoration_background = $node['decoration']['background'] ?? null;

		if ( is_array( $legacy_background ) && self::_breakpoint_value_has_any_key( $legacy_background, [ 'color', 'backgroundColor' ] ) ) {
			return true;
		}

		if ( is_array( $legacy_focus_font ) && self::_breakpoint_value_has_any_key( $legacy_focus_font, [ 'color', 'textColor' ] ) ) {
			return true;
		}

		if ( self::_has_meaningful_attr_values( $legacy_placeholder_font ) ) {
			return true;
		}

		$is_form_related_background_group = in_array( $current_attr_key, self::FORM_RELATED_BACKGROUND_ATTR_GROUPS, true );
		if (
			$is_form_related_background_group
			&& is_array( $legacy_decoration_background )
			&& self::_breakpoint_state_has_key( $legacy_decoration_background, 'backgroundColor' )
		) {
			return true;
		}

		$is_divi_non_contact_module = '' !== $module_name
			&& 'divi/contact-form' !== $module_name
			&& str_starts_with( $module_name, 'divi/' );
		if ( ! $is_divi_non_contact_module && self::_node_has_contact_form_module_field_border_shadow_legacy_attrs( $node ) ) {
			return true;
		}

		if ( self::_node_has_legacy_focus_border_attrs( $node ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check whether node has legacy focus border attrs.
	 *
	 * @since ??
	 *
	 * @param array $node Attr node.
	 *
	 * @return bool True when legacy attrs are present.
	 */
	private static function _node_has_legacy_focus_border_attrs( array $node ): bool {
		$focus_border     = $node['advanced']['focus']['border'] ?? null;
		$focus_use_border = $node['advanced']['focusUseBorder'] ?? null;

		return self::_has_meaningful_attr_values( $focus_border )
		|| self::_has_meaningful_attr_values( $focus_use_border );
	}

	/**
	 * Check breakpoint->value arrays for any key.
	 *
	 * @since ??
	 *
	 * @param array $breakpoint_map Breakpoint map.
	 * @param array $keys           Keys to check.
	 *
	 * @return bool True when any key is found.
	 */
	private static function _breakpoint_value_has_any_key( array $breakpoint_map, array $keys ): bool {
		foreach ( $breakpoint_map as $breakpoint_values ) {
			if ( ! is_array( $breakpoint_values ) || ! is_array( $breakpoint_values['value'] ?? null ) ) {
				continue;
			}

			foreach ( $keys as $key ) {
				if ( array_key_exists( $key, $breakpoint_values['value'] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check breakpoint->state arrays for a key.
	 *
	 * @since ??
	 *
	 * @param array  $breakpoint_map Breakpoint map.
	 * @param string $key            Key to check.
	 *
	 * @return bool True when key is found.
	 */
	private static function _breakpoint_state_has_key( array $breakpoint_map, string $key ): bool {
		foreach ( $breakpoint_map as $states ) {
			if ( ! is_array( $states ) ) {
				continue;
			}

			foreach ( $states as $state_values ) {
				if ( is_array( $state_values ) && array_key_exists( $key, $state_values ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check whether node has legacy contact-form module-level border/shadow attrs.
	 *
	 * @since ??
	 *
	 * @param array $node Attr node.
	 *
	 * @return bool True when legacy attrs are present.
	 */
	private static function _node_has_contact_form_module_field_border_shadow_legacy_attrs( array $node ): bool {
		$module = $node['module'] ?? null;

		if ( ! is_array( $module ) ) {
			return false;
		}

		$module_decoration = $module['decoration'] ?? null;

		if ( ! is_array( $module_decoration ) ) {
			return false;
		}

		return self::_has_meaningful_attr_values( $module_decoration['border'] ?? null )
		|| self::_has_meaningful_attr_values( $module_decoration['boxShadow'] ?? null );
	}

	/**
	 * Migrate contact-form module border/box-shadow attrs to field attrs.
	 *
	 * @since ??
	 *
	 * @param array  $node        Attr node.
	 * @param string $module_name Current module name.
	 *
	 * @return array Migrated node.
	 */
	private static function _migrate_contact_form_border_and_shadow_attrs( array $node, string $module_name ): array {
		if ( 'divi/contact-form' !== $module_name ) {
			return $node;
		}

		if ( ! self::_node_has_contact_form_module_field_border_shadow_legacy_attrs( $node ) ) {
			return $node;
		}

		$migrated_node = $node;

		if ( ! is_array( $migrated_node['field'] ?? null ) ) {
			$migrated_node['field'] = [];
		}

		if ( ! is_array( $migrated_node['field']['decoration'] ?? null ) ) {
			$migrated_node['field']['decoration'] = [];
		}

		$module_border = $migrated_node['module']['decoration']['border'] ?? null;
		$field_border  = $migrated_node['field']['decoration']['border'] ?? null;

		if ( is_array( $module_border ) && ! self::_has_meaningful_attr_values( $field_border ) ) {
			$migrated_node['field']['decoration']['border'] = $module_border;
		}
		unset( $migrated_node['module']['decoration']['border'] );

		$module_box_shadow = $migrated_node['module']['decoration']['boxShadow'] ?? null;
		$field_box_shadow  = $migrated_node['field']['decoration']['boxShadow'] ?? null;

		if ( is_array( $module_box_shadow ) && ! self::_has_meaningful_attr_values( $field_box_shadow ) ) {
			$migrated_node['field']['decoration']['boxShadow'] = $module_box_shadow;
		}
		unset( $migrated_node['module']['decoration']['boxShadow'] );

		if ( empty( $migrated_node['module']['decoration'] ) ) {
			unset( $migrated_node['module']['decoration'] );
		}

		if ( empty( $migrated_node['module'] ) ) {
			unset( $migrated_node['module'] );
		}

		return $migrated_node;
	}

	/**
	 * Check whether a value tree has meaningful non-empty values.
	 *
	 * @since ??
	 *
	 * @param mixed $value Value tree.
	 *
	 * @return bool True when meaningful value exists.
	 */
	private static function _has_meaningful_attr_values( $value ): bool {
		if ( null === $value ) {
			return false;
		}

		if ( is_scalar( $value ) ) {
			return '' !== (string) $value;
		}

		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $item ) {
			if ( self::_has_meaningful_attr_values( $item ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Migrate legacy focus/background color keys in a single node.
	 *
	 * @since ??
	 *
	 * @param array  $node             Attributes node.
	 * @param string $current_attr_key Current attr key.
	 *
	 * @return array Migrated node.
	 */
	private static function _migrate_field_node( array $node, string $current_attr_key = '' ): array {
		$migrated_node                    = $node;
		$decoration_background            = $migrated_node['decoration']['background'] ?? null;
		$focus_background                 = $migrated_node['advanced']['focus']['background'] ?? null;
		$focus_font                       = $migrated_node['advanced']['focus']['font']['font'] ?? null;
		$is_form_related_background_group = in_array( $current_attr_key, self::FORM_RELATED_BACKGROUND_ATTR_GROUPS, true );

		// Migrate legacy field background color key in decoration background.
		if ( $is_form_related_background_group ) {
			foreach ( is_array( $decoration_background ) ? $decoration_background : [] as $breakpoint => $states ) {
				if ( ! is_array( $states ) ) {
					continue;
				}

				foreach ( $states as $state => $state_values ) {
					if ( ! is_array( $state_values ) || ! array_key_exists( 'backgroundColor', $state_values ) ) {
						continue;
					}

					if ( ! array_key_exists( 'color', $state_values ) ) {
						$migrated_node['decoration']['background'][ $breakpoint ][ $state ]['color'] = $state_values['backgroundColor'];
					}

					unset( $migrated_node['decoration']['background'][ $breakpoint ][ $state ]['backgroundColor'] );
				}
			}
		}

		// Migrate legacy focus background color to decoration background focus state.
		foreach ( is_array( $focus_background ) ? $focus_background : [] as $breakpoint => $state_values ) {
			if ( ! is_array( $state_values ) || ! is_array( $state_values['value'] ?? null ) ) {
				continue;
			}

			$legacy_color = null;

			if ( array_key_exists( 'color', $state_values['value'] ) ) {
				$legacy_color = $state_values['value']['color'];
			} elseif ( array_key_exists( 'backgroundColor', $state_values['value'] ) ) {
				$legacy_color = $state_values['value']['backgroundColor'];
			}

			if ( null !== $legacy_color ) {
				if ( ! isset( $migrated_node['decoration']['background'][ $breakpoint ] )
					|| ! is_array( $migrated_node['decoration']['background'][ $breakpoint ] )
				) {
					$migrated_node['decoration']['background'][ $breakpoint ] = [];
				}

				if ( ! isset( $migrated_node['decoration']['background'][ $breakpoint ]['focus'] )
					|| ! is_array( $migrated_node['decoration']['background'][ $breakpoint ]['focus'] )
				) {
					$migrated_node['decoration']['background'][ $breakpoint ]['focus'] = [];
				}

				if ( ! array_key_exists( 'color', $migrated_node['decoration']['background'][ $breakpoint ]['focus'] ) ) {
					$migrated_node['decoration']['background'][ $breakpoint ]['focus']['color'] = $legacy_color;
				}
			}

			unset( $migrated_node['advanced']['focus']['background'][ $breakpoint ]['value']['color'] );
			unset( $migrated_node['advanced']['focus']['background'][ $breakpoint ]['value']['backgroundColor'] );

			if ( empty( $migrated_node['advanced']['focus']['background'][ $breakpoint ]['value'] ) ) {
				unset( $migrated_node['advanced']['focus']['background'][ $breakpoint ]['value'] );
			}

			if ( empty( $migrated_node['advanced']['focus']['background'][ $breakpoint ] ) ) {
				unset( $migrated_node['advanced']['focus']['background'][ $breakpoint ] );
			}
		}

		// Migrate legacy focus text color to decoration font focus state.
		foreach ( is_array( $focus_font ) ? $focus_font : [] as $breakpoint => $state_values ) {
			if ( ! is_array( $state_values ) || ! is_array( $state_values['value'] ?? null ) ) {
				continue;
			}

			$legacy_color = null;

			if ( array_key_exists( 'color', $state_values['value'] ) ) {
				$legacy_color = $state_values['value']['color'];
			} elseif ( array_key_exists( 'textColor', $state_values['value'] ) ) {
				$legacy_color = $state_values['value']['textColor'];
			}

			if ( null !== $legacy_color ) {
				if ( ! isset( $migrated_node['decoration']['font']['font'][ $breakpoint ] )
					|| ! is_array( $migrated_node['decoration']['font']['font'][ $breakpoint ] )
				) {
					$migrated_node['decoration']['font']['font'][ $breakpoint ] = [];
				}

				if ( ! isset( $migrated_node['decoration']['font']['font'][ $breakpoint ]['focus'] )
					|| ! is_array( $migrated_node['decoration']['font']['font'][ $breakpoint ]['focus'] )
				) {
					$migrated_node['decoration']['font']['font'][ $breakpoint ]['focus'] = [];
				}

				if ( ! array_key_exists( 'color', $migrated_node['decoration']['font']['font'][ $breakpoint ]['focus'] ) ) {
					$migrated_node['decoration']['font']['font'][ $breakpoint ]['focus']['color'] = $legacy_color;
				}
			}

			unset( $migrated_node['advanced']['focus']['font']['font'][ $breakpoint ]['value']['color'] );
			unset( $migrated_node['advanced']['focus']['font']['font'][ $breakpoint ]['value']['textColor'] );

			if ( empty( $migrated_node['advanced']['focus']['font']['font'][ $breakpoint ]['value'] ) ) {
				unset( $migrated_node['advanced']['focus']['font']['font'][ $breakpoint ]['value'] );
			}

			if ( empty( $migrated_node['advanced']['focus']['font']['font'][ $breakpoint ] ) ) {
				unset( $migrated_node['advanced']['focus']['font']['font'][ $breakpoint ] );
			}
		}

		// Migrate legacy placeholder font values.
		$migrated_node = self::_migrate_placeholder_font_attrs( $migrated_node );

		// Copy migrated field font values to placeholder font as fallback values.
		if ( 'field' === $current_attr_key ) {
			$migrated_node = self::_copy_field_font_attrs_to_placeholder_font_attrs( $migrated_node );
		}

		// Migrate legacy focus border values based on focusUseBorder.
		$migrated_node = self::_migrate_focus_border_attrs( $migrated_node );

		return $migrated_node;
	}

	/**
	 * Copy field font attrs to placeholder font attrs as fallback values.
	 *
	 * Preserves any explicit placeholder font attrs by only filling missing keys.
	 * This keeps legacy placeholder-specific values as the source of truth.
	 *
	 * @since ??
	 *
	 * @param array $node Attributes node.
	 *
	 * @return array Migrated node.
	 */
	private static function _copy_field_font_attrs_to_placeholder_font_attrs( array $node ): array {
		$field_font_attrs = $node['decoration']['font']['font'] ?? null;

		if ( ! is_array( $field_font_attrs ) || ! self::_has_meaningful_attr_values( $field_font_attrs ) ) {
			return $node;
		}

		if ( ! isset( $node['decoration']['placeholderFont']['font'] ) || ! is_array( $node['decoration']['placeholderFont']['font'] ) ) {
			$node['decoration']['placeholderFont']['font'] = [];
		}

		foreach ( $field_font_attrs as $breakpoint => $states ) {
			if ( ! is_array( $states ) ) {
				continue;
			}

			$existing_placeholder_breakpoint                              = $node['decoration']['placeholderFont']['font'][ $breakpoint ] ?? [];
			$node['decoration']['placeholderFont']['font'][ $breakpoint ] = self::_merge_missing_attr_values(
				is_array( $existing_placeholder_breakpoint ) ? $existing_placeholder_breakpoint : [],
				$states
			);
		}

		return $node;
	}

	/**
	 * Migrate legacy focus border attrs to decoration border focus state.
	 *
	 * @since ??
	 *
	 * @param array $node Attributes node.
	 *
	 * @return array Migrated node.
	 */
	private static function _migrate_focus_border_attrs( array $node ): array {
		$legacy_focus_border     = $node['advanced']['focus']['border'] ?? null;
		$focus_use_border_exists = is_array( $node['advanced'] ?? null ) && array_key_exists( 'focusUseBorder', $node['advanced'] );

		if ( ! is_array( $legacy_focus_border ) ) {
			if ( $focus_use_border_exists ) {
				unset( $node['advanced']['focusUseBorder'] );

				if ( empty( $node['advanced'] ) ) {
					unset( $node['advanced'] );
				}
			}

			return $node;
		}

		$focus_use_border = $node['advanced']['focusUseBorder'] ?? null;

		// Respect explicit disable values. When toggle is absent, migrate legacy focus border values.
		if ( $focus_use_border_exists && ! self::_is_focus_border_enabled( $focus_use_border ) ) {
			unset( $node['advanced']['focus']['border'] );
			unset( $node['advanced']['focusUseBorder'] );

			if ( empty( $node['advanced']['focus'] ) ) {
				unset( $node['advanced']['focus'] );
			}

			return $node;
		}

		if ( ! self::_has_meaningful_attr_values( $legacy_focus_border ) ) {
			unset( $node['advanced']['focus']['border'] );

			if ( empty( $node['advanced']['focus'] ) ) {
				unset( $node['advanced']['focus'] );
			}

			if ( $focus_use_border_exists ) {
				unset( $node['advanced']['focusUseBorder'] );
			}

			if ( empty( $node['advanced'] ) ) {
				unset( $node['advanced'] );
			}

			return $node;
		}

		foreach ( $legacy_focus_border as $breakpoint => $state_values ) {
			if ( ! is_array( $state_values ) ) {
				continue;
			}

			$legacy_border_values = $state_values['value'] ?? $state_values;

			if ( ! is_array( $legacy_border_values ) || ! self::_has_meaningful_attr_values( $legacy_border_values ) ) {
				continue;
			}

			if ( ! isset( $node['decoration']['border'][ $breakpoint ] ) || ! is_array( $node['decoration']['border'][ $breakpoint ] ) ) {
				$node['decoration']['border'][ $breakpoint ] = [];
			}

			if ( ! isset( $node['decoration']['border'][ $breakpoint ]['focus'] ) || ! is_array( $node['decoration']['border'][ $breakpoint ]['focus'] ) ) {
				$node['decoration']['border'][ $breakpoint ]['focus'] = [];
			}

			foreach ( $legacy_border_values as $key => $value ) {
				if ( ! array_key_exists( $key, $node['decoration']['border'][ $breakpoint ]['focus'] ) ) {
					$node['decoration']['border'][ $breakpoint ]['focus'][ $key ] = $value;
				}
			}
		}

		unset( $node['advanced']['focus']['border'] );
		unset( $node['advanced']['focusUseBorder'] );

		if ( empty( $node['advanced']['focus'] ) ) {
			unset( $node['advanced']['focus'] );
		}

		if ( empty( $node['advanced'] ) ) {
			unset( $node['advanced'] );
		}

		return $node;
	}

	/**
	 * Migrate legacy placeholder font attrs to decoration placeholder font.
	 *
	 * @since ??
	 *
	 * @param array $node Attributes node.
	 *
	 * @return array Migrated node.
	 */
	private static function _migrate_placeholder_font_attrs( array $node ): array {
		$legacy_placeholder_font = $node['advanced']['placeholder']['font']['font'] ?? null;

		if ( ! is_array( $legacy_placeholder_font ) || ! self::_has_meaningful_attr_values( $legacy_placeholder_font ) ) {
			return $node;
		}

		foreach ( $legacy_placeholder_font as $breakpoint => $state_values ) {
			if ( ! is_array( $state_values ) || ! is_array( $state_values['value'] ?? null ) ) {
				continue;
			}

			if ( ! isset( $node['decoration']['placeholderFont']['font'][ $breakpoint ] )
				|| ! is_array( $node['decoration']['placeholderFont']['font'][ $breakpoint ] )
			) {
				$node['decoration']['placeholderFont']['font'][ $breakpoint ] = [];
			}

			if ( ! isset( $node['decoration']['placeholderFont']['font'][ $breakpoint ]['value'] )
				|| ! is_array( $node['decoration']['placeholderFont']['font'][ $breakpoint ]['value'] )
			) {
				$node['decoration']['placeholderFont']['font'][ $breakpoint ]['value'] = [];
			}

			foreach ( $state_values['value'] as $key => $value ) {
				if ( ! array_key_exists( $key, $node['decoration']['placeholderFont']['font'][ $breakpoint ]['value'] ) ) {
					$node['decoration']['placeholderFont']['font'][ $breakpoint ]['value'][ $key ] = $value;
				}
			}
		}

		unset( $node['advanced']['placeholder']['font'] );

		if ( empty( $node['advanced']['placeholder'] ) ) {
			unset( $node['advanced']['placeholder'] );
		}

		return $node;
	}

	/**
	 * Check if legacy focus border toggle is enabled.
	 *
	 * @since ??
	 *
	 * @param mixed $focus_use_border Legacy focusUseBorder value.
	 *
	 * @return bool True when any breakpoint enables focus border.
	 */
	private static function _is_focus_border_enabled( $focus_use_border ): bool {
		if ( is_string( $focus_use_border ) ) {
			return 'on' === $focus_use_border;
		}

		if ( is_bool( $focus_use_border ) ) {
			return $focus_use_border;
		}

		if ( is_numeric( $focus_use_border ) ) {
			return (int) $focus_use_border > 0;
		}

		if ( ! is_array( $focus_use_border ) ) {
			return false;
		}

		foreach ( $focus_use_border as $breakpoint_values ) {
			if ( ! is_array( $breakpoint_values ) || ! array_key_exists( 'value', $breakpoint_values ) ) {
				continue;
			}

			if ( self::_is_focus_border_enabled( $breakpoint_values['value'] ) ) {
				return true;
			}
		}

		return false;
	}
}
