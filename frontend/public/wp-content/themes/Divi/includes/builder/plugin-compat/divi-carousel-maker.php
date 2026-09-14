<?php
/**
 * Compatibility for the Divi Carousel Maker plugin.
 *
 * @package Divi
 * @since 5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'et_builder_restore_pac_dcm_column_fields' ) ) :
	/**
	 * Restore Divi Carousel Maker column field defaults when fields are not reloaded.
	 *
	 * @since 5.0.0
	 *
	 * @param array $fields_unprocessed Column fields.
	 *
	 * @return array
	 */
	function et_builder_restore_pac_dcm_column_fields( $fields_unprocessed ) {
		if ( ! defined( 'PAC_DCM_PLUGIN_VERSION' ) || ! function_exists( 'pac_dcm_filter_column_output' ) ) {
			return $fields_unprocessed;
		}

		if ( ! is_array( $fields_unprocessed ) ) {
			return $fields_unprocessed;
		}

		static $cached_fields = array();
		$pac_dcm_fields       = array();

		foreach ( $fields_unprocessed as $field_key => $field_value ) {
			if ( 0 !== strpos( $field_key, 'pac_dcm_' ) ) {
				continue;
			}

			$pac_dcm_fields[ $field_key ] = $field_value;
		}

		if ( ! empty( $pac_dcm_fields ) ) {
			$cached_fields = $pac_dcm_fields;
			return $fields_unprocessed;
		}

		if ( empty( $cached_fields ) ) {
			return $fields_unprocessed;
		}

		foreach ( $cached_fields as $field_key => $field_value ) {
			if ( isset( $fields_unprocessed[ $field_key ] ) ) {
				continue;
			}

			$fields_unprocessed[ $field_key ] = $field_value;
		}

		return $fields_unprocessed;
	}

	add_filter( 'et_pb_all_fields_unprocessed_et_pb_column', 'et_builder_restore_pac_dcm_column_fields', 20 );
endif;
