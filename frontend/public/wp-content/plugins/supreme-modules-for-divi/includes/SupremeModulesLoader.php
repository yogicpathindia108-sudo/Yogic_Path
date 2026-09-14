<?php
/*Temporary fix*/
if ( ! function_exists( 'et_core_is_fb_enabled' ) ) :
	function et_core_is_fb_enabled() {
		return function_exists( 'et_fb_is_enabled' ) && et_fb_is_enabled();
	}
endif;
if ( ! function_exists( 'et_divi_divider_style_choices' ) ) :
	/**
	 * Returns divider style choices
	 *
	 * @return array
	 */
	function et_divi_divider_style_choices() {
		return apply_filters(
			'et_divi_divider_style_choices',
			array(
				'solid'  => esc_html__( 'Solid', 'supreme-modules-for-divi' ),
				'dotted' => esc_html__( 'Dotted', 'supreme-modules-for-divi' ),
				'dashed' => esc_html__( 'Dashed', 'supreme-modules-for-divi' ),
				'double' => esc_html__( 'Double', 'supreme-modules-for-divi' ),
				'groove' => esc_html__( 'Groove', 'supreme-modules-for-divi' ),
				'ridge'  => esc_html__( 'Ridge', 'supreme-modules-for-divi' ),
				'inset'  => esc_html__( 'Inset', 'supreme-modules-for-divi' ),
				'outset' => esc_html__( 'Outset', 'supreme-modules-for-divi' ),
			)
		);
	}
endif;
/*End of Temporary fix*/
