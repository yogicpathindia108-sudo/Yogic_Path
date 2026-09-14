<?php

require_once get_template_directory() . '/includes/builder-5/server/Packages/Conversion/Conversion.php';

/**
 * Import and Export data.
 *
 * @package Core\Portability
 */

use ET\Builder\Packages\Conversion\Conversion as D5BuilderConversion;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\FrontEnd\BlockParser\BlockParser;
use ET\Builder\VisualBuilder\OffCanvas\OffCanvasHooks;

/**
 * Handles the portability workflow.
 *
 * @package ET\Core\Portability
 */
class ET_Core_Portability {

	/**
	 * Current instance.
	 *
	 * @since 2.7.0
	 *
	 * @type object
	 */
	public $instance;

	/**
	 * @var ET_Core_Data_Utils
	 */
	protected static $_;

	/**
	 * Whether or not an import is in progress.
	 *
	 * @since 3.0.99
	 *
	 * @var bool
	 */
	protected static $_doing_import = false;

	/**
	 * Constructor.
	 *
	 * @param string $context Portability context previously registered.
	 */
	public function __construct( $context ) {
		$this->instance = et_core_cache_get( $context, 'et_core_portability' );

		self::$_ = ET_Core_Data_Utils::instance();

		if ( $this->instance && $this->instance->view ) {
			if ( et_core_is_fb_enabled() ) {
				$this->assets();
			} else {
				add_action( 'admin_footer', array( $this, 'modal' ) );
				add_action( 'customize_controls_print_footer_scripts', array( $this, 'modal' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'assets' ), 5 );
			}
		}
	}

	public static function doing_import() {
		return self::$_doing_import;
	}

	/**
	 * Whether options-type portability for this context may read or write WordPress Additional CSS (`wp_custom_css`).
	 *
	 * Theme Options and Customizer exports include Additional CSS for Customizer parity (Divi core 6d89d3417e).
	 * Role Editor (`et_pb_roles`) must not bundle or apply global CSS (#49101). New `options` contexts default
	 * to excluding Additional CSS unless explicitly allowlisted here.
	 *
	 * @since 5.2.0
	 *
	 * @return bool
	 */
	protected function _options_context_includes_wp_custom_css() {
		$contexts_with_additional_css = array(
			'epanel',
			'epanel_temp',
			'et_divi_mods',
			'et_extra_mods',
		);

		return true === in_array( $this->instance->context, $contexts_with_additional_css, true );
	}

