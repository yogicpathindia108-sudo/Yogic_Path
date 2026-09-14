<?php
/**
 * REST: MenuItemsController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\MenuManager;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Menu items REST Controller class.
 *
 * @since ??
 */
class MenuItemsController extends RESTController {

	/**
	 * Creates a menu item.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function create( WP_REST_Request $request ) {
		$menu_id = (int) $request->get_param( 'menu_id' );

		$menu = wp_get_nav_menu_object( $menu_id );

		if ( ! $menu ) {
			return self::response_error( 'menu_not_found', esc_html__( 'Menu not found.', 'et_builder_5' ), [], 404 );
		}

		$prepare = self::_prepare_menu_item_args( $request );

		if ( is_wp_error( $prepare ) ) {
			return $prepare;
		}

		$menu_item_id = wp_update_nav_menu_item( $menu_id, 0, wp_slash( $prepare ), false );

		if ( is_wp_error( $menu_item_id ) ) {
			return self::response_error(
				'menu_item_create_failed',
				$menu_item_id->get_error_message(),
				[
					'details' => $menu_item_id->get_error_data(),
				]
			);
		}

		$menu_item = self::_get_menu_item( (int) $menu_item_id );

		if ( is_wp_error( $menu_item ) ) {
			// Rollback: delete the inserted item since it cannot be loaded or returned.
			wp_delete_post( $menu_item_id, true );

			return self::response_error(
				$menu_item->get_error_code(),
				$menu_item->get_error_message(),
				[
					'details'      => $menu_item->get_error_data(),
					'rolled_back'  => true,
					'deleted_item' => $menu_item_id,
				],
				500
			);
		}

		return self::response_success(
			[
				'item' => self::_format_menu_item( $menu_item ),
			],
			[],
			201
		);
	}

	/**
	 * Deletes a menu item.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function delete( WP_REST_Request $request ) {
		$menu_item_id = (int) $request->get_param( 'id' );

		$menu_item = self::_get_menu_item( $menu_item_id );

		if ( is_wp_error( $menu_item ) ) {
			return $menu_item;
		}

		$previous = self::_format_menu_item( $menu_item );
		$result   = wp_delete_post( $menu_item_id, true );

		if ( ! $result ) {
			return self::response_error( 'menu_item_delete_failed', esc_html__( 'The menu item cannot be deleted.', 'et_builder_5' ), [], 500 );
		}

		return self::response_success(
			[
				'deleted'  => true,
				'previous' => $previous,
			]
		);
	}

	/**
	 * Reorders menu items.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function reorder( WP_REST_Request $request ) {
		$menu_id = (int) $request->get_param( 'menu_id' );
		$items   = $request->get_param( 'items' );

		$menu = wp_get_nav_menu_object( $menu_id );

		if ( ! $menu ) {
			return self::response_error( 'menu_not_found', esc_html__( 'Menu not found.', 'et_builder_5' ), [], 404 );
		}

		$snapshots = [];
		$updated   = [];

		foreach ( $items as $item ) {
			$menu_item_id = absint( $item['id'] ?? 0 );

			$menu_item = self::_get_menu_item( $menu_item_id );

			if ( is_wp_error( $menu_item ) ) {
				return $menu_item;
			}

			$belongs_to_menu = self::_is_item_in_menu( $menu_item_id, $menu_id );

			if ( true !== $belongs_to_menu ) {
				return $belongs_to_menu;
			}

			// Preserve full editable menu-item payload to prevent wp_update_nav_menu_item from clearing omitted fields.
			$snapshots[ $menu_item_id ] = [
				'menu-item-type'        => (string) $menu_item->type,
				'menu-item-title'       => (string) $menu_item->title,
				'menu-item-url'         => (string) $menu_item->url,
				'menu-item-object'      => (string) $menu_item->object,
				'menu-item-object-id'   => (int) $menu_item->object_id,
				'menu-item-parent-id'   => (int) $menu_item->menu_item_parent,
				'menu-item-position'    => (int) $menu_item->menu_order,
				'menu-item-status'      => (string) $menu_item->post_status,
				'menu-item-classes'     => implode( ' ', (array) $menu_item->classes ),
				'menu-item-xfn'         => (string) $menu_item->xfn,
				'menu-item-target'      => (string) $menu_item->target,
				'menu-item-description' => (string) $menu_item->description,
				'menu-item-attr-title'  => (string) $menu_item->attr_title,
			];
		}

		foreach ( $items as $item ) {
			$menu_item_id = absint( $item['id'] ?? 0 );
			$parent       = array_key_exists( 'parent', $item ) && ! is_null( $item['parent'] )
				? absint( $item['parent'] )
				: (int) $snapshots[ $menu_item_id ]['menu-item-parent-id'];
			$menu_order   = array_key_exists( 'menu_order', $item ) && ! is_null( $item['menu_order'] )
				? absint( $item['menu_order'] )
				: (int) $snapshots[ $menu_item_id ]['menu-item-position'];

			if ( 0 < $parent ) {
				$parent_validation = self::_validate_parent( $parent, $menu_id, $menu_item_id );

				if ( is_wp_error( $parent_validation ) ) {
					$rolled_back = ! empty( $updated );

					if ( $rolled_back ) {
						self::_rollback_items( $menu_id, $updated, $snapshots );
					}

					return self::response_error(
						$parent_validation->get_error_code(),
						$parent_validation->get_error_message(),
						array_merge(
							is_array( $parent_validation->get_error_data() ) ? $parent_validation->get_error_data() : [],
							[
								'failed_item_id'    => $menu_item_id,
								'invalid_parent_id' => $parent,
								'rolled_back'       => $rolled_back,
							]
						),
						self::_error_status( $parent_validation )
					);
				}
			}

			$updated_args                        = $snapshots[ $menu_item_id ];
			$updated_args['menu-item-parent-id'] = $parent;
			$updated_args['menu-item-position']  = 1 > $menu_order ? 1 : $menu_order;

			$update_result = wp_update_nav_menu_item( $menu_id, $menu_item_id, wp_slash( $updated_args ), false );

			if ( is_wp_error( $update_result ) ) {
				self::_rollback_items( $menu_id, $updated, $snapshots );

				return self::response_error(
					'menu_item_reorder_failed',
					$update_result->get_error_message(),
					[
						'failed_item_id' => $menu_item_id,
						'rolled_back'    => true,
					]
				);
			}

			$updated[] = $menu_item_id;
		}

		$updated_items = [];

		foreach ( $updated as $menu_item_id ) {
			$menu_item = self::_get_menu_item( $menu_item_id );

			if ( is_wp_error( $menu_item ) ) {
				return $menu_item;
			}

			$updated_items[] = self::_format_menu_item( $menu_item );
		}

		return self::response_success(
			[
				'menu_id' => $menu_id,
				'items'   => $updated_items,
			]
		);
	}

	/**
	 * Get the arguments for create action.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function create_args(): array {
		return [
			'menu_id'    => [
				'required'          => true,
				'type'              => 'integer',
				'minimum'           => 1, // Prevent zero or negative IDs.
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'type'       => [
				'required'          => false,
				'type'              => 'string',
				'format'            => 'text-field', // Sanitized using sanitize_text_field.
				'enum'              => [ 'custom', 'post_type', 'taxonomy', 'post_type_archive' ],
				'default'           => 'custom',
				'validate_callback' => function ( $param, $request ) {
					// Cross-field: required fields differ per type (cannot express in schema alone).
					$type = sanitize_key( (string) $param );

					if ( 'custom' === $type ) {
						if ( '' === sanitize_text_field( (string) $request->get_param( 'title' ) ) ) {
							return new WP_Error(
								'missing_menu_item_title',
								esc_html__( 'Title is required for custom menu items.', 'et_builder_5' )
							);
						}

						if ( '' === esc_url_raw( (string) $request->get_param( 'url' ) ) ) {
							return new WP_Error(
								'missing_menu_item_url',
								esc_html__( 'URL is required for custom menu items.', 'et_builder_5' )
							);
						}
					} elseif ( 'post_type_archive' === $type ) {
						if ( '' === sanitize_text_field( (string) $request->get_param( 'object' ) ) ) {
							return new WP_Error(
								'missing_menu_item_object',
								esc_html__( 'Object is required for post type archive menu items.', 'et_builder_5' )
							);
						}
					} elseif ( absint( $request->get_param( 'object_id' ) ) <= 0 ) {
						return new WP_Error(
							'missing_menu_item_object_id',
							esc_html__( 'Object ID is required for non-custom menu items.', 'et_builder_5' )
						);
					}

					return true;
				},
			],
			'title'      => [
				'required' => false,
				'type'     => 'string',
				'format'   => 'text-field', // Sanitized using sanitize_text_field.
			],
			'url'        => [
				'required' => false,
				'type'     => 'string',
				'format'   => 'uri',
			],
			'object'     => [
				'required' => false,
				'type'     => 'string',
				'format'   => 'text-field', // Sanitized using sanitize_text_field.
			],
			'object_id'  => [
				'required'          => false,
				'type'              => 'integer',
				'minimum'           => 0,
				'sanitize_callback' => 'absint',
			],
			'parent'     => [
				'required'          => false,
				'type'              => 'integer',
				'minimum'           => 0,
				'sanitize_callback' => 'absint',
			],
			'menu_order' => [
				'required'          => false,
				'type'              => 'integer',
				'minimum'           => 0,
				'sanitize_callback' => 'absint',
			],
			'status'     => [
				'required' => false,
				'type'     => 'string',
				'format'   => 'text-field', // Sanitized using sanitize_text_field.
				'enum'     => [ 'publish', 'draft' ],
				'default'  => 'publish',
			],
		];
	}

	/**
	 * Get the arguments for delete action.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function delete_args(): array {
		return [
			'id' => [
				'required'          => true,
				'type'              => 'integer',
				'minimum'           => 1, // Prevent zero or negative IDs.
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}

	/**
	 * Get the arguments for reorder action.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function reorder_args(): array {
		return [
			'menu_id' => [
				'required'          => true,
				'type'              => 'integer',
				'minimum'           => 1, // Prevent zero or negative IDs.
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'items'   => [
				'required'          => true,
				'type'              => 'array',
				'minItems'          => 1, // At least one item required.
				'validate_callback' => 'rest_validate_request_arg',
				'items'             => [
					'type'                 => 'object',
					'properties'           => [
						'id'         => [
							'required'          => true,
							'type'              => 'integer',
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						],
						'parent'     => [
							'required' => false,
							'type'     => [ 'integer', 'null' ],
							'minimum'  => 0,
						],
						'menu_order' => [
							'required' => false,
							'type'     => [ 'integer', 'null' ],
							'minimum'  => 0,
						],
					],
					'additionalProperties' => false,
				],
			],
		];
	}

	/**
	 * Permission callback for all menu item actions.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function menu_item_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'edit_theme_options' );
	}

	/**
	 * Prepares menu item arguments for create.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return array|WP_Error
	 */
	private static function _prepare_menu_item_args( WP_REST_Request $request ) {
		// WP REST schema (type, format, enum) handles sanitization before this method is called.
		$menu_id    = (int) $request->get_param( 'menu_id' );
		$type       = (string) $request->get_param( 'type' );
		$title      = (string) $request->get_param( 'title' );
		$url        = (string) $request->get_param( 'url' );
		$object     = (string) $request->get_param( 'object' );
		$object_id  = (int) $request->get_param( 'object_id' );
		$parent     = (int) $request->get_param( 'parent' );
		$menu_order = (int) $request->get_param( 'menu_order' );
		$status     = (string) $request->get_param( 'status' );

		// Validate parent before any database write: existence, scope, self-ref, cycles.
		if ( 0 < $parent ) {
			$parent_validation = self::_validate_parent( $parent, $menu_id );

			if ( is_wp_error( $parent_validation ) ) {
				return $parent_validation;
			}
		}

		$args = [
			'menu-item-type'      => $type,
			'menu-item-parent-id' => $parent,
			'menu-item-position'  => 1 > $menu_order ? 1 : $menu_order,
			'menu-item-status'    => $status,
		];

		if ( 'custom' === $type ) {
			$args['menu-item-title'] = $title;
			$args['menu-item-url']   = $url;

			return $args;
		}

		if ( '' === $object ) {
			$object = self::_detect_object_type( $type, $object_id );

			if ( is_wp_error( $object ) ) {
				return $object;
			}
		}

		$args['menu-item-object-id'] = $object_id;
		$args['menu-item-object']    = $object;

		return $args;
	}

