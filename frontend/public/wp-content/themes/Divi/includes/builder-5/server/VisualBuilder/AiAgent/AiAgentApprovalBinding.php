<?php
/**
 * AI Agent approval token binding (id vs args_hash).
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\AiAgent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WP_Post;

/**
 * Canonical token binding for gated AI Agent tools.
 *
 * JS `getApprovalBinding` must produce the same binding_type / binding_value
 * for sanitizer-only tools. `delete_rule` title→id resolution is PHP-only
 * (mint and consume share `resolve_delete_rule_id`); JS still binds numeric
 * `ruleId` values the same way.
 *
 * Canonical args_hash field lists (must match JS get-approval-binding):
 * create_page: title, post_type, post_status, post_date (only when future + non-empty).
 * create_template: live, title, create_header_layout, create_body_layout, create_footer_layout.
 * add_rule: title, rule, whenToUse, alwaysUse.
 * batch_remove_menu_items: ids (positive unique numeric sort).
 *
 * Hashed strings use the same sanitizers as the write routes so mint (raw
 * model args) and consume (`$request->get_params()` after REST sanitization)
 * produce the same binding.
 *
 * `delete_rule` binds the canonical post id: a whole-string positive integer,
 * otherwise the current user's published rule with that exact title. Do not use
 * leading `absint` — titles like `2026 Style Guide` would bind to post `2026`.
 *
 * @since ??
 */
class AiAgentApprovalBinding {
	/**
	 * JSON encode flags: match JS JSON.stringify (unescaped slashes and unicode).
	 *
	 * @var int
	 */
	private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

	/**
	 * Builds the token binding for a gated tool call.
	 *
	 * Accepts model args (camelCase, mint body) or REST params (snake_case, write).
	 *
	 * @since ??
	 *
	 * @param string $tool_name Tool name.
	 * @param array  $args      Model args or REST params.
	 *
	 * @return array{binding_type: string, binding_value: string}|null
	 */
	public static function from_args( string $tool_name, array $args ): ?array {
		switch ( $tool_name ) {
			case 'trash_page':
			case 'update_page_status':
			case 'delete_menu':
				return self::_id_binding( self::_read_id_value( self::_get_arg( $args, 'id' ) ) );
			case 'delete_template':
			case 'update_template':
			case 'assign_template':
				return self::_id_binding( self::_read_id_value( self::_get_arg( $args, 'template_id' ) ) );
			case 'delete_rule':
				return self::_id_binding( self::resolve_delete_rule_id( self::_get_arg( $args, 'ruleId' ) ) );
			case 'set_divi_layout':
				return self::_id_binding( self::_read_id_value( self::_get_arg( $args, 'post_id' ) ) );
			case 'assign_menu_location':
				return self::_menu_location_binding( $args );
			case 'update_theme_option':
				$key = self::_get_arg( $args, 'key' );
				return self::_id_binding( is_string( $key ) && '' !== $key ? $key : null );
			case 'create_page':
				return self::_create_page_binding( $args );
			case 'create_template':
				return self::_create_template_binding( $args );
			case 'add_rule':
				return self::_add_rule_binding( $args );
			case 'delete_library_item':
				return self::_delete_library_item_binding( $args );
			case 'batch_remove_menu_items':
				return self::_batch_remove_menu_items_binding( $args );
			default:
				return null;
		}
	}

	/**
	 * @since ??
	 *
	 * @param array $args Args.
	 *
	 * @return array{binding_type: string, binding_value: string}|null
	 */
	private static function _menu_location_binding( array $args ): ?array {
		$menu_id  = self::_read_id_value( self::_first_arg( $args, [ 'menuId', 'menu_id' ] ) );
		$location = self::_read_string( self::_get_arg( $args, 'location' ) );

		if ( null === $menu_id || '' === $location ) {
			return null;
		}

		return self::_id_binding( $menu_id . ':' . $location );
	}

	/**
	 * @since ??
	 *
	 * @param array $args Args.
	 *
	 * @return array{binding_type: string, binding_value: string}|null
	 */
	private static function _create_page_binding( array $args ): ?array {
		$post_status = self::_read_string( self::_first_arg( $args, [ 'status', 'post_status' ] ), 'publish' );
		$post_status = '' !== $post_status ? $post_status : 'publish';
		$post_type   = self::_read_string( self::_first_arg( $args, [ 'postType', 'post_type' ] ), 'page' );
		$post_type   = '' !== $post_type ? $post_type : 'page';

		$body = [
			'title'       => self::_read_string( self::_get_arg( $args, 'title' ) ),
			'post_type'   => $post_type,
			'post_status' => $post_status,
		];

		$scheduled_date = self::_read_string( self::_first_arg( $args, [ 'scheduledDate', 'post_date' ] ) );

		if ( 'future' === $post_status && '' !== $scheduled_date ) {
			$body['post_date'] = $scheduled_date;
		}

		return self::_args_hash_binding( $body );
	}

