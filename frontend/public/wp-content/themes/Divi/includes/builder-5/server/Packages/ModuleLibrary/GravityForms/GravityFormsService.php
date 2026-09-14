<?php
/**
 * Gravity Forms module service.
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\GravityForms;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\FrontEnd\BlockParser\SimpleBlockParser;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Packages\ModuleUtils\FormMarkupSecurityUtils;

/**
 * GravityFormsService class.
 *
 * @since ??
 */
class GravityFormsService {

	/**
	 * Hidden field that carries Divi button styling context across GF iframe-Ajax requests.
	 *
	 * @since ??
	 */
	private const DIVI_BUTTON_CLASS_AJAX_CONTEXT_FIELD = 'et_pb_gravity_forms_button_classes';

	/**
	 * Form/Ajax-mode pairs already passed to Gravity Forms during this request.
	 *
	 * @since ??
	 *
	 * @var array<string, bool>
	 */
	private static array $_enqueued_form_assets = [];

	/**
	 * Enqueues a Gravity Forms form's plugin and add-on assets once per exact form/Ajax pair.
	 *
	 * This is the only explicit Divi boundary that calls `gravity_form_enqueue_scripts()`. Dynamic
	 * Assets coordinates when to invoke it on the front end; `render_form_preview()` remains the
	 * Visual Builder / REST preview fallback. Both paths share this helper so Divi's request-local
	 * deduplication covers every exact `(formId, useAjax)` pair.
	 *
	 * @since ??
	 *
	 * @param int  $form_id  Gravity Forms form ID.
	 * @param bool $use_ajax Whether Gravity Forms Ajax rendering is enabled.
	 *
	 * @return void
	 */
	public static function enqueue_form_assets( int $form_id, bool $use_ajax ): void {
		self::_enqueue_form_assets( $form_id, $use_ajax );
	}

	/**
	 * Enqueues one exact form/Ajax pair, optionally reusing form data already fetched by the render path.
	 *
	 * @since ??
	 *
	 * @param int        $form_id  Gravity Forms form ID.
	 * @param bool       $use_ajax Whether Gravity Forms Ajax rendering is enabled.
	 * @param array|null $form     Optional form data already fetched from Gravity Forms.
	 *
	 * @return void
	 */
	private static function _enqueue_form_assets( int $form_id, bool $use_ajax, ?array $form = null ): void {
		$form = self::_get_renderable_gravity_form( $form_id, $form );

		if ( null === $form ) {
			return;
		}

		$enqueue_key = $form_id . ':' . ( $use_ajax ? '1' : '0' );

		if ( isset( self::$_enqueued_form_assets[ $enqueue_key ] ) ) {
			return;
		}

		self::$_enqueued_form_assets[ $enqueue_key ] = true;

		gravity_form_enqueue_scripts( $form_id, $use_ajax );
	}

	/**
	 * Collects distinct Gravity Forms asset requirements from supplied content.
	 *
	 * Dynamic Assets owns request content gathering and timing. This method only interprets
	 * `divi/gravity-forms` blocks (including nested and reusable references) and returns the
	 * `(formId, useAjax)` pairs `GravityFormsIntegrationAssetsProvider` should enqueue.
	 *
	 * @since ??
	 *
	 * @param string $content Content supplied by Dynamic Assets.
	 *
	 * @return array<int, array{formId: int, useAjax: bool}> Distinct form/Ajax pairs.
	 */
	public static function get_form_asset_requirements( string $content ): array {
		$seen_reusable_ids = [];

		return self::_get_gravity_forms_to_enqueue( $content, $seen_reusable_ids );
	}

	/**
	 * Collects distinct Gravity Forms embeds declared directly or through reusable blocks.
	 *
	 * Module preset render attributes are merged before values are read because both `formId` and
	 * `useAjax` are content-preset attributes. Raw block attributes merge last so explicit values
	 * always override preset-backed values, matching the module render pipeline.
	 *
	 * @since ??
	 *
	 * @param string     $content           Content to scan.
	 * @param array<int, bool> $seen_reusable_ids Reusable block IDs already visited in this scan.
	 *
	 * @return array<int, array{formId: int, useAjax: bool}> Distinct form/Ajax pairs.
	 */
	private static function _get_gravity_forms_to_enqueue( string $content, array &$seen_reusable_ids ): array {
		$has_gravity_forms_block = str_contains( $content, 'divi/gravity-forms' );
		$has_reusable_block      = str_contains( $content, '<!-- wp:block ' );

		if ( ! $has_gravity_forms_block && ! $has_reusable_block ) {
			return [];
		}

		$forms_to_enqueue = [];

		if ( $has_gravity_forms_block ) {
			$blocks = SimpleBlockParser::parse(
				$content,
				[
					'blockName'    => 'divi/gravity-forms',
					'excludeError' => true,
				]
			);

			foreach ( $blocks as $block ) {
				$raw_attrs             = $block->attrs();
				$preset_render_attrs   = GlobalPreset::get_merged_preset_render_attrs(
					[
						'moduleName'  => 'divi/gravity-forms',
						'moduleAttrs' => $raw_attrs,
					]
				);
				$selected_group_presets = GlobalPreset::get_selected_group_presets(
					[
						'moduleName'  => 'divi/gravity-forms',
						'moduleAttrs' => $raw_attrs,
					]
				);
				$group_render_attrs = GlobalPreset::merge_selected_group_presets_by_priority( $selected_group_presets )['renderAttrs'];
				$attrs              = array_replace_recursive(
					$preset_render_attrs,
					$group_render_attrs,
					$raw_attrs
				);

				$form_id = absint( $attrs['gravityForm']['innerContent']['desktop']['value']['formId'] ?? 0 );

				if ( $form_id < 1 ) {
					continue;
				}

				$use_ajax    = 'on' === ( $attrs['gravityForm']['advanced']['useAjax']['desktop']['value'] ?? 'off' );
				$enqueue_key = $form_id . ':' . ( $use_ajax ? '1' : '0' );

				$forms_to_enqueue[ $enqueue_key ] = [
					'formId'  => $form_id,
					'useAjax' => $use_ajax,
				];
			}
		}

		if ( $has_reusable_block ) {
			$pending_blocks = array_reverse( parse_blocks( $content ) );

			while ( [] !== $pending_blocks ) {
				$reusable_block = array_pop( $pending_blocks );

				if ( ! empty( $reusable_block['innerBlocks'] ) ) {
					foreach ( array_reverse( $reusable_block['innerBlocks'] ) as $inner_block ) {
						$pending_blocks[] = $inner_block;
					}
				}

				if ( 'core/block' !== ( $reusable_block['blockName'] ?? '' ) ) {
					continue;
				}

				$reusable_id = absint( $reusable_block['attrs']['ref'] ?? 0 );

				if ( $reusable_id < 1 || isset( $seen_reusable_ids[ $reusable_id ] ) ) {
					continue;
				}

				$seen_reusable_ids[ $reusable_id ] = true;

				$reusable_post = get_post( $reusable_id );

				if (
					! ( $reusable_post instanceof \WP_Post )
					|| 'wp_block' !== $reusable_post->post_type
					|| 'publish' !== $reusable_post->post_status
					|| '' !== (string) $reusable_post->post_password
				) {
					continue;
				}

				foreach ( self::_get_gravity_forms_to_enqueue( (string) $reusable_post->post_content, $seen_reusable_ids ) as $form ) {
					$enqueue_key = $form['formId'] . ':' . ( $form['useAjax'] ? '1' : '0' );

					$forms_to_enqueue[ $enqueue_key ] = $form;
				}
			}
		}

		return array_values( $forms_to_enqueue );
	}

	/**
	 * Loads one form and returns it only when it can render for a normal module embed.
	 *
	 * @since ??
	 *
	 * @param int           $form_id    Gravity Forms form ID.
	 * @param array|null    $form       Optional form data already fetched by the render path.
	 * @param callable|null $form_loader Optional form loader; defaults to `GFAPI::get_form()`.
	 *
	 * @return array|null
	 */
	private static function _get_renderable_gravity_form( int $form_id, ?array $form = null, ?callable $form_loader = null ): ?array {
		if ( $form_id < 1 ) {
			return null;
		}

		if ( null === $form ) {
			$form_loader = $form_loader ?? [ '\\GFAPI', 'get_form' ];
			$loaded_form = $form_loader( $form_id );

			if ( is_wp_error( $loaded_form ) || ! is_array( $loaded_form ) ) {
				return null;
			}

			$form = $loaded_form;
		}

		return self::_is_renderable_gravity_form( $form ) ? $form : null;
	}

	/**
	 * Determines whether already-loaded form data can render for a normal module embed.
	 *
	 * An absent `is_active` key preserves Gravity Forms' historical active default. An explicit
	 * false-like value, including `null`, remains inactive.
	 *
	 * @since ??
	 *
	 * @param array $form Gravity Forms form data.
	 *
	 * @return bool
	 */
	private static function _is_renderable_gravity_form( array $form ): bool {
		if ( [] === $form ) {
			return false;
		}

		$is_active = array_key_exists( 'is_active', $form ) ? (bool) $form['is_active'] : true;
		$is_trash  = (bool) ( $form['is_trash'] ?? false );

		return $is_active && ! $is_trash;
	}

	/**
	 * Returns form options keyed by form ID for Visual Builder settings.
	 *
	 * @since ??
	 *
	 * @return array<string, array{label: string}>
	 */
	public static function get_forms_options(): array {
		if ( is_callable( [ '\\GFFormsModel', 'get_forms_columns' ] ) ) {
			$forms = \GFFormsModel::get_forms_columns( true, false, 'id', 'ASC', [ 'id', 'title' ] );
		} elseif ( is_callable( [ '\\GFAPI', 'get_forms' ] ) ) {
			// Compatibility fallback for Gravity Forms versions before `get_forms_columns()`.
			$forms = \GFAPI::get_forms();
		} else {
			return [];
		}

		if ( ! is_array( $forms ) ) {
			return [];
		}

		return self::_normalize_forms_options( $forms );
	}