	/**
	 * Import a previously exported layout.
	 *
	 * @since 3.10    Return the result of the import instead of dieing.
	 * @since 2.7.0
	 *
	 * @param string $file_context Accepts 'upload', 'sideload'. Default 'upload'.
	 *
	 * @return bool|array
	 */
	public function import( $file_context = 'upload' ) {
		global $shortname;

		$this->prevent_failure();

		self::$_doing_import = true;

		$timestamp    = $this->get_timestamp();
		$filesystem   = $this->set_filesystem();
		$temp_file_id = sanitize_file_name( $timestamp );
		$temp_file    = $this->has_temp_file( $temp_file_id, 'et_core_import' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification is handled earlier.
		$include_global_presets = isset( $_POST['include_global_presets'] ) ? wp_validate_boolean( sanitize_text_field( $_POST['include_global_presets'] ) ) : false;
		$return_json            = isset( $_POST['et_cloud_return_json'] ) ? wp_validate_boolean( sanitize_text_field( $_POST['et_cloud_return_json'] ) ) : false;
		$temp_presets           = isset( $_POST['et_cloud_use_temp_presets'] ) ? wp_validate_boolean( sanitize_text_field( $_POST['et_cloud_use_temp_presets'] ) ) : false;
		$is_update_preset_id    = isset( $_POST['onboarding'] ) ? wp_validate_boolean( sanitize_text_field( $_POST['onboarding'] ) ) : false;
		$preset_prefix          = isset( $_POST['preset_prefix'] ) ? sanitize_text_field( $_POST['preset_prefix'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$global_presets = '';

		if ( $temp_file ) {
			$import = json_decode( $filesystem->get_contents( $temp_file ), true );
		} else {
			if ( ! isset( $_FILES['file']['name'] ) || ! et_()->ends_with( sanitize_file_name( $_FILES['file']['name'] ), '.json' ) ) {
				return array( 'message' => 'invalideFile' );
			}

			if ( ! in_array( $file_context, array( 'upload', 'sideload' ) ) ) {
				$file_context = 'upload';
			}

			$handle_file = "wp_handle_{$file_context}";
			$upload      = $handle_file( $_FILES['file'], array(
				'test_size' => false,
				'test_type' => false,
				'test_form' => false,
			) );

			/**
			 * Fires before an uploaded Portability JSON file is processed.
			 *
			 * @since 3.0.99
			 *
			 * @param string $file The absolute path to the uploaded JSON file's temporary location.
			 */
			do_action( 'et_core_portability_import_file', $upload['file'] );

			$temp_file    = $this->temp_file( $temp_file_id, 'et_core_import', $upload['file'] );
			$file_content = preg_replace( '/\x{FEFF}/u', '', $filesystem->get_contents( $temp_file ) ); // Replace BOM with empty string.
			$import       = json_decode( $file_content, true );
			$import       = $this->validate( $import );

			if ( $return_json ) {
				return array( 'jsonFromFile' => $import );
			}

			// Check if Import contains Google Api Settings.
			if ( isset( $import['data']['et_google_api_settings'] ) && ( 'epanel' === $this->instance->context || 'epanel_temp' === $this->instance->context ) ) {
				$et_google_api_settings = $import['data']['et_google_api_settings'];
			}

			if ( ! isset( $import['context'] ) || ( isset( $import['context'] ) && $import['context'] !== $this->instance->context ) ) {
				$this->delete_temp_files( 'et_core_import', [ $temp_file_id => $temp_file ] );

				return array( 'message' => 'importContextFail' );
			}

			$import['data'] = $this->apply_query( $import['data'], 'set' );

			$filesystem->put_contents( $upload['file'], wp_json_encode( (array) $import ) );
		}

		// Upload images and replace current urls.
		if ( isset( $import['images'] ) ) {
			$images = $this->maybe_paginate_images( (array) $import['images'], 'upload_images', $timestamp );
			$import['data'] = $this->replace_images_urls( $images, $import['data'] );
		}

		if ( ! empty( $import['global_colors'] ) ) {
			$import['data'] = $this->_maybe_inject_gcid( $import['data'], $import['global_colors'] );
		}

		$data = $import['data'];
		$success = array( 'timestamp' => $timestamp );

		$this->delete_temp_files( 'et_core_import', [ $temp_file_id => $temp_file ] );

		if ( 'options' === $this->instance->type ) {
			if ( ! $this->_options_context_includes_wp_custom_css() ) {
				unset( $data['wp_custom_css'] );
			}

			// Reset all data besides excluded data.
			$current_data = $this->apply_query( get_option( $this->instance->target, array() ), 'unset' );

			if ( $this->_options_context_includes_wp_custom_css() && isset( $data['wp_custom_css'] ) && function_exists( 'wp_update_custom_css_post' ) ) {
				wp_update_custom_css_post( $data['wp_custom_css'] );

				if ( 'yes' === get_theme_mod( 'et_pb_css_synced', 'no' ) ) {
					// If synced, clear the legacy custom css value to avoid unwanted merging of old and new css.
					$data[ "{$shortname}_custom_css" ] = '';
				}
			}

			// Import Google API settings.
			if ( isset( $et_google_api_settings ) ) {
				// Get exising Google API key, sine it is not added to export.
				$et_previous_google_api_settings   = get_option( 'et_google_api_settings' );
				$et_previous_google_api_key        = isset( $et_previous_google_api_settings['api_key'] ) ? $et_previous_google_api_settings['api_key'] : '';
				$et_google_api_settings['api_key'] = $et_previous_google_api_key;

				update_option( 'et_google_api_settings', $et_google_api_settings );
			}

			// Merge remaining current data with new data and update options.
			update_option( $this->instance->target, array_merge( $current_data, $data ) );

			if ( $this->_options_context_includes_wp_custom_css() ) {
				set_theme_mod( 'et_pb_css_synced', 'no' );
			}
		}

		// Pass the post content and let js save the post.
		if ( 'post' === $this->instance->type ) {
			$success['postContent'] = reset( $data );
			$is_gutenberg_format    = false;

			// Check if we are dealing with D5 format.
			// We break the loop on the first post that has content, no need to check all posts.
			foreach ( $data as $post ) {
				if ( ! empty( $post['post_content'] ) ) {
					$is_gutenberg_format = BlockParser::has_any_divi_block( $post['post_content'] );
					if ( $is_gutenberg_format ) {
						break;
					}
				}
			}

			// In some cases we receive the post array instaed of shortcode string. Handle this case.
			$shortcode_string = is_array( $success['postContent'] ) && ! empty( $success['postContent']['post_content'] ) ? $success['postContent']['post_content'] : $success['postContent'];

			// Load shortcode framework to proceed with the import.
			et_load_shortcode_framework();

			if ( ! empty( $import['presets'] ) ) {
				$preset_rewrite_map = [];

				if ( $include_global_presets && ! $is_update_preset_id ) {
					if ( $is_gutenberg_format ) {
						$preset_data        = $this->prepare_to_import_layout_presets( $import['presets'] );
						$preset_rewrite_map = $preset_data['preset_rewrite_map'];
					} else {
						$preset_rewrite_map = $this->prepare_to_import_d4_layout_presets( $import['presets'] );
						$global_presets     = $import['presets'];
					}
				}

				$shortcode_object = et_fb_process_shortcode( $shortcode_string );

				if ( $is_update_preset_id ) {
					$global_presets    = $import['presets'];
					$vb_presets_lookup = $this->_get_importing_presets_id_name_pairs( $global_presets, $preset_prefix );
					$this->_update_shortcode_with_onboarding_preset( $shortcode_object, $vb_presets_lookup );
				} else {
					$this->rewrite_module_preset_ids( $shortcode_object, $import['presets'], $preset_rewrite_map );
				}

				$shortcode_string = et_fb_process_to_shortcode( $shortcode_object, array(), '', false );
			}

			do_shortcode( $shortcode_string );

			$success['postContent'] = $shortcode_string;

			// Handle nested global module references in imported content (D5 format only).
			// Fixes invalid nested global module structures from early D5 versions (public beta 8 and below).
			if ( $is_gutenberg_format ) {
				// Register portability context if not already registered.
				// PortabilityPost constructor expects a registered context to populate $this->instance
				// with configuration data (context, name, type, target, etc.). Even though
				// maybe_deglobalize_nested_global_modules() doesn't use $this->instance, the constructor
				// will set it to null if the context isn't registered, which could cause issues if
				// other code accesses this public property.
				if ( ! et_core_cache_get( 'et_builder', 'et_core_portability' ) ) {
					et_core_portability_register(
						'et_builder',
						[
							'name'   => 'Divi Builder',
							'type'   => 'post',
							'target' => 'et_pb_layout',
						]
					);
				}
				$portability_instance   = new \ET\Builder\Framework\Portability\PortabilityPost( 'et_builder' );
				$success['postContent'] = $portability_instance->maybe_deglobalize_nested_global_modules( $success['postContent'] );
			}

			require_once ET_BUILDER_DIR . 'module/settings/Migration.php';

			$success['migrations']  = ET_Builder_Module_Settings_Migration::$migrated;
			$success['presets']     = isset( $import['presets'] ) && is_array( $import['presets'] ) ? $import['presets'] : (object) array();
		}

		if ( 'post_type' === $this->instance->type ) {
			$preset_rewrite_map  = array();
			$is_gutenberg_format = false;

			// Check if we are dealing with D5 format.
			// We break the loop on the first post that has content, no need to check all posts.
			foreach ( $data as $post ) {
				if ( ! empty( $post['post_content'] ) ) {
					$is_gutenberg_format = BlockParser::has_any_divi_block( $post['post_content'] );
					if ( $is_gutenberg_format ) {
						break;
					}
				}
			}

			if ( ! empty( $import['presets'] ) && $include_global_presets ) {
				if ( $is_gutenberg_format ) {
					$preset_data            = $this->prepare_to_import_layout_presets( $import['presets'] );
					$preset_rewrite_map     = $preset_data['preset_rewrite_map'];
					$default_module_presets = $preset_data['default_module_presets'];
					$default_group_presets  = $preset_data['default_group_presets'];
					$global_presets         = $import['presets'];
				} else {
					$preset_rewrite_map = $this->prepare_to_import_d4_layout_presets( $import['presets'] );
					$global_presets     = $import['presets'];
				}
			}

			foreach ( $data as &$post ) {
				// If we are dealing with D5 format, for selected default presets, we need to update the post content with default preset IDs.
				if ( ! empty( $import['presets'] ) && $include_global_presets && $is_gutenberg_format ) {
					// Update the post content with default preset IDs.
					$post['post_content'] = $this->_update_preset_ids_in_content(
						$post['post_content'],
						$default_module_presets,
						$default_group_presets
					);
				}

				$shortcode_object = et_fb_process_shortcode( $post['post_content'] );

				if ( ! empty( $import['presets'] ) ) {
					if ( $include_global_presets ) {
						$this->rewrite_module_preset_ids( $shortcode_object, $import['presets'], $preset_rewrite_map );
					} else {
						$this->_apply_global_presets( $shortcode_object, $import['presets'] );
					}
				}

				$post_content = et_fb_process_to_shortcode( $shortcode_object, array(), '', false );

				// Handle nested global module references in imported content (D5 format only).
				// Fixes invalid nested global module structures from early D5 versions (public beta 8 and below).
				if ( $is_gutenberg_format ) {
					// Register portability context if not already registered.
					// PortabilityPost constructor expects a registered context to populate $this->instance
					// with configuration data (context, name, type, target, etc.). Even though
					// maybe_deglobalize_nested_global_modules() doesn't use $this->instance, the constructor
					// will set it to null if the context isn't registered, which could cause issues if
					// other code accesses this public property.
					if ( ! et_core_cache_get( 'et_builder', 'et_core_portability' ) ) {
						et_core_portability_register(
							'et_builder',
							[
								'name'   => 'Divi Builder',
								'type'   => 'post',
								'target' => 'et_pb_layout',
							]
						);
					}
					$portability_instance = new \ET\Builder\Framework\Portability\PortabilityPost( 'et_builder' );
					$post['post_content'] = $portability_instance->maybe_deglobalize_nested_global_modules( $post['post_content'] );
				}

				// Add slashes for post content to avoid unwanted unslashing (by wp_unslash) while post is inserting.
				// If we are dealing with D5 format, we chose non-processed $post['post_content'].
				$post['post_content'] = str_starts_with( $post['post_content'], '<!--' )
					? wp_slash( $post['post_content'] )
					: wp_slash( $post_content );

				// Upload thumbnail image if exist.
				if ( ! empty( $post['post_meta'] ) && ! empty( $post['post_meta']['_thumbnail_id'] ) ) {
					$post_thumbnail_origin_id = (int) $post['post_meta']['_thumbnail_id'][0];

					if ( ! empty( $import['thumbnails'] ) && ! empty( $import['thumbnails'][ $post_thumbnail_origin_id ] ) ) {
						$post_thumbnail_new = $this->upload_images( $import['thumbnails'][ $post_thumbnail_origin_id ] );
						$new_thumbnail_data = reset( $post_thumbnail_new );

						// New thumbnail image was uploaded and it should be updated.
						if ( isset( $new_thumbnail_data['replacement_id'] ) ) {
							$new_thumbnail_id  = $new_thumbnail_data['replacement_id'];
							$post['thumbnail'] = $new_thumbnail_id;

							if ( ! function_exists( 'wp_crop_image' ) ) {
								include ABSPATH . 'wp-admin/includes/image.php';
							}

							$thumbnail_path = get_attached_file( $new_thumbnail_id );

							// Generate all the image sizes and update thumbnail metadata.
							$new_metadata = wp_generate_attachment_metadata( $new_thumbnail_id, $thumbnail_path );
							wp_update_attachment_metadata( $new_thumbnail_id, $new_metadata );
						}
					}
				}
			}

			$imported_posts = $this->import_posts( $data );

			if ( false === $imported_posts ) {
				/**
				 * Filters the error message when {@see ET_Core_Portability::import()} fails.
				 *
				 * @since 3.0.99
				 *
				 * @param mixed $error_message Default is `null`.
				 */
				if ( $error_message = apply_filters( 'et_core_portability_import_error_message', false ) ) {
					$error_message = array( 'message' => $error_message );
				}

				return $error_message;
			} else {
				$success['imported_posts'] = $imported_posts;
			}
		}

		if ( ! empty( $global_presets ) ) {
			if ( ! $this->import_global_presets( $global_presets, $temp_presets, true, $preset_prefix, true ) ) {
				if ( $error_message = apply_filters( 'et_core_portability_import_error_message', false ) ) {
					$error_message = array( 'message' => $error_message );
				}

				return $error_message;
			}
		}

		if ( ! empty( $import['global_colors'] ) ) {
			$this->import_global_colors( $import['global_colors'] );
			$success['globalColors'] = et_builder_get_all_global_colors( true );
		}

		if ( ! empty( $import['global_variables'] ) ) {
			$success['globalVariables'] = GlobalData::import_global_variables( $import['global_variables'] );
		}

		return $success;
	}


	/**
	 * Updates null preset IDs in post_content using pre-built default preset arrays.
	 *
	 * @param string $content                The imported post_content.
	 * @param array  $default_module_presets Module default IDs [module_name => preset_id].
	 * @param array  $default_group_presets  Group default IDs [group_name => preset_id].
	 *
	 * @return string Updated post_content.
	 */
	protected static function _update_preset_ids_in_content( $content, $default_module_presets, $default_group_presets ) {
		// If there's no content, return the original content.
		if ( empty( $content ) ) {
			return $content;
		}

		// Handle module presets.
		foreach ( $default_module_presets as $module_name => $default_id ) {
			// Test regex: https://regex101.com/r/3WqIKs/1.
			$pattern = '/(<!-- wp:' . preg_quote( $module_name, '/' ) . '[^>]*?"modulePreset"\s*:\s*)null/';
			$content = preg_replace(
				$pattern,
				'$1"' . $default_id . '"',
				$content
			);
		}

		// Handle group presets.
		foreach ( $default_group_presets as $group_name => $default_id ) {
			// Test regex: https://regex101.com/r/7ZW57i/1.
			$pattern = '/("presetId"\s*:\s*)null(\s*,\s*"groupName"\s*:\s*"' . preg_quote( $group_name, '/' ) . '")/';
			$content = preg_replace(
				$pattern,
				'$1"' . $default_id . '"$2',
				$content
			);
		}

		return $content;
	}

	/**
	 * Generates UUIDs for the D4 presets to avoid collisions.
	 *
	 * @since 4.5.0
	 *
	 * @param array $global_presets - The Global Presets to be imported.
	 *
	 * @return array - The list of module types for which preset ids have been changed.
	 */
	public function prepare_to_import_d4_layout_presets( &$global_presets ) {
		$preset_rewrite_map = array();
		$initial_preset_id  = ET_Builder_Global_Presets_Settings::MODULE_INITIAL_PRESET_ID;

		// Skip if this is D5 format (has 'module' or 'group' keys).
		if ( isset( $global_presets['module'] ) || isset( $global_presets['group'] ) ) {
			return $preset_rewrite_map;
		}

		foreach ( $global_presets as $component_type => &$component_presets ) {
			// Skip if component_presets is not an array or doesn't have 'presets' key.
			if ( ! is_array( $component_presets ) || ! isset( $component_presets['presets'] ) || ! is_array( $component_presets['presets'] ) ) {
				continue;
			}

			$preset_rewrite_map[ $component_type ] = array();
			foreach ( $component_presets['presets'] as $preset_id => $preset ) {
				$new_id                                  = ET_Core_Data_Utils::uuid_v4();
				$component_presets['presets'][ $new_id ] = $preset;
				$preset_rewrite_map[ $component_type ][ $preset_id ] = $new_id;
				unset( $component_presets['presets'][ $preset_id ] );
			}

			// Only process default if it exists and is set.
			if ( isset( $component_presets['default'] ) ) {
				if ( $component_presets['default'] === $initial_preset_id && ! isset( $preset_rewrite_map[ $component_type ][ $initial_preset_id ] ) ) {
					$new_id                       = ET_Core_Data_Utils::uuid_v4();
					$component_presets['default'] = $new_id;
					if ( isset( $component_presets['presets'][ $initial_preset_id ] ) ) {
						$component_presets['presets'][ $new_id ] = $component_presets['presets'][ $initial_preset_id ];
						unset( $component_presets['presets'][ $initial_preset_id ] );
					}
					$preset_rewrite_map[ $component_type ][ $initial_preset_id ] = $new_id;
				} elseif ( isset( $preset_rewrite_map[ $component_type ][ $component_presets['default'] ] ) ) {
					$component_presets['default'] = $preset_rewrite_map[ $component_type ][ $component_presets['default'] ];
				}
			}
		}

		return $preset_rewrite_map;
	}

	/**
	 * Initiate Export.
	 *
	 * @since 2.7.0
	 *
	 * @param bool $return
	 *
	 * @return null|array
	 */
	public function export( $return = false, $include_used_presets = false ) {
		$this->prevent_failure();
		et_core_nonce_verified_previously();

		et_load_shortcode_framework();

		$timestamp            = $this->get_timestamp();
		$filesystem           = $this->set_filesystem();
		$temp_file_id         = sanitize_file_name( $timestamp );
		$temp_file            = $this->has_temp_file( $temp_file_id, 'et_core_export' );
		$apply_global_presets = isset( $_POST['apply_global_presets'] ) ? wp_validate_boolean( $_POST['apply_global_presets'] ) : false;
		$global_presets       = '';
		$global_colors        = '';
		$global_variables     = '';
		$thumbnails           = '';

		if ( $temp_file ) {
			$file_data        = json_decode( $filesystem->get_contents( $temp_file ) );
			$data             = (array) $file_data->data;
			$global_presets   = $file_data->presets;
			$global_colors    = $file_data->global_colors;
			$global_variables = $file_data->global_variables;
			$canvases         = isset( $file_data->canvases ) ? (array) $file_data->canvases : array();
		} else {
			$canvases = array();
			$temp_file = $this->temp_file( $temp_file_id, 'et_core_export' );

			if ( 'options' === $this->instance->type ) {
				$data = get_option( $this->instance->target, array() );

				// Export the Customizer "Additional CSS" value as well (Theme Options / Customizer contexts only; not Role Editor — #49101).
				if ( $this->_options_context_includes_wp_custom_css() && function_exists( 'wp_get_custom_css' ) ) {
					$data['wp_custom_css'] = wp_get_custom_css();
				}
			}

			if ( 'post' === $this->instance->type ) {
				if ( ! ( isset( $_POST['post'] ) || isset( $_POST['content'] ) ) ) {
					wp_send_json_error();
				}

				et_load_shortcode_framework();

				$fields_validatation = array(
					'ID' => 'intval',
					// no post_content as the default case for no fields_validation will run it through perms based wp_kses_post, which is exactly what we want.
				);

				$post_data = array(
					'post_content' => stripcslashes( $_POST['content'] ), // need to run this through stripcslashes() as thats what wp_kses_post() expects.
					'ID'           => $_POST['post'],
				);

				$post_data = $this->validate( $post_data, $fields_validatation );

				$data = array( $post_data['ID'] => $post_data['post_content'] );

				// Export canvases for D5 content.
				$gutenberg_format = false !== strpos( $post_data['post_content'], '<!-- wp:' );
				if ( $gutenberg_format ) {
					$canvases = $this->_export_canvases( $post_data['ID'], $post_data['post_content'] );
				}

				if ( isset( $_POST['global_presets'] ) ) {
					// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized -- filter_post_data() function does sanitation.
					$post_global_presets = $this->_filter_post_data( $_POST['global_presets'] );
					$global_presets      = json_decode( stripslashes( $post_global_presets ) );
				}

				if ( isset( $_POST['global_colors'] ) ) {
					// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized -- filter_post_data() function does sanitation.
					$post_global_colors = $this->_filter_post_data( $_POST['global_colors'] );
					$global_colors      = json_decode( stripslashes( $post_global_colors ) );
				}

				if ( $include_used_presets ) {
					$used_global_presets = array();
					$used_global_colors  = array();

					$shortcode_object   = et_fb_process_shortcode( $post_data['post_content'] );

					$used_global_presets = array_merge(
						$this->get_used_global_presets( $shortcode_object, $used_global_presets ),
						$used_global_presets
					);

					if ( ! empty( $used_global_presets ) ) {
						$global_presets = (object) $used_global_presets;
					}

					$used_global_colors = $this->_get_used_global_colors( $shortcode_object, $used_global_colors, $global_presets );

					if ( ! empty( $used_global_colors ) ) {
						$used_global_colors = $this->_add_global_color_dependencies( $used_global_colors );
						$global_colors      = $this->_get_global_colors_data( $used_global_colors );
					}
				}
			}

			if ( 'post_type' === $this->instance->type ) {
				$data = $this->export_posts_query();
			}

			$data = $this->apply_query( $data, 'set' );

			// Export Google API settings.
			if ( 'epanel' === $this->instance->context || 'epanel_temp' === $this->instance->context ) {
				$et_google_api_settings = get_option( 'et_google_api_settings', array() );

				// Unset google api_key settings to prevent exporting it.
				if ( isset( $et_google_api_settings['api_key'] ) ) {
					unset( $et_google_api_settings['api_key'] );
				}

				$data['et_google_api_settings'] = $et_google_api_settings;
			}

			if ( 'post_type' === $this->instance->type ) {
				$d5_presets            = GlobalPreset::get_data();
				$used_global_presets   = array();
				$used_global_colors    = array();
				$used_global_variables = array();
				$options               = array(
					'apply_global_presets' => true,
				);

				et_load_shortcode_framework();

				foreach ( $data as $post ) {
					$gutenberg_format = false !== strpos( $post->post_content, '<!-- wp:' );

					if ( $gutenberg_format ) {
						// For Divi 5 content.
						$preset_ids = self::_get_preset_ids_from_d5_content( $post->post_content, $d5_presets );

						$module_preset_ids = $preset_ids['modulePreset'];
						$group_preset_ids  = $preset_ids['groupPreset'];

						// We'll look for used module presets and get the module presets data and set it to $used_global_presets.
						// Nested group presets are extracted within _prepare_used_module_presets_data_from_d5_content.
						foreach ( $module_preset_ids as $preset_id ) {
							self::_prepare_used_module_presets_data_from_d5_content( $used_global_presets, $d5_presets, $preset_id );
						}

						// We'll look for used group presets and get the group presets data and set it to $used_global_presets.
						foreach ( $group_preset_ids as $preset_id ) {
							self::_prepare_used_group_presets_data_from_d5_content( $used_global_presets, $d5_presets, $preset_id );
						}

						$global_variable_ids = self::_get_gvids_from_d5_content( $post->post_content );

						$used_global_variables = array_merge( $used_global_variables, $global_variable_ids );

						// Extract global color IDs directly from D5 content.
						$content_global_color_ids = self::_get_gcids_from_d5_content( $post->post_content );

						// Convert global color IDs to global_colors_info format for _get_global_colors_data.
						if ( ! empty( $content_global_color_ids ) ) {
							$content_global_colors_info = array();
							foreach ( $content_global_color_ids as $color_id ) {
								$content_global_colors_info[ $color_id ] = array();
							}
							$used_global_colors = array_merge( $used_global_colors, $content_global_colors_info );
						}
					} else {
						// For Divi 4 content.
						$shortcode_object = et_fb_process_shortcode( $post->post_content );

						// We have to always process global presets to get the global colors correctly.
						$global_presets_from_post = $this->get_used_global_presets( $shortcode_object, $used_global_presets );
						$used_global_presets      = array_merge(
							$global_presets_from_post,
							$used_global_presets
						);

						$used_global_colors = $this->_get_used_global_colors( $shortcode_object, $used_global_colors, $global_presets_from_post );

						if ( $apply_global_presets ) {
							$shortcode_object   = et_fb_process_to_shortcode( $shortcode_object, $options, '', false );
							$post->post_content = $shortcode_object;
						}
					}
				}

				if ( ! empty( $used_global_presets ) ) {
					$global_presets = (object) $used_global_presets;

					// Extract global color IDs from presets.
					$preset_global_color_ids = self::_extract_global_color_ids_from_presets( $used_global_presets );

					// Extract global variable IDs from presets.
					$preset_global_variable_ids = self::_extract_global_variable_ids_from_presets( $used_global_presets );

					// Merge preset global color IDs with content-based global color IDs.
					if ( ! empty( $preset_global_color_ids ) ) {
						// Convert global color IDs to global_colors_info format for _get_global_colors_data.
						$preset_global_colors_info = array();
						foreach ( $preset_global_color_ids as $color_id ) {
							$preset_global_colors_info[ $color_id ] = array();
						}
						$used_global_colors = array_merge( $used_global_colors, $preset_global_colors_info );
					}

					// Merge preset global variable IDs with content-based global variable IDs.
					if ( ! empty( $preset_global_variable_ids ) ) {
						$used_global_variables = array_merge( $used_global_variables, $preset_global_variable_ids );
					}
				}

				if ( ! empty( $used_global_colors ) ) {
					$used_global_colors = $this->_add_global_color_dependencies( $used_global_colors );
					$global_colors      = $this->_get_global_colors_data( $used_global_colors );
				} else {
					$global_colors = array();
				}

				if ( ! empty( $used_global_variables ) ) {
					// Remove duplicates and ensure we have unique IDs.
					$used_global_variables = array_unique( $used_global_variables );
					$global_variables      = GlobalData::export_global_variables( $used_global_variables );
				} else {
					$global_variables = array();
				}

				// Export canvases for D5 content in post_type exports.
				$canvases = array();
				foreach ( $data as $post ) {
					$gutenberg_format = false !== strpos( $post->post_content, '<!-- wp:' );
					if ( $gutenberg_format ) {
						$post_canvases = $this->_export_canvases( $post->ID, $post->post_content );
						// Merge canvases from all posts.
						if ( ! empty( $post_canvases['local'] ) ) {
							if ( ! isset( $canvases['local'] ) ) {
								$canvases['local'] = array();
							}
							$canvases['local'] = array_merge( $canvases['local'], $post_canvases['local'] );
						}
						if ( ! empty( $post_canvases['global'] ) ) {
							if ( ! isset( $canvases['global'] ) ) {
								$canvases['global'] = array();
							}
							$canvases['global'] = array_merge( $canvases['global'], $post_canvases['global'] );
						}
					}
				}
			}

			// put contents into file, this is temporary,
			// if images get paginated, this content will be brought back out
			// of a temp file in paginated request
			$file_data = array(
				'data'             => $data,
				'presets'          => $global_presets,
				'global_colors'    => $global_colors,
				'global_variables' => $global_variables,
				'canvases'         => $canvases,
			);
			$filesystem->put_contents( $temp_file, wp_json_encode( $file_data ) );
		}

		$thumbnails = $this->_get_thumbnail_images( $data );

		$images = $this->get_data_images( $data );

		// Get canvases from temp file if it exists (for paginated exports).
		if ( $temp_file && ! isset( $canvases ) ) {
			$file_data = json_decode( $filesystem->get_contents( $temp_file ), true );
			$canvases  = isset( $file_data['canvases'] ) ? $file_data['canvases'] : array();
		}
		if ( ! isset( $canvases ) ) {
			$canvases = array();
		}

		$data = array(
			'context'          => $this->instance->context,
			'data'             => $data,
			'presets'          => $global_presets,
			'global_colors'    => $global_colors,
			'global_variables' => $global_variables,
			'canvases'         => $canvases,
			'images'           => $this->maybe_paginate_images( $images, 'encode_images', $timestamp ),
			'thumbnails'       => $thumbnails,
		);

		// Return exported content instead of printing it
		if ( $return ) {
			return array_merge( $data, [ 'timestamp' => $timestamp ] );
		}

		$filesystem->put_contents( $temp_file, wp_json_encode( (array) $data ) );

		wp_send_json_success( array( 'timestamp' => $timestamp ) );
	}

	/**
	 * Export canvases for a post.
	 *
	 * Exports local canvases from postmeta and global canvases that are targeted by interactions
	 * or that append to the main canvas.
	 *
	 * @since 5.0.0
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Post content (serialized Gutenberg blocks).
	 *
	 * @return array Canvas data structure with 'local' and 'global' keys.
	 */
	private function _export_canvases( $post_id, $content ) {
		$exported_canvases = array(
			'local'  => array(),
			'global' => array(),
		);

		// Get local canvases from et_pb_canvas posts.
		$local_posts = get_posts(
			array(
				'post_type'      => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					array(
						'key'   => '_divi_canvas_parent_post_id',
						'value' => $post_id,
					),
				),
			)
		);

		foreach ( $local_posts as $post ) {
			$canvas_id = get_post_meta( $post->ID, '_divi_canvas_id', true );
			if ( ! $canvas_id ) {
				continue;
			}

			$canvas_meta = $this->_get_canvas_meta_from_post( $post );

			$exported_canvases['local'][ $canvas_id ] = $this->_build_local_canvas_export_array(
				$canvas_id,
				array(
					'id'                 => $canvas_id,
					'name'               => $post->post_title,
					'isMain'             => false,
					'isGlobal'           => false,
					'appendToMainCanvas' => $canvas_meta['append_to_main'],
					'createdAt'          => $canvas_meta['created_at'],
				),
				$post->post_content
			);
		}

		// Export global canvases targeted by interactions.
		$this->_export_canvases_targeted_by_interactions( $content, $exported_canvases );

		// Include canvases that append to main canvas (both local and global).
		// These canvases will be rendered with the main canvas on the frontend.
		$this->_export_canvases_that_append_to_main( $post_id, $exported_canvases );

		return $exported_canvases;
	}

	/**
	 * Export global canvases targeted by interactions.
	 *
	 * Finds global canvases that contain modules with interactionTarget attributes matching
	 * the target IDs extracted from interactions in the main content.
	 *
	 * @since 5.0.0
	 *
	 * @param string $content Post content to extract interaction targets from.
	 * @param array  $exported_canvases Canvas data structure (passed by reference to add to it).
	 *
	 * @return void
	 */
	private function _export_canvases_targeted_by_interactions( $content, &$exported_canvases ) {
		// Extract interaction target IDs from content.
		$target_ids = OffCanvasHooks::extract_interaction_target_ids_from_content( $content );

		if ( empty( $target_ids ) ) {
			return;
		}

		// Export global canvases that match the condition.
		$this->_export_global_canvases_matching_condition(
			$exported_canvases,
			function( $post, $canvas_content ) use ( $target_ids ) {
				// Check if this canvas contains any of the target IDs by searching for interactionTarget attribute.
				return $this->_canvas_content_contains_target( $canvas_content, $target_ids );
			}
		);
	}

	/**
	 * Export global canvases that have appendToMainCanvas set.
	 *
	 * These canvases will be appended to the main canvas on the frontend, so they should be included in exports.
	 * Note: Local canvases are already exported, so we only need to check global canvases.
	 *
	 * @since 5.0.0
	 *
	 * @param int   $post_id Post ID (unused but kept for consistency).
	 * @param array $exported_canvases Canvas data structure (passed by reference to add to it).
	 *
	 * @return void
	 */
	private function _export_canvases_that_append_to_main( $post_id, &$exported_canvases ) {
		// Export global canvases that match the condition.
		$this->_export_global_canvases_matching_condition(
			$exported_canvases,
			function( $post, $canvas_content ) {
				// Check if this canvas appends to main canvas.
				$canvas_meta = $this->_get_canvas_meta_from_post( $post );

				// Only include canvases that append to main canvas.
				return 'above' === $canvas_meta['append_to_main'] || 'below' === $canvas_meta['append_to_main'];
			}
		);
	}

	/**
	 * Export global canvases matching a condition.
	 *
	 * Helper function to reduce code duplication when exporting global canvases.
	 * Handles common logic: fetching global canvas posts, filtering archived/existing canvases,
	 * and building the canvas data structure.
	 *
	 * @since 5.0.0
	 *
	 * @param array    $exported_canvases Canvas data structure (passed by reference to add to it).
	 * @param callable $condition_callback Callback function that receives ($post, $canvas_content) and returns bool.
	 *                                     Should return true if the canvas should be exported.
	 *
	 * @return void
	 */
	private function _export_global_canvases_matching_condition( &$exported_canvases, callable $condition_callback ) {
		// Get all global canvases.
		$global_posts = get_posts(
			array(
				'post_type'      => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		foreach ( $global_posts as $post ) {
			$canvas_id = get_post_meta( $post->ID, '_divi_canvas_id', true );
			if ( ! $canvas_id ) {
				continue;
			}

			// Skip archived canvases.
			$is_archived = get_post_meta( $post->ID, '_divi_canvas_archived', true );
			if ( $is_archived ) {
				continue;
			}

			// Skip if already exported as global canvas.
			if ( isset( $exported_canvases['global'][ $canvas_id ] ) ) {
				continue;
			}

			// Skip if already exported as local canvas (a canvas shouldn't be both local and global).
			if ( isset( $exported_canvases['local'][ $canvas_id ] ) ) {
				continue;
			}

			// Check if canvas matches condition.
			$canvas_content = $post->post_content;
			if ( ! $condition_callback( $post, $canvas_content ) ) {
				continue;
			}

			// Build canvas data structure.
			$exported_canvases['global'][ $canvas_id ] = $this->_build_global_canvas_export_array(
				$canvas_id,
				$post,
				$canvas_content
			);
		}
	}

	/**
	 * Check if canvas content contains any of the target IDs.
	 *
	 * Searches for interactionTarget attributes in canvas content that match any of the provided target IDs.
	 * Handles both simple and nested interactionTarget structures.
	 *
	 * @since 5.0.0
	 *
	 * @param string   $canvas_content Canvas content (serialized Gutenberg blocks).
	 * @param string[] $target_ids Array of target IDs to search for.
	 *
	 * @return bool True if canvas content contains any of the target IDs, false otherwise.
	 */
	private function _canvas_content_contains_target( $canvas_content, $target_ids ) {
		foreach ( $target_ids as $target_id ) {
			$escaped_target_id = preg_quote( $target_id, '/' );
			// Match interactionTarget with quoted value (most common case).
			if ( preg_match( '/"interactionTarget"\s*:\s*"' . $escaped_target_id . '"/', $canvas_content ) ) {
				return true;
			}
			// Match interactionTarget with nested structure (e.g., desktop breakpoint with value property).
			if ( preg_match( '/"interactionTarget"\s*:\s*\{[^}]*"value"\s*:\s*"' . $escaped_target_id . '"/', $canvas_content ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Build global canvas export array structure.
	 *
	 * Helper function to create a consistent global canvas export data structure.
	 *
	 * @since 5.0.0
	 *
	 * @param string $canvas_id Canvas ID.
	 * @param object $post WordPress post object.
	 * @param string $canvas_content Canvas content (serialized Gutenberg blocks).
	 *
	 * @return array Global canvas export data structure.
	 */
	private function _build_global_canvas_export_array( $canvas_id, $post, $canvas_content ) {
		$canvas_meta = $this->_get_canvas_meta_from_post( $post );

		return array(
			'id'                 => $canvas_id,
			'name'               => $post->post_title,
			'isMain'             => false,
			'isGlobal'           => true,
			'appendToMainCanvas' => $canvas_meta['append_to_main'],
			'createdAt'          => $canvas_meta['created_at'],
			'content'            => $canvas_content,
		);
	}

	/**
	 * Build local canvas export array structure.
	 *
	 * Helper function to create a consistent local canvas export data structure.
	 *
	 * @since 5.0.0
	 *
	 * @param string $canvas_id Canvas ID.
	 * @param array  $canvas_meta Canvas metadata array.
	 * @param string $canvas_content Canvas content (serialized Gutenberg blocks).
	 *
	 * @return array Local canvas export data structure.
	 */
	private function _build_local_canvas_export_array( $canvas_id, $canvas_meta, $canvas_content ) {
		return array(
			'id'                 => $canvas_meta['id'] ?? $canvas_id,
			'name'               => $canvas_meta['name'] ?? '',
			'isMain'             => false,
			'isGlobal'           => false,
			'appendToMainCanvas' => $canvas_meta['appendToMainCanvas'] ?? null,
			'createdAt'          => $canvas_meta['createdAt'] ?? current_time( 'mysql' ),
			'content'            => $canvas_content,
		);
	}

	/**
	 * Get canvas metadata from a WordPress post object.
	 *
	 * Helper function to extract canvas metadata (created_at, append_to_main) from a post.
	 *
	 * @since 5.0.0
	 *
	 * @param object $post WordPress post object.
	 *
	 * @return array Canvas metadata with 'created_at' and 'append_to_main' keys.
	 */
	private function _get_canvas_meta_from_post( $post ) {
		$canvas_created_at = get_post_meta( $post->ID, '_divi_canvas_created_at', true );
		$canvas_created_at = $canvas_created_at ? $canvas_created_at : $post->post_date;

		$append_to_main = get_post_meta( $post->ID, '_divi_canvas_append_to_main', true );
		$append_to_main = '' === $append_to_main ? null : $append_to_main;

		return array(
			'created_at'     => $canvas_created_at,
			'append_to_main' => $append_to_main,
		);
	}

	/**
	 * Import canvases for a post.
	 *
	 * Imports local canvases as postmeta and global canvases as et_pb_canvas post types.
	 *
	 * @since 5.0.0
	 *
	 * @param int   $post_id Post ID.
	 * @param array $canvases Canvas data structure with 'local' and 'global' keys.
	 *
	 * @return void
	 */
	private function _import_canvases( $post_id, $canvases ) {
		if ( ! is_array( $canvases ) ) {
			return;
		}

		// Import local canvases.
		if ( isset( $canvases['local'] ) && is_array( $canvases['local'] ) ) {
			foreach ( $canvases['local'] as $canvas_id => $canvas_data ) {
				if ( ! is_array( $canvas_data ) || ! isset( $canvas_data['content'] ) ) {
					continue;
				}

				// Check if local canvas already exists (by canvas ID and parent post ID).
				$existing_posts = get_posts(
					array(
						'post_type'      => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
						'posts_per_page' => 1,
						'meta_query'     => array(
							array(
								'key'   => '_divi_canvas_id',
								'value' => $canvas_id,
							),
							array(
								'key'   => '_divi_canvas_parent_post_id',
								'value' => $post_id,
							),
						),
					)
				);

				$this->_import_single_canvas(
					$canvas_id,
					$canvas_data,
					$post_id,
					$existing_posts,
					true // is_local
				);
			}
		}

		// Import global canvases.
		if ( isset( $canvases['global'] ) && is_array( $canvases['global'] ) ) {
			foreach ( $canvases['global'] as $canvas_id => $canvas_data ) {
				if ( ! is_array( $canvas_data ) || ! isset( $canvas_data['content'] ) ) {
					continue;
				}

				// Check if global canvas already exists (by canvas ID, without parent_post_id).
				$existing_posts = get_posts(
					array(
						'post_type'      => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
						'posts_per_page' => 1,
						'meta_query'     => array(
							array(
								'key'   => '_divi_canvas_id',
								'value' => $canvas_id,
							),
							array(
								'key'     => '_divi_canvas_parent_post_id',
								'compare' => 'NOT EXISTS',
							),
						),
					)
				);

				$this->_import_single_canvas(
					$canvas_id,
					$canvas_data,
					null, // parent_post_id (null for global)
					$existing_posts,
					false // is_local
				);
			}
		}
	}

	/**
	 * Import a single canvas (local or global).
	 *
	 * Helper function to reduce code duplication when importing canvases.
	 * Handles both local and global canvas import logic.
	 *
	 * @since 5.0.0
	 *
	 * @param string   $canvas_id Canvas ID.
	 * @param array    $canvas_data Canvas data from import.
	 * @param int|null $parent_post_id Parent post ID for local canvases, null for global.
	 * @param array    $existing_posts Array of existing posts found for this canvas.
	 * @param bool     $is_local Whether this is a local canvas.
	 *
	 * @return void
	 */
	private function _import_single_canvas( $canvas_id, $canvas_data, $parent_post_id, $existing_posts, $is_local ) {
		$default_name = $is_local ? 'Local Canvas' : 'Global Canvas';
		$meta_input   = array(
			'_divi_canvas_id'             => $canvas_id,
			'_divi_canvas_created_at'     => $canvas_data['createdAt'] ?? current_time( 'mysql' ),
			'_divi_canvas_append_to_main' => $canvas_data['appendToMainCanvas'] ?? null,
		);

		if ( $is_local ) {
			$meta_input['_divi_canvas_parent_post_id'] = $parent_post_id;
		}

		$post_data = array(
			'post_title'   => $canvas_data['name'] ?? $default_name,
			'post_status'  => 'publish',
			'post_type'    => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
			'post_content' => wp_slash( $canvas_data['content'] ),
			'meta_input'   => $meta_input,
		);

		if ( ! empty( $existing_posts ) ) {
			$existing_post_id = $existing_posts[0]->ID;

			// For global canvases, check if archived and unarchive if needed.
			if ( ! $is_local ) {
				$is_archived = get_post_meta( $existing_post_id, '_divi_canvas_archived', true );
				if ( $is_archived ) {
					delete_post_meta( $existing_post_id, '_divi_canvas_archived' );
				}
			}

			// Update existing post.
			$post_data['ID'] = $existing_post_id;
			wp_update_post( $post_data );
			// Update meta separately since wp_update_post doesn't always handle meta_input reliably.
			update_post_meta( $existing_post_id, '_divi_canvas_append_to_main', $canvas_data['appendToMainCanvas'] ?? null );
		} else {
			// Create new post.
			wp_insert_post( $post_data );
		}
	}

	/**
	 * Serialize a single layout post in chunks.
	 *
	 * @since 4.0
	 *
	 * @param integer $id Unique ID to represent this layout serialization.
	 * @param integer $post_id
	 * @param string $content
	 * @param array $theme_builder_meta
	 * @param integer $chunk
	 *
	 * @return array
	 */
	public function serialize_layout( $id, $post_id, $content, $theme_builder_meta = array(), $chunk = 0 ) {
		$this->prevent_failure();

		$fields_validatation = array(
			// No post_content as the default case for no fields_validation will run it through perms based wp_kses_post, which is exactly what we want.
			'ID' => 'intval',
		);

		if ( false === strpos( $content, '<!-- wp:divi/' ) ) {
			// Handle D4 content.
			$post_data = array(
				// Need to run this through stripcslashes() as thats what wp_kses_post() expects.
				'post_content' => stripcslashes( $content ),
				'ID'           => $post_id,
			);
		} else {
			// Handle D5 content.
			$post_data = array(
				// No need to run this through stripcslashes() as we do in D4. The page content
				// is stored in Gutenberg block format, and removing backslashes here would corrupt the content.
				// We ran into a similar issue previously and resolved it the same way.
				// See: https://github.com/elegantthemes/submodule-builder-5/blob/2a76339dea8eb986515fb6b48ffc4f7ea904ec6c/server/Framework/Portability/PortabilityPost.php#L2307.
				'post_content' => $content,
				'ID'           => $post_id,
			);
		}

		$shortcode_object   = et_fb_process_shortcode( $post_data['post_content'] );
		$used_global_colors = $this->get_theme_builder_library_used_global_colors( $shortcode_object );
		$post_data          = $this->validate( $post_data, $fields_validatation );
		$data               = array( $post_data['ID'] => $post_data['post_content'] );
		$data               = $this->apply_query( $data, 'set' );
		$images             = $this->get_data_images( $data );
		$images             = $this->chunk_images( $images, 'encode_images', $id, $chunk );
		$global_colors      = array();

		// Generate list of used global colors.
		if ( ! empty( $used_global_colors ) ) {
			$used_global_colors = $this->_add_global_color_dependencies( $used_global_colors );
			$global_colors      = $this->_get_global_colors_data( $used_global_colors );
		}

		// Export canvases for D5 content.
		$canvases = array();
		$gutenberg_format = false !== strpos( $post_data['post_content'], '<!-- wp:' );
		if ( $gutenberg_format ) {
			$canvases = $this->_export_canvases( $post_data['ID'], $post_data['post_content'] );
		}

		$data   = array(
			'context'       => 'et_builder',
			'data'          => $data,
			'images'        => $images['images'],
			'post_title'    => get_post_field( 'post_title', $post_id ),
			'post_type'     => get_post_type( $post_id ),
			'theme_builder' => $theme_builder_meta,
			'global_colors' => $global_colors,
			'canvases'      => $canvases,
		);
		$chunks = $images['chunks'];
		$ready  = $images['ready'];

		return array(
			'ready'  => $ready,
			'chunks' => $chunks,
			'data'   => $data,
		);
	}

	/**
	 * Serialize Theme Builder templates in chunks.
	 *
	 * @since 4.0
	 *
	 * @param integer $id Unique ID to represent this theme builder serialization process.
	 * @param array $step
	 * @param integer $steps
	 * @param integer $step_index
	 * @param integer $chunk
	 *
	 * @return array|false
	 */
	public function serialize_theme_builder( $id, $step, $steps, $step_index = 0, $chunk = 0 ) {
		if ( $step_index >= $steps ) {
			return false;
		}

		$this->prevent_failure();

		$temp_file_id = sanitize_file_name( 'et_theme_builder_' . $id );
		$temp_file    = $this->has_temp_file( $temp_file_id, 'et_core_export' );

		if ( $temp_file ) {
			$data = json_decode( $this->get_filesystem()->get_contents( $temp_file ), true );
		} else {
			$temp_file = $this->temp_file( $temp_file_id, 'et_core_export' );
			$data      = array(
				'context'              => 'et_theme_builder',
				'templates'            => array(),
				'layouts'              => array(),
				'presets'              => array(),
				'global_colors'        => array(),
				'global_variables'     => array(),
				'has_default_template' => false,
				'has_global_layouts'   => false,
			);
		}

		$chunks = 1;

		switch ( $step['type'] ) {
			case 'template':
				$header_id  = $step['data']['layouts']['header']['id'];
				$body_id    = $step['data']['layouts']['body']['id'];
				$footer_id  = $step['data']['layouts']['footer']['id'];
				$is_default = $step['data']['default'];

				if ( 0 !== $header_id && ! current_user_can( 'edit_post', $header_id ) ) {
					$step['data']['layouts']['header']['id'] = 0;
				}

				if ( 0 !== $body_id && ! current_user_can( 'edit_post', $body_id ) ) {
					$step['data']['layouts']['body']['id'] = 0;
				}

				if ( 0 !== $footer_id && ! current_user_can( 'edit_post', $footer_id ) ) {
					$step['data']['layouts']['footer']['id'] = 0;
				}

				if ( $is_default ) {
					$data['has_default_template'] = true;
				}

				$data['templates'][] = $step['data'];
				break;

			case 'layout':
				$post_id   = $step['data']['post_id'];
				$is_global = $step['data']['is_global'];

				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					break;
				}

				if ( 0 === $chunk && isset( $data['layouts'][ $post_id ] ) ) {
					// The layout is already exported.
					break;
				}

				if ( $is_global ) {
					$data['has_global_layouts'] = true;
				}

				$step_data = $this->serialize_layout(
					$id,
					$post_id,
					get_post_field( 'post_content', $post_id ),
					array(
						'is_global' => $is_global,
					),
					$chunk
				);

				$step_data['data']['post_meta'] = array_merge(
					et_()->array_get( $step_data, 'data.post_meta', array() ),
					et_core_get_post_builder_meta( $post_id )
				);

				$data['layouts'][ $post_id ] = $step_data['data'];
				$chunks = $step_data['chunks'];
				break;

			case 'presets':
				// Only set D4 presets if D5 presets haven't been collected yet.
				if ( empty( $data['presets'] ) ) {
					$data['presets'] = $step['data'];
				}
				break;

			case 'collect_global_data':
				$layout_ids = $step['data']['layout_ids'];

				if ( ! empty( $layout_ids ) ) {
					$used_global_colors    = array();
					$used_global_variables = array();
					$used_presets          = array();
					$all_d5_presets        = GlobalPreset::get_data();

					foreach ( $layout_ids as $layout_id ) {
						$content = get_post_field( 'post_content', $layout_id );

						if ( empty( $content ) ) {
							continue;
						}

						// Extract global color IDs from layout content.
						$content_global_color_ids = self::_get_gcids_from_d5_content( $content );
						if ( ! empty( $content_global_color_ids ) ) {
							foreach ( $content_global_color_ids as $color_id ) {
								$used_global_colors[ $color_id ] = array();
							}
						}

						// Extract global variable IDs from layout content.
						$content_global_variable_ids = self::_get_gvids_from_d5_content( $content );
						if ( ! empty( $content_global_variable_ids ) ) {
							$used_global_variables = array_merge( $used_global_variables, $content_global_variable_ids );
						}

						// Extract D5 presets from layout content.
						$preset_ids        = self::_get_preset_ids_from_d5_content( $content, $all_d5_presets );
						$module_preset_ids = $preset_ids['modulePreset'];
						$group_preset_ids  = $preset_ids['groupPreset'];

						// Prepare used module presets data.
						foreach ( $module_preset_ids as $preset_id ) {
							self::_prepare_used_module_presets_data_from_d5_content( $used_presets, $all_d5_presets, $preset_id );
						}

						// Prepare used group presets data.
						foreach ( $group_preset_ids as $preset_id ) {
							self::_prepare_used_group_presets_data_from_d5_content( $used_presets, $all_d5_presets, $preset_id );
						}
					}

					// Extract global colors and variables from presets.
					if ( ! empty( $used_presets ) ) {
						$preset_global_color_ids = self::_extract_global_color_ids_from_presets( $used_presets );
						if ( ! empty( $preset_global_color_ids ) ) {
							foreach ( $preset_global_color_ids as $color_id ) {
								$used_global_colors[ $color_id ] = array();
							}
						}

						$preset_global_variable_ids = self::_extract_global_variable_ids_from_presets( $used_presets );
						if ( ! empty( $preset_global_variable_ids ) ) {
							$used_global_variables = array_merge( $used_global_variables, $preset_global_variable_ids );
						}
					}

					// Process global colors.
					if ( ! empty( $used_global_colors ) ) {
						$used_global_colors = $this->_add_global_color_dependencies( $used_global_colors );
						$global_colors_data = $this->_get_global_colors_data( $used_global_colors );

						if ( ! empty( $global_colors_data ) ) {
							$data['global_colors'] = $global_colors_data;
						}
					}

					// Process global variables.
					if ( ! empty( $used_global_variables ) ) {
						$used_global_variables = array_unique( $used_global_variables );
						$global_variables_data = GlobalData::export_global_variables( $used_global_variables );

						if ( ! empty( $global_variables_data ) ) {
							$data['global_variables'] = $global_variables_data;
						}
					}

					// Process D5 presets.
					if ( ! empty( $used_presets ) ) {
						$data['presets'] = $used_presets;
					}
				}
				break;

			case 'global_colors':
				$data['global_colors'] = $step['data'];
				break;

			case 'global_variables':
				$data['global_variables'] = $step['data'];
				break;

			case 'd5_presets':
				$data['presets'] = $step['data'];
				break;
		}

		$ready = ( $step_index + 1 >= $steps ) && ( $chunk + 1 >= $chunks );

		if ( ! $ready ) {
			$this->get_filesystem()->put_contents( $temp_file, wp_json_encode( $data ) );
		} else {
			$this->delete_temp_files( 'et_core_export', array( $temp_file_id => $temp_file ) );
		}

		return array(
			'ready'  => $ready,
			'chunks' => $chunks,
			'data'   => $data,
		);
	}

	/**
	 * Export Theme Builder templates in chunks.
	 *
	 * @since 4.0
	 *
	 * @param integer $id Unique ID to represent this theme builder export process.
	 * @param array $step
	 * @param integer $steps
	 * @param integer $step_index
	 * @param integer $chunk
	 *
	 * @return array|false
	 */
	public function export_theme_builder( $id, $step, $steps, $step_index = 0, $chunk = 0 ) {
		$result = $this->serialize_theme_builder( $id, $step, $steps, $step_index, $chunk );

		if ( false === $result ) {
			return false;
		}

		$temp_file_id = sanitize_file_name( 'et_theme_builder_export_' . $id );
		$temp_file    = $this->temp_file( $temp_file_id, 'et_core_export' );

		if ( $result['ready'] ) {
			$this->get_filesystem()->put_contents( $temp_file, wp_json_encode( $result[ 'data' ] ) );
		}

		return array_merge( $result, array(
			'temp_file'    => $temp_file,
			'temp_file_id' => $temp_file_id,
		) );
	}

	/**
	 * Get whether an array represents a valid Theme Builder export.
	 *
	 * @since 4.0
	 *
	 * @param array $export
	 *
	 * @return boolean
	 */
	public function is_valid_theme_builder_export( $export ) {
		$valid_context = isset( $export['context'] ) && $export['context'] === $this->instance->context;
		$has_templates = isset( $export['templates'] ) && is_array( $export['templates'] );
		$has_layouts   = isset( $export['layouts'] ) && is_array( $export['layouts'] );

		return $valid_context && $has_templates && $has_layouts;
	}

	/**
	 * Import a single layout in chunks.
	 *
	 * @since 4.0
	 *
	 * @param string $id Unique ID to represent this layout serialization.
	 * @param array $layout
	 * @param integer $chunk
	 *
	 * @return array|false
	 */
	public function import_layout( $id, $layout, $chunk = 0, $onboarding = false ) {
		$post_id = 0;
		$import  = $this->validate( $layout );

		if ( false === $import ) {
			return false;
		}

		$import['data'] = $this->apply_query( $import['data'], 'set' );

		if ( ! isset( $import['context'] ) || ( isset( $import['context'] ) && 'et_builder' !== $import['context'] ) ) {
			return false;
		}

		$result = $this->chunk_images( self::$_->array_get( $import, 'images', array() ), 'upload_images', $id, $chunk );

		if ( $result['ready'] ) {
			$import['data']   = $this->replace_images_urls( $result['images'], $import['data'] );
			$post_type        = self::$_->array_get( $import, 'post_type', 'post' );
			$post_title       = self::$_->array_get( $import, 'post_title', '' );
			$post_status      = self::$_->array_get( $import, 'post_status', 'publish' );
			$post_meta        = self::$_->array_get( $import, 'post_meta', array() );
			$post_type_object = get_post_type_object( $post_type );

			if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->create_posts ) ) {
				return false;
			}

			$content = array_values( $import['data'] );
			$content = wp_slash( D5BuilderConversion::maybeConvertContent( $content[0] ) );

			$maybe_gutenberg_format = false !== strpos( $content, '<!-- wp:' );

			if ( $maybe_gutenberg_format && et_theme_builder_is_layout_post_type( $post_type ) ) {
				// Local data contains the \\u0022, cloud data contains u0022.
				// https://regex101.com/r/lsQ6jy/1 - Replace u0022 with \\u0022 only if not already preceded by a backslash.
				// Extra slashes for escaping.
				$content = preg_replace( '/(?<!\\\\)u00/', '\\\\\\\\u00', $content );
			}

			$args    = array(
				'post_status'  => $post_status,
				'post_type'    => $post_type,
				'post_content' => current_user_can( 'unfiltered_html' ) ? $content : wp_kses_post( $content ),
			);

			if ( ! empty( $post_title ) ) {
				$args['post_title'] = current_user_can( 'unfiltered_html' ) ? $post_title : wp_kses( $post_title, 'entities' );
			}

			$post_id = et_theme_builder_insert_layout( $args );

			if ( is_wp_error( $post_id ) ) {
				return false;
			}

			if ( $onboarding ) {
				update_post_meta( $post_id, '_et_pb_use_divi_5', 'on' );
			}

			foreach ( $post_meta as $entry ) {
				$meta_value = $entry['value'];

				// Preserve backslashes in Custom CSS when storing post meta.
				if ( isset( $entry['key'] ) && '_et_pb_custom_css' === $entry['key'] ) {
					$meta_value = wp_slash( $meta_value );
				}

				update_post_meta( $post_id, $entry['key'], $meta_value );
			}

			// Import Global Colors for each layout.
			if ( ! empty( $import['global_colors'] ) ) {
				$this->import_global_colors( $import['global_colors'] );
			}

			// Import canvases if present in import data.
			if ( isset( $import['canvases'] ) && is_array( $import['canvases'] ) && $post_id > 0 ) {
				$this->_import_canvases( $post_id, $import['canvases'] );
			}
		}

		return array(
			'ready'   => $result['ready'],
			'chunks'  => $result['chunks'],
			'id'      => $post_id,
		);
	}

	/**
	 * Get importing presets ID and name pairs from presets data.
	 *
	 * @since 4.26.1
	 *
	 * @param array $presets Presets data.
	 */
	protected function _get_importing_presets_id_name_pairs( $presets, $preset_prefix = '' ) {
		$presets_lookup = [];

		foreach ( $presets as $module_type => $value ) {
			$presets_lookup[ $module_type ] = [];

			foreach ( $value['presets'] as $module_preset_id => $preset ) {
				$preset_name = $preset['name'];

				if ( '' !== $preset_prefix ) {
					$prefix_with_space = $preset_prefix . ' ';

					if ( 0 !== strpos( $preset_name, $prefix_with_space ) ) {
						$preset_name = $prefix_with_space . $preset_name;
					}
				}

				$presets_lookup[ $module_type ][ $module_preset_id ] = $preset_name;
			}
		}

		return $presets_lookup;
	}

	/**
	 * Import Theme Builder templates in chunks.
	 *
	 * @since 4.0
	 *
	 * @param integer $id Unique ID to represent this theme builder import process.
	 * @param array $step
	 * @param integer $steps
	 * @param integer $step_index
	 * @param integer $chunk
	 *
	 * @return array|false
	 */
	public function import_theme_builder( $id, $step, $steps, $step_index = 0, $chunk = 0 ) {
		if ( $step_index >= $steps ) {
			return false;
		}

		$layout_id_map = array();
		$chunks        = 1;

		if ( ! isset( $step['type'] ) ) {
			$step['type'] = '';
		}

		switch ( $step['type'] ) {
			case 'layout':
				$presets             = et_()->array_get( $step, 'presets', array() );
				$presets_rewrite_map = et_()->array_get( $step, 'presets_rewrite_map', array() );
				$is_update_preset_id = et_()->array_get( $step, 'is_update_preset_id', false );
				$import_presets      = et_()->array_get( $step, 'import_presets', false );
				$layouts             = et_()->array_get( $step['data'], 'data', array() );
				$preset_prefix       = et_()->array_get( $step, 'preset_prefix', '' );

				if ( $is_update_preset_id ) {
					$tb_presets_lookup = $this->_get_importing_presets_id_name_pairs( $presets, $preset_prefix );
				}

				// Apply any presets to the layouts' shortcodes prior to importing them.
				if ( ! empty( $presets ) && ! empty( $layouts ) ) {
					foreach ( $layouts as $key => $layout ) {
						// Skip D4 shortcode based preset processing for D5 content because
						// it isn't needed and breaks the import process.
						if ( BlockParser::has_any_divi_block( $layout ) ) {
							continue;
						}

						$shortcode_object = et_fb_process_shortcode( $layout );

						if ( $import_presets ) {
							if ( $is_update_preset_id ) {
								$this->_update_shortcode_with_onboarding_preset( $shortcode_object, $tb_presets_lookup );
							} else {
								$this->rewrite_module_preset_ids( $shortcode_object, $presets, $presets_rewrite_map );
							}
						} else {
							$this->_apply_global_presets( $shortcode_object, $presets );
						}

						$layouts[ $key ] = et_fb_process_to_shortcode( $shortcode_object, array(), '', false );
					}

					$step['data']['data'] = $layouts;
				}

				$result = $this->import_layout( $id, $step['data'], $chunk, $step['onboarding'] );

				if ( false === $result ) {
					break;
				}

				if ( $result['ready'] ) {
					if ( ! isset( $layout_id_map[ $step['id'] ] ) ) {
						$layout_id_map[ $step['id'] ] = array();
					}

					// Since a single layout can be duplicated multiple times if
					// it's global we have to keep an array of duplicated ids.
					$layout_id_map[ $step['id'] ][ $step['template_id'] ] = $result['id'];
				}

				$chunks = $result['chunks'];
				break;

			case 'global_colors':
				$this->import_global_colors( $step['data'] );
				break;

			case 'global_variables':
				GlobalData::import_global_variables( $step['data'] );
				break;

			case 'd5_presets':
				$this->import_global_presets( $step['data'] );
				break;

			case 'collect_global_data':
				// This step is for processing during export, not import.
				break;
		}

		$ready = ( $step_index + 1 >= $steps ) && ( $chunk + 1 >= $chunks );

		return array(
			'ready'         => $ready,
			'chunks'        => $chunks,
			'layout_id_map' => $layout_id_map,
		);
	}

	/**
	 * Download temporary file.
	 *
	 * @since 4.0
	 *
	 * @param string $filename
	 * @param string $temp_file_id
	 * @param string $temp_file
	 * @return void
	 */
	public function download_file( $filename, $temp_file_id, $temp_file ) {
		$this->prevent_failure();

		$filename = sanitize_file_name( $filename );

		header( 'Content-Description: File Transfer' );
		header( "Content-Disposition: attachment; filename=\"{$filename}.json\"" );
		header( 'Content-Type: application/json' );
		header( 'Pragma: no-cache' );

		if ( file_exists( $temp_file ) ) {
			echo et_core_esc_previously( $this->get_filesystem()->get_contents( $temp_file ) );
		}

		$this->delete_temp_files( 'et_core_export', array( $temp_file_id => $temp_file ) );

		wp_die();
	}

	/**
	 * Download Export Data.
	 *
	 * @since 2.7.0
	 */
	public function download_export() {
		$this->prevent_failure();
		et_core_nonce_verified_previously();

		// Retrieve data.
		$timestamp = isset( $_GET['timestamp'] ) ? sanitize_text_field( $_GET['timestamp'] ) : null;
		$name = isset( $_GET['name'] ) ? sanitize_text_field( rawurldecode( $_GET['name'] ) ) : $this->instance->name;
		$filesystem = $this->set_filesystem();
		$temp_file = $this->temp_file( sanitize_file_name( $timestamp ), 'et_core_export' );

		header( 'Content-Description: File Transfer' );
		header( "Content-Disposition: attachment; filename=\"{$name}.json\"" );
		header( 'Content-Type: application/json' );
		header( 'Pragma: no-cache' );

		if ( file_exists( $temp_file ) ) {
			echo et_core_esc_previously( $filesystem->get_contents( $temp_file ) );
		}

		$this->delete_temp_files( 'et_core_export' );

		exit;
	}

	protected function to_megabytes( $value ) {
		$unit = strtoupper( substr( $value, -1 ) );
		$amount = intval( substr( $value, 0, -1 ) );

		// Known units
		switch ( $unit ) {
			case 'G': return $amount << 10;
			case 'M': return $amount;
		}

		if ( is_numeric( $unit ) ) {
			// Numeric unit is present, assume bytes
			return intval( $value ) >> 20;
		}

		// Unknown unit ...
		return intval( $value );

	}// end to_megabytes()

	/**
	 * Get selected posts data.
	 *
	 * @since 2.7.0
	 */
	protected function export_posts_query() {
		et_core_nonce_verified_previously();

		$args = array(
			'post_type'      => $this->instance->target,
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		);

		// Only include selected posts if set and not empty.
		if ( isset( $_POST['selection'] ) ) {
			$include = json_decode( stripslashes( $_POST['selection'] ), true );

			if ( ! empty( $include ) ) {
				$include = array_map( 'intval', array_values( $include ) );
				$args['post__in'] = $include;
			}
		}

		$get_posts = get_posts( apply_filters( "et_core_portability_export_wp_query_{$this->instance->context}", $args ) );
		$taxonomies = get_object_taxonomies( $this->instance->target );
		$posts = array();

		foreach ( $get_posts as $key => $post ) {
			unset(
				$post->post_author,
				$post->guid
			);

			$posts[$post->ID] = $post;

			// Include post meta.
			$post_meta = (array) get_post_meta( $post->ID );

			if ( isset( $post_meta['_edit_lock'] ) ) {
				unset(
					$post_meta['_edit_lock'],
					$post_meta['_edit_last']
				);
			}

			$posts[$post->ID]->post_meta = $post_meta;

			// Include terms.
			$get_terms = (array) wp_get_object_terms( $post->ID, $taxonomies );
			$terms = array();

			// Order terms to make sure children are after the parents.
			while ( $term = array_shift( $get_terms ) ) {
				if ( 0 === $term->parent || isset( $terms[$term->parent] ) ) {
					$terms[$term->term_id] = $term;
				} else {
					// if parent category is also exporting then add the term to the end of the list and process it later
					// otherwise add a term as usual
					if ( $this->is_parent_term_included( $get_terms, $term->parent ) ) {
						$get_terms[] = $term;
					} else {
						$terms[$term->term_id] = $term;
					}
				}
			}

			$posts[$post->ID]->terms = array();

			foreach ( $terms as $term ) {
				$parents_data = array();

				if ( $term->parent ) {
					$parent_slug = isset( $terms[$term->parent] ) ? $terms[$term->parent]->slug : $this->get_parent_slug( $term->parent, $term->taxonomy );
					$parents_data = $this->get_all_parents( $term->parent, $term->taxonomy );
				} else {
					$parent_slug = 0;
				}

				$posts[$post->ID]->terms[$term->term_id] = array(
					'name'        => $term->name,
					'slug'        => $term->slug,
					'taxonomy'    => $term->taxonomy,
					'parent'      => $parent_slug,
					'all_parents' => $parents_data,
					'description' => $term->description
				);
			}
		}

		return $posts;
	}

	/**
	 * Check whether the $parent_id included into the $terms_list.
	 *
	 * @since 2.7.0
	 *
	 * @param array $terms_list Array of term objects.
	 * @param int   $parent_id  .
	 *
	 * @return bool
	 */
	protected function is_parent_term_included( $terms_list, $parent_id ) {
		$is_parent_found = false;

		foreach ( $terms_list as $term => $term_details ) {
			if ( $parent_id === $term_details->term_id ) {
				$is_parent_found = true;
			}
		}

		return $is_parent_found;
	}

	/**
	 * Retrieve the term slug.
	 *
	 * @since 2.7.0
	 *
	 * @param int    $parent_id .
	 * @param string $taxonomy  .
	 *
	 * @return int|string
	 */
	protected function get_parent_slug( $parent_id, $taxonomy ) {
		$term_data = get_term( $parent_id, $taxonomy );
		$slug = '' === $term_data->slug ? 0 : $term_data->slug;

		return $slug;
	}

	/**
	 * Prepare array of all parents so the correct hierarchy can be restored during the import.
	 *
	 * @since 2.7.0
	 *
	 * @param int    $parent_id .
	 * @param string $taxonomy  .
	 *
	 * @return array
	 */
	protected function get_all_parents( $parent_id, $taxonomy ) {
		$parents_data_array = array();
		$parent = $parent_id;

		// retrieve data for all parent categories
		if ( 0 !== $parent  ) {
			while( 0 !== $parent ) {
				$parent_term_data = get_term( $parent, $taxonomy );
				$parents_data_array[$parent_term_data->slug] = array(
					'name' => $parent_term_data->name,
					'description' => $parent_term_data->description,
					'parent' => 0 !== $parent_term_data->parent ? $this->get_parent_slug( $parent_term_data->parent, $taxonomy ) : 0,
				);

				$parent = $parent_term_data->parent;
			}
		}
		//reverse order of items, to simplify the restoring process
		return array_reverse( $parents_data_array );
	}

	/**
	 * Check if a layout exists in the database already based on both its title and its slug.
	 *
	 * @param string $title
	 * @param string $slug
	 *
	 * @return int $post_id The post id if it exists, zero otherwise.
	 */
	protected static function layout_exists( $title, $slug ) {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_name = %s",
			array(
				wp_unslash( sanitize_post_field( 'post_title', $title, 0, 'db' ) ),
				wp_unslash( sanitize_post_field( 'post_name', $slug, 0, 'db' ) ),
			)
		) );
	}

	/**
	 * Get used global preset ids from the d5 post content.
	 * Match pattern => "modulePreset":"wc8l3h...f".
	 *
	 * @since 5.0.0
	 *
	 * @param string $content The post content.
	 *
	 * @return array
	 */
	protected static function _get_preset_ids_from_d5_content( $content, $d5_presets ) {
		$patterns = [
			// Extract raw JSON value (array or string) - normalize_preset_stack() handles both formats.
			'modulePreset' => '/"modulePreset":\s*(\[[^\]]*\]|"[^"]*")/',
			// Test regex: https://regex101.com/r/mzpdLo/1.
			'groupPreset'  => '/"groupPreset":\s*(\{[^{}]*?(?:\{[^{}]*?\}[^{}]*?)*\})/s',
			// Test regex: https://regex101.com/r/QRHsq1/1.
			'moduleName'   => '/<!-- wp:(divi\/[a-z-]+)\b/',
		];

		$results = [
			'modulePreset' => [],
			'groupPreset'  => [],
		];

		// Normalize $d5_presets.
		$d5_presets = is_array( $d5_presets ) ? $d5_presets : [
			'module' => [],
			'group'  => [],
		];

		// Extract modulePreset - decode JSON and normalize using GlobalPreset::normalize_preset_stack().
		preg_match_all( $patterns['modulePreset'], $content, $matches );
		if ( isset( $matches[1] ) ) {
			foreach ( $matches[1] as $json_value ) {
				if ( ! empty( $json_value ) ) {
					$decoded = json_decode( $json_value, true );
					if ( null !== $decoded ) {
						// normalize_preset_stack() handles both string and array formats.
						$normalized_ids          = GlobalPreset::normalize_preset_stack( $decoded );
						$results['modulePreset'] = array_merge( $results['modulePreset'], $normalized_ids );
					}
				}
			}
		}

		// Extract groupPreset.
		preg_match_all( $patterns['groupPreset'], $content, $matches );
		$group_names = [];
		if ( isset( $matches[1] ) ) {
			$preset_ids = [];
			foreach ( $matches[1] as $json ) {
				$data = json_decode( $json, true );
				if ( is_array( $data ) ) {
					foreach ( $data as $module => $entry ) {
						if ( isset( $entry['presetId'] ) && null !== $entry['presetId'] && ! empty( $entry['presetId'] ) ) {
							// Normalize preset ID array to individual IDs (handles stacked presets).
							$normalized_ids = GlobalPreset::normalize_preset_stack( $entry['presetId'] );
							$preset_ids     = array_merge( $preset_ids, $normalized_ids );
						}
						if ( ! empty( $entry['groupName'] ) ) {
							$group_names[] = $entry['groupName'];
						}
					}
				}
			}
			$results['groupPreset'] = $preset_ids;
		}

		// Extract module names and add default preset IDs.
		preg_match_all( $patterns['moduleName'], $content, $matches );
		$module_names = isset( $matches[1] ) ? $matches[1] : [];

		foreach ( $module_names as $name ) {
			if ( isset( $d5_presets['module'][ $name ]['default'] ) ) {
				$default_id = $d5_presets['module'][ $name ]['default'];
				if ( ! empty( $default_id ) && ! in_array( $default_id, $results['modulePreset'], true ) ) {
					// Check if default preset is edited (has non-empty attrs).
					$preset = $d5_presets['module'][ $name ]['items'][ $default_id ] ?? [];
					if ( ! empty( $preset['attrs'] ) || ! empty( $preset['styleAttrs'] ) || ! empty( $preset['renderAttrs'] ) ) {
						$results['modulePreset'][] = $default_id;
					}
				}
			}
		}

		// Add default group preset IDs.
		$all_group_names = array_merge( $group_names, array_keys( $d5_presets['group'] ?? [] ) );
		foreach ( $group_names as $name ) {
			if ( isset( $d5_presets['group'][ $name ]['default'] ) ) {
				$default_id = $d5_presets['group'][ $name ]['default'];
				if ( ! empty( $default_id ) && ! in_array( $default_id, $results['groupPreset'], true ) ) {
					// Check if default preset is edited.
					$preset = $d5_presets['group'][ $name ]['items'][ $default_id ] ?? [];
					if ( ! empty( $preset['attrs'] ) || ! empty( $preset['styleAttrs'] ) || ! empty( $preset['renderAttrs'] ) ) {
						$results['groupPreset'][] = $default_id;
					}
				}
			}
		}

		// Remove duplicates.
		$results['modulePreset'] = array_unique( $results['modulePreset'] );
		$results['groupPreset']  = array_unique( $results['groupPreset'] );

		return $results;
	}

	/**
	 * Get used global variable ids from the d5 post content.
	 * Match pattern => gvid-.
	 *
	 * @since 5.0.0
	 *
	 * @param string $content The post content.
	 *
	 * @return array
	 */
	protected static function _get_gvids_from_d5_content( $content ) {
		// https://regex101.com/r/LAN7kK/1 - Regex.
		$pattern = '/(gvid-[a-zA-Z0-9-_]+)/';
		preg_match_all( $pattern, $content, $matches );

		return isset( $matches[1] ) ? $matches[1] : [];
	}

	/**
	 * Extract global color IDs from D5 Gutenberg content.
	 *
	 * @since 5.0.0
	 *
	 * @param string $content The post content in Gutenberg format.
	 *
	 * @return array Array of unique global color IDs found in the content.
	 */
	protected static function _get_gcids_from_d5_content( $content ) {
		// Extract all gcid-* patterns from the content.
		$pattern = '/(gcid-[a-zA-Z0-9\-_]+)/';
		preg_match_all( $pattern, $content, $matches );

		return isset( $matches[1] ) ? array_unique( $matches[1] ) : [];
	}

	/**
	 * Get used global presets from the post content of d5 gutenberg format content.
	 * This function is basically recieve the all d5 presets and return the used presets,
	 * by checking the post content.
	 *
	 * So returned data will be like:
	 * ```php
	 * module => [
	 *   'divi/blurb' => [
	 *      'default' => 'wc873hxlff',
	 *      'items' => [
	 *         'wc873hxlff' => [
	 *         'id' => 'wc873hxlff',
	 *         'name' => 'Blurb Preset 1',
	 *         'attrs' => [
	 *            ...
	 *       ]
	 *    ],
	 *   ...
	 * ]
	 * ```
	 *
	 * @since 5.0.0
	 *
	 * @param array $used_presets The used presets data.
	 * @param array $d5_presets   The D5 presets data.
	 * @param int   $preset_id    The preset ID.
	 *
	 * @return void
	 */
	protected static function _prepare_used_module_presets_data_from_d5_content( &$used_presets, $d5_presets, $preset_id ) {
		// Initialize the 'module' key if it doesn't exist.
		if ( ! isset( $used_presets['module'] ) ) {
			$used_presets['module'] = [];
		}

		foreach ( $d5_presets['module'] as $module_name => $module_data ) {
			// Skip if the preset_id isn't in this module's items.
			if ( ! isset( $module_data['items'][ $preset_id ] ) ) {
				continue;
			}

			if ( ! isset( $used_presets['module'][ $module_name ] ) ) {
				$used_presets['module'][ $module_name ] = [
					'default' => $module_data['default'],
					'items'   => [],
				];
			}

			$used_presets['module'][ $module_name ]['items'][ $preset_id ] = $module_data['items'][ $preset_id ];

			// Extract nested group presets from this module preset.
			$preset_data = $module_data['items'][ $preset_id ];
			if ( isset( $preset_data['groupPresets'] ) && is_array( $preset_data['groupPresets'] ) ) {
				foreach ( $preset_data['groupPresets'] as $nested_preset_ref ) {
					if ( isset( $nested_preset_ref['presetId'] ) && ! empty( $nested_preset_ref['presetId'] ) ) {
						// Normalize preset ID array to individual IDs (handles stacked presets).
						$nested_preset_ids = GlobalPreset::normalize_preset_stack( $nested_preset_ref['presetId'] );
						foreach ( $nested_preset_ids as $nested_preset_id ) {
							self::_prepare_used_group_presets_data_from_d5_content( $used_presets, $d5_presets, $nested_preset_id );
						}
					}
				}
			}

			return;
		}
	}

	/**
	 * Get used global presets from the post content of d5 gutenberg format content.
	 * This function is basically recieve the all d5 presets and return the used presets,
	 * by checking the post content.
	 *
	 * @since 5.0.0
	 *
	 * @param array $used_presets The used presets data.
	 * @param array $d5_presets   The D5 presets data.
	 * @param int   $preset_id    The preset ID.
	 *
	 * @return void
	 */
	protected static function _prepare_used_group_presets_data_from_d5_content( &$used_presets, $d5_presets, $preset_id ) {
		// Initialize the 'group' key if it doesn't exist.
		if ( ! isset( $used_presets['group'] ) ) {
			$used_presets['group'] = [];
		}

		// Iterate through each group type.
		foreach ( $d5_presets['group'] as $group_name => $group_data ) {
			// Skip if the preset_id isn't in this group's items.
			if ( ! isset( $group_data['items'][ $preset_id ] ) ) {
				continue;
			}

			// Initialize the group entry if it doesn't exist.
			if ( ! isset( $used_presets['group'][ $group_name ] ) ) {
				$used_presets['group'][ $group_name ] = [
					'default' => $group_data['default'],
					'items'   => [],
				];
			}

			// Already processed this preset; avoid duplicate work and circular recursion.
			if ( isset( $used_presets['group'][ $group_name ]['items'][ $preset_id ] ) ) {
				return;
			}

			// Add the preset data to the items array.
			$used_presets['group'][ $group_name ]['items'][ $preset_id ] = $group_data['items'][ $preset_id ];

			// Recursively collect nested group presets referenced by this group preset.
			$preset_data = $group_data['items'][ $preset_id ];
			if ( isset( $preset_data['attrs']['groupPreset'] ) && is_array( $preset_data['attrs']['groupPreset'] ) ) {
				foreach ( $preset_data['attrs']['groupPreset'] as $nested_preset_ref ) {
					if ( ! is_array( $nested_preset_ref ) || ! isset( $nested_preset_ref['presetId'] ) || empty( $nested_preset_ref['presetId'] ) ) {
						continue;
					}

					// Normalize preset ID array to individual IDs (handles stacked presets).
					$nested_preset_ids = GlobalPreset::normalize_preset_stack( $nested_preset_ref['presetId'] );
					foreach ( $nested_preset_ids as $nested_preset_id ) {
						self::_prepare_used_group_presets_data_from_d5_content( $used_presets, $d5_presets, $nested_preset_id );
					}
				}
			}

			return;
		}
	}

	/**
	 * Extract global color IDs from presets.
	 *
	 * This method extracts global color IDs (gcid-*) from preset data by recursively
	 * searching through the array/object structure. This is more reliable than JSON
	 * encoding because it handles nested structures and escaped JSON strings
	 * (like $variable(...)) correctly.
	 *
	 * @since 5.0.0
	 *
	 * @param array $presets The presets data (module or group presets).
	 *
	 * @return array Array of unique global color IDs.
	 */
	protected static function _extract_global_color_ids_from_presets( $presets ) {
		if ( empty( $presets ) ) {
			return array();
		}

		return self::_extract_ids_recursive( $presets, '/(gcid-[a-zA-Z0-9\-_]+)/' );
	}

	/**
	 * Extract global variable IDs from presets.
	 *
	 * This method extracts global variable IDs (gvid-*) from preset data by recursively
	 * searching through the array/object structure. This is more reliable than JSON
	 * encoding because it handles nested structures and escaped JSON strings
	 * (like $variable(...)) correctly.
	 *
	 * @since 5.0.0
	 *
	 * @param array $presets The presets data (module or group presets).
	 *
	 * @return array Array of unique global variable IDs.
	 */
	protected static function _extract_global_variable_ids_from_presets( $presets ) {
		if ( empty( $presets ) || ! is_array( $presets ) ) {
			return array();
		}

		return self::_extract_ids_recursive( $presets, '/(gvid-[a-zA-Z0-9\-_]+)/' );
	}

	/**
	 * Recursively extract IDs matching a pattern from an array structure.
	 *
	 * This helper method searches through arrays, objects, and strings to find
	 * IDs matching the provided regex pattern. It handles nested structures
	 * and escaped JSON strings correctly.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed  $data    The data to search (array, object, or string).
	 * @param string $pattern The regex pattern to match IDs (must include capture group).
	 *
	 * @return array Array of unique IDs found.
	 */
	protected static function _extract_ids_recursive( $data, $pattern ) {
		$ids = array();

		if ( is_string( $data ) ) {
			// Extract IDs from string values.
			if ( preg_match_all( $pattern, $data, $matches ) ) {
				$ids = array_merge( $ids, $matches[1] );
			}
		} elseif ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				$ids = array_merge( $ids, self::_extract_ids_recursive( $value, $pattern ) );
			}
		} elseif ( is_object( $data ) ) {
			foreach ( $data as $value ) {
				$ids = array_merge( $ids, self::_extract_ids_recursive( $value, $pattern ) );
			}
		}

		return array_unique( $ids );
	}

	/**
	 * Update shortcode with onboarding preset.
	 *
	 * @since 4.26.1
	 *
	 * @param array $shortcode_object - The shortcode object to be updated.
	 * @param array $presets_lookup   - The lookup table for presets.
	 */
	protected function _update_shortcode_with_onboarding_preset( &$shortcode_object, $presets_lookup ) {
		// Load shortcode framework to proceed with the import.
		et_load_shortcode_framework();

		$global_presets_manager  = ET_Builder_Global_Presets_Settings::instance();
		$module_preset_attribute = ET_Builder_Global_Presets_Settings::MODULE_PRESET_ATTRIBUTE;
		$global_presets          = $global_presets_manager->get_global_presets();

		foreach ( $shortcode_object as &$module ) {
			$module_type = $global_presets_manager->maybe_convert_module_type( $module['type'], $module['attrs'] );

			if ( isset( $presets_lookup[ $module_type ] ) ) {
				$module_preset_id = et_()->array_get( $module, "attrs.{$module_preset_attribute}", 'default' );

				if ( 'default' === $module_preset_id ) {
					continue;
				}

				$module_preset_name = et_()->array_get( $presets_lookup, "{$module_type}.{$module_preset_id}", '' );

				if ( isset( $global_presets->$module_type ) ) {
					foreach ( $global_presets->$module_type->presets as $preset_id => $preset ) {
						if ( $preset->name === $module_preset_name ) {
							$module['attrs'][ $module_preset_attribute ] = $preset_id;
							break;
						}
					}
				}
			}

			if ( isset( $module['content'] ) && is_array( $module['content'] ) ) {
				$this->_update_shortcode_with_onboarding_preset( $module['content'], $presets_lookup );
			}
		}
	}

	/**
	 * Conver and import D5 presets from onboarding.
	 *
	 * 1. Retrieve all existing presets.
	 * 2. Convert new presets data if needed.
	 * 3. Create a lookup table for existing presets by name.
	 * 4. For each module type in converted presets:
	 *  a. If module type does not exist in all presets, initialize it.
	 *  b. For each incoming preset:
	 *      i. Generate new name with prefix.
	 *     ii. If preset with new name exists, remove the existing preset.
	 *    iii. Add incoming preset to current presets.
	 *  c. If not skipping default, update the default preset.
	 * 5. Save all presets data.
	 *
	 * @since 5.0.0
	 *
	 * @param array  $new_presets_d4 Array of new presets data.
	 * @param string $preset_prefix  Prefix to be added to the preset names.
	 * @param bool   $skip_default   Whether to skip updating the default preset key.
	 */
	protected function _convert_and_import_d5_presets_from_onboarding( $new_presets_d4, $preset_prefix, $skip_default ) {
		$all_presets = GlobalPreset::get_data();

		if ( empty( $all_presets ) ) {
			$all_presets = [
				'module' => [],
			];
		}

		$result = GlobalPreset::process_presets_for_import( $new_presets_d4, false );
		$converted_presets = $result['presets'];

		$preset_lookup = [];
		foreach ( $all_presets['module'] as $module_type => $exist_presets ) {
			foreach ( $exist_presets['items'] as $exist_preset_id => $exist_preset ) {
				$preset_lookup[ $module_type ][ $exist_preset['name'] ] = $exist_preset_id;
			}
		}

		foreach ( $converted_presets['module'] as $module_type => $incoming_presets ) {
			// If the existing presets do not have the new module type, add it.
			if ( ! isset( $all_presets['module'][ $module_type ] ) ) {
				$all_presets['module'][ $module_type ] = [
					'items'   => $incoming_presets['items'],
					'default' => $incoming_presets['default'],
				];

				continue;
			}

			foreach ( $incoming_presets['items'] as $preset_id => $incoming_preset ) {
				$new_name = $preset_prefix . ' ' . $incoming_preset['name'];

				// Check if a preset with the same name already exists using the lookup table.
				if ( isset( $preset_lookup[ $module_type ][ $new_name ] ) ) {
					$exist_preset_id = $preset_lookup[ $module_type ][ $new_name ];

					// During onboarding content import (skip_default=true), preserve existing
					// matching presets to keep default preset IDs stable across subsequent imports.
					if ( $skip_default ) {
						continue;
					}

					unset( $all_presets['module'][ $module_type ]['items'][ $exist_preset_id ] );
				}

				$incoming_preset['name'] = $new_name;

				// Add the incoming preset to the current presets.
				$all_presets['module'][ $module_type ]['items'][ $preset_id ] = $incoming_preset;
			}

			if ( ! $skip_default ) {
				$all_presets['module'][ $module_type ]['default'] = $incoming_presets['default'];
			}
		}

		GlobalPreset::save_data( $all_presets );
	}


	/**
	 * Imports Global Presets
	 *
	 * @since 4.0.10 Made public.
	 *
	 * @param array  $presets           - The Global Presets to be imported.
	 * @param bool   $is_temp_presets   - Whether the presets are temporary or not.
	 * @param bool   $override_defaults - Whether the default presets should be overridden.
	 * @param string $preset_prefix     - A prefix to be prepended to preset name of the preset being imported, if override_defaults is true.
	 * @param bool   $skip_default      - Whether updating the default preset key should be skipped.
	 *
	 * @return boolean
	 */
	public function import_global_presets(
		$presets,
		$is_temp_presets   = false,
		$override_defaults = false,
		$preset_prefix     = '',
		$skip_default = false
	) {
		if ( ! is_array( $presets ) ) {
			return false;
		}

		if ( $preset_prefix ) {
			$this->_convert_and_import_d5_presets_from_onboarding( $presets, $preset_prefix, $skip_default );

			return true;
		}

		// Handle D5 format with module/group presets.
		if ( isset( $presets['module'] ) || isset( $presets['group'] ) ) {
			// Use process_presets_for_import to properly merge presets and create default presets.
			// This ensures that modules/groups with imported presets but no default preset get one created.
			GlobalPreset::process_presets_for_import( $presets );

			return true;
		}

		// Load shortcode framework to proceed with the import.
		et_load_shortcode_framework();

		$all_modules            = ET_Builder_Element::get_modules();
		$module_presets_manager = ET_Builder_Global_Presets_Settings::instance();
		$global_presets         = $module_presets_manager->get_global_presets();
		$temp_presets           = $module_presets_manager->get_temp_presets();
		$presets_to_import      = array();

		foreach ( $presets as $module_type => $module_presets ) {
			$presets_to_import[ $module_type ] = array(
				'presets' => array(),
			);

			foreach ( $module_presets['presets'] as $preset_id => $preset ) {
				$presets_to_import[ $module_type ]['presets'][ $preset_id ] = array(
					'name'     => sanitize_text_field( $preset['name'] ),
					'created'  => time() * 1000,
					'updated'  => time() * 1000,
					'version'  => $preset['version'],
					'settings' => $preset['settings'],
				);
			}
		}

		if ( $is_temp_presets ) {
			et_update_option( ET_Builder_Global_Presets_Settings::GLOBAL_PRESETS_OPTION_TEMP, $presets_to_import );
		}

		$new_presets_d4 = array();

		// Process new presets before converting to D5 format.
		foreach ( $presets_to_import as $module_type => $module_presets ) {
			$new_presets_d4[ $module_type ] = array(
				'presets' => array(),
			);

			foreach ( $module_presets['presets'] as $preset_id => $preset ) {
				$new_presets_d4[ $module_type ]['presets'][ $preset_id ]             = array();
				$new_presets_d4[ $module_type ]['presets'][ $preset_id ]['name']     = sanitize_text_field( $preset['name'] );
				$new_presets_d4[ $module_type ]['presets'][ $preset_id ]['created']  = $preset['created'];
				$new_presets_d4[ $module_type ]['presets'][ $preset_id ]['updated']  = $preset['updated'];
				$new_presets_d4[ $module_type ]['presets'][ $preset_id ]['version']  = $preset['version'];
				$new_presets_d4[ $module_type ]['presets'][ $preset_id ]['settings'] = array();

				foreach ( $preset['settings'] as $setting_name => $value ) {
					$setting_name_sanitized = sanitize_text_field( $setting_name );
					$value_sanitized        = sanitize_text_field( $value );

					$new_presets_d4[ $module_type ]['presets'][ $preset_id ]['settings'][ $setting_name_sanitized ] = $value_sanitized;
				}

				// Inject Global colors into imported presets.
				$preset_settings = (array) $new_presets_d4[ $module_type ]['presets'][ $preset_id ]['settings'];
				$new_presets_d4[ $module_type ]['presets'][ $preset_id ]['settings'] = ET_Builder_Global_Presets_Settings::maybe_set_global_colors( $preset_settings );
			}
		}

		GlobalPreset::process_presets_for_import( $new_presets_d4 );

		return true;
	}

	/**
	 * Prepare to import non-duplicate presets.
	 *
	 * @since 4.26.0
	 *
	 * @param array $presets Presets to import.
	 *
	 * @return array
	 */
	public function prepare_to_import_non_duplicate_presets( $presets ) {
		$global_presets_manager = ET_Builder_Global_Presets_Settings::instance();
		$existing_presets       = $global_presets_manager->get_global_presets();
		$existing_names         = [];

		foreach ( $existing_presets as $module_slug => $data ) {
			foreach ( $data->presets as $preset ) {
				$existing_names[ $module_slug ][ trim( $preset->name ) ] = true;
			}
		}

		foreach ( $presets as $module_slug => $data ) {
			foreach ( $data['presets'] as $preset_id => $preset ) {
				if ( isset( $existing_names[ $module_slug ][ trim( $preset['name'] ) ] ) ) {
					unset( $presets[ $module_slug ]['presets'][ $preset_id ] );
				}
			}
		}

		return $presets;
	}

	/**
	 * Import global colors.
	 *
	 * @since 4.9.0
	 *
	 * @param array $incoming_global_colors Global Colors Array.
	 *
	 * @return void
	 */
	public function import_global_colors( $incoming_global_colors ) {
		$excluded_colors = array( 'gcid-primary-color', 'gcid-secondary-color', 'gcid-heading-color', 'gcid-body-color' );
		$global_colors   = array();
		$clean_incoming  = array();

		foreach ( $incoming_global_colors as $incoming_gcolor ) {
			$key = et_()->sanitize_text_fields( $incoming_gcolor[0] );

			// Skip excluded colors.
			if ( in_array( $key, $excluded_colors, true ) ) {
				continue;
			}

			array_push( $clean_incoming, $incoming_gcolor );
		}

		$converted_colors = GlobalData::get_imported_global_colors( $clean_incoming );

		// Ensure converted_colors is an array.
		if ( ! is_array( $converted_colors ) ) {
			$converted_colors = array();
		}

		// Get Global Data options.
		$global_data        = et_get_option( 'et_global_data', false );
		$full_global_colors = array();

		if ( false !== $global_data && is_array( $global_data ) && ! empty( $global_data['global_colors'] ) ) {
			$full_global_colors = $global_data['global_colors'];
		}

		$d5_global_colors = array_merge( $full_global_colors, $converted_colors );

		// Use already_sanitized=true to ensure merge logic is used instead of replace.
		GlobalData::set_global_colors( $d5_global_colors, true );
	}

	/**
	 * Import post.
	 *
	 * @since 2.7.0
	 *
	 * @param array $posts Array of data formatted by the portability exporter.
	 *
	 * @return bool
	 */
	protected function import_posts( $posts ) {
		/**
		 * Filters the array of builder layouts to import. Returning an empty value will
		 * short-circuit the import process.
		 *
		 * @since 3.0.99
		 *
		 * @param array $posts
		 */
		$posts = apply_filters( 'et_core_portability_import_posts', $posts );

		$imported_posts = array();

		if ( empty( $posts ) ) {
			return false;
		}

		foreach ( $posts as $post ) {
			if ( isset( $post['post_status'] ) && 'auto-draft' === $post['post_status'] ) {
				continue;
			}

			$fields_validatation = array(
				'ID'         => 'intval',
				'post_title' => 'sanitize_text_field',
				'post_type'  => 'sanitize_text_field',
			);

			if ( ! $post = $this->validate( $post, $fields_validatation ) ) {
				continue;
			}

			$layout_exists = self::layout_exists( $post['post_title'], $post['post_name'] );

			if ( $layout_exists && get_post_type( $layout_exists ) === $post['post_type'] ) {
				// Make sure the post is published.
				if ( 'publish' !== get_post_status( $layout_exists ) ) {
					wp_update_post( array(
						'ID'          => intval( $layout_exists ),
						'post_status' => 'publish',
					) );
				}

				$imported_posts[] = intval( $layout_exists );

				continue;
			}

			$post['import_id'] = $post['ID'];
			unset( $post['ID'] );

			$post['post_author'] = (int) get_current_user_id();

			// Insert or update post.
			$post_id = wp_insert_post( $post, true );

			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}

			$imported_posts[] = $post_id;

			// Insert and set terms.
			if ( isset( $post['terms'] ) && is_array( $post['terms'] ) ) {
				$processed_terms = array();

				foreach ( $post['terms'] as $term ) {
					$fields_validatation = array(
						'name'        => 'sanitize_text_field',
						'slug'        => 'sanitize_title',
						'taxonomy'    => 'sanitize_title',
						'parent'      => 'sanitize_title',
						'description' => 'wp_kses_post',
					);

					if ( ! $term = $this->validate( $term, $fields_validatation ) ) {
						continue;
					}

					if ( empty( $term['parent'] ) ) {
						$parent = 0;
					} else {
						if ( isset( $term['all_parents'] ) && ! empty( $term['all_parents'] ) ) {
							$this->restore_parent_categories( $term['all_parents'], $term['taxonomy'] );
						}

						$parent = term_exists( $term['parent'], $term['taxonomy'] );

						if ( is_array( $parent ) ){
							$parent = $parent['term_id'];
						}
					}

					if ( ! $insert = term_exists( $term['slug'], $term['taxonomy'] ) ) {
						$insert = wp_insert_term( $term['name'], $term['taxonomy'], array(
							'slug'        => $term['slug'],
							'description' => isset( $term['description'] ) ? $term['description'] : '',
							'parent'      => intval( $parent ),
						) );
					}

					if ( is_array( $insert ) && ! is_wp_error( $insert ) ) {
						$processed_terms[$term['taxonomy']][] = $term['slug'];
					}
				}

				// Set post terms.
				foreach ( $processed_terms as $taxonomy => $ids ) {
					wp_set_object_terms( $post_id, $ids, $taxonomy );
				}
			}

			// Insert or update post meta.
			if ( isset( $post['post_meta'] ) && is_array( $post['post_meta'] ) ) {
				foreach ( $post['post_meta'] as $meta_key => $meta ) {

					$meta_key = sanitize_text_field( $meta_key );

					if ( count( $meta ) < 2 ) {
						$meta = wp_kses_post( $meta[0] );
					} else {
						$meta = array_map( 'wp_kses_post', $meta );
					}

					update_post_meta( $post_id, $meta_key, $meta );
				}
			}

			// Assign new thumbnail if provided.
			if ( isset( $post['thumbnail'] ) ) {
				set_post_thumbnail( $post_id, $post['thumbnail'] );
			}
		}

		return $imported_posts;
	}