	/**
	 * @since ??
	 *
	 * @param array $args Args.
	 *
	 * @return array{binding_type: string, binding_value: string}|null
	 */
	private static function _create_template_binding( array $args ): ?array {
		return self::_args_hash_binding(
			[
				'live'                 => self::_read_boolean( self::_get_arg( $args, 'live' ), true ),
				'title'                => self::_read_string( self::_get_arg( $args, 'title' ) ),
				'create_header_layout' => self::_read_boolean( self::_first_arg( $args, [ 'createHeaderLayout', 'create_header_layout' ] ), false ),
				'create_body_layout'   => self::_read_boolean( self::_first_arg( $args, [ 'createBodyLayout', 'create_body_layout' ] ), false ),
				'create_footer_layout' => self::_read_boolean( self::_first_arg( $args, [ 'createFooterLayout', 'create_footer_layout' ] ), false ),
			]
		);
	}

	/**
	 * @since ??
	 *
	 * @param array $args Args.
	 *
	 * @return array{binding_type: string, binding_value: string}|null
	 */
	private static function _add_rule_binding( array $args ): ?array {
		return self::_args_hash_binding(
			[
				'title'     => self::_read_string( self::_get_arg( $args, 'title' ) ),
				'rule'      => self::_read_textarea( self::_get_arg( $args, 'rule' ) ),
				'whenToUse' => self::_read_textarea( self::_get_arg( $args, 'whenToUse' ) ),
				'alwaysUse' => self::_read_boolean( self::_get_arg( $args, 'alwaysUse' ), false ),
			]
		);
	}

	/**
	 * Binds delete_library_item to String(itemId) from mint or REST nested shapes.
	 *
	 * @since ??
	 *
	 * @param array $args Model args or REST params.
	 *
	 * @return array{binding_type: string, binding_value: string}|null
	 */
	private static function _delete_library_item_binding( array $args ): ?array {
		$item_id = self::_read_id_value( self::_get_arg( $args, 'itemId' ) );

		if ( null !== $item_id ) {
			return self::_id_binding( $item_id );
		}

		$clicked_item = self::_get_arg( $args, 'clickedItem' );

		if ( ! is_array( $clicked_item ) ) {
			$data = self::_get_arg( $args, 'data' );

			if ( is_array( $data ) ) {
				$clicked_item = array_key_exists( 'clickedItem', $data ) ? $data['clickedItem'] : null;
			}
		}

		if ( ! is_array( $clicked_item ) ) {
			return null;
		}

		return self::_id_binding( self::_read_id_value( self::_get_arg( $clicked_item, 'id' ) ) );
	}

	/**
	 * Binds batch_remove_menu_items to args_hash of canonical { ids: number[] }.
	 *
	 * @since ??
	 *
	 * @param array $args Model args or REST params.
	 *
	 * @return array{binding_type: string, binding_value: string}|null
	 */
	private static function _batch_remove_menu_items_binding( array $args ): ?array {
		$ids = self::_canonical_menu_item_ids( self::_get_arg( $args, 'ids' ) );

		if ( null === $ids ) {
			return null;
		}

		return self::_args_hash_binding( [ 'ids' => $ids ] );
	}

	/**
	 * Positive unique menu item IDs, sorted numerically.
	 *
	 * PHP `_sort_keys` preserves list order, so mint `[3,1,2]` and REST `[1,2,3]`
	 * must be normalized before hash.
	 *
	 * @since ??
	 *
	 * @param mixed $ids Raw ids.
	 *
	 * @return int[]|null
	 */
	private static function _canonical_menu_item_ids( $ids ): ?array {
		if ( ! is_array( $ids ) ) {
			return null;
		}

		$unique = [];

		foreach ( $ids as $id ) {
			$normalized = self::_read_positive_int( $id );

			if ( null === $normalized ) {
				continue;
			}

			$unique[ $normalized ] = $normalized;
		}

		if ( [] === $unique ) {
			return null;
		}

		$canonical = array_values( $unique );
		sort( $canonical, SORT_NUMERIC );

		return $canonical;
	}

