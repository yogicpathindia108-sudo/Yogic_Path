<?php
/**
 * Class for checking if post contains modules that makes post content not compatible with Divi 5.
 *
 * @since ??
 *
 * @package D5_Readiness
 */

namespace Divi\D5_Readiness\Server\Checks\PostFeature;

/**
 * Class for checking if post contains modules that makes post content not compatible with Divi 5.
 *
 * @package D5_Readiness
 */

use Divi\D5_Readiness\Helpers;
use Divi\D5_Readiness\Server\AJAXEndpoints\CompatibilityChecks;
use Divi\D5_Readiness\Server\Checks\PostFeatureCheck;

/**
 * Class for checking if post contains modules that makes post content not compatible with Divi 5.
 *
 * @since ??
 *
 * @package D5_Readiness
 */
class ModuleUsage extends PostFeatureCheck {

	/**
	 * Module's post feature check results.
	 *
	 * @var array
	 *
	 * @since ??
	 */
	protected $_module_results = [];

	/**
	 * Constructor.
	 *
	 * @param int    $post_id      The post ID.
	 * @param string $post_content The post content.
	 * @param array  $post_meta    The post meta.
	 *
	 * @return void
	 */
	public function __construct( $post_id, $post_content, $post_meta ) {
		$this->_post_id      = $post_id;
		$this->_post_content = $post_content;
		$this->_post_meta    = $post_meta;

		$this->_feature_name = __( 'Module Use', 'et_builder' );
	}

	/**
	 * Check the post content for certain module use.
	 *
	 * @param string $content The post content.
	 *
	 * @return bool|array false if no modules detected, results array otherwise.
	 */
	protected function _check_post_content( $content ) {
		$module_slugs   = CompatibilityChecks::third_party_module_slugs();
		$module_results = [];

		foreach ( $module_slugs as $slug => $module_info ) {
			if ( strpos( $content, $slug ) !== false ) {
				if ( Helpers\is_third_party_module_convertible( $slug ) ) {
					// Skip convertible modules - they should not be flagged as incompatible
					continue;
				}

				// Include all other non-convertible modules (third-party, WooCommerce, native).
				$module_results[] = $module_info['name'];
			}
		}

		if ( empty( $module_results ) ) {
			return false;
		}

		$this->_module_results = $module_results;

		$results = [
			'detected'    => count( $module_results ) > 0,
			'description' => 'Modules found: ' . implode( ', ', $module_results ),
		];

		return $results;
	}

	/**
	 * Get feature name.
	 *
	 * @since ??
	 */
	public function get_feature_name() {
		// implode the module results and return them, appending "Use" at the end of each module name.
		$module_results = array_map(
			function( $module ) {
				return $module . ' Use';
			},
			$this->_module_results
		);

		return implode( ', ', $module_results );
	}
}