	/**
	 * Normalizes lightweight Gravity Forms rows for the builder catalog.
	 *
	 * @since ??
	 *
	 * @param array<int, mixed> $forms Rows containing only `id` and optional `title`.
	 *
	 * @return array<string, array{label: string}>
	 */
	private static function _normalize_forms_options( array $forms ): array {
		$options = [];

		foreach ( $forms as $form ) {
			if ( ! is_array( $form ) || empty( $form['id'] ) ) {
				continue;
			}

			$form_id = absint( $form['id'] );

			if ( $form_id < 1 ) {
				continue;
			}

			$options[ (string) $form_id ] = [
				'label' => isset( $form['title'] )
					? sanitize_text_field( wp_strip_all_tags( (string) $form['title'] ) )
					: (string) $form_id,
			];
		}

		return $options;
	}

	/**
	 * Returns true when Visual Builder preview should render Gravity Forms in a temporary invalid-submission context.
	 *
	 * This lets GF emit its own real validation summary + per-field messages (including custom messages and
	 * compound-field copy such as Name "Please complete the following fields: First, Last.") without persisting entries.
	 *
	 * @since ??
	 *
	 * @param string               $context Preview/render context.
	 * @param array<string, mixed> $args    Render args.
	 *
	 * @return bool
	 */
	private static function _should_render_real_validation_preview( string $context, array $args ): bool {
		return 'visual_builder_preview' === $context && 'on' === ( $args['validationPreview'] ?? 'off' );
	}

	/**
	 * Loads `GFFormDisplay` on demand when available.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _ensure_gf_form_display_loaded(): bool {
		if ( class_exists( '\GFFormDisplay' ) ) {
			return true;
		}

		if ( class_exists( '\GFCommon' ) && method_exists( '\GFCommon', 'get_base_path' ) ) {
			$path = trailingslashit( \GFCommon::get_base_path() ) . 'form_display.php';

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}

		return class_exists( '\GFFormDisplay' );
	}

	/**
	 * Flushes Gravity Forms' form-display cache for one form when supported.
	 *
	 * GF 2.9+ caches the result of `gform_pre_render` by form and context. A validation preview must not
	 * reuse an ordinary render's cached form, and its temporary validation state must not remain cached
	 * for a later ordinary render in the same request.
	 *
	 * @since ??
	 *
	 * @param int $form_id Gravity Forms form id.
	 *
	 * @return void
	 */
	private static function _flush_form_display_cache( int $form_id ): void {
		if ( method_exists( '\\GFFormDisplay', 'flush_cached_forms' ) ) {
			\GFFormDisplay::flush_cached_forms( $form_id . '_form_display' );
		}
	}

	/**
	 * Clones a form's field objects for an isolated validation-preview pass.
	 *
	 * Gravity Forms validation mutates field objects in place. The form returned by GFAPI can share those objects
	 * with Gravity Forms' request-local cache, so preview validation must not receive the cached instances.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $form Form object/array used by GF validation.
	 *
	 * @return array<string, mixed>
	 */
	private static function _clone_form_for_validation_preview( array $form ): array {
		$preview_form            = $form;
		$preview_form['fields']  = [];
		$cloned_field_objects    = new \SplObjectStorage();
		$fields                  = is_array( $form['fields'] ?? null ) ? $form['fields'] : [];

		foreach ( $fields as $key => $field ) {
			$preview_form['fields'][ $key ] = self::_clone_validation_preview_field( $field, $cloned_field_objects );
		}

		return $preview_form;
	}

	/**
	 * Recursively clones one field and any nested field collection it owns.
	 *
	 * Repeater fields retain child `GF_Field` objects in their public `fields` property. A normal PHP
	 * object clone is shallow, so cloning only the repeater itself would still let GF validation mutate
	 * the request-cached child objects. Other field properties are intentionally left to normal clone
	 * semantics; only the field-object graph that Gravity Forms validates in place needs isolation.
	 *
	 * @since ??
	 *
	 * @param mixed             $field                Field value from a form's field collection.
	 * @param \SplObjectStorage $cloned_field_objects Original-to-clone identity map for shared/cyclic graphs.
	 *
	 * @return mixed
	 */
	private static function _clone_validation_preview_field( $field, \SplObjectStorage $cloned_field_objects ) {
		if ( ! is_object( $field ) ) {
			return $field;
		}

		if ( $cloned_field_objects->contains( $field ) ) {
			return $cloned_field_objects[ $field ];
		}

		$preview_field = clone $field;
		$cloned_field_objects->attach( $field, $preview_field );

		if ( property_exists( $preview_field, 'fields' ) && is_array( $preview_field->fields ) ) {
			foreach ( $preview_field->fields as $key => $child_field ) {
				$preview_field->fields[ $key ] = self::_clone_validation_preview_field( $child_field, $cloned_field_objects );
			}
		}

		return $preview_field;
	}