	/**
	 * Canonical delete_rule id: whole-string positive integer, else exact title.
	 *
	 * @since ??
	 *
	 * @param mixed $value Model `ruleId` or REST param.
	 *
	 * @return string|null Post id string, or null when unbound.
	 */
	public static function resolve_delete_rule_id( $value ): ?string {
		$positive_int = self::_read_positive_int( $value );

		if ( null !== $positive_int ) {
			return (string) $positive_int;
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		return self::_find_rule_id_by_title( sanitize_text_field( $value ) );
	}

	/**
	 * Exact title match for a published rule owned by the current user.
	 *
	 * Ambiguous titles (0 or 2+ rows) return null so mint/consume refuse the
	 * token instead of deleting the wrong post.
	 *
	 * @since ??
	 *
	 * @param string $title Sanitized rule title.
	 *
	 * @return string|null Post id string.
	 */
	private static function _find_rule_id_by_title( string $title ): ?string {
		$user_id = get_current_user_id();

		if ( 0 === $user_id || '' === $title ) {
			return null;
		}

		$posts = get_posts(
			[
				'post_type'              => AiAgentRules::POST_TYPE,
				'post_status'            => [ 'publish' ],
				'author'                 => $user_id,
				'title'                  => $title,
				'numberposts'            => 2,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			]
		);

		if ( 1 !== count( $posts ) || ! ( $posts[0] instanceof WP_Post ) ) {
			return null;
		}

		return (string) $posts[0]->ID;
	}

	/**
	 * @since ??
	 *
	 * @param mixed $value Raw id.
	 *
	 * @return int|null
	 */
	private static function _read_positive_int( $value ): ?int {
		if ( is_int( $value ) ) {
			return 0 < $value ? $value : null;
		}

		if ( is_float( $value ) && is_finite( $value ) && $value === floor( $value ) ) {
			$normalized = (int) $value;

			return 0 < $normalized ? $normalized : null;
		}

		if ( is_string( $value ) && ctype_digit( $value ) ) {
			$normalized = absint( $value );

			return 0 < $normalized ? $normalized : null;
		}

		return null;
	}

	/**
	 * @since ??
	 *
	 * @param string|null $binding_value Binding value.
	 *
	 * @return array{binding_type: string, binding_value: string}|null
	 */
	private static function _id_binding( ?string $binding_value ): ?array {
		if ( null === $binding_value ) {
			return null;
		}

		return [
			'binding_type'  => 'id',
			'binding_value' => $binding_value,
		];
	}

	/**
	 * @since ??
	 *
	 * @param array $body Canonical REST body.
	 *
	 * @return array{binding_type: string, binding_value: string}|null
	 */
	private static function _args_hash_binding( array $body ): ?array {
		$sorted = self::_sort_keys( $body );
		$json   = wp_json_encode( $sorted, self::JSON_FLAGS );

		if ( ! is_string( $json ) ) {
			return null;
		}

		return [
			'binding_type'  => 'args_hash',
			'binding_value' => hash( 'sha256', $json ),
		];
	}

	/**
	 * Recursively sort object keys. Preserve list arrays.
	 *
	 * @since ??
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	private static function _sort_keys( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( array_keys( $value ) === range( 0, count( $value ) - 1 ) ) {
			return array_map( [ self::class, '_sort_keys' ], $value );
		}

		ksort( $value, SORT_STRING );

		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::_sort_keys( $item );
		}

		return $value;
	}

	/**
	 * @since ??
	 *
	 * @param array  $args Args.
	 * @param string $key  Key.
	 *
	 * @return mixed
	 */
	private static function _get_arg( array $args, string $key ) {
		return array_key_exists( $key, $args ) ? $args[ $key ] : null;
	}

	/**
	 * @since ??
	 *
	 * @param array $args Args.
	 * @param array $keys Keys in priority order.
	 *
	 * @return mixed
	 */
	private static function _first_arg( array $args, array $keys ) {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $args ) && null !== $args[ $key ] ) {
				return $args[ $key ];
			}
		}

		return null;
	}

	/**
	 * @since ??
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Fallback.
	 *
	 * @return string
	 */
	private static function _read_string( $value, string $fallback = '' ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : $fallback;
	}

	/**
	 * @since ??
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Fallback.
	 *
	 * @return string
	 */
	private static function _read_textarea( $value, string $fallback = '' ): string {
		return is_string( $value ) ? sanitize_textarea_field( $value ) : $fallback;
	}

	/**
	 * @since ??
	 *
	 * @param mixed $value    Raw value.
	 * @param bool  $fallback Fallback when absent/untyped.
	 *
	 * @return bool
	 */
	private static function _read_boolean( $value, bool $fallback ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( null === $value || '' === $value ) {
			return $fallback;
		}

		return rest_sanitize_boolean( $value );
	}

	/**
	 * @since ??
	 *
	 * @param mixed $value Raw id.
	 *
	 * @return string|null
	 */
	private static function _read_id_value( $value ): ?string {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}

		return null;
	}
}
