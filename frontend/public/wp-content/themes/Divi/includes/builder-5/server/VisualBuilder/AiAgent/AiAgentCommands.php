<?php
/**
 * AI Agent commands post type registration.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\AiAgent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;

/**
 * Registers AI agent commands post type.
 *
 * @since ??
 */
class AiAgentCommands implements DependencyInterface {
	/**
	 * AI command post type key.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'et_ai_command';

	/**
	 * Register post type on init.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		add_action( 'init', [ self::class, 'register_post_type' ] );
	}

	/**
	 * Register the AI commands custom post type.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'              => [
					'name'          => __( 'AI Commands', 'et_builder_5' ),
					'singular_name' => __( 'AI Command', 'et_builder_5' ),
				],
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'supports'            => [ 'title', 'editor' ],
			]
		);
	}
}