	/**
	 * Runs `$renderer` in a temporary GF postback-with-validation-errors context.
	 *
	 * This mirrors the state shape GF expects (`$_POST` submit markers plus `GFFormDisplay::$submission[$form_id]`)
	 * so `gravity_form()` emits real validation copy. All mutated globals/statics are restored in `finally`.
	 *
	 * @since ??
	 *
	 * @param int      $form_id  Gravity Forms form id.
	 * @param array    $form     Form object/array used by GF validation.
	 * @param callable $renderer Callback that renders the form HTML.
	 *
	 * @return string
	 */
	private static function _render_with_real_validation_preview_context( int $form_id, array $form, callable $renderer ): string {
		if ( ! self::_ensure_gf_form_display_loaded() || ! method_exists( '\GFFormDisplay', 'validate' ) ) {
			$rendered = $renderer();
			return is_string( $rendered ) ? $rendered : '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Internal preview-state snapshot; no user action is processed.
		$post_snapshot = isset( $_POST ) && is_array( $_POST ) ? $_POST : [];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Internal preview-state snapshot; no user action is processed.
		$files_snapshot                   = isset( $_FILES ) && is_array( $_FILES ) ? $_FILES : [];
		$submission_snapshot              = \GFFormDisplay::$submission ?? [];
		$has_submission_initiator         = property_exists( '\\GFFormDisplay', 'submission_initiated_by' );
		$submission_initiated_by_snapshot = $has_submission_initiator
			? \GFFormDisplay::$submission_initiated_by
			: null;
		$failed_validation_page           = 1;
		$preview_form                     = self::_clone_form_for_validation_preview( $form );

		try {
			// Validation preview must never consume a live front-end/REST request payload or uploads.
			$_POST  = [];
			$_FILES = [];

			$_POST[ 'is_submit_' . $form_id ]                = '1';
			$_POST['gform_submit']                           = (string) $form_id;
			$_POST[ 'gform_target_page_number_' . $form_id ] = 0;
			$_POST[ 'gform_source_page_number_' . $form_id ] = 1;
			$_POST['gform_field_values']                     = [];

			if ( method_exists( '\GFFormDisplay', 'get_state' ) ) {
				$_POST[ 'state_' . $form_id ] = \GFFormDisplay::get_state( $preview_form, [] );
			}

			if ( $has_submission_initiator && defined( '\\GFFormDisplay::SUBMISSION_INITIATED_BY_API_VALIDATION' ) ) {
				\GFFormDisplay::$submission_initiated_by = \GFFormDisplay::SUBMISSION_INITIATED_BY_API_VALIDATION;
			}
			\GFFormDisplay::validate( $preview_form, [], 1, $failed_validation_page );

			\GFFormDisplay::$submission[ $form_id ] = [
				'is_valid'             => false,
				'form'                 => $preview_form,
				'lead'                 => [],
				'confirmation_message' => '',
				'page_number'          => $failed_validation_page,
				'source_page_number'   => 1,
			];

			self::_flush_form_display_cache( $form_id );
			$rendered = $renderer();
			return is_string( $rendered ) ? $rendered : '';
		} finally {
			self::_flush_form_display_cache( $form_id );
			$_POST                                   = $post_snapshot;
			$_FILES                                  = $files_snapshot;
			\GFFormDisplay::$submission              = is_array( $submission_snapshot ) ? $submission_snapshot : [];

			if ( $has_submission_initiator ) {
				\GFFormDisplay::$submission_initiated_by = $submission_initiated_by_snapshot;
			}
		}
	}

	/**
	 * Gets the explicit Gravity Forms preview result.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $args Arguments: `formId` (string|int), optional `context`
	 *                                      (`visual_builder_preview` strips inline handlers that require GF JS),
	 *                                      optional `useAjax` (`'on'` enables Gravity Forms iframe-based Ajax),
	 *                                      optional `validationPreview` (`'on'` renders VB preview in GF invalid-submission mode).
	 *
	 * @return array{status: 'ready'|'unavailable', html: string, errorCode?: string}
	 */
	public static function get_form_preview_response( array $args ): array {
		$form_id = absint( $args['formId'] ?? 0 );
		$context = isset( $args['context'] ) ? (string) $args['context'] : '';

		/*
		 * Single source of truth for both `gravity_form_enqueue_scripts()` and `gravity_form()`; Gravity Forms
		 * requires the same `$ajax` value across both calls so add-ons (Stripe, reCAPTCHA, file uploads, etc.)
		 * enqueue the matching script set.
		 */
		$use_ajax = 'on' === ( $args['useAjax'] ?? 'off' );

		if ( $form_id < 1 ) {
			return self::_unavailable_form_preview_response( 'invalid_form' );
		}

		/*
		 * GravityFormsModule::load() guards normal module registration when the plugin is absent.
		 * Keep this service boundary defensively safe for direct callers and tests: form markup still
		 * requires Gravity Forms runtime APIs.
		 */
		if ( ! class_exists( '\GFAPI' ) || ! function_exists( 'gravity_form' ) ) {
			return self::_unavailable_form_preview_response( 'plugin_unavailable' );
		}

		$form = self::_get_renderable_gravity_form( $form_id );

		if ( null === $form ) {
			return self::_unavailable_form_preview_response( 'form_unavailable' );
		}

		$post_id                           = isset( $args['postId'] ) ? absint( $args['postId'] ) : 0;
		$should_append_divi_button_classes = self::_should_append_divi_button_classes( $post_id );
		$style_state_before = 'visual_builder_preview' === $context
			? self::_get_wp_styles_preview_state()
			: [];

		self::_enqueue_form_assets( $form_id, $use_ajax, $form );

		if ( 'visual_builder_preview' === $context ) {
			self::_enqueue_divi_dynamic_asset_style_for_visual_builder_preview( $post_id );
		}

		$render_form_markup = static function () use ( $form_id, $use_ajax ) {
			/*
			 * Fourth `gravity_form()` argument is `$display_inactive` (GF does not render inactive forms when false).
			 * Sixth argument is `$ajax`: when `true`, GF emits a `target='gform_ajax_frame_{id}'`, an iframe with the same
			 * id, and a hidden `gform_ajax` input so submissions are handled without a full page reload.
			 * Eighth argument `$echo = false` returns markup as a string (Gravity Forms documented API). One call only.
			 */
			return gravity_form(
				$form_id,
				true,
				true,
				false,
				null,
				$use_ajax,
				0,
				false,
				null,
				null
			);
		};

		$html = self::_should_render_real_validation_preview( $context, $args )
			? self::_render_with_real_validation_preview_context( $form_id, $form, $render_form_markup )
			: $render_form_markup();

		if ( ! is_string( $html ) ) {
			$html = '';
		} else {
			$html = trim( $html );
		}

		// Queued preview styles are not form markup and must not turn an empty GF render into `ready`.
		if ( '' === $html ) {
			return self::_unavailable_form_preview_response( 'render_failed' );
		}

		$new_style_handles = 'visual_builder_preview' === $context
			? array_values( array_diff( self::_get_wp_styles_queue_handles(), $style_state_before['queue'] ?? [] ) )
			: [];
		$late_inline_styles = 'visual_builder_preview' === $context
			? self::_get_wp_styles_inline_style_deltas( $style_state_before['inline'] ?? [] )
			: [];

		$html = self::_add_divi_button_class_to_gf_submit( $html, $should_append_divi_button_classes );

		if ( $use_ajax && $should_append_divi_button_classes ) {
			/*
			 * GF's iframe-Ajax postback is a new request, so the outer control transform above cannot
			 * reach the controls that GF renders after paging. Carry a signed, form-scoped marker in
			 * the submission; register_ajax_button_class_filters() consumes it on that later request.
			 */
			$html = self::_append_divi_button_class_ajax_context_to_form( $html, $form );
		}

		if ( 'visual_builder_preview' === $context ) {
			$html = self::_strip_visual_builder_preview_inline_handlers( $html );
			$html = self::_ensure_gform_wrapper_visible_for_visual_builder_preview( $html );
			$html = self::_prepend_visual_builder_preview_style_markup( $new_style_handles, $html, $late_inline_styles );
		}

		/*
		 * Intentionally unescaped: Gravity Forms renders this markup via its own WordPress-integrated pipeline.
		 * Divi only applies preview-safe transforms here (for example, stripping unsupported VB inline handlers).
		 */
		$html = et_core_intentionally_unescaped( $html, 'html' );

		if ( '' === $html ) {
			return self::_unavailable_form_preview_response( 'render_failed' );
		}

		return [
			'status' => 'ready',
			'html'   => $html,
		];
	}

	/**
	 * Registers form-scoped GF filters for a Divi form's iframe-Ajax continuation request.
	 *
	 * This marker is a request-context discriminator, not submission authorization. Signing it avoids
	 * activating Divi's transform for ordinary GF submissions or a coincidental field with the same
	 * name; Gravity Forms still owns submission validation. The filters use GF's public, per-form hooks
	 * and run after add-on filters, matching the initial render's final post-processing boundary.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function register_ajax_button_class_filters(): void {
		// Rendering-only context; no state is changed and GF performs its own submission validation.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$posted_form_id = $_POST['gform_submit'] ?? '';
		$posted_marker  = $_POST[ self::DIVI_BUTTON_CLASS_AJAX_CONTEXT_FIELD ] ?? '';
		$form_id        = is_scalar( $posted_form_id ) ? absint( wp_unslash( (string) $posted_form_id ) ) : 0;
		$marker         = is_scalar( $posted_marker ) ? sanitize_text_field( wp_unslash( (string) $posted_marker ) ) : '';
		$is_ajax        = isset( $_POST['gform_ajax'] );
		$is_submit      = $form_id > 0 ? ( $_POST[ 'is_submit_' . $form_id ] ?? '' ) : '';
		$is_form        = is_scalar( $is_submit ) && '1' === wp_unslash( (string) $is_submit );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $is_ajax || ! $is_form || ! hash_equals( self::_get_divi_button_class_ajax_context( $form_id ), $marker ) ) {
			return;
		}

		foreach ( [ 'gform_next_button', 'gform_previous_button', 'gform_submit_button' ] as $hook_prefix ) {
			add_filter( $hook_prefix . '_' . $form_id, [ self::class, 'filter_ajax_button_markup' ], PHP_INT_MAX, 2 );
		}

		add_filter(
			'gform_get_form_filter_' . $form_id,
			[ self::class, 'filter_ajax_form_markup' ],
			PHP_INT_MAX,
			2
		);
	}

	/**
	 * Applies the existing Divi control transform through GF's per-button Ajax hooks.
	 *
	 * @since ??
	 *
	 * @param mixed $button_markup Gravity Forms button markup from the filter chain.
	 * @param mixed $form          Gravity Forms form data from the filter chain.
	 *
	 * @return string
	 */
	public static function filter_ajax_button_markup( $button_markup, $form ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by the GF filter contract.
		if ( ! is_string( $button_markup ) ) {
			return '';
		}

		if ( ! is_array( $form ) ) {
			return $button_markup;
		}

		return self::_add_divi_button_class_to_gf_submit( $button_markup );
	}

	/**
	 * Re-appends the signed Divi context marker inside a GF iframe-Ajax continuation form.
	 *
	 * This runs on GF's complete-form filter rather than `gform_form_after_open`: GF's iframe script
	 * replaces the old wrapper with `jQuery.html()`, whose fragment parser can move an input emitted
	 * immediately after the opening form tag outside the form. Inserting beside GF's own footer inputs
	 * keeps the marker submittable across every page.
	 *
	 * @since ??
	 *
	 * @param mixed $markup Existing markup after the opening form tag.
	 * @param mixed $form   Gravity Forms form data.
	 *
	 * @return string
	 */
	public static function filter_ajax_form_markup( $markup, $form ): string {
		if ( ! is_string( $markup ) ) {
			return '';
		}

		if ( ! is_array( $form ) ) {
			return $markup;
		}

		return self::_append_divi_button_class_ajax_context_to_form( $markup, $form );
	}

	/**
	 * Builds the hidden field carrying the signed Divi Ajax context.
	 *
	 * @since ??
	 *
	 * @param array $form Gravity Forms form data.
	 *
	 * @return string
	 */
	private static function _get_divi_button_class_ajax_context_markup( array $form ): string {
		$form_id = absint( $form['id'] ?? 0 );

		if ( $form_id < 1 ) {
			return '';
		}

		return sprintf(
			'<input type="hidden" name="%1$s" value="%2$s" />',
			esc_attr( self::DIVI_BUTTON_CLASS_AJAX_CONTEXT_FIELD ),
			esc_attr( self::_get_divi_button_class_ajax_context( $form_id ) )
		);
	}

	/**
	 * Builds the request-stable signature used to scope GF Ajax button filters to Divi renders.
	 *
	 * @since ??
	 *
	 * @param int $form_id Gravity Forms form ID.
	 *
	 * @return string
	 */
	private static function _get_divi_button_class_ajax_context( int $form_id ): string {
		return wp_hash( 'divi-gravity-forms-button-classes:' . $form_id );
	}

	/**
	 * Inserts the signed Ajax context marker into the one form returned by `gravity_form()`.
	 *
	 * The initial Divi render and GF's continuation filter both own a complete form string, so inserting
	 * immediately before its closing tag keeps the marker beside GF's own submittable footer inputs.
	 *
	 * @since ??
	 *
	 * @param string $html Gravity Forms HTML.
	 * @param array  $form Gravity Forms form data.
	 *
	 * @return string
	 */
	private static function _append_divi_button_class_ajax_context_to_form( string $html, array $form ): string {
		if ( str_contains( $html, 'name="' . self::DIVI_BUTTON_CLASS_AJAX_CONTEXT_FIELD . '"' ) ) {
			return $html;
		}

		$closing_form_offset = stripos( $html, '</form>' );

		if ( false === $closing_form_offset ) {
			return $html;
		}

		$marker = self::_get_divi_button_class_ajax_context_markup( $form );

		return '' === $marker ? $html : substr_replace( $html, $marker, $closing_form_offset, 0 );
	}

	/**
	 * Renders Gravity Forms markup for preview or frontend.
	 *
	 * Unavailable forms intentionally produce no normal module/frontend markup. The Visual Builder
	 * REST endpoint consumes {@see self::get_form_preview_response()} directly so it can expose the
	 * explicit status without leaking builder-only error copy into rendered content.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $args Arguments for {@see self::get_form_preview_response()}.
	 *
	 * @return string
	 */
	public static function render_form_preview( array $args ): string {
		$response = self::get_form_preview_response( $args );

		return $response['html'];
	}

	/**
	 * Builds a consistent unavailable preview response.
	 *
	 * @since ??
	 *
	 * @param string $error_code Stable machine-readable reason.
	 *
	 * @return array{status: 'unavailable', html: '', errorCode: string}
	 */
	private static function _unavailable_form_preview_response( string $error_code ): array {
		return [
			'status'    => 'unavailable',
			'html'      => '',
			'errorCode' => $error_code,
		];
	}

	/**
	 * Determines whether Divi button classes should be appended to Gravity Forms controls.
	 *
	 * Keep GF defaults on CPT/TB contexts where global Divi `et_pb_button` rules have stronger
	 * selector chains and can unintentionally override Orbital defaults.
	 *
	 * Accepts an optional `$post_id` so REST callers can resolve CPT context even when WordPress
	 * has no queried object (the case for Visual Builder preview REST requests, where
	 * {@see Conditions::is_custom_post_type()} otherwise returns `false` and Divi classes would
	 * leak into the CPT preview HTML).
	 *
	 * @since ??
	 *
	 * @param int $post_id Optional post ID for explicit CPT detection (Visual Builder REST path).
	 *
	 * @return bool
	 */
	private static function _should_append_divi_button_classes( int $post_id = 0 ): bool {
		$is_custom_post_type = false;
		$is_tb_enabled       = false;

		if ( $post_id > 0 ) {
			$post_type = get_post_type( $post_id );

			if ( is_string( $post_type ) && '' !== $post_type ) {
				$is_custom_post_type = (bool) et_builder_is_post_type_custom( $post_type );
			}
		}

		if ( ! $is_custom_post_type ) {
			try {
				$is_custom_post_type = Conditions::is_custom_post_type();
			} catch ( \Throwable $error ) {
				$is_custom_post_type = false;
			}
		}

		try {
			$is_tb_enabled = Conditions::is_tb_enabled();
		} catch ( \Throwable $error ) {
			$is_tb_enabled = false;
		}

		return ! ( $is_custom_post_type || $is_tb_enabled );
	}

	/**
	 * Determines whether dynamic-assets CSS should use the CPT-wrapped variant.
	 *
	 * Mirrors the front-end cache suffix behavior (`_cpt`) used by dynamic assets.
	 *
	 * @since ??
	 *
	 * @param int $post_id Optional post ID for explicit CPT detection in VB REST preview context.
	 *
	 * @return bool
	 */
	private static function _should_use_cpt_wrapped_dynamic_asset( int $post_id = 0 ): bool {
		if ( et_is_builder_plugin_active() ) {
			return false;
		}

		if ( 0 < $post_id ) {
			return et_builder_post_is_of_custom_post_type( $post_id ) && et_pb_is_pagebuilder_used( $post_id );
		}

		return et_builder_should_wrap_styles();
	}

	/**
	 * Enqueues Divi Gravity Forms dynamic-assets CSS for Visual Builder preview HTML injection.
	 *
	 * The Visual Builder preview request does not run the normal dynamic-assets enqueue flow, so module-level
	 * static guardrail CSS must be explicitly enqueued here to keep VB and FE rendering parity.
	 *
	 * @since ??
	 *
	 * @param int $post_id Optional post ID for CPT suffix resolution in VB REST preview context.
	 *
	 * @return void
	 */
	private static function _enqueue_divi_dynamic_asset_style_for_visual_builder_preview( int $post_id = 0 ): void {
		$suffix               = self::_should_use_cpt_wrapped_dynamic_asset( $post_id ) ? '_cpt' : '';
		$dynamic_assets_path  = trailingslashit( DynamicAssetsUtils::get_dynamic_assets_path() );
		$dynamic_assets_url   = trailingslashit( DynamicAssetsUtils::get_dynamic_assets_path( true ) );
		$assets_list          = DynamicAssetsUtils::get_assets_list(
			[
				'prefix'           => $dynamic_assets_path,
				'suffix'           => $suffix,
				'specialty_suffix' => '',
			]
		);
		$gravity_forms_assets = $assets_list['divi/gravity-forms']['css'] ?? null;
		$css_file_path        = is_array( $gravity_forms_assets ) ? ( $gravity_forms_assets[0] ?? '' ) : $gravity_forms_assets;

		if ( ! is_string( $css_file_path ) || '' === $css_file_path ) {
			return;
		}

		if ( ! file_exists( $css_file_path ) ) {
			return;
		}

		if ( ! str_starts_with( $css_file_path, $dynamic_assets_path ) ) {
			return;
		}

		$relative_css = ltrim( substr( $css_file_path, strlen( $dynamic_assets_path ) ), '/' );

		$handle = "et_builder_gravity_forms_dynamic_asset{$suffix}";

		if ( ! wp_style_is( $handle, 'registered' ) ) {
			wp_register_style(
				$handle,
				$dynamic_assets_url . $relative_css,
				[],
				defined( 'ET_BUILDER_PRODUCT_VERSION' ) ? \ET_BUILDER_PRODUCT_VERSION : null
			);
		}

		if ( wp_style_is( $handle, 'registered' ) && ! wp_style_is( $handle, 'enqueued' ) ) {
			wp_enqueue_style( $handle );
		}
	}

	/**
	 * Drops inline event handlers that call Gravity Forms globals not present in the Visual Builder document.
	 *
	 * The VB preview injects form HTML only; GF frontend scripts are not executed there, so handlers such as
	 * `gformToggleRadioOther` throw ReferenceError when used (e.g. radio fields with an "Other" choice).
	 *
	 * The same applies to `gform.submission.handleButtonClick(this)` on submit and paging controls: the
	 * front end needs it (see `_add_divi_button_class_to_gf_submit()`), but in the preview the `gform` global
	 * does not exist, so it is dropped here and the preview stays inert.
	 *
	 * @since ??
	 *
	 * @param string $html Form HTML.
	 *
	 * @return string
	 */
	private static function _strip_visual_builder_preview_inline_handlers( string $html ): string {
		/*
		 * Matches `onchange="…gformToggleRadioOther…"` with either single or double quotes;
		 * `(["\'])` captures the opening quote and `\1` requires the same closing quote so
		 * `value="..."` and `value='...'` are both handled by one pass without needing two.
		 * regex101: https://regex101.com/r/93MLs4/1 (PHP flavor).
		 */
		$patterns = [
			'/\s+onchange\s*=\s*(["\'])[^"\']*gformToggleRadioOther[^"\']*\1/i',

			/*
			 * Same shape as the pattern above, applied to GF's submission handler on submit controls.
			 * regex101: https://regex101.com/r/pE9GDM/1 (PHP/PCRE2 flavor). The pattern is also exercised by
			 * `GravityFormsServiceTest::test_strip_handlers_removes_gf_submission_handler_for_preview`
			 * plus its non-GF `onclick` twin.
			 */
			'/\s+onclick\s*=\s*(["\'])[^"\']*gform\.submission\.handleButtonClick[^"\']*\1/i',
		];

		foreach ( $patterns as $pattern ) {
			$replaced = preg_replace( $pattern, '', $html );
			$html     = is_string( $replaced ) ? $replaced : $html;
		}

		return $html;
	}

	/**
	 * Makes the main `gform_wrapper_*` div visible for Visual Builder preview markup.
	 *
	 * Orbital / theme markup can output `style="display:none"` on the wrapper; Gravity Forms then
	 * shows it via JS after conditional logic and `gform_post_render`. VB preview injects HTML only,
	 * so that script does not run there and the form can stay hidden unless the wrapper-level hide is
	 * removed for preview context.
	 *
	 * @since ??
	 *
	 * @param string $html Form HTML.
	 *
	 * @return string
	 */
	private static function _ensure_gform_wrapper_visible_for_visual_builder_preview( string $html ): string {
		/*
		 * Matches the OPEN TAG of the numbered wrapper div only (`id="gform_wrapper_<n>"`, either
		 * quote style via the `(["\'])…\1` pair) so the rewrites below never touch other nodes.
		 * regex101: https://regex101.com/r/n93fK8/1 (PHP flavor).
		 */
		$out = preg_replace_callback(
			'/<div\b[^>]*\bid\s*=\s*(["\'])gform_wrapper_\d+\1[^>]*>/i',
			static function ( array $matches ): string {
				$tag = $matches[0];

				/*
				 * Fast path: a style attr whose entire value is `display:none` (whitespace-tolerant)
				 * is dropped attribute-and-all.
				 * regex101: https://regex101.com/r/uwl5lT/1 (PHP flavor).
				 */
				$without_simple_hide = preg_replace( '/\sstyle\s*=\s*(["\'])\s*display\s*:\s*none\s*\1/i', '', $tag );

				if ( is_string( $without_simple_hide ) && $without_simple_hide !== $tag ) {
					return $without_simple_hide;
				}

				/*
				 * Compound path: capture the whole style attr value (either quote style, `s` flag so
				 * values with newlines still match), then remove ONLY the `display:none` declaration
				 * (optionally `!important`, optional trailing `;`) from within it.
				 * regex101 (attr): https://regex101.com/r/k2FOnD/1 (PHP flavor).
				 * regex101 (decl): https://regex101.com/r/VCuygn/1 (PHP flavor).
				 */
				$without_compound_hide = preg_replace_callback(
					'/\sstyle\s*=\s*(["\'])(.*?)\1/is',
					static function ( array $style_match ): string {
						$quote = $style_match[1];
						$value = (string) ( $style_match[2] ?? '' );
						$value = (string) preg_replace( '/\s*display\s*:\s*none(?:\s*!important)?\s*;?/i', '', $value );
						$value = trim( $value, " \t\n\r\0\x0B;" );

						if ( '' === $value ) {
							return '';
						}

						return ' style=' . $quote . $value . $quote;
					},
					$tag,
					1
				);

				return is_string( $without_compound_hide ) ? $without_compound_hide : $tag;
			},
			$html
		);

		return is_string( $out ) ? $out : $html;
	}

	/**
	 * Returns a snapshot of handles currently queued on {@see \WP_Styles}.
	 *
	 * @since ??
	 *
	 * @return list<string>
	 */
	private static function _get_wp_styles_queue_handles(): array {
		global $wp_styles;

		if ( ! ( $wp_styles instanceof \WP_Styles ) ) {
			return [];
		}

		return array_values( array_unique( array_map( 'strval', $wp_styles->queue ?? [] ) ) );
	}

	/**
	 * Snapshots queue membership and registered inline CSS before GF preview rendering.
	 *
	 * Queue membership alone is insufficient: Gravity Forms and add-ons may append inline CSS to a
	 * handle that was queued (and possibly printed) earlier in the request. The queue does not change
	 * in that case, but the appended CSS still belongs to this preview response.
	 *
	 * @since ??
	 *
	 * @return array{queue: list<string>, inline: array<string, list<string>>}
	 */
	private static function _get_wp_styles_preview_state(): array {
		global $wp_styles;

		$inline_styles = [];

		if ( $wp_styles instanceof \WP_Styles ) {
			foreach ( $wp_styles->registered as $handle => $dependency ) {
				$after = $dependency->extra['after'] ?? [];

				if ( is_array( $after ) ) {
					$inline_styles[ (string) $handle ] = array_values( array_map( 'strval', $after ) );
				}
			}
		}

		return [
			'queue'  => self::_get_wp_styles_queue_handles(),
			'inline' => $inline_styles,
		];
	}

	/**
	 * Returns inline CSS appended since the supplied preview-state snapshot.
	 *
	 * `WP_Styles::add_inline_style()` appends to `extra['after']`. If a third-party callback replaces
	 * that list instead, transport the current list rather than silently dropping the replacement.
	 *
	 * @since ??
	 *
	 * @param array<string, list<string>> $before Inline styles before GF preview rendering.
	 *
	 * @return array<string, list<string>> Newly appended/replaced inline styles by handle.
	 */
	private static function _get_wp_styles_inline_style_deltas( array $before ): array {
		global $wp_styles;

		if ( ! ( $wp_styles instanceof \WP_Styles ) ) {
			return [];
		}

		$deltas = [];

		foreach ( $wp_styles->registered as $handle => $dependency ) {
			$after = $dependency->extra['after'] ?? [];

			if ( ! is_array( $after ) || [] === $after ) {
				continue;
			}

			$current  = array_values( array_map( 'strval', $after ) );
			$previous = $before[ (string) $handle ] ?? [];
			$is_append = count( $previous ) <= count( $current )
				&& $previous === array_slice( $current, 0, count( $previous ) );
			$delta = $is_append ? array_slice( $current, count( $previous ) ) : $current;

			if ( [] !== $delta ) {
				$deltas[ (string) $handle ] = $delta;
			}
		}

		return $deltas;
	}

	/**
	 * Returns registered handles reachable from the supplied stylesheet roots, including the roots.
	 *
	 * This deliberately does not use `WP_Dependencies::all_deps()`: that method skips handles already
	 * present in `done`, while transport classification still needs to know that a done handle belongs
	 * to a newly requested dependency graph so its late inline delta can be emitted in the right place.
	 *
	 * @since ??
	 *
	 * @param list<string> $handles Stylesheet graph roots.
	 * @param \WP_Styles   $styles  Registry that owns the dependencies.
	 *
	 * @return list<string>
	 */
	private static function _get_wp_style_dependency_closure( array $handles, \WP_Styles $styles ): array {
		$closure = [];
		$pending = array_values( array_map( 'strval', $handles ) );

		while ( [] !== $pending ) {
			$handle = array_shift( $pending );

			if ( isset( $closure[ $handle ] ) || ! isset( $styles->registered[ $handle ] ) ) {
				continue;
			}

			$closure[ $handle ] = true;
			$dependency         = $styles->registered[ $handle ];

			if ( is_object( $dependency ) && is_array( $dependency->deps ?? null ) ) {
				foreach ( $dependency->deps as $dependency_handle ) {
					$pending[] = (string) $dependency_handle;
				}
			}
		}

		return array_keys( $closure );
	}

	/**
	 * Prepends `<link>` / inline style markup for GF styles enqueued during preview rendering.
	 *
	 * The Visual Builder injects preview HTML into the canvas without a normal `wp_print_styles()` pass for
	 * that REST sub-request, so theme/orbital styles registered by `gravity_form_enqueue_scripts()` would not
	 * otherwise apply. {@see \WP_Styles::do_items()} resolves dependencies and generates the same tags core would print in the head.
	 *
	 * @since ??
	 *
	 * @param array<int, string>          $handles            Style handles newly queued since preview rendering started.
	 * @param string                      $html               Form HTML.
	 * @param array<string, list<string>> $late_inline_styles Inline CSS appended to reused handles.
	 *
	 * @return string
	 */
	private static function _prepend_visual_builder_preview_style_markup( array $handles, string $html, array $late_inline_styles = [] ): string {
		if ( [] === $handles && [] === $late_inline_styles ) {
			return $html;
		}

		global $wp_styles;

		if ( ! ( $wp_styles instanceof \WP_Styles ) ) {
			return $html;
		}

		$handles = array_values(
			array_filter(
				$handles,
				static function ( string $handle ) use ( $wp_styles ): bool {
					return isset( $wp_styles->registered[ $handle ] );
				}
			)
		);
		$new_graph_handles = array_fill_keys(
			self::_get_wp_style_dependency_closure( $handles, $wp_styles ),
			true
		);
		$queued_handles = array_fill_keys( self::_get_wp_styles_queue_handles(), true );
		$done_handles   = array_fill_keys( array_map( 'strval', $wp_styles->done ?? [] ), true );
		$late_inline_styles = array_filter(
			$late_inline_styles,
			static function ( array $styles, string $handle ) use ( $wp_styles, $new_graph_handles, $queued_handles, $done_handles ): bool {
				if ( [] === $styles || ! isset( $wp_styles->registered[ $handle ] ) ) {
					return false;
				}

				$is_done          = isset( $done_handles[ $handle ] );
				$is_in_new_graph  = isset( $new_graph_handles[ $handle ] );
				$is_existing_asset = isset( $queued_handles[ $handle ] ) || $is_done;

				/*
				 * A not-yet-done member of the new graph must retain its real source/dependencies; core will
				 * print its current inline data in canonical order. Reused queued/done handles need only the
				 * delta. A registered-but-never-queued handle is dormant and must remain unprinted.
				 */
				return $is_existing_asset && ( ! $is_in_new_graph || $is_done );
			},
			ARRAY_FILTER_USE_BOTH
		);

		if ( [] === $handles && [] === $late_inline_styles ) {
			return $html;
		}

		/*
		 * Resolve dependencies on an isolated registry. Core's `do_items()` iterates the entire existing
		 * `to_do` list, not only the handles passed to it; calling it on the global instance can therefore
		 * print/consume unrelated styles. A deep-enough clone keeps dependency objects mutable locally and
		 * preserves the global `to_do` / `done` bookkeeping exactly.
		 */
		$transport_styles = clone $wp_styles;
		$transport_styles->registered = array_map(
			static function ( $dependency ) {
				return is_object( $dependency ) ? clone $dependency : $dependency;
			},
			$wp_styles->registered
		);
		$transport_styles->to_do = [];

		/*
		 * Reused handles need only the CSS appended during this render. Their link (and any older inline
		 * CSS) already belongs to an earlier owner-document installation, so turn the cloned dependency
		 * into a source-less alias and force only that delta through WP_Styles' canonical formatter.
		 */
		foreach ( $late_inline_styles as $handle => $styles ) {
			$dependency                 = $transport_styles->registered[ $handle ];
			$dependency->src            = false;
			$dependency->deps           = [];
			$dependency->extra          = [ 'after' => $styles ];
			$transport_styles->done     = array_values( array_diff( $transport_styles->done, [ $handle ] ) );
			$handles[]                  = $handle;
		}

		$handles = array_values( array_unique( $handles ) );

		ob_start();
		$transport_styles->do_items( $handles, false );

		$markup = ob_get_clean();

		if ( ! is_string( $markup ) || '' === $markup ) {
			return $html;
		}

		return $markup . $html;
	}

	/**
	 * Parses real attributes from one bounded `<input>` or `<button>` opening tag.
	 *
	 * The parser advances through the tag once and consumes quoted values as indivisible units. Text such
	 * as `type=button` or `class='gform_button'` inside an ARIA value therefore cannot become an attribute.
	 * Source offsets are retained so callers can replace the real class attribute without normalizing or
	 * rebuilding unrelated markup. Malformed input fails closed at the first token it cannot parse.
	 *
	 * @since ??
	 *
	 * @param string $tag Opening `<input>` or `<button>` tag.
	 *
	 * @return list<array{name: string, value: string, start: int, length: int, quote: string, value_start: int, value_length: int}> Parsed attribute occurrences.
	 */
	private static function _parse_opening_tag_attribute_occurrences( string $tag ): array {
		$tag_trim = trim( $tag );
		$tag_length = strlen( $tag_trim );

		if ( '<' !== ( $tag_trim[0] ?? '' ) ) {
			return [];
		}

		$cursor = 1;

		while ( $cursor < $tag_length && ctype_space( $tag_trim[ $cursor ] ) ) {
			++$cursor;
		}

		$tag_name_start = $cursor;

		while ( $cursor < $tag_length && ctype_alpha( $tag_trim[ $cursor ] ) ) {
			++$cursor;
		}

		$tag_name = strtolower( substr( $tag_trim, $tag_name_start, $cursor - $tag_name_start ) );

		if (
			! in_array( $tag_name, [ 'input', 'button' ], true )
			|| ! ( ctype_space( $tag_trim[ $cursor ] ?? '' ) || in_array( $tag_trim[ $cursor ] ?? '', [ '/', '>' ], true ) )
		) {
			return [];
		}

		$attributes = [];
		$tag_closed = false;

		while ( $cursor < $tag_length ) {
			while ( $cursor < $tag_length && ctype_space( $tag_trim[ $cursor ] ) ) {
				++$cursor;
			}

			if (
				$cursor < $tag_length
				&& (
					'>' === $tag_trim[ $cursor ]
					|| ( '/' === $tag_trim[ $cursor ] && '>' === ( $tag_trim[ $cursor + 1 ] ?? '' ) )
				)
			) {
				$tag_closed = true;
				break;
			}

			if (
				$cursor >= $tag_length
				|| ( '/' === $tag_trim[ $cursor ] && '>' === ( $tag_trim[ $cursor + 1 ] ?? '' ) )
			) {
				break;
			}

			$attribute_start = $cursor;

			/*
			 * HTML attribute names may use framework prefixes such as Alpine's `@click`, Vue's
			 * `:class`, or bracketed bindings. Parse those names so one extension attribute cannot
			 * hide later real `type`, `class`, `id`, or `name` signals from control classification.
			 * `wp_kses()` still decides which attributes survive; this parser only records bounded
			 * source spans. Whitespace and HTML's attribute-name delimiters remain hard failures.
			 */
			if (
				ctype_space( $tag_trim[ $cursor ] )
				|| str_contains( "\"'<>/=", $tag_trim[ $cursor ] )
			) {
				return [];
			}

			++$cursor;

			while ( $cursor < $tag_length ) {
				$attribute_name_character = $tag_trim[ $cursor ];

				if (
					ctype_space( $attribute_name_character )
					|| str_contains( "\"'<>/=", $attribute_name_character )
				) {
					break;
				}

				++$cursor;
			}

			$attribute_name = strtolower( substr( $tag_trim, $attribute_start, $cursor - $attribute_start ) );
			$attribute_end  = $cursor;
			$value          = '';
			$quote          = '';
			$value_start    = $cursor;
			$value_length   = 0;

			while ( $cursor < $tag_length && ctype_space( $tag_trim[ $cursor ] ) ) {
				++$cursor;
			}

			if ( '=' === ( $tag_trim[ $cursor ] ?? '' ) ) {
				++$cursor;

				while ( $cursor < $tag_length && ctype_space( $tag_trim[ $cursor ] ) ) {
					++$cursor;
				}

				if ( '"' === ( $tag_trim[ $cursor ] ?? '' ) || "'" === ( $tag_trim[ $cursor ] ?? '' ) ) {
					$quote = $tag_trim[ $cursor ];
					++$cursor;

					$value_start            = $cursor;
					$closing_quote_position = strpos( $tag_trim, $quote, $cursor );

					if ( false === $closing_quote_position ) {
						return [];
					}

					$value_length = $closing_quote_position - $value_start;
					$value        = substr( $tag_trim, $value_start, $value_length );
					$cursor       = $closing_quote_position + 1;
				} else {
					$value_start = $cursor;

					while (
						$cursor < $tag_length
						&& ! ctype_space( $tag_trim[ $cursor ] )
						&& '>' !== $tag_trim[ $cursor ]
						&& ! ( '/' === $tag_trim[ $cursor ] && '>' === ( $tag_trim[ $cursor + 1 ] ?? '' ) )
					) {
						++$cursor;
					}

					$value_length = $cursor - $value_start;
					$value        = substr( $tag_trim, $value_start, $value_length );
				}

				$attribute_end = $cursor;
			}

			$attributes[] = [
				'name'         => $attribute_name,
				'value'        => $value,
				'start'        => $attribute_start,
				'length'       => $attribute_end - $attribute_start,
				'quote'        => $quote,
				'value_start'  => $value_start,
				'value_length' => $value_length,
			];
		}

		return $tag_closed ? $attributes : [];
	}

	/**
	 * Parses the first occurrence of each opening-tag attribute for classification and rewriting.
	 *
	 * HTML consumers use the first duplicate attribute. Keep that behavior for existing callers while
	 * `_parse_opening_tag_attribute_occurrences()` retains every source span for lossless pre-KSES encoding.
	 *
	 * @since ??
	 *
	 * @param string $tag Opening `<input>` or `<button>` tag.
	 *
	 * @return array<string, array{value: string, start: int, length: int, quote: string, value_start: int, value_length: int}>
	 */
	private static function _parse_opening_tag_attributes( string $tag ): array {
		$attributes = [];

		foreach ( self::_parse_opening_tag_attribute_occurrences( $tag ) as $attribute ) {
			$name = $attribute['name'];

			if ( array_key_exists( $name, $attributes ) ) {
				continue;
			}

			unset( $attribute['name'] );
			$attributes[ $name ] = $attribute;
		}

		return $attributes;
	}

	/**
	 * Tests whether a value is one of Gravity Forms' numbered control identifiers.
	 *
	 * String predicates keep the exact prefix/numeric-suffix contract readable without adding another
	 * regular expression to the security-sensitive control classifier.
	 *
	 * @since ??
	 *
	 * @param string $value  Candidate identifier.
	 * @param string $prefix Required prefix.
	 * @param string $suffix Optional required suffix after the numeric segment.
	 *
	 * @return bool
	 */
	private static function _matches_numbered_control_identifier( string $value, string $prefix, string $suffix = '' ): bool {
		if ( ! str_starts_with( strtolower( $value ), strtolower( $prefix ) ) ) {
			return false;
		}

		$numeric_segment = substr( $value, strlen( $prefix ) );

		if ( '' !== $suffix ) {
			if ( ! str_ends_with( strtolower( $numeric_segment ), strtolower( $suffix ) ) ) {
				return false;
			}

			$numeric_segment = substr( $numeric_segment, 0, -strlen( $suffix ) );
		}

		return '' !== $numeric_segment && ctype_digit( $numeric_segment );
	}

	/**
	 * Encodes literal angle brackets inside parsed quoted attribute values before KSES processing.
	 *
	 * Existing entities contain no literal angle bracket and are left byte-identical. Replacements run
	 * from the end of the tag so earlier parser offsets stay valid as encoded values grow.
	 *
	 * @since ??
	 *
	 * @param string $tag Opening `<input>` or `<button>` tag.
	 *
	 * @return string Opening tag with literal attribute-value angles encoded.
	 */
	private static function _encode_opening_tag_attribute_angle_brackets( string $tag ): string {
		// Parser offsets are relative to its trimmed input; splice into the same normalized string.
		$tag         = trim( $tag );
		$attributes  = self::_parse_opening_tag_attribute_occurrences( $tag );
		$replacements = [];

		foreach ( $attributes as $attribute ) {
			if ( '' === $attribute['quote'] ) {
				continue;
			}

			$encoded_value = str_replace( [ '<', '>' ], [ '&lt;', '&gt;' ], $attribute['value'] );

			if ( $encoded_value === $attribute['value'] ) {
				continue;
			}

			$replacements[] = [
				'start'  => $attribute['value_start'],
				'length' => $attribute['value_length'],
				'value'  => $encoded_value,
			];
		}

		usort(
			$replacements,
			static fn( array $left, array $right ): int => $right['start'] <=> $left['start']
		);

		foreach ( $replacements as $replacement ) {
			$tag = substr_replace( $tag, $replacement['value'], $replacement['start'], $replacement['length'] );
		}

		return $tag;
	}

	/**
	 * Restores sanitized duplicate attributes that `wp_kses()` otherwise drops by name.
	 *
	 * Each duplicate is sanitized in isolation with the same allowlist before its bounded attribute
	 * span is reinserted. This preserves GF/filter markup without bypassing KSES value checks or
	 * restoring attributes that the active control allowlist rejects.
	 *
	 * @since ??
	 *
	 * @param string                           $tag_name      Lowercase control tag name.
	 * @param string                           $source_tag    Encoded pre-KSES opening tag.
	 * @param string                           $sanitized_tag Main KSES result.
	 * @param array<string, array<string, bool>> $allowlist     KSES allowlist for this control.
	 *
	 * @return string Sanitized tag with allowed duplicate occurrences restored.
	 */
	private static function _restore_sanitized_duplicate_attributes( string $tag_name, string $source_tag, string $sanitized_tag, array $allowlist ): string {
		$seen       = [];
		$duplicates = [];

		foreach ( self::_parse_opening_tag_attribute_occurrences( $source_tag ) as $attribute ) {
			$name = $attribute['name'];

			if ( ! isset( $seen[ $name ] ) ) {
				$seen[ $name ] = true;
				continue;
			}

			$attribute_source = substr( $source_tag, $attribute['start'], $attribute['length'] );
			$single_tag       = '<' . $tag_name . ' ' . $attribute_source . '>';
			$sanitized_single = wp_kses( $single_tag, $allowlist );
			$sanitized_attrs  = self::_parse_opening_tag_attribute_occurrences( $sanitized_single );

			if ( [] === $sanitized_attrs ) {
				continue;
			}

			$sanitized_attribute = $sanitized_attrs[0];
			$duplicates[]        = substr(
				$sanitized_single,
				$sanitized_attribute['start'],
				$sanitized_attribute['length']
			);
		}

		if ( [] === $duplicates ) {
			return $sanitized_tag;
		}

		$insertion_offset = strrpos( $sanitized_tag, '>' );

		if ( false === $insertion_offset ) {
			return $sanitized_tag;
		}

		if ( '/' === ( $sanitized_tag[ $insertion_offset - 1 ] ?? '' ) ) {
			--$insertion_offset;
		}

		return substr_replace( $sanitized_tag, ' ' . implode( ' ', $duplicates ), $insertion_offset, 0 );
	}

	/**
	 * Merges Divi Builder button surface classes into an opening `<input` / `<button` tag.
	 *
	 * When `$classes` is set (Gravity Forms submit path), it is the parsed `class` list after GF-specific
	 * normalization (for example ensuring `gform_button`). When omitted (paging path), classes are read
	 * from the tag itself.
	 *
	 * @since ??
	 *
	 * @param string                  $tag                               Opening `<input` or `<button` tag.
	 * @param array<int, string>|null $classes                           Optional pre-parsed class tokens.
	 * @param bool                    $should_append_divi_button_classes Whether to append the Divi button classes.
	 *
	 * @return string Tag with `et_pb_button`, `et_pb_bg_layout_light`, and `et_pb_module` on the `class` attribute.
	 */
	private static function _merge_et_pb_button_module_classes_into_opening_tag( string $tag, ?array $classes = null, bool $should_append_divi_button_classes = true ): string {
		$tag_trim       = trim( $tag );
		$attributes     = self::_parse_opening_tag_attributes( $tag_trim );
		$class_attribute = $attributes['class'] ?? null;

		if ( null === $classes ) {
			$classes = [];

			if ( is_array( $class_attribute ) ) {
				$classes = preg_split( '/\s+/', trim( $class_attribute['value'] ) );
			}

			if ( ! is_array( $classes ) ) {
				$classes = [];
			}

			$classes = array_values( array_filter( $classes ) );
		}

		$classes = FormMarkupSecurityUtils::sanitize_class_tokens( $classes );

		if ( $should_append_divi_button_classes ) {
			if ( ! in_array( 'et_pb_button', $classes, true ) ) {
				$classes[] = 'et_pb_button';
			}

			if ( ! in_array( 'et_pb_bg_layout_light', $classes, true ) ) {
				$classes[] = 'et_pb_bg_layout_light';
			}

			if ( ! in_array( 'et_pb_module', $classes, true ) ) {
				$classes[] = 'et_pb_module';
			}
		}

		if ( ! is_array( $class_attribute ) ) {
			$quote_char    = '"';
			$updated_class = 'class=' . $quote_char . implode( ' ', $classes ) . $quote_char;

			/*
			 * Anchors at the opening `<input` / `<button` tag name and captures it as `$1`
			 * so we can prepend our `class="…"` attribute right after the tag name.
			 * regex101: https://regex101.com/r/NUSiMT/1 (PHP flavor).
			 */
			$updated_tag = preg_replace( '/^(<\s*(?:input|button)\b)/i', '$1 ' . $updated_class, $tag_trim, 1 );

			return is_string( $updated_tag ) ? $updated_tag : $tag;
		}

		$quote_char = in_array( $class_attribute['quote'], [ '"', "'" ], true )
			? $class_attribute['quote']
			: '"';
		$updated_class = 'class=' . $quote_char . implode( ' ', $classes ) . $quote_char;

		return substr_replace(
			$tag_trim,
			$updated_class,
			$class_attribute['start'],
			$class_attribute['length']
		);
	}

	/**
	 * Detects Gravity Forms' own submission click handler on a form control.
	 *
	 * Gravity Forms routes submission, paging and save-and-continue through
	 * `onclick='gform.submission.handleButtonClick(this);'` (`GFFormDisplay::get_form_button()`). A control
	 * that loses it drops into GF's fallback submit listener, which logs
	 * `"Unsupported submission flow detected for form #n"` on every submission.
	 *
	 * This is an allowlist of one known handler *value*, not a relaxation of the kses attribute allowlist:
	 * event-handler attributes stay stripped, and the caller re-attaches the canonical literal returned
	 * here. Anything else, including any other `onclick`, yields an empty string and stays removed. The
	 * direction matters: a detection miss loses the handler, it never admits markup.
	 *
	 * @since ??
	 *
	 * @param string $tag Original (unsanitized) opening tag.
	 *
	 * @return string Canonical handler to re-attach, or an empty string when the tag carries no GF handler.
	 */
	private static function _get_gravity_forms_submission_handler( string $tag ): string {
		$attributes = self::_parse_opening_tag_attributes( $tag );

		if ( ! isset( $attributes['onclick'] ) ) {
			return '';
		}

		$handler_value = html_entity_decode( $attributes['onclick']['value'], ENT_QUOTES );

		/*
		 * Normalize away whitespace and an optional trailing semicolon before comparing, so formatting
		 * variations across GF versions still match the single accepted handler. `/\s+/` is the trivial
		 * whitespace-only normalization; accepted and rejected values are pinned by
		 * `test_get_gravity_forms_submission_handler_*`.
		 */
		$normalized_handler = preg_replace( '/\s+/', '', $handler_value );
		$normalized_handler = is_string( $normalized_handler ) ? rtrim( $normalized_handler, ';' ) : '';

		if ( 'gform.submission.handleButtonClick(this)' !== $normalized_handler ) {
			return '';
		}

		// Return a literal this class owns rather than echoing back the matched input.
		return 'gform.submission.handleButtonClick(this);';
	}

	/**
	 * Re-attaches Gravity Forms' submission handler to an already-sanitized opening tag.
	 *
	 * Runs after `wp_kses()` so the handler is never subject to the attribute allowlist. `$handler` must come
	 * from `_get_gravity_forms_submission_handler()`; an empty value leaves the markup untouched.
	 *
	 * @since ??
	 *
	 * @param string $html    Sanitized markup whose first opening tag receives the handler.
	 * @param string $handler Canonical handler from `_get_gravity_forms_submission_handler()`.
	 *
	 * @return string
	 */
	private static function _restore_gravity_forms_submission_handler( string $html, string $handler ): string {
		if ( '' === $handler || '' === $html ) {
			return $html;
		}

		/*
		 * Only the OPENING TAG is inspected, never the whole string: in the
		 * `<input type="submit">` → `<button …>LABEL</button>` branch `$html` carries the escaped button
		 * label, and a label containing the text `onclick=` would otherwise look like an existing handler
		 * and silently suppress the real one.
		 */
		$opening_tag_attributes = self::_parse_opening_tag_attributes( $html );

		/*
		 * Both production call sites hand this method `wp_kses()` output produced with event handlers
		 * disallowed, so an `onclick` is always already gone and this guard cannot fire from them. It is
		 * kept so the helper stays correct for any future caller that passes markup still carrying one;
		 * it is exercised directly by `test_data_onclick_is_not_treated_as_the_gf_handler`.
		 */
		if ( isset( $opening_tag_attributes['onclick'] ) ) {
			return $html;
		}

		/*
		 * Match the complete first opening tag while consuming quoted values atomically. Its final `>`
		 * is therefore the real tag close even when a quoted attribute contains a literal greater-than.
		 * This bounded match is pinned by `test_restore_gravity_forms_submission_handler_*`, including
		 * malformed and quoted-angle cases.
		 * regex101: https://regex101.com/r/IjwhAK/1 (PHP/PCRE2 flavor).
		 */
		if (
			1 !== preg_match(
				'/^\s*<(?:input|button)\b(?:[^\'"<>\r\n]|"[^"]*"|\'[^\']*\')*>/i',
				$html,
				$opening_tag_matches
			)
		) {
			return $html;
		}

		$opening_tag      = (string) ( $opening_tag_matches[0] ?? '' );
		$insertion_offset = strlen( $opening_tag ) - 1;

		while ( $insertion_offset > 0 && ctype_space( $html[ $insertion_offset - 1 ] ) ) {
			--$insertion_offset;
		}

		if ( '/' === ( $html[ $insertion_offset - 1 ] ?? '' ) ) {
			--$insertion_offset;
		}

		$attribute_prefix  = ctype_space( $html[ $insertion_offset - 1 ] ?? '' ) ? '' : ' ';
		$onclick_attribute = $attribute_prefix . 'onclick="' . esc_attr( $handler ) . '"';

		return substr_replace( $html, $onclick_attribute, $insertion_offset, 0 );
	}

	/**
	 * Builds a bounded `wp_kses()` allowlist for a GF form control.
	 *
	 * Kept local to this service so Contact Form 7 keeps using the shared no-handler button helper
	 * unchanged. Event handlers are permitted here only because the paging branch rewrites `class`
	 * on a tag GF built — see `_add_divi_button_class_to_gf_submit()`.
	 *
	 * `aria-*` attributes present on the source tag are allowlisted explicitly: HTMLUtility's
	 * `aria-*` wildcard is not expanded by `wp_kses()` the way `data-*` is. Non-`on*` extension
	 * attributes (HTMX / Alpine / Vue / Angular idioms, `required`, etc.) are still dropped.
	 * Event handlers remain available only when the caller explicitly opts in for GF paging;
	 * submit controls keep the narrower handler restoration policy.
	 *
	 * @since ??
	 *
	 * @param string $tag_name             Lowercased tag name (`input` or `button`).
	 * @param string $tag                  Opening tag used to discover `aria-*` attributes to keep.
	 * @param bool   $allow_event_handlers Whether the paging branch may retain event handlers.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function _get_form_control_kses_allowlist( string $tag_name, string $tag, bool $allow_event_handlers ): array {
		if ( 'input' === $tag_name ) {
			$allowed_attributes = FormMarkupSecurityUtils::get_allowed_input_attributes_for_kses( $allow_event_handlers );
		} else {
			$allowed_attributes         = FormMarkupSecurityUtils::get_allowed_attributes_for_element_for_kses( 'button', $allow_event_handlers );
			$allowed_attributes['type'] = true;
		}

		// wp_kses does not honor an `aria-*` wildcard key; allow each real ARIA attr on the source tag.
		foreach ( array_keys( self::_parse_opening_tag_attributes( $tag ) ) as $attribute_name ) {
			if ( str_starts_with( $attribute_name, 'aria-' ) ) {
				$allowed_attributes[ $attribute_name ] = true;
			}
		}

		return [ $tag_name => $allowed_attributes ];
	}

	/**
	 * Adds Divi button classes to Gravity Forms submit controls (`gform_button`) and multi-page paging buttons.
	 *
	 * Paging uses `<input|button type="button" class="gform_next_button|gform_previous_button" …>`
	 * (GF 2.x emits `<input>`; GF 3.x emits `<button>`). Those must keep `type="button"`, so they are
	 * not converted to `<button type="submit">` like the send control.
	 *
	 * The normal path of every branch keeps Gravity Forms'
	 * `onclick='gform.submission.handleButtonClick(this);'` handler, which GF's submission pipeline depends
	 * on, but the branches get there by different routes. The exception is the degraded fallbacks below
	 * (`<button type="submit">` returned when `wp_kses()` empties the tag): those emit a bare control with
	 * no handler, because at that point the original attributes are already gone.
	 *
	 * The two branches are deliberately not symmetric, because they are not doing the same thing.
	 *
	 * `wp_kses()` is used here as a correctness check on markup THIS function emits, not as a security
	 * boundary around Gravity Forms. There is no such boundary to hold: GF's own `[gravityform]` shortcode
	 * (`GFForms::parse_shortcode()`) and its own Gutenberg block (`GF_Block_Form::render_block()`) both
	 * return form HTML unescaped — each with an explicit `nosemgrep` suppression marking that as intended —
	 * and a form cannot be escaped and still function (its scripts, nonces and ajax iframe would not
	 * survive). Every consumer of `gravity_form()` therefore trusts GF's output, and so does this module.
	 *
	 * - The **submit branches REBUILD the tag**: `<input type="submit" …>` is reconstructed as
	 *   `<button type="submit" …>…</button>`. Once this function is the author of the emitted markup, the
	 *   emitted attribute set is its responsibility, so it strips every handler and re-attaches only the one
	 *   value it can name, via `_restore_gravity_forms_submission_handler()`.
	 * - The **paging branch REWRITES ONLY THE CLASS ATTRIBUTE** of a tag GF built. It still runs `wp_kses()`
	 *   over the result when Divi button classes are appended, so the attribute allowlist applies and
	 *   unlisted attributes are dropped; what differs is that event handlers (Divi's `on*` table) and
	 *   any `aria-*` present on the source tag are permitted through. That preserves GF's own
	 *   `onclick='gform.submission.handleButtonClick(this);'` and filter-added `on*` / `aria-*`
	 *   attributes — not arbitrary extension attributes (HTMX / Alpine / Vue / Angular idioms).
	 *   When Divi button classes are disabled (CPT / Theme Builder), the paging branch returns the
	 *   opening tag unchanged so filter markup stays byte-identical.
	 *
	 * The asymmetry is therefore a consequence of the module's standing rule: it disables plugin behaviour
	 * only where leaving it would break something. Reconstruction forces re-authorization; class-appending
	 * does not.
	 *
	 * @since ??
	 *
	 * @param string $html                              Form HTML output.
	 * @param bool   $should_append_divi_button_classes Whether to append the Divi button classes.
	 *
	 * @return string
	 */
	private static function _add_divi_button_class_to_gf_submit( string $html, bool $should_append_divi_button_classes = true ): string {
		/*
		 * Iterates every complete `<input>` / `<button>` opening tag. Quoted values are consumed
		 * atomically, so a literal `>` inside a value is not mistaken for the tag close. Unclosed
		 * quotes do not match. Valid HTML whitespace, including line breaks between attributes, is
		 * accepted while `<` remains excluded so malformed markup cannot consume a later tag.
		 * Happy-path and adversarial behavior is pinned by the complete `test_add_divi_btn_*` matrix.
		 * regex101: https://regex101.com/r/Opyq6t/1 (PHP/PCRE2 flavor).
		 */
		$processed_html = preg_replace_callback(
			'/<(input|button)\b(?:[^\'"<>]|"[^"]*"|\'[^\']*\')*>/i',
			function ( array $tag_matches ) use ( $should_append_divi_button_classes ): string {
				$tag = $tag_matches[0] ?? '';

				if ( '' === $tag ) {
					return '';
				}

				$tag_trim   = trim( $tag );
				$tag_name   = strtolower( (string) ( $tag_matches[1] ?? '' ) );
				$attributes = self::_parse_opening_tag_attributes( $tag_trim );
				$classes    = preg_split( '/\s+/', trim( $attributes['class']['value'] ?? '' ) );

				if ( ! is_array( $classes ) ) {
					$classes = [];
				}

				$classes = array_values( array_filter( $classes ) );

				$type_value         = strtolower( $attributes['type']['value'] ?? '' );
				$is_type_button     = 'button' === $type_value;
				$is_type_submit     = 'submit' === $type_value;
				$has_type_attribute = isset( $attributes['type'] );

				/*
				 * Paging detection: `<input|button type="button" class="…gform_next_button|gform_previous_button…">`.
				 * GF 2.x emits `<input>` for text/image paging; GF 3.x emits `<button>`. Classification
				 * requires the real type attribute and an exact token from the real class attribute.
				 * The captured tag name is lowercased so the value-guard below recognizes it — without
				 * that, an uppercase `<BUTTON>` fails `'button' === $paging_tag_name` and skips this
				 * branch (leaving the tag unstyled), rather than reaching kses with a bad key.
				 * regex101 patterns (PHP flavor):
				 * - anchor: https://regex101.com/r/koWXc7/1
				 * - type=button: https://regex101.com/r/SNasCe/1
				 * - paging next: https://regex101.com/r/f7xQkd/1
				 * - paging prev: https://regex101.com/r/cR4eOH/1
				 * Anchor / paging-class patterns: GravityFormsServiceTest::test_add_divi_btn_paging_*.
				 * type=button discrimination: test_add_divi_btn_generic_type_button_* and
				 * test_add_divi_btn_last_page_previous_submit_button_is_not_caught_as_paging.
				 */
				$paging_tag_name = null;

				if (
					$is_type_button
					&& (
						in_array( 'gform_next_button', $classes, true )
						|| in_array( 'gform_previous_button', $classes, true )
					)
				) {
					$paging_tag_name = $tag_name;
				}

				if ( 'input' === $paging_tag_name || 'button' === $paging_tag_name ) {
					/*
					 * CPT / Theme Builder: do not rewrite or kses paging controls when Divi button
					 * classes are disabled — leave GF (and filter) markup byte-identical.
					 */
					if ( ! $should_append_divi_button_classes ) {
						return $tag;
					}

					$updated_paging_tag   = self::_merge_et_pb_button_module_classes_into_opening_tag( $tag, null, true );
					$updated_paging_tag   = self::_encode_opening_tag_attribute_angle_brackets( $updated_paging_tag );
					$paging_allowlist      = self::_get_form_control_kses_allowlist( $paging_tag_name, $tag, true );
					$sanitized_paging_tag = wp_kses(
						$updated_paging_tag,
						$paging_allowlist
					);
					$sanitized_paging_tag = self::_restore_sanitized_duplicate_attributes(
						$paging_tag_name,
						$updated_paging_tag,
						$sanitized_paging_tag,
						$paging_allowlist
					);

					return '' !== $sanitized_paging_tag ? $sanitized_paging_tag : '';
				}

				$is_button_tag = 'button' === $tag_name;

				/*
				 * The shared exact-value predicate rejects attribute-name suffixes such as `data-type`
				 * and unquoted value prefixes such as `submitter`.
				 * regex101: https://regex101.com/r/6Czge5/1 (PHP flavor).
				 */
				$is_submit_input = 'input' === $tag_name
					&& $is_type_submit;

				$has_link_submit_id = self::_matches_numbered_control_identifier(
					$attributes['id']['value'] ?? '',
					'gform_submit_button_',
					'_link'
				);
				$has_submit_data_type = 'submit' === strtolower( $attributes['data-submission-type']['value'] ?? '' );

				/*
				 * GF's non-legacy link submit is the one type=button exception: it must carry a
				 * canonical `_link` id plus the plugin's exact submission metadata. Requiring
				 * every signal prevents `data-submission-type="submit"` from acting as a type attr.
				 */
				$is_link_style_submit = $is_button_tag
					&& $is_type_button
					&& $has_link_submit_id
					&& $has_submit_data_type;

				/*
				 * A `<button>` is treated as submit when (a) `type="submit"`, (b) `type=` is absent
				 * (HTML default), or (c) it satisfies the explicit GF link-submit contract above.
				 * See https://html.spec.whatwg.org/multipage/form-elements.html#attr-button-type.
				 * regex101: https://regex101.com/r/mlp83q/1 (PHP flavor).
				 */
				$is_submit_button = $is_button_tag && (
					$is_type_submit
					|| ! $has_type_attribute
					|| $is_link_style_submit
				);

				if ( ! $is_submit_input && ! $is_submit_button ) {
					return $tag;
				}

				/*
				 * GF submit identifier: `id="gform_submit_button_{n}"`; `\d+` enforces a numeric suffix.
				 */
				$has_gf_submit_id = self::_matches_numbered_control_identifier(
					$attributes['id']['value'] ?? '',
					'gform_submit_button_'
				);

				/*
				 * Some themes / GF configurations rely on `name` without the canonical submit `id`.
				 */
				$has_gf_submit_name = self::_matches_numbered_control_identifier(
					$attributes['name']['value'] ?? '',
					'gform_submit_button_'
				);

				$has_gform_button_class = in_array( 'gform_button', $classes, true );

				if ( ! $has_gform_button_class && ! $has_gf_submit_id && ! $has_gf_submit_name && ! $is_link_style_submit ) {
					return $tag;
				}

				if ( ! $has_gform_button_class ) {
					$classes[] = 'gform_button';
				}

				$updated_tag = self::_merge_et_pb_button_module_classes_into_opening_tag( $tag_trim, $classes, $should_append_divi_button_classes );
				$updated_tag = self::_encode_opening_tag_attribute_angle_brackets( $updated_tag );

				/*
				 * GF drives submission from `onclick='gform.submission.handleButtonClick(this);'`
				 * (`GFFormDisplay::get_form_button()`). kses strips every event
				 * handler below; this recovers that one known handler so it can be re-attached afterwards.
				 * A control that loses it still submits, but only via GF's fallback listener, which logs
				 * `"Unsupported submission flow detected for form #n"` on every submission.
				 */
				$gf_submission_handler = self::_get_gravity_forms_submission_handler( $tag );

				if ( $is_button_tag ) {
					$button_allowlist      = self::_get_form_control_kses_allowlist( 'button', $tag, false );
					$sanitized_button_tag = wp_kses(
						$updated_tag,
						$button_allowlist
					);
					$sanitized_button_tag = self::_restore_sanitized_duplicate_attributes(
						'button',
						$updated_tag,
						$sanitized_button_tag,
						$button_allowlist
					);

					if ( '' === $sanitized_button_tag ) {
						return '<button type="submit">';
					}

					return self::_restore_gravity_forms_submission_handler( $sanitized_button_tag, $gf_submission_handler );
				}

				$submit_input_attributes = self::_parse_opening_tag_attributes( $updated_tag );
				$button_text             = $submit_input_attributes['value']['value'] ?? '';

				/*
				 * Convert `<input type="submit" value="…" …>` into `<button type="submit" …>…</button>`
				 * by removing only the parsed real type/value spans, then stripping the leading
				 * `<input` and trailing `>`. Descending offsets keep earlier spans valid.
				 * regex101 patterns (PHP flavor):
				 * - `<input` strip: https://regex101.com/r/aCDkls/1
				 * - tag-end strip: https://regex101.com/r/uYjEKv/1
				 * Conversion is exercised by GravityFormsServiceTest::test_add_divi_btn_submit_input_*.
				 */
				$attributes_to_remove = array_values(
					array_filter(
						[
							$submit_input_attributes['type'] ?? null,
							$submit_input_attributes['value'] ?? null,
						],
						'is_array'
					)
				);

				usort(
					$attributes_to_remove,
					static fn( array $left, array $right ): int => $right['start'] <=> $left['start']
				);

				foreach ( $attributes_to_remove as $attribute_to_remove ) {
					$removal_start = $attribute_to_remove['start'];
					$attribute_end = $attribute_to_remove['start'] + $attribute_to_remove['length'];

					while ( $removal_start > 0 && ctype_space( $updated_tag[ $removal_start - 1 ] ) ) {
						--$removal_start;
					}

					$updated_tag = substr_replace( $updated_tag, '', $removal_start, $attribute_end - $removal_start );
				}

				$input_allowlist             = self::_get_form_control_kses_allowlist( 'input', $tag, false );
				$sanitized_submit_input_tag = wp_kses(
					$updated_tag,
					$input_allowlist
				);
				$sanitized_submit_input_tag = self::_restore_sanitized_duplicate_attributes(
					'input',
					$updated_tag,
					$sanitized_submit_input_tag,
					$input_allowlist
				);

				if ( '' === $sanitized_submit_input_tag ) {
					return '<button type="submit"></button>';
				}

				$updated_tag = $sanitized_submit_input_tag;

				$button_attributes = preg_replace( '/^<input\b/i', '', $updated_tag );
				$button_attributes = is_string( $button_attributes ) ? preg_replace( '/\s*\/?>$/', '', $button_attributes ) : '';
				$button_attributes = is_string( $button_attributes ) ? trim( $button_attributes ) : '';

				$button_text  = esc_html( wp_specialchars_decode( $button_text, ENT_QUOTES ) );
				$button_html  = '<button type="submit"';
				$button_html .= '' !== $button_attributes ? ' ' . $button_attributes : '';
				$button_html .= '>' . $button_text . '</button>';

				$button_html = wp_kses(
					$button_html,
					self::_get_form_control_kses_allowlist( 'button', $tag, false )
				);

				if ( '' === $button_html ) {
					return '<button type="submit">' . $button_text . '</button>';
				}

				/*
				 * The handler survives the `<input type="submit">` → `<button type="submit">` conversion:
				 * it is read from the original input tag and re-attached to the constructed button.
				 */
				return self::_restore_gravity_forms_submission_handler( $button_html, $gf_submission_handler );
			},
			$html
		);

		if ( ! is_string( $processed_html ) ) {
			return $html;
		}

		return $processed_html;
	}
}
