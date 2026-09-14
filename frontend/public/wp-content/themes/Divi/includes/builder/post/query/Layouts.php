<?php


class ET_Builder_Post_Query_Layouts extends ET_Core_Post_Query {

	/**
	 * Whether or not to automatically exclude product tour layouts from the results.
	 *
	 * @since 3.0.99
	 * @var bool
	 */
	protected $_exclude_product_tour = true;

	/**
	 * @inheritDoc
	 */
	protected function _add_tax_query( $tax_name, $args, $negate = null ) {
		$args   = self::$_->array_flatten( $args );
		$negate = $this->_reset_negate();

		if ( ! $args ) {
			return $this;
		}

		parent::_add_tax_query( $tax_name, $args, $negate );

		return $this;
	}

	public function in_pack() {
		$args = func_get_args();

		return $this->with_tag( $args );
	}

	public function is_fullwidth() {
		$tax_name = ET_Builder_Post_Taxonomy_LayoutWidth::instance()->name;

		return $this->_add_tax_query( $tax_name, array( 'fullwidth' ) );
	}

	public function is_type() {
		$tax_name = ET_Builder_Post_Taxonomy_LayoutType::instance()->name;
		$args     = func_get_args();

		return $this->_add_tax_query( $tax_name, $args );
	}

	/**
	 * @inheritDoc
	 */
	public function run( $args = array() ) {
		$exclude_product_tour = apply_filters( 'et_builder_layout_query_exclude_product_tour', $this->_exclude_product_tour );

		if ( $exclude_product_tour ) {
			$this->not()->with_meta( '_et_pb_layout_applicability', 'product_tour' );
		}

		return parent::run( $args );
	}

	/**
	 * @inheritDoc
	 */
	public function with_meta( $key, $value = null ) {
		if ( '_et_pb_layout_applicability' === $key && 'product_tour' === $value ) {
			$this->_exclude_product_tour = false;
		}

		return parent::with_meta( $key, $value );
	}

	public function with_scope() {
		$tax_name = ET_Builder_Post_Taxonomy_LayoutScope::instance()->name;
		$args     = func_get_args();

		return $this->_add_tax_query( $tax_name, $args );
	}

	/**
	 * Whether a database error indicates the library index query hit MySQL join-size limits.
	 *
	 * @since 5.7.2
	 *
	 * @param string $error Database error message from `$wpdb->last_error`.
	 *
	 * @return bool
	 */
	public static function is_library_query_join_limit_error( $error ) {
		if ( empty( $error ) || ! is_string( $error ) ) {
			return false;
		}

		return false !== stripos( $error, 'MAX_JOIN_SIZE' )
			|| false !== stripos( $error, 'SQL_BIG_SELECTS' );
	}
}
