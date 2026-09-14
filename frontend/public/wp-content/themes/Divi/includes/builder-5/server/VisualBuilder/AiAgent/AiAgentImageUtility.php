<?php
/**
 * AI Agent: AiAgentImageUtility class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\AiAgent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\VisualBuilder\Fonts\FontsUtility;
use ET\Builder\VisualBuilder\Hooks\HooksRegistration;
use WP_Error;

/**
 * Utility for uploading and deleting AI agent chat image attachments.
 *
 * @since ??
 */
class AiAgentImageUtility {

	/**
	 * Maximum upload size in bytes (10MB).
	 */
	private const MAX_FILE_SIZE = 10485760;

	/**
	 * Maximum number of files allowed in a chat folder.
	 */
	public const MAX_ATTACHMENTS_PER_CHAT = 15;

	/**
	 * Allowed chatId format: chat-{timestamp}-{alphanumeric}.
	 */
	private const CHAT_ID_PATTERN = '/^chat-\d+-[a-z0-9]+$/';

	/**
	 * Legacy wp_option prefix from first-claimer ownership (issue #50337).
	 */
	private const LEGACY_CHAT_OWNER_OPTION_PREFIX = '_et_ai_agent_chat_owner_';

	/**
	 * Validate chatId format before any path construction.
	 *
	 * @since ??
	 *
	 * @param string $chat_id Chat identifier.
	 *
	 * @return bool
	 */
	private static function _validate_chat_id( string $chat_id ): bool {
		return 1 === preg_match( self::CHAT_ID_PATTERN, $chat_id );
	}

	/**
	 * Assert the current user owns the thread for this chat.
	 *
	 * Image mutations require a matching `et_divi_ai_chat_threads` row. There is
	 * no `manage_options` bypass and no first-claimer wp_option.
	 *
	 * @since ??
	 *
	 * @param string $chat_id Chat identifier.
	 *
	 * @return bool|WP_Error
	 */
	public static function assert_chat_ownership( string $chat_id ) {
		$owner_user_id = AiAgentThreadOwnership::get_thread_user_id( $chat_id );

		if ( null === $owner_user_id || (int) get_current_user_id() !== $owner_user_id ) {
			return new WP_Error(
				'not_found',
				esc_html__( 'Thread not found.', 'et_builder_5' ),
				[ 'status' => 404 ]
			);
		}

		return true;
	}
  
  /**
   * Verify the current user owns this chat for REST permission callbacks.
   *
   * Does not claim a folder. Ownership is the thread row only.
   *
   * @since ??
   *
   * @param string $chat_id Chat identifier.
   *
   * @return bool|WP_Error
   */
  public static function verify_chat_ownership( string $chat_id ) {
  	if ( ! self::_validate_chat_id( $chat_id ) ) {
  		return new WP_Error(
  			'invalid_chat_id',
  			esc_html__( 'Invalid chat ID.', 'et_builder_5' ),
  			[ 'status' => 400 ]
  		);
  	}
  	return self::assert_chat_ownership( $chat_id );
  }
  