	/**
	 * Detects object value for non-custom menu item types.
	 *
	 * Note: This method is only called when 'object' parameter is empty.
	 * For post_type_archive, 'object' is required by validation, so this
	 * method should never be called for that type.
	 *
	 * @since ??
	 *
	 * @param string $type      Menu item type.
	 * @param int    $object_id Object ID.
	 *
	 * @return string|WP_Error
	 */
	private static function _detect_object_type( string $type, int $object_id ) {
		if ( 'post_type' === $type ) {
			$post = get_post( $object_id );

			if ( ! $post ) {
				return self::response_error( 'invalid_post_id', esc_html__( 'Invalid post ID.', 'et_builder_5' ) );
			}

			return get_post_type( $post );
		}

		if ( 'taxonomy' === $type ) {
			$term = get_term( $object_id );

			if ( ! $term || is_wp_error( $term ) ) {
				return self::response_error( 'invalid_term_id', esc_html__( 'Invalid term ID.', 'et_builder_5' ) );
			}

			return (string) get_term_field( 'taxonomy', $term );
		}

		// post_type_archive requires 'object' parameter (validated in create_args),
		// so this method should never be reached for that type.
		// If it is reached, return an error.
		if ( 'post_type_archive' === $type ) {
			return self::response_error(
				'missing_menu_item_object',
				esc_html__( 'Object (post type slug) is required for post type archive menu items.', 'et_builder_5' )
			);
		}

		return '';
	}

