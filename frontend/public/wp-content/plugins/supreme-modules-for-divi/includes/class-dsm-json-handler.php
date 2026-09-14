<?php
// Prevent direct access to files
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DSM_JSON_Handler' ) ) {
    class DSM_JSON_Handler {
        const MIME_TYPE = 'application/json';

        /**
         * Add JSON to allowed file uploads.
         *
         * @since 2.0.5
         */
        public function dsm_mime_types( $mimes ) {
            $mimes['json'] = self::MIME_TYPE;
            return $mimes;
        }

        /**
         * (Optional) Correct filetype for .json files if WP cannot detect it.
         *
         * @since 2.0.5
         */
        public function dsm_check_filetype_and_ext( $types, $file, $filename, $mimes ) {
            // If WP already detected a valid type, do not override
            if ( ! empty( $types['ext'] ) && ! empty( $types['type'] ) ) {
                return $types;
            }

            // Only treat files that actually end with .json as JSON
            if ( preg_match( '/\.json$/i', $filename ) ) {
                $types['ext']  = 'json';
                $types['type'] = self::MIME_TYPE;
            }

            return $types;
        }

        /**
         * DSM_JSON_Handler constructor.
         */
        public function __construct() {
            add_filter( 'upload_mimes', array( $this, 'dsm_mime_types' ) );
            add_filter( 'wp_check_filetype_and_ext', array( $this, 'dsm_check_filetype_and_ext' ), 10, 4 );
        }
    }
}