	/**
	 * Restore the categories hierarchy in library.
	 *
	 * @since 2.7.0
	 *
	 * @param array $parents_array    Array of parent categories data.
	 * @param string $taxonomy
	 */
	protected function restore_parent_categories( $parents_array, $taxonomy ) {
		foreach( $parents_array as $slug => $category_data ) {
			$current_category = term_exists( $slug, $taxonomy );

			if ( ! is_array( $current_category ) ) {
				$parent_id = 0 !== $category_data['parent'] ? term_exists( $category_data['parent'], $taxonomy ) : 0;
				wp_insert_term( $category_data['name'], $taxonomy, array(
					'slug'        => $slug,
					'description' => $category_data['description'],
					'parent'      => is_array( $parent_id ) ? $parent_id['term_id'] : $parent_id,
				) );
			} else if ( ( ! isset( $current_category['parent'] ) || 0 === $current_category['parent'] ) && 0 !== $category_data['parent'] ) {
				$parent_id = 0 !== $category_data['parent'] ? term_exists( $category_data['parent'], $taxonomy ) : 0;
				wp_update_term( $current_category['term_id'], $taxonomy, array( 'parent' => is_array( $parent_id ) ? $parent_id['term_id'] : $parent_id ) );
			}
		}
	}

	/**
	 * Generates UUIDs for the D5 presets to avoid collisions.
	 *
	 * @since 4.5.0
	 *
	 * @param array $global_presets - The Global Presets to be imported.
	 *
	 * @return array - The list of module types for which preset ids have been changed
	 */
	public function prepare_to_import_layout_presets( &$global_presets ) {
		$preset_rewrite_map     = array();
		$default_module_presets = array();
		$default_group_presets  = array();

		foreach ( [ 'module', 'group' ] as $component_type ) {
			if ( ! isset( $global_presets[ $component_type ] ) ) {
				continue;
			}

			foreach ( $global_presets[ $component_type ] as $category => &$category_data ) {
				$preset_rewrite_map[ $component_type ][ $category ] = array();
				$items     = $category_data['items'] ?? [];
				$new_items = array();

				// Get default preset ID.
				$current_default = $category_data['default'] ?? '';

				// Iterate through each preset, generate a new ID, and update the data.
				foreach ( $items as $preset_id => $preset ) {
					// Skip invalid items.
					if ( empty( $preset ) || ! is_array( $preset ) ) {
						continue;
					}

					// Check if preset ID exists in current presets.
					$existing_preset   = isset( $existing_presets[ $component_type ][ $category ]['items'][ $preset_id ] )
						? $existing_presets[ $component_type ][ $category ]['items'][ $preset_id ]
						: null;
					$is_default_preset = $preset_id === $current_default;

					// Use original ID unless conflicting.
					$new_id = ! empty( $existing_preset ) ? ET_Core_Data_Utils::uuid_v4() : $preset_id;

					// Update the default preset if it was changed.
					if ( $is_default_preset ) {
						if ( 'module' === $component_type ) {
							$default_module_presets[ $category ] = $new_id;
						} else {
							$default_group_presets[ $category ] = $new_id;
						}
					}

					// Track rewritten IDs.
					if ( $new_id !== $preset_id ) {
						$preset_rewrite_map[ $component_type ][ $category ][ $preset_id ] = $new_id;
					}

					// Update preset data.
					$preset_data               = $preset;
					$preset_data['id']         = $new_id; // Ensure id matches items key.
					$preset_data['name']       = ! empty( $existing_preset )
						? sprintf( __( '%s imported', 'et_builder' ), $preset_data['name'] ) : $preset_data['name'];

					// For module presets, moduleName should match the category (module type).
					// For group presets, moduleName should be preserved from the original preset
					// (it indicates which module created the group preset, e.g., 'divi/heading').
					if ( 'module' === $component_type ) {
						$preset_data['moduleName'] = $category;
					} else {
						// For group presets, preserve the original moduleName or fall back to category.
						$preset_data['moduleName'] = $preset['moduleName'] ?? $category;
					}

					$preset_data['type']       = $component_type;
					$preset_data['version']    = $preset_data['version'] ?? '5.0.0';
					$preset_data['created']    = time() * 1000;
					$preset_data['updated']    = time() * 1000;

					if ( 'group' === $component_type ) {
						$preset_data['groupName'] = $preset_data['groupName'] ?? $category;
						$preset_data['groupId']   = $preset_data['groupId'] ?? str_replace( 'divi/', 'module.decoration.', $category );
					}

					// Store in new items.
					$new_items[ $new_id ] = $preset_data;
				}

				// Default preset is set to empty string, so new defalt can be set everytime.
				$category_data['items']   = $new_items;
				$category_data['default'] = '';
			}
			// Clean up reference.
			unset( $category_data );
		}

		$group_rewrite_map = $preset_rewrite_map['group'] ?? array();
		if ( ! empty( $group_rewrite_map ) ) {
			$global_presets = $this->_remap_nested_group_preset_references( $global_presets, $group_rewrite_map );
		}

		return array(
			'preset_rewrite_map'     => $preset_rewrite_map,
			'default_module_presets' => $default_module_presets,
			'default_group_presets'  => $default_group_presets,
		);
	}

