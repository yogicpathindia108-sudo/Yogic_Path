<?php
/**
 * Loop Query Taxonomies: QueryTaxonomiesController.
 *
 * @package Builder\Packages\Module\Options\Loop\QueryTaxonomies
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Loop\QueryTaxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Query Taxonomies REST Controller class.
 *
 * @since ??
 */
class QueryTaxonomiesController extends RESTController {
	/**
	 * Return all taxonomies with their terms for the Loop module.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$taxonomies_data = [];

		// Get post_type parameter from request.
		$post_types_param = $request->get_param( 'post_type' );
		$post_types       = [];

		// Parse post_type parameter (supports comma-separated values).
		// If post_type is empty or not provided, $post_types will remain empty array.
		if ( ! empty( $post_types_param ) ) {
			if ( is_string( $post_types_param ) ) {
				$post_types = array_map( 'sanitize_key', array_map( 'trim', explode( ',', sanitize_text_field( $post_types_param ) ) ) );
			} elseif ( is_array( $post_types_param ) ) {
				$post_types = array_map( 'sanitize_key', $post_types_param );
			}
			// Remove empty values.
			$post_types = array_filter( $post_types );
		}

		// Get taxonomies based on post types or all public taxonomies.
		if ( ! empty( $post_types ) ) {
			// Get taxonomies for specific post types.
			$taxonomies = [];
			foreach ( $post_types as $post_type ) {
				$post_type_taxonomies = get_object_taxonomies( $post_type, 'objects' );
				if ( ! empty( $post_type_taxonomies ) ) {
					foreach ( $post_type_taxonomies as $taxonomy_slug => $taxonomy_object ) {
						// Only include public taxonomies.
						if ( $taxonomy_object->public ) {
							$taxonomies[ $taxonomy_slug ] = $taxonomy_object;
						}
					}
				}
			}
		} else {
			// Get all public taxonomies if no post_type specified or if post_type is empty.
			$taxonomies = get_taxonomies(
				[
					'public' => true,
				],
				'objects'
			);
		}

		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy_slug => $taxonomy_object ) {
				// Get all terms for this taxonomy.
				$terms = get_terms(
					[
						'taxonomy'   => $taxonomy_slug,
						'hide_empty' => false,
						'number'     => 0, // Get all terms.
					]
				);

				// Skip taxonomies with no terms.
				if ( is_wp_error( $terms ) || empty( $terms ) ) {
					continue;
				}

				// Format terms data.
				$formatted_terms = [];
				foreach ( $terms as $term ) {
					$formatted_terms[] = [
						'id'   => $term->term_id,
						'name' => $term->name,
						'slug' => $term->slug,
					];
				}

				// Add taxonomy data.
				$taxonomies_data[ $taxonomy_slug ] = [
					'label' => $taxonomy_object->label,
					'terms' => $formatted_terms,
				];
			}
		}

		return self::response_success( $taxonomies_data );
	}

	/**
	 * Index action arguments.
	 *
	 * Endpoint arguments as used in `register_rest_route()`.
	 *
	 * @return array
	 */
	public static function index_args(): array {
		return [
			'post_type' => [
				'description'       => __( 'Comma-separated list of post types to get taxonomies for. If empty, returns taxonomies for all post types.', 'et_builder_5' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'validate_callback' => function ( $param ) {
					// Allow empty string, single post type, or comma-separated post types.
					if ( empty( $param ) ) {
						return true;
					}

					if ( is_string( $param ) ) {
						$post_types = array_map( 'sanitize_key', array_map( 'trim', explode( ',', sanitize_text_field( $param ) ) ) );
						foreach ( $post_types as $post_type ) {
							if ( ! post_type_exists( $post_type ) ) {
								return new \WP_Error(
									'invalid_post_type',
									sprintf( __( 'Post type "%s" does not exist.', 'et_builder_5' ), $post_type )
								);
							}
						}
						return true;
					}

					return false;
				},
			],
		];
	}

	/**
	 * Index action permission.
	 *
	 * Endpoint permission callback as used in `register_rest_route()`.
	 *
	 * @return bool
	 */
	public static function index_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}
}