	/**
	 * Absolute path to the et-ai-agent uploads base directory.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private static function _get_base_dir(): string {
		$upload_dir = wp_upload_dir();

		return $upload_dir['basedir'] . '/et-ai-agent';
	}

	/**
	 * Ensure the et-ai-agent base directory exists and is protected against PHP execution.
	 *
	 * Creates the directory (if missing) and writes a `.htaccess` that denies execution of
	 * PHP files. This is defense-in-depth: even if a PHP-JPEG polyglot passes MIME validation,
	 * it cannot be executed as PHP on Apache-based servers — including misconfigured ones.
	 *
	 * @since ??
	 *
	 * @return bool True when the base directory exists or was created successfully.
	 */
	private static function _ensure_protected_base_dir(): bool {
		$base_dir = self::_get_base_dir();
		$htaccess = $base_dir . '/.htaccess';
		$index    = $base_dir . '/index.php';

		if ( ! is_dir( $base_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- scoped to our own upload directory.
			wp_mkdir_p( $base_dir );

			if ( ! is_dir( $base_dir ) ) {
				return false;
			}
		}

		if ( is_dir( $base_dir ) ) {
			if ( ! file_exists( $htaccess ) ) {
				$rules = implode(
					"\n",
					[
						'# Deny direct execution of any server-side scripting language.',
						'<FilesMatch "\.(php|phtml|php3|php4|php5|php6|php7|php8|phps|pht|phar)$">',
						'    Deny from all',
						'</FilesMatch>',
						'php_flag engine off',
					]
				);

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing a static protection file, no user input involved.
				file_put_contents( $htaccess, $rules );
			}

			if ( ! file_exists( $index ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing a static protection file, no user input involved.
				file_put_contents( $index, '<?php' . PHP_EOL . '// Silence is golden.' . PHP_EOL );
			}
		}

		return true;
	}

	/**
	 * Regular (non-dot) files in a directory.
	 *
	 * @since ??
	 *
	 * @param string $dir Absolute directory path.
	 *
	 * @return array
	 */
	private static function _list_regular_files( string $dir ): array {
		$listed = glob( $dir . '/*' );

		if ( ! is_array( $listed ) ) {
			return [];
		}

		$files = [];

		foreach ( $listed as $path ) {
			if ( is_file( $path ) ) {
				$files[] = $path;
			}
		}

		return $files;
	}

	/**
	 * Upload a JPEG or PNG image to the per-chat folder.
	 *
	 * @since ??
	 *
	 * @param array  $file    Uploaded file array from $_FILES.
	 * @param string $chat_id Chat folder key.
	 *
	 * @return array|WP_Error
	 */
	public static function upload_image( array $file, string $chat_id ) {
		if ( ! self::_ensure_protected_base_dir() ) {
			return new WP_Error(
				'upload_dir_error',
				esc_html__( 'Unable to create or access the AI Agent upload directory.', 'et_builder_5' ),
				[ 'status' => 500 ]
			);
		}

		if ( ! self::_validate_chat_id( $chat_id ) ) {
			return new WP_Error(
				'invalid_chat_id',
				esc_html__( 'Invalid chat identifier.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$ownership = self::assert_chat_ownership( $chat_id );

		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		if ( empty( $file ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error(
				'file_empty',
				esc_html__( 'No image file provided.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		if ( isset( $file['size'] ) && self::MAX_FILE_SIZE < (int) $file['size'] ) {
			return new WP_Error(
				'file_too_large',
				esc_html__( 'Image must be under 10MB.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$dir_filter = function ( $uploads ) use ( $chat_id ) {
			$uploads['subdir'] = '/et-ai-agent/' . $chat_id;
			$uploads['path']   = $uploads['basedir'] . $uploads['subdir'];
			$uploads['url']    = $uploads['baseurl'] . $uploads['subdir'];

			return $uploads;
		};

		$upload_dir = wp_upload_dir();
		$chat_dir   = $upload_dir['basedir'] . '/et-ai-agent/' . $chat_id;

		// Pre-create the chat directory to prevent race conditions in wp_upload_dir()
		// during concurrent multi-image paste requests.
		if ( ! is_dir( $chat_dir ) ) {
			wp_mkdir_p( $chat_dir );

			if ( ! is_dir( $chat_dir ) ) {
				return new WP_Error(
					'upload_dir_error',
					esc_html__( 'Unable to create or access the chat upload directory.', 'et_builder_5' ),
					[ 'status' => 500 ]
				);
			}
		}

		if ( self::MAX_ATTACHMENTS_PER_CHAT <= count( self::_list_regular_files( $chat_dir ) ) ) {
			return new WP_Error(
				'too_many_attachments',
				sprintf(
					/* translators: %d: maximum number of image attachments allowed per chat. */
					esc_html__( 'You can attach up to %d images per chat.', 'et_builder_5' ),
					self::MAX_ATTACHMENTS_PER_CHAT
				),
				[ 'status' => 400 ]
			);
		}

		add_filter( 'upload_dir', $dir_filter );
		add_filter( 'wp_check_filetype_and_ext', [ HooksRegistration::class, 'check_filetype_and_ext_ai_agent' ], 999, 3 );

		// phpcs:ignore ET.Functions.DangerousFunctions.ET_handle_upload -- test_type is enabled and MIME checking is implemented.
		$upload = wp_handle_upload(
			$file,
			[
				'test_size' => true,
				'test_type' => true,
				'test_form' => false,
				'action'    => 'wp_handle_upload_rest',
			]
		);

		remove_filter( 'wp_check_filetype_and_ext', [ HooksRegistration::class, 'check_filetype_and_ext_ai_agent' ], 999 );
		remove_filter( 'upload_dir', $dir_filter );

		if ( ! empty( $upload['error'] ) ) {
			if ( ! empty( $upload['file'] ) && file_exists( $upload['file'] ) ) {
				wp_delete_file( $upload['file'] );
			}

			if ( function_exists( 'et_debug' ) ) {
				$upload_error_message = is_scalar( $upload['error'] ) ? (string) $upload['error'] : 'non-scalar upload error';

				et_debug(
					sprintf(
						'AiAgent image upload failed for chat "%1$s": %2$s',
						$chat_id,
						$upload_error_message
					)
				);
			}

			return new WP_Error(
				'upload_error',
				esc_html__( 'Image upload failed. Please try a different file.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$editor = wp_get_image_editor( $upload['file'] );

		if ( is_wp_error( $editor ) ) {
			wp_delete_file( $upload['file'] );

			return $editor;
		}

		$width  = 0;
		$height = 0;
		$size   = $editor->get_size();

		if ( is_array( $size ) ) {
			$width  = isset( $size['width'] ) ? (int) $size['width'] : 0;
			$height = isset( $size['height'] ) ? (int) $size['height'] : 0;
		}

		$filename  = basename( $upload['file'] );
		$mime_type = isset( $upload['type'] ) ? $upload['type'] : '';

		return [
			'url'      => esc_url_raw( $upload['url'] ),
			'filename' => $filename,
			'width'    => $width,
			'height'   => $height,
			'mimeType' => $mime_type,
		];
	}

	/**
	 * Delete a single image file from a chat folder.
	 *
	 * @since ??
	 *
	 * @param string $chat_id  Chat folder key.
	 * @param string $filename Server-stored filename.
	 *
	 * @return bool|WP_Error
	 */
	public static function delete_image( string $chat_id, string $filename ) {
		if ( ! self::_validate_chat_id( $chat_id ) ) {
			return false;
		}

		$ownership = self::assert_chat_ownership( $chat_id );

		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		$filename = sanitize_file_name( $filename );

		if ( '' === $filename ) {
			return false;
		}

		$base_dir = realpath( self::_get_base_dir() );

		if ( false === $base_dir ) {
			return false;
		}

		$file_path = self::_get_base_dir() . '/' . $chat_id . '/' . $filename;
		$resolved  = realpath( $file_path );

		if ( false === $resolved || ! str_starts_with( $resolved, $base_dir ) ) {
			return false;
		}

		if ( ! file_exists( $resolved ) ) {
			return false;
		}

		wp_delete_file( $resolved );

		return true;
	}

	/**
	 * Extract chatId and filename from an et-ai-agent attachment URL.
	 *
	 * @since ??
	 *
	 * @param string $url Full attachment URL.
	 *
	 * @return array{chatId: string, filename: string}|WP_Error
	 */
	public static function parse_attachment_url( string $url ) {
		$upload_dir = wp_upload_dir();
		$base_url   = $upload_dir['baseurl'] . '/et-ai-agent/';

		if ( ! str_starts_with( $url, $base_url ) ) {
			return new WP_Error(
				'invalid_url',
				esc_html__( 'URL does not point to an AI agent attachment.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$relative = substr( $url, strlen( $base_url ) );
		$parts    = explode( '/', $relative, 2 );

		if ( 2 !== count( $parts ) || '' === $parts[0] || '' === $parts[1] ) {
			return new WP_Error(
				'invalid_url',
				esc_html__( 'Could not parse chat ID and filename from URL.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		return [
			'chatId'   => $parts[0],
			'filename' => $parts[1],
		];
	}

	/**
	 * Import a chat attachment into the WordPress media library.
	 *
	 * Copies the file from the et-ai-agent chat folder into the standard WP
	 * uploads directory, creates a proper wp_posts attachment entry with
	 * generated metadata (thumbnails, dimensions), and returns the permanent
	 * media library URL and attachment ID.
	 *
	 * @since ??
	 *
	 * @param string $chat_id  Chat folder key.
	 * @param string $filename Server-stored filename.
	 * @param string $title    Optional attachment title.
	 * @param string $alt_text Optional alt text.
	 *
	 * @return array|WP_Error {
	 *     @type int    $attachmentId Media library attachment post ID.
	 *     @type string $url          Permanent media library URL.
	 *     @type int    $width        Image width in pixels.
	 *     @type int    $height       Image height in pixels.
	 * }
	 */
	public static function import_to_media_library(
		string $chat_id,
		string $filename,
		string $title = '',
		string $alt_text = ''
	) {
		if ( ! self::_validate_chat_id( $chat_id ) ) {
			return new WP_Error(
				'invalid_chat_id',
				esc_html__( 'Invalid chat identifier.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$ownership = self::assert_chat_ownership( $chat_id );

		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		$filename = sanitize_file_name( $filename );
		$base_dir = realpath( self::_get_base_dir() );

		if ( false === $base_dir || '' === $filename ) {
			return new WP_Error(
				'invalid_file',
				esc_html__( 'Invalid file reference.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$source_path = self::_get_base_dir() . '/' . $chat_id . '/' . $filename;
		$resolved    = realpath( $source_path );

		if ( false === $resolved || ! str_starts_with( $resolved, $base_dir ) || ! file_exists( $resolved ) ) {
			return new WP_Error(
				'file_not_found',
				esc_html__( 'Attachment file not found.', 'et_builder_5' ),
				[ 'status' => 404 ]
			);
		}

		$filetype = wp_check_filetype( $filename );

		if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], [ 'image/jpeg', 'image/png' ], true ) ) {
			return new WP_Error(
				'invalid_mime',
				esc_html__( 'Only image files can be imported to the media library.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading local validated file.
		$file_content = file_get_contents( $resolved );

		if ( false === $file_content ) {
			return new WP_Error(
				'read_failed',
				esc_html__( 'Failed to read attachment file.', 'et_builder_5' ),
				[ 'status' => 500 ]
			);
		}

		$upload = wp_upload_bits( $filename, null, $file_content );

		if ( ! empty( $upload['error'] ) ) {
			if ( function_exists( 'et_debug' ) ) {
				$upload_error_message = is_scalar( $upload['error'] ) ? (string) $upload['error'] : 'non-scalar upload error';

				et_debug(
					sprintf(
						'AiAgent image import failed for chat "%1$s", file "%2$s": %3$s',
						$chat_id,
						$filename,
						$upload_error_message
					)
				);
			}

			return new WP_Error(
				'upload_failed',
				esc_html__( 'Failed to import image to the media library.', 'et_builder_5' ),
				[ 'status' => 500 ]
			);
		}

		$attachment_title = '' !== $title ? $title : pathinfo( $filename, PATHINFO_FILENAME );

		$attachment_data = [
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_text_field( $attachment_title ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		$attachment_id = wp_insert_attachment( $attachment_data, $upload['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $upload['file'] );

			return $attachment_id;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		if ( '' !== $alt_text ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
		}

		$width  = isset( $metadata['width'] ) ? (int) $metadata['width'] : 0;
		$height = isset( $metadata['height'] ) ? (int) $metadata['height'] : 0;

		return [
			'attachmentId' => $attachment_id,
			'url'          => esc_url_raw( wp_get_attachment_url( $attachment_id ) ),
			'width'        => $width,
			'height'       => $height,
		];
	}

	/**
	 * Delete all images for a chat folder.
	 *
	 * @since ??
	 *
	 * @param string $chat_id Chat folder key.
	 *
	 * @return bool|WP_Error
	 */
	public static function delete_chat_images( string $chat_id ) {
		if ( ! self::_validate_chat_id( $chat_id ) ) {
			return false;
		}

		$ownership = self::assert_chat_ownership( $chat_id );

		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		return self::purge_chat_upload_dir( $chat_id );
	}

	/**
	 * Remove a chat upload directory without checking thread ownership.
	 *
	 * Call only after the caller has already authorized thread deletion
	 * (`delete_thread`). Idempotent when the directory is missing.
	 *
	 * @since ??
	 *
	 * @param string $chat_id Chat folder key.
	 *
	 * @return bool
	 */
	public static function purge_chat_upload_dir( string $chat_id ): bool {
		if ( ! self::_validate_chat_id( $chat_id ) ) {
			return false;
		}

		$base_dir      = realpath( self::_get_base_dir() );
		$chat_dir_path = self::_get_base_dir() . '/' . $chat_id;

		if ( false === $base_dir ) {
			delete_option( self::LEGACY_CHAT_OWNER_OPTION_PREFIX . $chat_id );

			return true;
		}

		$resolved = realpath( $chat_dir_path );

		if ( false === $resolved ) {
			delete_option( self::LEGACY_CHAT_OWNER_OPTION_PREFIX . $chat_id );

			return true;
		}

		if ( ! str_starts_with( $resolved, $base_dir ) ) {
			return false;
		}

		if ( is_dir( $resolved ) ) {
			$files = glob( $resolved . '/*' );

			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						wp_delete_file( $file );
					}
				}
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- scoped to validated chat directory.
			rmdir( $resolved );
		}

		delete_option( self::LEGACY_CHAT_OWNER_OPTION_PREFIX . $chat_id );

		return true;
	}

	/**
	 * Supported font extensions allowed for AI agent attach/install.
	 *
	 * Intersects `et_pb_get_supported_font_formats()` with the font MIME map so
	 * formats present only in `mime_types_font()` (woff/woff2/eot by default)
	 * cannot be staged or installed.
	 *
	 * @since ??
	 *
	 * @return string[]
	 */
	private static function _get_supported_font_formats(): array {
		$formats = et_pb_get_supported_font_formats();

		if ( ! is_array( $formats ) ) {
			return [];
		}

		$mime_map = FontsUtility::mime_types_font();

		return array_values(
			array_filter(
				array_map( 'strtolower', $formats ),
				static function ( $format ) use ( $mime_map ) {
					return isset( $mime_map[ $format ] );
				}
			)
		);
	}

	/**
	 * Upload a supported font file to the per-chat folder.
	 *
	 * @since ??
	 *
	 * @param array  $file    Uploaded file array from $_FILES.
	 * @param string $chat_id Chat folder key.
	 *
	 * @return array|WP_Error
	 */
	public static function upload_font( array $file, string $chat_id ) {
		if ( ! extension_loaded( 'fileinfo' ) ) {
			return new WP_Error(
				'fileinfo_extension_not_loaded',
				esc_html__( 'Font upload requires the fileinfo PHP extension.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! self::_ensure_protected_base_dir() ) {
			return new WP_Error(
				'upload_dir_error',
				esc_html__( 'Unable to create or access the AI Agent upload directory.', 'et_builder_5' ),
				[ 'status' => 500 ]
			);
		}

		if ( ! self::_validate_chat_id( $chat_id ) ) {
			return new WP_Error(
				'invalid_chat_id',
				esc_html__( 'Invalid chat identifier.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$ownership = self::assert_chat_ownership( $chat_id );

		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		if ( empty( $file ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error(
				'file_empty',
				esc_html__( 'No font file provided.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		if ( isset( $file['size'] ) && self::MAX_FILE_SIZE < (int) $file['size'] ) {
			return new WP_Error(
				'file_too_large',
				esc_html__( 'Font must be under 10MB.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$original_name = isset( $file['name'] ) ? (string) $file['name'] : '';
		$ext           = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
		$supported     = self::_get_supported_font_formats();

		if ( '' === $ext || ! in_array( $ext, $supported, true ) ) {
			return new WP_Error(
				'invalid_font_format',
				esc_html__( 'This font format is not supported.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$dir_filter = function ( $uploads ) use ( $chat_id ) {
			$uploads['subdir'] = '/et-ai-agent/' . $chat_id;
			$uploads['path']   = $uploads['basedir'] . $uploads['subdir'];
			$uploads['url']    = $uploads['baseurl'] . $uploads['subdir'];

			return $uploads;
		};

		$upload_dir = wp_upload_dir();
		$chat_dir   = $upload_dir['basedir'] . '/et-ai-agent/' . $chat_id;

		if ( ! is_dir( $chat_dir ) ) {
			wp_mkdir_p( $chat_dir );

			if ( ! is_dir( $chat_dir ) ) {
				return new WP_Error(
					'upload_dir_error',
					esc_html__( 'Unable to create or access the chat upload directory.', 'et_builder_5' ),
					[ 'status' => 500 ]
				);
			}
		}

		if ( self::MAX_ATTACHMENTS_PER_CHAT <= count( self::_list_regular_files( $chat_dir ) ) ) {
			return new WP_Error(
				'too_many_attachments',
				sprintf(
					/* translators: %d: maximum number of attachments allowed per chat. */
					esc_html__( 'You can attach up to %d files per chat.', 'et_builder_5' ),
					self::MAX_ATTACHMENTS_PER_CHAT
				),
				[ 'status' => 400 ]
			);
		}

		add_filter( 'upload_dir', $dir_filter );
		add_filter( 'wp_check_filetype_and_ext', [ HooksRegistration::class, 'check_filetype_and_ext_font' ], 999, 3 );

		// phpcs:ignore ET.Functions.DangerousFunctions.ET_handle_upload -- test_type is enabled and MIME checking is implemented.
		$upload = wp_handle_upload(
			$file,
			[
				'test_size' => true,
				'test_type' => true,
				'test_form' => false,
				'action'    => 'wp_handle_upload_rest',
			]
		);

		remove_filter( 'wp_check_filetype_and_ext', [ HooksRegistration::class, 'check_filetype_and_ext_font' ], 999 );
		remove_filter( 'upload_dir', $dir_filter );

		if ( ! empty( $upload['error'] ) ) {
			if ( ! empty( $upload['file'] ) && file_exists( $upload['file'] ) ) {
				wp_delete_file( $upload['file'] );
			}

			if ( function_exists( 'et_debug' ) ) {
				$upload_error_message = is_scalar( $upload['error'] ) ? (string) $upload['error'] : 'non-scalar upload error';

				et_debug(
					sprintf(
						'AiAgent font upload failed for chat "%1$s": %2$s',
						$chat_id,
						$upload_error_message
					)
				);
			}

			return new WP_Error(
				'upload_error',
				esc_html__( 'Font upload failed. Please try a different file.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$filename  = basename( $upload['file'] );
		$mime_type = isset( $upload['type'] ) ? $upload['type'] : '';

		return [
			'url'      => esc_url_raw( $upload['url'] ),
			'filename' => $filename,
			'mimeType' => $mime_type,
			'kind'     => 'font',
		];
	}

	/**
	 * Install a staged chat font into Custom Fonts via FontsUtility::font_add.
	 *
	 * Copies the chat-folder file to a temp path first so wp_handle_upload cannot
	 * unlink the staged original.
	 *
	 * @since ??
	 *
	 * @param string $chat_id   Chat folder key.
	 * @param string $filename  Server-stored filename.
	 * @param string $font_name Optional custom font family name.
	 *
	 * @return array|WP_Error {
	 *     @type string $uploaded_font Sanitized family name.
	 *     @type array  $updated_fonts Full et_uploaded_fonts map.
	 * }
	 */
	public static function install_to_custom_fonts( string $chat_id, string $filename, string $font_name = '' ) {
		if ( ! extension_loaded( 'fileinfo' ) ) {
			return new WP_Error(
				'fileinfo_extension_not_loaded',
				esc_html__( 'Font install requires the fileinfo PHP extension.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! self::_validate_chat_id( $chat_id ) ) {
			return new WP_Error(
				'invalid_chat_id',
				esc_html__( 'Invalid chat identifier.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$ownership = self::assert_chat_ownership( $chat_id );

		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		if ( false !== strpos( $filename, '/' ) || false !== strpos( $filename, '\\' ) ) {
			return new WP_Error(
				'invalid_url',
				esc_html__( 'Could not parse chat ID and filename from URL.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$filename = sanitize_file_name( $filename );
		$base_dir = realpath( self::_get_base_dir() );

		if ( false === $base_dir || '' === $filename ) {
			return new WP_Error(
				'invalid_file',
				esc_html__( 'Invalid file reference.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$source_path = self::_get_base_dir() . '/' . $chat_id . '/' . $filename;
		$resolved    = realpath( $source_path );

		if ( false === $resolved || ! str_starts_with( $resolved, $base_dir ) || ! file_exists( $resolved ) ) {
			return new WP_Error(
				'file_not_found',
				esc_html__( 'Attachment file not found.', 'et_builder_5' ),
				[ 'status' => 404 ]
			);
		}

		$file_size = filesize( $resolved );

		if ( false === $file_size || self::MAX_FILE_SIZE < $file_size ) {
			return new WP_Error(
				'file_too_large',
				esc_html__( 'Font must be under 10MB.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$ext       = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$supported = self::_get_supported_font_formats();

		if ( '' === $ext || ! in_array( $ext, $supported, true ) ) {
			return new WP_Error(
				'invalid_font_format',
				esc_html__( 'This font format is not supported.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$checked = HooksRegistration::check_filetype_and_ext_font(
			[
				'ext'             => false,
				'type'            => false,
				'proper_filename' => false,
			],
			$resolved,
			$filename
		);

		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			return new WP_Error(
				'invalid_mime',
				esc_html__( 'Only supported font files can be installed as custom fonts.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! in_array( strtolower( (string) $checked['ext'] ), $supported, true ) ) {
			return new WP_Error(
				'invalid_font_format',
				esc_html__( 'This font format is not supported.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		$temp = wp_tempnam( $filename );

		if ( false === $temp ) {
			return new WP_Error(
				'temp_file_error',
				esc_html__( 'Failed to prepare font file for install.', 'et_builder_5' ),
				[ 'status' => 500 ]
			);
		}

		if ( ! copy( $resolved, $temp ) ) {
			wp_delete_file( $temp );

			return new WP_Error(
				'copy_failed',
				esc_html__( 'Failed to prepare font file for install.', 'et_builder_5' ),
				[ 'status' => 500 ]
			);
		}

		$resolved_font_name = '' !== $font_name ? $font_name : pathinfo( $filename, PATHINFO_FILENAME );

		$result = FontsUtility::font_add(
			[
				$checked['ext'] => [
					'name'     => $filename,
					'type'     => $checked['type'],
					'tmp_name' => $temp,
					'error'    => 0,
					'size'     => filesize( $temp ),
				],
			],
			$resolved_font_name,
			[
				'font_weights'   => 'all',
				'generic_family' => 'sans-serif',
			],
			[
				'action' => 'wp_handle_upload_rest',
			]
		);

		if ( file_exists( $temp ) ) {
			wp_delete_file( $temp );
		}

		return $result;
	}
}