	/**
	 * Remaps nested group preset references after group preset IDs are rewritten.
	 *
	 * Handles both:
	 * - module presets: `items[*].groupPresets[*].presetId`
	 * - group presets: `items[*].attrs.groupPreset[*].presetId`
	 *
	 * @since 5.3.0
	 *
	 * @param array $global_presets D5 global presets payload.
	 * @param array $group_rewrite_map Group preset rewrite map keyed by group name.
	 *
	 * @return array Updated presets payload.
	 */
	protected function _remap_nested_group_preset_references( $global_presets, $group_rewrite_map ) {
		if ( ! is_array( $global_presets ) || ! is_array( $group_rewrite_map ) || empty( $group_rewrite_map ) ) {
			return $global_presets;
		}

		foreach ( $global_presets['module'] ?? array() as $module_name => $module_data ) {
			$items = $module_data['items'] ?? array();

			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $preset_id => $preset_data ) {
				if ( ! is_array( $preset_data ) ) {
					continue;
				}

				$group_refs = $preset_data['groupPresets'] ?? array();
				if ( ! is_array( $group_refs ) ) {
					continue;
				}

				$global_presets['module'][ $module_name ]['items'][ $preset_id ]['groupPresets'] = $this->_remap_group_preset_reference_map(
					$group_refs,
					$group_rewrite_map
				);
			}
		}

