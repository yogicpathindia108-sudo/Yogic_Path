<?php
/**
 * Cache State for Dynamic Assets.
 *
 * Holds all cache and path-related properties.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets\DynamicAssets\State;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Cache state container.
 *
 * Holds all properties related to caching, paths, and post identification.
 *
 * @since ??
 */
class CacheState {

	/**
	 * TB template ids.
	 *
	 * @var array
	 */
	public array $tb_template_ids = [];

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public int $post_id = 0;

	/**
	 * Original post content from the post object.
	 *
	 * @var string
	 */
	public string $original_post_content = '';

	/**
	 * Object ID.
	 *
	 * @var int
	 */
	public int $object_id = 0;

	/**
	 * Entire page content, including TB Header, TB Body Layout, Post Content and TB Footer.
	 *
	 * Content is not passed through `the_content` filter, This means that `$_all_content` will include auto-embedded
	 * videos or expanded blocks, the content is considered raw.
	 *
	 * @var string
	 */
	public string $all_content = '';

	/**
	 * Folder Name.
	 *
	 * @var string
	 */
	public string $folder_name = '';

	/**
	 * Cache Directory Path.
	 *
	 * @var string
	 */
	public string $cache_dir_path = '';

	/**
	 * Cache Directory URL.
	 *
	 * @var string
	 */
	public string $cache_dir_url = '';

	/**
	 * Product directory.
	 *
	 * @var string
	 */
	public string $product_dir = '';

	/**
	 * Resource owner.
	 *
	 * @var string
	 */
	public string $owner = '';

	/**
	 * Suffix used for files on custom post types.
	 *
	 * @var string
	 */
	public string $cpt_suffix = '';

	/**
	 * Check if RTL is used.
	 *
	 * @var bool
	 */
	public bool $is_rtl = false;

	/**
	 * Suffix used for files on RTL websites.
	 *
	 * @var string
	 */
	public string $rtl_suffix = '';

	/**
	 * Prefix used for files that contain css from theme builder templates.
	 *
	 * @var string
	 */
	public string $tb_prefix = '';

	/**
	 * Is page builder used.
	 *
	 * @var bool
	 */
	public bool $page_builder_used = false;

	/**
	 * Dynamic Enqueued Assets list.
	 *
	 * Contains an object with 'head' and 'body' properties for enqueued assets.
	 *
	 * @var array|\stdClass
	 */
	public $enqueued_assets = [];
}