	/**
	 * Returns formatted menu item.
	 *
	 * @since ??
	 *
	 * @param object $menu_item The menu item object from wp_setup_nav_menu_item.
	 *
	 * @return array
	 */
	private static function _format_menu_item( object $menu_item ): array {
		return [
			'id'         => (int) $menu_item->ID,
			'menu_id'    => self::_get_menu_id( (int) $menu_item->ID ),
			'parent'     => (int) $menu_item->menu_item_parent,
			'menu_order' => (int) $menu_item->menu_order,
			'type'       => (string) $menu_item->type,
			'object'     => (string) $menu_item->object,
			'object_id'  => (int) $menu_item->object_id,
			'title'      => (string) $menu_item->title,
			'url'        => (string) $menu_item->url,
		];
	}

	/**
	 * Retrieves a menu item.
	 *
	 * @since ??
	 *
	 * @param int $menu_item_id Menu item ID.
	 *
	 * @return object|WP_Error
	 */
	private static function _get_menu_item( int $menu_item_id ) {
		if ( 0 >= $menu_item_id ) {
			return self::response_error( 'missing_menu_item_id', esc_html__( 'Menu item ID is required.', 'et_builder_5' ) );
		}

		$post = get_post( $menu_item_id );

		if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
			return self::response_error( 'menu_item_not_found', esc_html__( 'Menu item not found.', 'et_builder_5' ), [], 404 );
		}