		foreach ( $global_presets['group'] ?? array() as $group_name => $group_data ) {
			$items = $group_data['items'] ?? array();

			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $preset_id => $preset_data ) {
				if ( ! is_array( $preset_data ) ) {
					continue;
				}

				$group_refs = $preset_data['attrs']['groupPreset'] ?? array();
				if ( ! is_array( $group_refs ) ) {
					continue;
				}

				$global_presets['group'][ $group_name ]['items'][ $preset_id ]['attrs']['groupPreset'] = $this->_remap_group_preset_reference_map(
					$group_refs,
					$group_rewrite_map
				);
			}
		}

		return $global_presets;
	}

	/**
	 * Remaps a group preset reference map by group name and preset ID.
	 *
	 * @since 5.3.0
	 *
	 * @param array $group_refs Group references keyed by group id.
	 * @param array $group_rewrite_map Group rewrite map keyed by group name.
	 *
	 * @return array Updated group references.
	 */
	protected function _remap_group_preset_reference_map( $group_refs, $group_rewrite_map ) {
		if ( ! is_array( $group_refs ) || ! is_array( $group_rewrite_map ) ) {
			return $group_refs;
		}

		foreach ( $group_refs as $group_id => $group_ref ) {
			if ( ! is_array( $group_ref ) ) {
				continue;
			}

			$group_name = $group_ref['groupName'] ?? '';
			$preset_id  = $group_ref['presetId'] ?? null;

			if ( empty( $group_name ) || ! isset( $group_rewrite_map[ $group_name ] ) || null === $preset_id ) {
				continue;
			}

			$id_map        = $group_rewrite_map[ $group_name ];
			$preset_id_arr = $this->_normalize_import_preset_ids_for_rewrite( $preset_id );

			if ( empty( $preset_id_arr ) ) {
				continue;
			}

			$remapped_ids = array_values(
				array_unique(
					array_map(
						function( $id ) use ( $id_map ) {
							return $id_map[ $id ] ?? $id;
						},
						$preset_id_arr
					)
				)
			);

			$group_refs[ $group_id ]['presetId'] = is_array( $preset_id ) ? $remapped_ids : ( $remapped_ids[0] ?? $preset_id );
		}

		return $group_refs;
	}

	/**
	 * Normalize imported preset IDs for rewrite mapping lookup.
	 *
	 * Keeps reserved IDs (like `_initial`) so they can be remapped.
	 *
	 * @since 5.3.0
	 *
	 * @param mixed $preset_id_value Preset ID value (string or stacked array).
	 *
	 * @return array
	 */
	protected function _normalize_import_preset_ids_for_rewrite( $preset_id_value ) {
		if ( is_string( $preset_id_value ) ) {
			$preset_id_value = trim( $preset_id_value );
			return '' === $preset_id_value ? array() : array( $preset_id_value );
		}

		if ( ! is_array( $preset_id_value ) ) {
			return array();
		}

		$preset_ids = array();
		foreach ( $preset_id_value as $preset_id ) {
			if ( ! is_scalar( $preset_id ) ) {
				continue;
			}

			$preset_id = trim( (string) $preset_id );
			if ( '' === $preset_id ) {
				continue;
			}

			$preset_ids[] = $preset_id;
		}

		return $preset_ids;
	}

	/**
	 * Injects the given Global Presets settings into the imported layout
	 *
	 * @since 4.5.0
	 *
	 * @param array $shortcode_object - The multidimensional array representing a page/module structure
	 * @param array $global_presets - The Global Presets to be imported
	 * @param array $preset_rewrite_map - The list of module types for which preset ids have been changed
	 */
	protected function rewrite_module_preset_ids( &$shortcode_object, $global_presets, $preset_rewrite_map ) {
		$global_presets_manager  = ET_Builder_Global_Presets_Settings::instance();
		$module_preset_attribute = ET_Builder_Global_Presets_Settings::MODULE_PRESET_ATTRIBUTE;

		foreach ( $shortcode_object as &$module ) {
			$module_type      = $global_presets_manager->maybe_convert_module_type( $module['type'], $module['attrs'] );
			$module_preset_id = et_()->array_get( $module, "attrs.{$module_preset_attribute}", 'default' );

			if ( $module_preset_id === 'default' ) {
				$module['attrs'][ $module_preset_attribute ] = et_()->array_get( $global_presets, "{$module_type}.default", 'default' );
			} else {
				if ( isset( $preset_rewrite_map[ $module_type ][ $module_preset_id ] ) ) {
					$module['attrs'][ $module_preset_attribute ] = $preset_rewrite_map[ $module_type ][ $module_preset_id ];
				} else {
					$module['attrs'][ $module_preset_attribute ] = et_()->array_get( $global_presets, "{$module_type}.default", 'default' );
				}
			}

			if ( isset( $module['content'] ) && is_array( $module['content'] ) ) {
				$this->rewrite_module_preset_ids( $module['content'], $global_presets, $preset_rewrite_map );
			}
		}
	}

	/**
	 * Injects global color ids into the imported layout
	 *
	 * @since 4.10.0
	 *
	 * @param array $data - The multidimensional array representing a import object structure.
	 */
	protected function _maybe_inject_gcid( &$data, &$gcolors = null ) {
		foreach ( $data as $post_id => &$post_data ) {
			// Get post content for format detection.
			$post_content = is_array( $post_data ) ? $post_data['post_content'] : $post_data;

			// Skip shortcode processing for D5 Gutenberg format - global colors are already in the JSON.
			if ( ! empty( $post_content ) && BlockParser::has_any_divi_block( $post_content ) ) {
				continue;
			}

			// Process D4 shortcode format.
			if ( is_array( $post_data ) ) {
				$shortcode_object = et_fb_process_shortcode( $post_data['post_content'] );
				$this->_inject_gcid( $shortcode_object, $gcolors );
				$data[ $post_id ]['post_content'] = et_fb_process_to_shortcode( $shortcode_object, array(), '', false );
			} else {
				$shortcode_object = et_fb_process_shortcode( $post_data );
				$this->_inject_gcid( $shortcode_object, $gcolors );
				$data[ $post_id ] = et_fb_process_to_shortcode( $shortcode_object, array(), '', false );
			}
		}

		unset( $post_data );

		return $data;
	}

	/**
	 * Process and inject global color ids into the shortcode
	 *
	 * @since 4.10.0
	 *
	 * @param array $shortcode_object - The multidimensional array representing a page/module structure.
	 */
	protected function _inject_gcid( &$shortcode_object, &$global_colors ) {
		foreach ( $shortcode_object as &$module ) {
			// No global colors set for this module.
			if ( ! empty( $module['attrs']['global_colors_info'] ) && ! empty( $global_colors ) ) {
				$colors_array = json_decode( $module['attrs']['global_colors_info'], true );

				if ( ! empty( $colors_array ) ) {
					foreach ( $colors_array as $color_id => $attrs_array ) {
						if ( ! empty( $attrs_array ) ) {
							// Get settings for this global color.
							$color = '';
							foreach ( $global_colors as $gcid ) {
								if ( $color_id === $gcid[0] && 'yes' === $gcid[1]['active'] ) {
									$color = $gcid[1]['color'];
								}
							}

							foreach ( $attrs_array as $attr_name ) {
								if ( isset( $module['attrs'][ $attr_name ] ) && '' !== $module['attrs'][ $attr_name ] ) {
									// Match substring (needed for attrs like gradient stops).
									$module['attrs'][ $attr_name ] = str_replace( $color, $color_id, $module['attrs'][ $attr_name ] );
								}
							}
						}
					}
				}
			}

			if ( isset( $module['content'] ) && is_array( $module['content'] ) ) {
				$this->_inject_gcid( $module['content'], $global_colors );
			}
		}
	}

	/**
	 * Injects the given Global Presets settings into the imported layout
	 *
	 * @since 3.26
	 *
	 * @param array $shortcode_object - The multidimensional array representing a page/module structure
	 * @param array $global_presets   - The Global Presets to be applied
	 */
	protected function _apply_global_presets( &$shortcode_object, $global_presets ) {
		$global_presets_manager  = ET_Builder_Global_Presets_Settings::instance();
		$module_preset_attribute = ET_Builder_Global_Presets_Settings::MODULE_PRESET_ATTRIBUTE;

		foreach ( $shortcode_object as &$module ) {
			$module_type = $global_presets_manager->maybe_convert_module_type( $module['type'], $module['attrs'] );

			if ( isset( $global_presets[ $module_type ] ) ) {
				$default_preset_id = et_()->array_get( $global_presets, "{$module_type}.default", null );
				$module_preset_id  = et_()->array_get( $module, "attrs.{$module_preset_attribute}", $default_preset_id );

				if ( 'default' === $module_preset_id ) {
					$module_preset_id = $default_preset_id;
				}

				$preset_settings = array();

				if ( isset( $global_presets[ $module_type ]['presets'][ $module_preset_id ] ) ) {
					$preset_settings = $global_presets[ $module_type ]['presets'][ $module_preset_id ]['settings'];
				} else {
					if ( isset( $global_presets[ $module_type ]['presets'][ $default_preset_id ]['settings'] ) ) {
						$preset_settings = $global_presets[ $module_type ]['presets'][ $default_preset_id ]['settings'];
					}
				}

				$merged_global_colors_info = array();

				if ( isset( $module['attrs']['global_colors_info'] ) ) {
					// Retrive global_colors_info from post meta, which saved as string[][].
					$gc_info_prepared = str_replace(
						array( '&#91;', '&#93;' ),
						array( '[', ']' ),
						$module['attrs']['global_colors_info']
					);

					$used_global_colors        = json_decode( $gc_info_prepared, true );
					$merged_global_colors_info = $used_global_colors;
				}

				// Merge Global Colors from preset.
				if ( isset( $preset_settings['global_colors_info'] ) ) {
					$preset_global_colors = json_decode( $preset_settings['global_colors_info'], true );

					if ( ! empty( $preset_global_colors ) ) {
						foreach ( $preset_global_colors as $color_id => $settings_list ) {
							if ( ! empty( $settings_list ) ) {
								if ( isset( $used_global_colors[ $color_id ] ) ) {
									$merged_global_colors_info[ $color_id ] = array_merge( $used_global_colors[ $color_id ], $settings_list );
								} else {
									$merged_global_colors_info[ $color_id ] = $settings_list;
								}

								foreach ( $settings_list as $setting_name ) {
									$preset_settings[ $setting_name ] = $color_id;
								}
							}
						}
					}
				}

				$module['attrs']                       = array_merge( $preset_settings, $module['attrs'] );
				$module['attrs']['global_colors_info'] = wp_json_encode( $merged_global_colors_info );
			}

			if ( isset( $module['content'] ) && is_array( $module['content'] ) ) {
				$this->_apply_global_presets( $module['content'], $global_presets );
			}
		}
	}

	/**
	 * Restrict data according the argument registered.
	 *
	 * @since 2.7.0
	 *
	 * @param array  $data   Array of data the query is applied on.
	 * @param string $method Whether data should be set or reset. Accepts 'set' or 'unset' which is
	 *                       should be used when treating existing data in the db.
	 *
	 * @return array
	 */
	protected function apply_query( $data, $method ) {
		$operator = ( $method === 'set' ) ? true : false;

		foreach ( $data as $id => $value ) {
			if ( ! empty( $this->instance->exclude ) && isset( $this->instance->exclude[$id] ) === $operator ) {
				unset( $data[$id] );
			}

			if ( ! empty( $this->instance->include ) && isset( $this->instance->include[$id] ) === ! $operator ) {
				unset( $data[$id] );
			}
		}

		return $data;
	}

	/**
	 * Serialize images in chunks.
	 *
	 * @since 4.0
	 *
	 * @param array $images
	 * @param string $method Method applied on images.
	 * @param string $id Unique ID to use for temporary files.
	 * @param integer $chunk
	 *
	 * @return array
	 */
	protected function chunk_images( $images, $method, $id, $chunk = 0 ) {
		$images_per_chunk = 5;
		$chunks           = 1;

		/**
		 * Filters whether or not images in the file being imported should be paginated.
		 *
		 * @since 3.0.99
		 *
		 * @param bool $paginate_images Default `true`.
		 */
		$paginate_images = apply_filters( 'et_core_portability_paginate_images', true );

		if ( $paginate_images && count( $images ) > $images_per_chunk ) {
			$chunks       = ceil( count( $images ) / $images_per_chunk );
			$slice        = $images_per_chunk * $chunk;
			$images       = array_slice( $images, $slice, $images_per_chunk );
			$images       = $this->$method( $images );
			$filesystem   = $this->get_filesystem();
			$temp_file_id = sanitize_file_name( "images_{$id}" );
			$temp_file    = $this->temp_file( $temp_file_id, 'et_core_export' );
			$temp_images  = json_decode( $filesystem->get_contents( $temp_file ), true );

			if ( is_array( $temp_images ) ) {
				$images = array_merge( $temp_images, $images );
			}

			if ( $chunk + 1 < $chunks ) {
				$filesystem->put_contents( $temp_file, wp_json_encode( (array) $images ) );
			} else {
				$this->delete_temp_files( 'et_core_export', array( $temp_file_id => $temp_file ) );
			}
		} else {
			$images = $this->$method( $images );
		}

		return array(
			'ready'  => $chunk + 1 >= $chunks,
			'chunks' => $chunks,
			'images' => $images,
		);
	}

	/**
	 * Paginate images processing.
	 *
	 * @since    1.0.0
	 *
	 * @param        $images
	 * @param string $method    Method applied on images.
	 * @param int    $timestamp Timestamp used to store data upon pagination.
	 *
	 * @return array
	 * @internal param array $data Array of images.
	 */
	protected function maybe_paginate_images( $images, $method, $timestamp ) {
		et_core_nonce_verified_previously();

		$page = isset( $_POST['page'] ) ? (int) $_POST['page'] : 1;
		$result = $this->chunk_images( $images, $method, $timestamp, max( $page - 1, 0 ) );

		if ( ! $result['ready'] ) {
			wp_send_json( array(
				'page'        => $page,
				'total_pages' => $result['chunks'],
				'timestamp'   => $timestamp,
			) );
		}

		return $result['images'];
	}

	/**
	 * Get all thumbnail images in the data given.
	 *
	 * @since 4.7.4
	 *
	 * @param array $data Array of data.
	 *
	 * @return array
	 */
	protected function _get_thumbnail_images( $data ) {
		$thumbnails = array();

		foreach ( $data as $post_data ) {
			// If post has thumbnail.
			if ( ! empty( $post_data->post_meta ) && ! empty( $post_data->post_meta->_thumbnail_id ) ) {
				$post_thumbnail = get_the_post_thumbnail_url( $post_data->ID );

				// If thumbnail image found in the WP Media library.
				if ( $post_thumbnail ) {
					$thumbnail_id    = (int) $post_data->post_meta->_thumbnail_id[0];
					$thumbnail_image = $this->encode_images( array( $thumbnail_id ) );

					$thumbnails[ $thumbnail_id ] = $thumbnail_image;
				}
			}
		}

		return $thumbnails;
	}

	/**
	 * Get all images in the data given.
	 *
	 * @since 2.7.0
	 *
	 * @param array $data  Array of data.
	 * @param bool  $force Set whether the value should be added by force. Usually used for image ids.
	 *
	 * @return array
	 */
	protected function get_data_images( $data, $force = false ) {
		if ( empty( $data ) ) {
			return array();
		}

		$images     = array();
		$images_src = array();
		$basenames  = array(
			'src',
			'image_url',
			'background_image',
			'image',
			'url',
			'bg_img_?\d?',
		);
		$suffixes  = array(
			'__hover',
			'_tablet',
			'_phone'
		);

		foreach ( $basenames as $basename ) {
			$images_src[] = $basename;
			foreach ( $suffixes as $suffix ) {
				$images_src[] = $basename . $suffix;
			}
		}

		foreach ( $data as $value ) {
			// If the $value is an object and there is no post_content property,
			// it's unlikely to contain any image data so we can continue with the next iteration.
			if ( is_object( $value ) && ! property_exists( $value, 'post_content' ) ) {
				continue;
			}

			if ( is_array( $value ) || is_object( $value ) ) {
				// If the $value contains the post_content property, set $value to use
				// this object's property value instead of the entire object.
				if ( is_object( $value ) && property_exists( $value, 'post_content' ) ) {
					$value = $value->post_content;
				}

				$images = array_merge( $images, $this->get_data_images( (array) $value ) );
				continue;
			}

			// Extract images from D5 formatted content.
			// D5 format uses HTML comments similar to this one to store the blocks:
			// <!-- wp:divi/section [module JSON goes here] -->
			// We test the passed value against the "<!-- wp:" string.
			$maybe_gutenberg_format = preg_match( '/<!-- wp:/', $value );

			// If there is a match the content is tested for all the image attributes in JSON format.
			// The regex tests for all the attributes in the $images_src array.
			// For example: "src":"https://url-goes-here/image.png"
			// $matches[2] holds an array of all the image URLS matches this way.
			// Reference: https://regex101.com/r/riATt3/1.
			if ( $maybe_gutenberg_format && preg_match_all( '/"(' . implode( '|', $images_src ) . ')":"(.*?)"/i', $value, $matches ) && $matches[2] ) {
				$images = array_merge( array_unique( $matches[2] ), $images );
			}

			// Extract images from HTML or shortcodes.
			if ( preg_match_all( '/(' . implode( '|', $images_src ) . ')="(?P<src>\w+[^"]*)"/i', $value, $matches ) ) {
				foreach ( array_unique( $matches['src'] ) as $key => $src ) {
					$images = array_merge( $images, $this->get_data_images( array( $key => $src ) ) );
				}
			}

			// Extract images from shortcodes gallery.
			if ( preg_match_all( '/gallery_ids="(?P<ids>\w+[^"]*)"/i', $value, $matches ) ) {
				foreach ( array_unique( $matches['ids'] ) as $galleries ) {
					$explode = explode( ',', str_replace( ' ', '', $galleries ) );

					foreach ( $explode as $image_id ) {
						$images = array_merge( $images, $this->get_data_images( array( (int) $image_id ), true ) );
					}
				}
			}

			if ( preg_match( '/^.+?\.(jpg|jpeg|jpe|png|gif|svg|webp)/', $value, $match ) || $force ) {
				$basename = basename( $value );

				// Skip if the value is not a valid URL or an image ID (integer).
				if ( ! ( wp_http_validate_url( $value ) || is_int( $value ) ) ) {
					continue;
				}

				// Skip if the images array already contains the value to avoid duplicates.
				if ( isset( $images[$value] ) ) {
					continue;
				}

				$images[$value] = $value;
			}
		}

		return $images;
	}

	/**
	 * Get the attachment post id for the given url.
	 *
	 * @since 3.22.3
	 *
	 * @param string $url The url of an attachment file.
	 *
	 * @return int
	 */
	protected function _get_attachment_id_by_url( $url ) {
		global $wpdb;

		// Remove any thumbnail size suffix from the filename and use that as a fallback.
		$fallback_url = preg_replace( '/-\d+x\d+(\.[^.]+)$/i', '$1', $url );

		// Scenario: Trying to find the attachment for a file called x-150x150.jpg.
		// 1. Since WordPress adds the -150x150 suffix for thumbnail sizes we cannot be
		//    sure if this is an attachment or an attachment's generated thumbnail.
		// 2. Since both x.jpg and x-150x150.jpg can be uploaded as separate attachments
		//    we must decide which is a better match.
		// 3. The above is why we order by guid length and use the first result.
		$attachments_query = $wpdb->prepare( "
			SELECT id
			FROM $wpdb->posts
			WHERE `post_type` = %s
				AND `guid` IN ( %s, %s )
			ORDER BY CHAR_LENGTH( `guid` ) DESC
		", 'attachment', esc_url_raw( $url ), esc_url_raw( $fallback_url ) );

		$attachment_id = (int) $wpdb->get_var( $attachments_query );

		return $attachment_id;
	}

	/**
	 * Encode image in a base64 format.
	 *
	 * @since 2.7.0
	 *
	 * @param array $images Array of data for which images need to be encoded if any.
	 *
	 * @return array
	 */
	protected function encode_images( $images ) {
		$encoded = array();

		foreach ( $images as $url ) {
			$id = 0;
			$image = '';

			if ( is_int( $url ) ) {
				$id = $url;
				$url = wp_get_attachment_url( $id );
			} else {
				$id = $this->_get_attachment_id_by_url( $url );
			}

			if ( $id > 0 ) {
				$image = $this->_encode_attachment_image( $id );
			}

			if ( empty( $image ) ) {
				// Case 1: No attachment found.
				// Case 2: Attachment found, but file does not exist (may be stored on a CDN, for example).
				$image = $this->_encode_remote_image( $url );
			}

			if ( empty( $image ) ) {
				// All fetching methods have failed - bail on encoding.
				continue;
			}

			$encoded[ $url ] = array(
				'encoded' => $image,
				'url'     => $url,
			);

			// Add image id for replacement purposes.
			if ( $id > 0 ) {
				$encoded[ $url ]['id'] = $id;
			}
		}

		return $encoded;
	}

	/**
	 * Encode an image attachment.
	 *
	 * @since 3.22.3
	 *
	 * @param int $id
	 *
	 * @return string
	 */
	protected function _encode_attachment_image( $id ) {
		/**
		 * @var WP_Filesystem_Base $wp_filesystem
		 */
		global $wp_filesystem;

		if ( ! current_user_can( 'read_post', $id ) ) {
			return '';
		}

		$file = get_attached_file( $id );

		if ( ! $wp_filesystem->exists( $file ) ) {
			return '';
		}

		$image = $wp_filesystem->get_contents( $file );

		if ( empty( $image ) ) {
			return '';
		}

		return base64_encode( $image );
	}

	/**
	 * Encode a remote image.
	 *
	 * @since 3.22.3
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	protected function _encode_remote_image( $url ) {
		$request = wp_remote_get( esc_url_raw( $url ), array(
			'timeout'     => 2,
			'redirection' => 2,
		) );

		if ( ! is_array( $request ) || is_wp_error( $request ) ) {
			return '';
		}

		if ( ! self::$_->includes( $request['headers']['content-type'], 'image' ) ) {
			return '';
		}

		$image = wp_remote_retrieve_body( $request );

		if ( ! $image ) {
			return '';
		}

		return base64_encode( $image );
	}

	/**
	 * Decode base64 formatted image and upload it to WP media.
	 *
	 * @since 2.7.0
	 *
	 * @param array $images Array of encoded images which needs to be uploaded.
	 *
	 * @return array
	 */
	protected function upload_images( $images ) {
		$filesystem = $this->set_filesystem();

		/**
		 * Filters whether or not to allow duplicate images to be uploaded
		 * during Portability import.
		 *
		 * @since 4.14.8
		 *
		 * @param bool $allow_duplicates Whether or not to allow duplicates. Default is `false`.
		 */
		$allow_duplicates = apply_filters( 'et_core_portability_import_attachment_allow_duplicates', false );

		foreach ( $images as $key => $image ) {
			if (
				! is_array( $image ) ||
				empty( $image['url'] ) ||
				empty( $image['encoded'] ) ||
				! is_string( $image['encoded'] )
			) {
				// Skip malformed image payloads to avoid noisy runtime warnings.
				continue;
			}

			$basename = sanitize_file_name( wp_basename( $image['url'] ) );
			$id       = 0;
			$url      = '';
			$decoded  = base64_decode( $image['encoded'] );

			if ( ! $allow_duplicates ) {
				$attachments = get_posts( array(
					'posts_per_page' => -1,
					'post_type'      => 'attachment',
					'meta_key'       => '_wp_attached_file',
					'meta_value'     => pathinfo( $basename, PATHINFO_FILENAME ),
					'meta_compare'   => 'LIKE',
				) );

				// Avoid duplicates.
				if ( ! is_wp_error( $attachments ) && ! empty( $attachments ) ) {
					foreach ( $attachments as $attachment ) {
						$attachment_url = wp_get_attachment_url( $attachment->ID );
						$file           = get_attached_file( $attachment->ID );
						$filename       = sanitize_file_name( wp_basename( $file ) );

						// Use existing image only if the content matches.
						if ( $filesystem->get_contents( $file ) === $decoded ) {
							$id  = isset( $image['id'] ) ? $attachment->ID : 0;
							$url = $attachment_url;

							break;
						}
					}
				}
			}

			// Create new image.
			if ( empty( $url ) ) {
				$temp_file = wp_tempnam();
				$filesystem->put_contents( $temp_file, $decoded );
				$filetype = wp_check_filetype_and_ext( $temp_file, $basename );

				if ( ! $allow_duplicates && ! empty( $attachments ) && ! is_wp_error( $attachments ) ) {
					// Avoid further duplicates if the proper_filename matches an existing image.
					if ( isset( $filetype['proper_filename'] ) && $filetype['proper_filename'] !== $basename ) {
						foreach ( $attachments as $attachment ) {
							$attachment_url = wp_get_attachment_url( $attachment->ID );
							$file           = get_attached_file( $attachment->ID );
							$filename       = sanitize_file_name( wp_basename( $file ) );

							if ( isset( $filename ) && $filename === $filetype['proper_filename'] ) {
								// Use existing image only if the basenames and content match.
								if ( $filesystem->get_contents( $file ) === $filesystem->get_contents( $temp_file ) ) {
									$id  = isset( $image['id'] ) ? $attachment->ID : 0;
									$url = $attachment_url;

									$filesystem->delete( $temp_file );

									break;
								}
							}
						}
					}
				}

				$file = array(
					'name'     => $basename,
					'tmp_name' => $temp_file,
				);


				$upload        = media_handle_sideload( $file );
				$attachment_id = is_wp_error( $upload ) ? 0 : $upload;

				/**
				 * Fires when image attachments are created during portability import.
				 *
				 * @since 4.14.6
				 *
				 * @param int $attachment_id The attachment id or 0 if attachment upload failed.
				 */
				do_action( 'et_core_portability_import_attachment_created', $attachment_id );

				if ( ! is_wp_error( $upload ) ) {
					// Set the replacement as an id if the original image was set as an id (for gallery).
					$id = isset( $image['id'] ) ? $upload : 0;
					$url = wp_get_attachment_url( $upload );
				} else {
					// Make sure the temporary file is removed if media_handle_sideload didn't take care of it.
					$filesystem->delete( $temp_file );
				}
			}

			// Only declare the replace if a url is set.
			if ( $id > 0 ) {
				$images[$key]['replacement_id'] = $id;
			}

			if ( ! empty( $url ) ) {
				$images[$key]['replacement_url'] = $url;
			}

			unset( $url );
		}

		return $images;
	}

	/**
	 * Replace encoded image url with a real url
	 *
	 * @param $subject     - The string to perform replacing for
	 * @param array $image - The image settings
	 *
	 * @return string|string[]|null
	 */
	protected function replace_image_url( $subject, $image ) {
		$maybe_gutenberg_format = preg_match( '/<!-- wp:/', $subject );

		if ( isset( $image['replacement_id'] ) && isset( $image['id'] ) ) {
			$search      = $image['id'];
			$replacement = $image['replacement_id'];

			if ( $maybe_gutenberg_format ) {
				// D5 Gutenberg format: "galleryIds":{"desktop":{"value":"23,22,21"}} (comma-separated string in JSON).
				// Use word boundaries to match complete IDs only (prevents "12" matching within "123").
				// Regex101 link: https://regex101.com/r/0i8W9Y/2.
				$subject = preg_replace( "/(galleryIds.*?\"[^\"]*\".*?\"value\":\".*?\\b){$search}(\\b.*?\")/", "\${1}{$replacement}\${2}", $subject );
			} else {
				// D4 shortcode format: gallery_ids="123,456,789" (comma-separated string).
				// Use word boundaries to match complete IDs only (prevents "12" matching within "123").
				// Regex101 link: https://regex101.com/r/RAN4II/2 .
				$subject = preg_replace( "/(\\bgallery_ids=\"[^\"]*\\b){$search}(\\b[^\"]*\")/", "\${1}{$replacement}\${2}", $subject );
			}
		}

		if ( isset( $image['url'] ) && isset( $image['replacement_url'] ) && $image['url'] !== $image['replacement_url'] ) {
			$search      = $image['url'];
			$replacement = $image['replacement_url'];
			$subject     = str_replace( $search, $replacement, $subject );
		}

		return $subject;
	}

	/**
	 * Replace image urls with newly uploaded images.
	 *
	 * @since 2.7.0
	 *
	 * @param array $images Array of new images uploaded.
	 * @param array $data   Array of for which images url needs to be replaced.
	 *
	 * @return array|mixed|object
	 */
	protected function replace_images_urls( $images, $data ) {
		foreach ( $data as $post_id => &$post_data ) {
			foreach ( $images as $image ) {
				if ( is_array( $post_data ) ) {
					foreach ( $post_data as $post_param => &$param_value ) {
						if ( ! is_array( $param_value ) ) {
							$data[ $post_id ][ $post_param ] = $this->replace_image_url( $param_value, $image );
						}
					}
					unset($param_value);
				} else {
					$data[ $post_id ] = $this->replace_image_url( $post_data, $image );
				}
			}
		}
		unset($post_data);

		return $data;
	}

	/**
	 * Validate data and remove any malicious code.
	 *
	 * @since 2.7.0
	 *
	 * @param array $data              Array of data which needs to be validated.
	 * @param array $fields_validation Array of field and validation callback.
	 *
	 * @return array|bool
	 */
	protected function validate( $data, $fields_validation = array() ) {
		if ( ! is_array( $data ) ) {
			return false;
		}

		foreach ( $data as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				if ( isset( $value['key'], $value['value'] ) && '_et_pb_custom_css' === $value['key'] ) {
					// Custom CSS needs sanitization to preserve escape sequences.
					$value['value'] = et_core_sanitize_custom_css_meta_value( $value['value'] );

					$data[ $key ] = $value;
					continue;
				}

				$data[$key] = $this->validate( $value, $fields_validation );
			} else {
				if ( isset( $fields_validation[$key] ) ) {
					// @phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
					$data[$key] = call_user_func( $fields_validation[$key], $value );
				} else {
					if ( current_user_can( 'unfiltered_html' ) ) {
						$data[ $key ] = $value;
					} else {
						$data[ $key ] = wp_kses_post( $value );
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Filters a variable with string filter
	 *
	 * @param mixed $data - Value to filter.
	 *
	 * @return mixed
	 */
	protected function _filter_post_data( $data ) {
		return filter_var( $data, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES );
	}

	/**
	 * Prevent import and export timeout or memory failure.
	 *
	 * @since 2.7.0
	 *
	 * It doesn't need to be reset as in both case the request exit.
	 */
	protected function prevent_failure() {
		@set_time_limit( 0 );

		// Increase memory which is safe at this stage of the request.
		if ( et_core_get_memory_limit() < 256 ) {
			@ini_set( 'memory_limit', '256M' );
		}
	}

	/**
	 * Set WP filesystem to direct. This should only be use to create a temporary file.
	 *
	 * @since 2.7.0
	 *
	 * It is safe to do so since the created file is removed immediately after import. The method does'nt have
	 * to be reset since the ajax query is exited.
	 */
	protected function set_filesystem() {
		global $wp_filesystem;

		add_filter( 'filesystem_method', array( $this, 'replace_filesystem_method' ) );
		WP_Filesystem();

		return $wp_filesystem;
	}

	/**
	 * Proxy method for set_filesystem() to avoid calling it multiple times.
	 *
	 * @since 4.0
	 *
	 * @return WP_Filesystem_Direct
	 */
	protected function get_filesystem() {
		static $filesystem = null;

		if ( null === $filesystem ) {
			$filesystem = $this->set_filesystem();
		}

		return $filesystem;
	}

	/**
	 * Check if a temporary file is register. Returns temporary file if it exists.
	 *
	 * @since 4.0 Made method public.
	 *
	 * @param string $id    Unique id used when the temporary file was created.
	 * @param string $group Group name in which files are grouped.
	 *
	 * @return bool|string
	 */
	public function has_temp_file( $id, $group ) {
		$temp_files = get_option( '_et_core_portability_temp_files', array() );

		if ( isset( $temp_files[$group][$id] ) && file_exists( $temp_files[$group][$id] ) ) {
			return $temp_files[$group][$id];
		}

		return false;
	}

	/**
	 * Create a temp file and register it.
	 *
	 * @since 2.7.0
	 * @since 4.0 Made method public. Added $content parameter.
	 *
	 * @param string      $id        Unique id reference for the temporary file.
	 * @param string      $group     Group name in which files are grouped.
	 * @param string|bool $temp_file Path to the temporary file. False create a new temporary file.
	 *
	 * @return bool|string
	 */
	public function temp_file( $id, $group, $temp_file = false, $content = '' ) {
		$temp_files = get_option( '_et_core_portability_temp_files', array() );

		if ( ! isset( $temp_files[$group] ) ) {
			$temp_files[$group] = array();
		}

		if ( isset( $temp_files[$group][$id] ) && file_exists( $temp_files[$group][$id] ) ) {
			return $temp_files[$group][$id];
		}

		$temp_file = $temp_file ? $temp_file : wp_tempnam();
		$temp_files[$group][$id] = $temp_file;

		update_option( '_et_core_portability_temp_files', $temp_files, false );

		if ( ! empty( $content ) ) {
			$this->get_filesystem()->put_contents( $temp_file, $content );
		}

		return $temp_file;
	}

	/**
	 * Get temp file contents or an empty string if it does not exist.
	 *
	 * @since 4.0
	 *
	 * @param string $id    Unique id used when the temporary file was created.
	 * @param string $group Group name in which files are grouped.
	 *
	 * @return string
	 */
	public function get_temp_file_contents( $id, $group ) {
		$file = $this->has_temp_file( $id, $group );

		if ( ! $file ) {
			return '';
		}

		$content = $this->get_filesystem()->get_contents( $file );

		return $content ? $content : '';
	}

	/**
	 * Delete all the temp files.
	 *
	 * @since 2.7.0
	 *
	 * @param bool|string $group         Group name in which files are grouped. Set to true to remove all groups and files.
	 * @param array       $defined_files Array or temoporary files to delete. No argument deletes all temp files.
	 */
	public function delete_temp_files( $group = false, $defined_files = false ) {
		$filesystem = $this->set_filesystem();
		$temp_files = get_option( '_et_core_portability_temp_files', array() );

		// Remove all temp files accross all groups if group is true.
		if ( $group === true ) {
			foreach( $temp_files as $group_id => $_group ) {
				$this->delete_temp_files( $group_id );
			}
		}

		if ( ! isset( $temp_files[$group] ) ) {
			return;
		}

		$delete_files = ( is_array( $defined_files ) && ! empty( $defined_files ) ) ? $defined_files : $temp_files[$group];

		foreach ( $delete_files as $id => $temp_file ) {
			if ( isset( $temp_files[$group][$id] ) && $filesystem->delete( $temp_files[$group][$id] ) ) {
				unset( $temp_files[$group][$id] );
			}
		}

		if ( empty( $temp_files[$group] ) ) {
			unset( $temp_files[$group] );
		}

		if ( empty( $temp_files ) ) {
			delete_option( '_et_core_portability_temp_files' );
		} else {
			update_option( '_et_core_portability_temp_files', $temp_files, false );
		}
	}

	/**
	 * Set WP filesystem method to direct.
	 *
	 * @since 2.7.0
	 */
	public function replace_filesystem_method() {
		return 'direct';
	}

	/**
	 * Get timestamp or create one if it isn't set.
	 *
	 * @since 2.7.0
	 */
	public function get_timestamp() {
		et_core_nonce_verified_previously();

		if ( isset( $_POST['timestamp'] ) && ! empty( $_POST['timestamp'] ) ) {
			return sanitize_text_field( $_POST['timestamp'] );
		}

		return function_exists( 'hrtime' ) ? (string) hrtime( true ) : (string) microtime( true ); // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.hrtimeFound -- Intentional use of new PHP function
	}

	/**
	 * Collect global color dependencies and add them to the used global colors array.
	 *
	 * This ensures that if a color references another color (e.g., HSL relative colors),
	 * the base color is also included in the export.
	 *
	 * @since 5.0.0
	 *
	 * @param array $used_global_colors Array of used global colors (format: [color_id => []]).
	 *
	 * @return array The used global colors array with dependency colors added.
	 */
	protected function _add_global_color_dependencies( $used_global_colors ) {
		if ( empty( $used_global_colors ) ) {
			return $used_global_colors;
		}

		// Collect all global color dependencies (including nested colors).
		$used_global_color_ids       = array_keys( $used_global_colors );
		$color_ids_with_dependencies = GlobalData::collect_global_color_dependencies( $used_global_color_ids );

		// Add dependency color IDs to used_global_colors if they're not already present.
		foreach ( $color_ids_with_dependencies as $color_id ) {
			if ( ! isset( $used_global_colors[ $color_id ] ) ) {
				$used_global_colors[ $color_id ] = array();
			}
		}

		return $used_global_colors;
	}

	/**
	 * Get Global Colors array from provided global_colors_info.
	 *
	 * @since 4.10.8
	 *
	 * @param array $global_colors_info Array of global colors to process.
	 *
	 * @return array The list of the Global Colors for export.
	 */
	protected function _get_global_colors_data( $global_colors_info = array() ) {
		$global_color_ids = array_unique( array_keys( $global_colors_info ) );

		if ( empty( $global_color_ids ) ) {
			return array();
		}

		// Use D5 GlobalData::get_global_colors() to get both D4 and D5 global colors.
		$all_global_colors = GlobalData::get_global_colors();
		$used_colors       = array();

		foreach ( $global_color_ids as $color_id ) {
			if ( isset( $all_global_colors[ $color_id ] ) ) {
				$color_data = array(
					$color_id,
					$all_global_colors[ $color_id ],
				);

				$used_colors[] = $color_data;
			}
		}

		return $used_colors;
	}

	/**
	 * Get List of global colors used in shortcode.
	 *
	 * @since 4.10.8
	 *
	 * @param array $shortcode_object   The multidimensional array representing a page structure.
	 * @param array $used_global_colors List of global colors to merge with.
	 * @param array $presets            Object of presets.
	 *
	 * @return array - The list of the Global Colors.
	 */
	protected function _get_used_global_colors( $shortcode_object, $used_global_colors = array(), $presets = array() ) {
		if ( ! is_array( $shortcode_object ) ) {
			/**
			 * When exporting D5 items from the backend `Divi Library' portability system ( D4 code ),
			 * we get a Gutenberg string format content rather than a shortcode array,
			 * we currently don't process global colors for library items hence returning empty array.
			 */
			return $used_global_colors;
		}

		foreach ( $shortcode_object as $module ) {
			if ( isset( $module['attrs']['global_colors_info'] ) ) {
				// Retrive global_colors_info from post meta, which saved as string[][].
				$gc_info_prepared   = str_replace(
					array( '&#91;', '&#93;' ),
					array( '[', ']' ),
					$module['attrs']['global_colors_info']
				);

				// Make sure we pass array to array_merge to avoid Fatal Error.
				$gc_info_array      = json_decode( $gc_info_prepared, true );
				$gc_info_array      = is_array( $gc_info_array ) ? $gc_info_array : [];
				$used_global_colors = array_merge( $used_global_colors, $gc_info_array );
			}

			if ( isset( $module['content'] ) && is_array( $module['content'] ) ) {
				$used_global_colors = array_merge( $used_global_colors, $this->_get_used_global_colors( $module['content'], $used_global_colors, $presets ) );
			}
		}

		if ( ! empty( $presets ) ) {
			foreach ( $presets as $module_type => $module_presets ) {
				foreach ( $module_presets->presets as $preset_id => $preset ) {
					if ( isset( $preset->settings->global_colors_info ) ) {
						$used_global_colors = array_merge( $used_global_colors, json_decode( $preset->settings->global_colors_info, true ) );
					}
				}
			}
		}

		return $used_global_colors;
	}

	/**
	 * Returns Global Presets used for a given shortcode only
	 *
	 * @since 3.26
	 *
	 * @param array $shortcode_object - The multidimensional array representing a page structure
	 * @param array $used_global_presets
	 *
	 * @return array - The list of the Global Presets
	 *
	 */
	protected function get_used_global_presets( $shortcode_object, $used_global_presets = array() ) {
		if ( ! is_array( $shortcode_object ) ) {
			/**
			 * When exporting D5 items from the backend `Divi Library' portability system ( D4 code ),
			 * we get a Gutenberg string format content rather than a shortcode array,
			 * we currently don't process global presets for library items hence returning empty array.
			 */
			return $used_global_presets;
		}

		$global_presets_manager = ET_Builder_Global_Presets_Settings::instance();

		foreach ( $shortcode_object as $module ) {
			$module_type = $global_presets_manager->maybe_convert_module_type( $module['type'], $module['attrs'] );
			$preset_id   = $global_presets_manager->get_module_preset_id( $module_type, $module['attrs'] );
			$preset      = $global_presets_manager->get_module_preset( $module_type, $preset_id );

			if ( $preset_id !== 'default' && count( (array) $preset ) !== 0 && count( (array) $preset->settings ) !== 0 ) {
				if ( ! isset( $used_global_presets[ $module_type ] ) ) {
					$used_global_presets[ $module_type ] = (object) array(
						'presets' => (object) array(),
					);
				}

				if ( ! isset( $used_global_presets[ $module_type ]->presets->$preset_id ) ) {
					$used_global_presets[ $module_type ]->presets->$preset_id = (object) array(
						'name'     => $preset->name,
						'version'  => $preset->version,
						'settings' => $preset->settings,
					);
				}

				if ( ! isset( $used_global_presets[ $module_type ]->default ) ) {
					$used_global_presets[ $module_type ]->default = $global_presets_manager->get_module_default_preset_id( $module_type );
				}
			}

			if ( isset( $module['content'] ) && is_array( $module['content'] ) ) {
				$used_global_presets = array_merge( $used_global_presets, $this->get_used_global_presets( $module['content'], $used_global_presets ) );
			}
		}

		return $used_global_presets;
	}

	/**
	 * Returns Global Colors used for a given theme builder shortcode.
	 *
	 * @since 4.18.0
	 *
	 * @param array $shortcode_object - The multidimensional array representing a page structure.
	 *
	 * @return array The list of the Global Colors
	 */
	public function get_theme_builder_library_used_global_colors( $shortcode_object ) {
		return $this->_get_used_global_colors( $shortcode_object );
	}

	/**
	 * Returns Global Presets used for a given theme builder shortcode.
	 *
	 * @since 4.18.0
	 *
	 * @param array $shortcode_object - The multidimensional array representing a page structure.
	 *
	 * @return array The list of the Global Presets
	 */
	public function get_theme_builder_library_used_global_presets( $shortcode_object ) {
		return $this->get_used_global_presets( $shortcode_object );
	}

	/**
	 * Returns images used for a given theme builder shortcode.
	 *
	 * @since 4.18.0
	 *
	 * @param array $data - ID and Post content.
	 *
	 * @return array The list of the encoded images
	 */
	public function get_theme_builder_library_images( $data ) {
		$timestamp = $this->get_timestamp();
		$images    = $this->get_data_images( $data );

		return $this->maybe_paginate_images( $images, 'encode_images', $timestamp );
	}

	/**
	 * Returns thumbnails used for a given theme builder shortcode.
	 *
	 * @since 4.18.0
	 *
	 * @param array $data - ID and Post content.
	 *
	 * @return array The list of the thumbnails
	 */
	public function get_theme_builder_library_thumbnail_images( $data ) {
		return $this->_get_thumbnail_images( $data );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since ?.? Script `et-core-portability` now loads in footer along with `et-core-admin`.
	 * @since 2.7.0
	 */
	public function assets() {
		$time = '<span>1</span>';

		wp_enqueue_style( 'et-core-portability', ET_CORE_URL . 'admin/css/portability.css', array(
			'et-core-admin',
		), ET_CORE_VERSION );

		wp_enqueue_script(
			'et-core-portability',
			ET_CORE_URL . 'admin/js/portability.js',
			array(
				'jquery',
				'jquery-ui-tabs',
				'jquery-form',
				'et-core-admin',
			),
			ET_CORE_VERSION,
			true
		);

		wp_localize_script( 'et-core-portability', 'etCorePortability', array(
			'nonces'        => array(
				'import'  => wp_create_nonce( 'et_core_portability_import' ),
				'export'  => wp_create_nonce( 'et_core_portability_export' ),
				'cancel'  => wp_create_nonce( 'et_core_portability_cancel' ),
				'presets' => wp_create_nonce( 'et_core_portability_import_default_presets' ),
			),
			'postMaxSize'   => $this->to_megabytes( @ini_get( 'post_max_size' ) ),
			'uploadMaxSize' => $this->to_megabytes( @ini_get( 'upload_max_filesize' ) ),
			'text'          => array(
				// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain -- Following the standard.
				'browserSupport'    => esc_html__( 'The browser version you are currently using is outdated. Please update to the newest version.', ET_CORE_TEXTDOMAIN ),
				'memoryExhausted'   => esc_html__( 'You reached your server memory limit. Please try increasing your PHP memory limit.', ET_CORE_TEXTDOMAIN ),
				'maxSizeExceeded'   => esc_html__( 'This file cannot be imported. It may be caused by file_uploads being disabled in your php.ini. It may also be caused by post_max_size or/and upload_max_filesize being smaller than file selected. Please increase it or transfer more substantial data at the time.', ET_CORE_TEXTDOMAIN ),
				'invalideFile'      => esc_html__( 'Invalid File format. You should be uploading a JSON file.', ET_CORE_TEXTDOMAIN ),
				'importContextFail' => esc_html__( 'This file should not be imported in this context.', ET_CORE_TEXTDOMAIN ),
				'noFileSelected'    => esc_html__( 'Please select at least one file to import.', ET_CORE_TEXTDOMAIN ),
				'noItemsSelected'   => esc_html__( 'Please select at least one item to export or disable the "Only export selected items" option', ET_CORE_TEXTDOMAIN ),
				'noItemsToExport'   => esc_html__( 'There are no items to export.', ET_CORE_TEXTDOMAIN ),
				'importing'         => sprintf( esc_html__( 'Import estimated time remaining: %smin', ET_CORE_TEXTDOMAIN ), $time ),
				'exporting'         => sprintf( esc_html__( 'Export estimated time remaining: %smin', ET_CORE_TEXTDOMAIN ), $time ),
				'backuping'         => sprintf( esc_html__( 'Backup estimated time remaining: %smin', ET_CORE_TEXTDOMAIN ), $time ),
				// phpcs:enable
			),
		) );
	}

	/**
	 * Modal HTML.
	 *
	 * @since 2.7.0
	 */
	public function modal() {
		$export_url = add_query_arg( array(
			'et_core_portability' => true,
			'context'             => $this->instance->context,
			'name'                => $this->instance->name,
			'nonce'               => wp_create_nonce( 'et_core_portability_export' ),

		), admin_url() );

		$is_etdev_plugin_activated = is_plugin_active( 'etdev/etdev.php' );

		// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain -- Existing codebase.
		?>
		<div class="et-core-modal-overlay et-core-form" data-et-core-portability="<?php echo esc_attr( $this->instance->context ); ?>">
			<div class="et-core-modal">
				<div class="et-core-modal-header">
					<h3 class="et-core-modal-title"><?php echo esc_html( $this->instance->title ); ?></h3><a href="#" class="et-core-modal-close" data-et-core-modal="close"></a>
				</div>
				<div data-et-core-tabs class="et-core-modal-tabs-enabled">
					<ul class="et-core-tabs">
						<li><a href="#et-core-portability-export"><?php esc_html_e( 'Export', ET_CORE_TEXTDOMAIN ); ?></a></li>
						<li><a href="#et-core-portability-import"><?php esc_html_e( 'Import', ET_CORE_TEXTDOMAIN ); ?></a></li>
					</ul>
					<div id="et-core-portability-export">
						<div class="et-core-modal-content">
							<?php printf( esc_html__( 'Exporting your %s will create a JSON file that can be imported into a different website.', ET_CORE_TEXTDOMAIN ), esc_html( $this->instance->name ) ); ?>
							<h3><?php esc_html_e( 'Export File Name', ET_CORE_TEXTDOMAIN ); ?></h3>
							<form class="et-core-portability-export-form">
								<input type="text" name="" value="<?php echo esc_attr( $this->instance->name ); ?>">
								<?php if ( 'post_type' === $this->instance->type ) : ?>
									<div class="et-core-clearfix"></div>
									<label><input type="checkbox" name="et-core-portability-posts" <?php echo $is_etdev_plugin_activated ? 'checked' : ''; ?> /><?php esc_html_e( 'Only export selected items', ET_CORE_TEXTDOMAIN ); ?></label>
								<?php endif; ?>
								<?php if ( $is_etdev_plugin_activated ) : ?>
									<div class="et-core-clearfix"></div>
									<label><input type="checkbox" name="et-core-portability-apply-presets" checked /><?php esc_html_e( 'Export Presets As Static Styles', ET_CORE_TEXTDOMAIN ); ?></label>
								<?php endif; ?>
							</form>
						</div>
						<div class="et-core-action-buttons-container">
							<a class="et-core-modal-action et-core-button-primary et-core-portability-import" href="#" data-et-core-portability-export="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Download Export', ET_CORE_TEXTDOMAIN ); ?></a>
							<?php if ( 'et_builder_layouts' === $this->instance->context ) { ?>
								<a class="et-core-modal-action et-core-button-secondary et-core-portability-cloud" href="#" data-et-core-portability-export-to-cloud="1"><?php esc_html_e( 'Export To Divi Cloud', 'et-core' ); ?></a>
							<?php } ?>
						</div>
						<div class="et-core-action-buttons-container__during_action">
							<a class="et-core-modal-action et-core-button-secondary et-core-portability-cancel" href="#" data-et-core-portability-cancel><?php esc_html_e( 'Cancel Export', ET_CORE_TEXTDOMAIN ); ?></a>
						</div>
					</div>
					<div id="et-core-portability-import">
						<div class="et-core-modal-content">
							<?php if ( 'post' === $this->instance->type ) : ?>
								<?php printf( esc_html__( 'Importing a previously-exported %s file will overwrite all content currently on this page.', ET_CORE_TEXTDOMAIN ), esc_html( $this->instance->name ) ); ?>
							<?php elseif ( 'post_type' === $this->instance->type ) : ?>
								<?php printf( esc_html__( 'Select a previously-exported Divi Builder Layouts file to begin importing items. Large collections of image-heavy exports may take several minutes to upload.', ET_CORE_TEXTDOMAIN ), esc_html( $this->instance->name ) ); ?>
							<?php else : ?>
								<?php printf( esc_html__( 'Importing a previously-exported %s file will overwrite all current data. Please proceed with caution!', ET_CORE_TEXTDOMAIN ), esc_html( $this->instance->name ) ); ?>
							<?php endif; ?>
							<h3><?php esc_html_e( 'Select Files To Import', ET_CORE_TEXTDOMAIN ); ?></h3>
							<form class="et-core-portability-import-form">
								<span class="et-core-portability-import-placeholder"><?php esc_html_e( 'No File Selected', ET_CORE_TEXTDOMAIN ); ?></span>
								<input type="file"
									<?php if ( 'post_type' === $this->instance->type ) : ?>
										multiple accept="application/json,.json"
									<?php endif; ?>
								>
								<button class="et-core-button"><?php esc_html_e( 'Choose Files', ET_CORE_TEXTDOMAIN ); ?></button>

								<div class="et-core-clearfix"></div>
								<?php if ( 'post_type' !== $this->instance->type ) : ?>
									<label><input type="checkbox" name="et-core-portability-import-backup" /><?php esc_html_e( 'Download Backup Before Importing', ET_CORE_TEXTDOMAIN ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain -- intentional use of ET_CORE_TEXTDOMAIN ?></label>
								<?php endif; ?>
								<?php if ( 'post_type' === $this->instance->type ) : ?>
									<label><input type="checkbox" name="et-core-portability-import-include-global-presets" /><?php esc_html_e( 'Import Presets', ET_CORE_TEXTDOMAIN ); ?></label>
								<?php endif; ?>
							</form>
						</div>
						<div class="et-core-action-buttons-container">
							<a class="et-core-modal-action et-core-portability-import" href="#"><?php printf( esc_html__( 'Import %s', ET_CORE_TEXTDOMAIN ), esc_html( $this->instance->name ) ); ?></a>
							<a class="et-core-modal-action et-core-button-secondary et-core-portability-cancel" href="#" data-et-core-portability-cancel><?php esc_html_e( 'Cancel Import', ET_CORE_TEXTDOMAIN ); ?></a>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		// phpcs:enable
	}
}

if ( ! function_exists( 'et_core_portability_register' ) ) :
/**
 * Register portability.
 *
 * This function should be called in an 'admin_init' action callback.
 *
 * @since 2.7.0
 *
 * @param string $context A unique ID used to register the portability arguments.
 *
 * @param array  $args {
 *      Array of arguments used to register the portability.
 *
 * 		@type string $name	  The name used in the various text string.
 * 		@type bool   $view	  Whether the assets and content should load or not.
 * 		      				  Example: `isset( $_GET['page'] ) && $_GET['page'] == 'example'`.
 * 		@type string $db	  The option_name from the wp_option table used to export and import data.
 * 		@type array  $include Optional. Array of all the options scritcly included. Options ids must be set
 *         					  as the array keys.
 *      @type array  $exclude Optional. Array of excluded options. Options ids must be set as the array keys.
 * }
 */
function et_core_portability_register( $context, $args ) {
	$defaults = array(
		'context' => $context,
		'title'   => esc_html__( 'Portability', ET_CORE_TEXTDOMAIN ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain -- intentional use of ET_CORE_TEXTDOMAIN
		'name'    => false,
		'view'    => false,
		'type'    => false,
		'target'  => false,
		'include' => array(),
		'exclude' => array(),
	);

	$data = apply_filters( "et_core_portability_args_{$context}", (object) array_merge( $defaults, (array) $args ) );

	et_core_cache_set( $context, $data, 'et_core_portability' );

	// Stop here if not allowed.
	if ( function_exists( 'et_pb_is_allowed' ) && ! et_pb_is_allowed( array( 'portability', "{$data->context}_portability" ) ) ) {

		// Set view to false if not allowed.
		$data->view = false;
		et_core_cache_set( $context, $data, 'et_core_portability' );

		return;
	}

	if ( $data->view ) {
		et_core_portability_load( $context );
	}
}
endif;


if ( ! function_exists( 'et_core_portability_load' ) ) :
/**
 * Load Portability class.
 *
 * @since 2.7.0
 *
 * @param string $context A unique ID used to register the portability arguments.
 * @return ET_Core_Portability
 */
function et_core_portability_load( $context ) {
	return new ET_Core_Portability( $context );
}
endif;


if ( ! function_exists( 'et_core_portability_link' ) ) :
/**
 * HTML link to trigger the portability modal.
 *
 * @since 2.7.0
 *
 * @param string       $context    The context used to register the portability.
 * @param string|array $attributes Optional. Query string or array of attributes. Default empty.
 *
 * @return string
 */
function et_core_portability_link( $context, $attributes = array() ) {
	$instance = et_core_cache_get( $context, 'et_core_portability' );

	if ( ! $capability = et_core_portability_cap( $context ) ) {
		return '';
	}

	if ( ! current_user_can( $capability ) || ! ( isset( $instance->view ) && $instance->view ) ) {
		return '';
	}

	$defaults = array(
		'title' => esc_attr__( 'Import & Export', ET_CORE_TEXTDOMAIN ),
	);
	$attributes = array_merge( $defaults, $attributes );

	// Forced attributes.
	$attributes['href'] = '#';
	$context = esc_attr( $context );
	$attributes['data-et-core-modal'] = "[data-et-core-portability='{$context}']";

	$string = '';

	foreach ( $attributes as $attribute => $value ) {
		if ( null !== $value ){
			$string .= esc_attr( $attribute ) . '="' . esc_attr( $value ) . '" ';
		}
	}

	return sprintf(
		'<a %1$s><span>%2$s</span></a>',
		trim( $string ),
		esc_html( $attributes['title'] )
	);
}
endif;


if ( ! function_exists( 'et_core_portability_ajax_import' ) ) :
/**
 * Ajax portability Import.
 *
 * @since 2.7.0
 */
function et_core_portability_ajax_import() {
	if ( ! isset( $_POST['context'] ) ) {
		et_core_die();
	}

	$context = sanitize_text_field( $_POST['context'] );
	$post_id = isset( $_POST['post'] ) ? (int) $_POST['post'] : 0;
	$replace = isset( $_POST['replace'] ) ? '1' === $_POST['replace'] : false;

	if ( ! $capability = et_core_portability_cap( $context ) ) {
		et_core_die();
	}

	if ( ! et_core_security_check_passed( $capability, 'et_core_portability_import', 'nonce' ) ) {
		et_core_die();
	}

	$portability = et_core_portability_load( $context );

	if ( ! $result = $portability->import() ) {
		wp_send_json_error();
	} else if ( is_array( $result ) && isset( $result['message'] ) ) {
		wp_send_json_error( $result );
	} else if ( $result ) {
		if ( isset( $result['postContent'] ) ) {
			$content                 = $result['postContent'];
			$migrated_content        = D5BuilderConversion::maybeConvertContent( $content );
			$formatted_content       = "<!-- wp:divi/placeholder -->{$migrated_content}<!-- /wp:divi/placeholder -->";
			$result['postContent']   = $formatted_content;

			if ( $replace && 0 < $post_id && current_user_can( 'edit_post', $post_id ) ) {
				wp_update_post(
					[
						'ID'           => $post_id,
						'post_content' => wp_slash( $formatted_content ),
						'meta_input'   => [ '_et_pb_use_divi_5' => 'on' ],
					]
				);
			}
		}

		if ( isset( $result['imported_posts'] ) && is_array( $result['imported_posts'] ) ) {
			foreach ( $result['imported_posts'] as $post_id ) {
				if ( ! is_numeric( $post_id ) ) {
					continue;
				}

				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					continue;
				}

				$post = get_post( $post_id );
				if ( ! $post || empty( $post->post_content ) ) {
					continue;
				}

				if ( 'revision' === $post->post_type ) {
					continue;
				}

				$original_content  = $post->post_content;
				$migrated_content  = D5BuilderConversion::maybeConvertContent( $original_content );
				$formatted_content = "<!-- wp:divi/placeholder -->{$migrated_content}<!-- /wp:divi/placeholder -->";

				if ( $original_content !== $formatted_content ) {
					wp_update_post( [
						'ID'           => $post_id,
						'post_content' => wp_slash( $formatted_content ),
						'meta_input'   => [ '_et_pb_use_divi_5' => 'on' ],
					] );
				} else {
					update_post_meta( $post_id, '_et_pb_use_divi_5', 'on' );
				}
			}
		}

		wp_send_json_success( $result );
	}

	wp_send_json_error();
}
add_action( 'wp_ajax_et_core_portability_import', 'et_core_portability_ajax_import' );
endif;


if ( ! function_exists( 'et_core_portability_ajax_export' ) ) :
	/**
	 * Ajax portability Export.
	 *
	 * @since 2.7.0
	 */
	function et_core_portability_ajax_export() {
		if ( ! isset( $_POST['context'] ) ) {
				wp_send_json_error();
				return;
		}

		$context = sanitize_text_field( $_POST['context'] );

		// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure, WordPress.CodeAnalysis.AssignmentInCondition.Found -- Existing codebase.
		if ( ! $capability = et_core_portability_cap( $context ) ) {
				wp_send_json_error();
				return;
		}

		if ( ! et_core_security_check_passed( $capability, 'et_core_portability_export', 'nonce' ) ) {
				wp_send_json_error();
				return;
		}

		$return = isset( $_POST['return'] ) && sanitize_text_field( $_POST['return'] );

		if ( $return ) {
			$data = et_core_portability_load( $context )->export( $return );

			wp_send_json_success( $data );
		} else {
			et_core_portability_load( $context )->export();
		}

		wp_send_json_error();
	}
	add_action( 'wp_ajax_et_core_portability_export', 'et_core_portability_ajax_export' );
endif;


if ( ! function_exists( 'et_core_portability_ajax_cancel' ) ) :
/**
 * Cancel portability action.
 *
 * @since 2.7.0
 */
function et_core_portability_ajax_cancel() {
	if ( ! isset( $_POST['context'] ) ) {
		et_core_die();
	}

	$context = sanitize_text_field( $_POST['context'] );

	if ( ! $capability = et_core_portability_cap( $context ) ) {
		et_core_die();
	}

	if ( ! et_core_security_check_passed( $capability, 'et_core_portability_cancel' ) ) {
		et_core_die();
	}

	et_core_portability_load( $context )->delete_temp_files( true );

	wp_send_json_error();
}
add_action( 'wp_ajax_et_core_portability_cancel', 'et_core_portability_ajax_cancel' );
endif;

if ( ! function_exists( 'et_core_upload_and_get_urls_from_presets_images' ) ) :
	/**
	 * Upload images and return the new URLs for presets.
	 *
	 * @since 4.26.1
	 * @param array $data Array of images to upload.
	 *
	 * @return array
	 */
	function et_core_upload_and_get_urls_from_presets_images( $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Require the file that includes wp_generate_attachment_metadata().
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		$mime_types    = get_allowed_mime_types();
		$wp_upload_dir = wp_upload_dir();
		$uploaded_urls = [];

		foreach ( $data as $imageInfo ) {
			$image_data = base64_decode( $imageInfo['encoded'] );
			$temp_file  = tempnam( $wp_upload_dir['path'], 'preset_' );
			$filetype   = wp_check_filetype( basename( $temp_file ), null );

			if ( ! in_array( $filetype['type'], $mime_types, true ) ) {
				continue;
			}

			file_put_contents( $temp_file, $image_data );

			$attachment = [
				'guid'           => $wp_upload_dir['url'] . '/' . basename( $temp_file ),
				'post_mime_type' => $filetype['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $temp_file ) ),
				'post_content'   => '',
				'post_status'    => 'inherit'
			];

			$attach_id   = wp_insert_attachment( $attachment, $temp_file );
			$attach_data = wp_generate_attachment_metadata( $attach_id, $temp_file );

			wp_update_attachment_metadata( $attach_id, $attach_data );

			$uploaded_urls[] = [
				'old_url' => $imageInfo['url'],
				'new_url' => wp_get_attachment_url( $attach_id ),
			];

			unlink( $temp_file );
		}

		return $uploaded_urls;
	}
endif;

if ( ! function_exists( 'et_core_replace_image_urls_in_presets' ) ) :
	/**
	 * Replace image URLs in presets JSON string.
	 */
	function et_core_replace_image_urls_in_presets( $presets, $uploaded_urls ) {
		foreach ( $uploaded_urls as $url ) {
			$presets = str_replace( $url['old_url'], $url['new_url'], $presets );
		}

		return $presets;
	}
endif;

if ( ! function_exists( 'et_core_portability_import_default_presets' ) ) :
	/**
	 * Set the default preset for a modules.
	 *
	 * @since 4.26.0
	 */
	function et_core_portability_import_default_presets() {
		if ( ! et_core_security_check_passed( 'manage_options', 'et_core_portability_import_default_presets', 'nonce' ) ) {
			et_core_die();
		}

		$presets       = isset( $_POST['presets'] ) ? sanitize_text_field( $_POST['presets'] ) : '';
		$preset_prefix = isset( $_POST['presetPrefix'] ) ? sanitize_text_field( $_POST['presetPrefix'] ) : '';
		$global_colors = isset( $_POST['globalColors'] ) ? sanitize_text_field( $_POST['globalColors'] ) : '';
		$images        = isset( $_POST['images'] ) ? sanitize_text_field( $_POST['images'] ) : '';
		$uploaded_urls = [];

		$portability = et_core_portability_load( 'et_builder' );

		if ( $global_colors ) {
			$global_colors = json_decode( stripslashes( $global_colors ), true );
			$portability->import_global_colors( $global_colors );
		}

		if ( $images ) {
			$images        = json_decode( stripslashes( $images ), true );
			$uploaded_urls = et_core_upload_and_get_urls_from_presets_images( $images );
		}

		if ( $presets ) {
			if ( ! empty( $uploaded_urls ) ) {
				$presets = et_core_replace_image_urls_in_presets( $presets, $uploaded_urls );
			}

			$presets = json_decode( stripslashes( $presets ), true );
			$portability->import_global_presets( $presets, false, true, $preset_prefix );
		}

		ET_Core_PageResource::remove_static_resources( 'all', 'all' );

		wp_send_json_success();
	}

	add_action( 'wp_ajax_et_core_portability_import_default_presets', 'et_core_portability_import_default_presets' );
endif;

if ( ! function_exists( 'et_core_portability_export' ) ) :
/**
 * Portability export.
 *
 * @since 2.7.0
 */
function et_core_portability_export() {
	if ( ! isset( $_GET['et_core_portability'], $_GET['timestamp'] ) ) {
		return;
	}

	if ( ! et_core_security_check_passed( 'edit_posts' ) ) {
		wp_die( esc_html__( 'The export process failed. Please refresh the page and try again.', ET_CORE_TEXTDOMAIN ) );
	}

	et_core_portability_load( sanitize_text_field( $_GET['timestamp'] ) )->download_export();
}
add_action( 'admin_init', 'et_core_portability_export', 20 );
endif;


if ( ! function_exists( 'et_core_portability_cap' ) ):
/**
 * Returns the required WordPress Capability for a Portability context.
 *
 * @since 3.0.91
 *
 * @param string $context The Portability context
 *
 * @return string
 */
function et_core_portability_cap( $context ) {
	$capability       = '';
	$options_contexts = array(
		'et_pb_roles',
		'epanel',
		'epanel_temp',
		'et_divi_mods',
		'et_extra_mods',
	);
	$post_contexts    = array(
		'et_builder',
		'et_theme_builder',
		'et_code_snippets',
		'et_builder_layouts',
	);

	if ( in_array( $context, $options_contexts, true ) ) {
		$capability = 'edit_theme_options';
	} else if ( in_array( $context, $post_contexts, true ) ) {
		$capability = 'edit_posts';
	}

	return $capability;
}
endif;