		$menu_item = wp_setup_nav_menu_item( $post );

		if ( false === $menu_item ) {
			return self::response_error(
				'menu_item_setup_failed',
				esc_html__( 'Menu item could not be prepared.', 'et_builder_5' ),
				[],
				500
			);
		}

		return $menu_item;
	}

	/**
	 * Gets menu ID for a menu item.
	 *
	 * @since ??
	 *
	 * @param int $menu_item_id Menu item ID.
	 *
	 * @return int
	 */
	private static function _get_menu_id( int $menu_item_id ): int {
		$menu_ids = wp_get_post_terms(
			$menu_item_id,
			'nav_menu',
			[
				'fields' => 'ids',
			]
		);

		if ( is_wp_error( $menu_ids ) || empty( $menu_ids ) ) {
			return 0;
		}

		return absint( $menu_ids[0] );
	}

	/**
	 * Ensures the menu item belongs to a menu.
	 *
	 * @since ??
	 *
	 * @param int $menu_item_id Menu item ID.
	 * @param int $menu_id      Menu ID.
	 *
	 * @return true|WP_Error
	 */
	private static function _is_item_in_menu( int $menu_item_id, int $menu_id ) {
		if ( self::_get_menu_id( $menu_item_id ) !== $menu_id ) {
			return self::response_error(
				'menu_item_menu_mismatch',
				esc_html__( 'Menu item does not belong to the provided menu.', 'et_builder_5' ),
				[],
				400
			);
		}

		return true;
	}

	/**
	 * Rolls back updated menu items to their original state.
	 *
	 * @since ??
	 *
	 * @param int   $menu_id   Menu ID.
	 * @param array $updated   Updated menu item IDs.
	 * @param array $snapshots Original values by menu item ID.
	 *
	 * @return void
	 */
	private static function _rollback_items( int $menu_id, array $updated, array $snapshots ): void {
		foreach ( $updated as $menu_item_id ) {
			$snapshot = $snapshots[ $menu_item_id ] ?? null;

			if ( null === $snapshot ) {
				continue;
			}

			wp_update_nav_menu_item(
				$menu_id,
				$menu_item_id,
				wp_slash(
					$snapshot
				),
				false
			);
		}
	}

	/**
	 * Validates a parent menu item ID for hierarchy integrity.
	 *
	 * Checks existence, menu scope, self-reference, and cycles.
	 *
	 * @since ??
	 *
	 * @param int $parent_id Parent menu item ID.
	 * @param int $menu_id   Target menu ID.
	 * @param int $item_id   Current item ID (0 for new items). Used for self-reference check.
	 *
	 * @return true|WP_Error
	 */
	private static function _validate_parent( int $parent_id, int $menu_id, int $item_id = 0 ) {
		// Self-reference check.
		if ( 0 !== $item_id && $parent_id === $item_id ) {
			return new WP_Error(
				'invalid_parent_self',
				esc_html__( 'A menu item cannot be its own parent.', 'et_builder_5' ),
				[
					'item_id'   => $item_id,
					'parent_id' => $parent_id,
				]
			);
		}

		// Existence check.
		$parent_item = self::_get_menu_item( $parent_id );

		if ( is_wp_error( $parent_item ) ) {
			return new WP_Error(
				'invalid_parent_not_found',
				sprintf(
					/* translators: %d: parent menu item ID */
					esc_html__( 'Parent menu item %d not found.', 'et_builder_5' ),
					$parent_id
				),
				[
					'parent_id' => $parent_id,
					'status'    => 404,
				]
			);
		}

		// Scope check: parent must belong to the same menu.
		$parent_in_menu = self::_is_item_in_menu( $parent_id, $menu_id );

		if ( is_wp_error( $parent_in_menu ) ) {
			return $parent_in_menu;
		}

		// Cycle detection: walk up the ancestor chain with maximum depth guard.
		$visited        = [ $item_id ];
		$current_parent = (int) $parent_item->menu_item_parent;
		$max_depth      = 100; // Prevent infinite loops in corrupted data.
		$depth          = 0;

		while ( 0 !== $current_parent ) {
			++$depth;

			if ( $depth > $max_depth ) {
				return new WP_Error(
					'invalid_parent_depth',
					esc_html__( 'Parent relationship exceeds maximum depth limit.', 'et_builder_5' ),
					[
						'item_id'   => $item_id,
						'parent_id' => $parent_id,
						'max_depth' => $max_depth,
					]
				);
			}

			if ( in_array( $current_parent, $visited, true ) ) {
				return new WP_Error(
					'invalid_parent_cycle',
					esc_html__( 'Parent relationship would create a cycle in the menu tree.', 'et_builder_5' ),
					[
						'item_id'   => $item_id,
						'parent_id' => $parent_id,
					]
				);
			}

			$visited[] = $current_parent;

			$ancestor = self::_get_menu_item( $current_parent );

			if ( is_wp_error( $ancestor ) ) {
				// Broken ancestor chain — stop walking, allow the update.
				break;
			}

			$current_parent = (int) $ancestor->menu_item_parent;
		}

		return true;
	}

	/**
	 * Gets error status code from WP_Error data payload.
	 *
	 * @since ??
	 *
	 * @param WP_Error $error Error instance.
	 *
	 * @return int
	 */
	private static function _error_status( WP_Error $error ): int {
		$error_data = $error->get_error_data();

		if ( is_array( $error_data ) && isset( $error_data['status'] ) && is_numeric( $error_data['status'] ) ) {
			return (int) $error_data['status'];
		}

		if ( is_numeric( $error_data ) ) {
			return (int) $error_data;
		}

		return 400;
	}
}
