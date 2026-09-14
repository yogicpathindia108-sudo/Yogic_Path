<?php
/**
 * Security class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Security;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Security\DynamicContent\DynamicContentFixes;
use ET\Builder\Security\AttributeSecurity\AttributeSecurity;
use ET\Builder\Security\HtmlSecurity\HtmlSecurity;
use ET\Builder\Framework\DependencyManagement\DependencyTree;


/**
 * Security Class.
 *
 * This class is responsible for loading all the security functionalities. It accepts
 * a DependencyTree on construction, specifying the dependencies and their priorities for loading.
 *
 * @since ??
 *
 * @param DependencyTree $dependencyTree The dependency tree instance specifying the dependencies and priorities.
 */
class Security {
	/**
	 * Stores the dependencies that were passed to the constructor.
	 *
	 * This property holds an instance of the DependencyTree class that represents the dependencies
	 * passed to the constructor of the current object.
	 *
	 * @since ??
	 *
	 * @var DependencyTree $dependencies An instance of DependencyTree representing the dependencies.
	 */
	private $_dependency_tree;

	/**
	 * Constructs a new instance of the class and sets its dependencies.
	 *
	 * @param DependencyTree $dependency_tree The dependency tree for the class to load.
	 *
	 * @since ??
	 *
	 * @return void
	 *
	 * @example
	 * ```php
	 * $dependency_tree = new DependencyTree();
	 * $security = new Security($dependency_tree);
	 * ```
	 */
	public function __construct( DependencyTree $dependency_tree ) {
		$this->_dependency_tree = $dependency_tree;
	}

	/**
	 * Loads and initializes all the functionalities related to the Security area.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function initialize() {
		$this->_dependency_tree->load_dependencies();

		/*
		 * Save-time `post_content` transforms (priorities 5, 6, 10) are replayed in order by
		 * {@see Security::apply_post_content_save_transforms()} for Visual Builder save verification.
		 */
		// Security Audit - elegantthemes/Divi#41951 .
		add_filter( 'wp_insert_post_data', [ $this, 'sanitize_dynamic_content_fields' ], 10, 2 );

		// Custom attribute sanitization - always runs regardless of user capabilities.
		add_filter( 'wp_insert_post_data', [ $this, 'sanitize_custom_attributes_fields' ], 5, 2 );

		// HTML Before/After sanitization - sanitizes based on user capabilities.
		add_filter( 'wp_insert_post_data', [ $this, 'sanitize_html_fields' ], 6, 2 );
	}

	/**
	 * Sanitize dynamic content on save.
	 *
	 * Check on save post if the user has the unfiltered_html capability,
	 * if they do, we can bail, because they can save whatever they want,
	 * if they don't, we need to strip the enable_html flag from the dynamic content item,
	 * and then re-encode it, and put the new value back in the post content.
	 *
	 * @since ??
	 *
	 * @param array $data  An array of slashed post data.
	 *
	 * @return array $data Modified post data.
	 */
	public function sanitize_dynamic_content_fields( $data ) {
		// Exit early if there's nothing to fix or user has `unfiltered_html` capability.
		if ( strpos( $data['post_content'], 'enable_html' ) === false || current_user_can( 'unfiltered_html' ) ) {
			return $data;
		}

		return DynamicContentFixes::disable_html( $data );
	}

	/**
	 * Sanitize custom attributes on save.
	 *
	 * This function ensures that custom module attributes are properly sanitized
	 * regardless of user capabilities. Custom attributes are a security feature
	 * and should always be validated against the HTMLUtility whitelist.
	 *
	 * @since ??
	 *
	 * @param array $data  An array of slashed post data.
	 *
	 * @return array $data Modified post data.
	 */
	public function sanitize_custom_attributes_fields( $data ) {
		return AttributeSecurity::sanitize_custom_attributes_fields( $data );
	}

	/**
	 * Sanitize HTML Before/After fields on save.
	 *
	 * This function ensures that HTML Before and After fields are properly sanitized
	 * based on user capabilities. Users with `unfiltered_html` capability can save
	 * any HTML, while users without it will have their HTML sanitized via wp_kses_post.
	 *
	 * @since ??
	 *
	 * @param array $data  An array of slashed post data.
	 *
	 * @return array $data Modified post data.
	 */
	public function sanitize_html_fields( $data ) {
		return HtmlSecurity::sanitize_html_fields( $data );
	}

	/**
	 * Apply Divi-owned `post_content` transforms in the same order as `wp_insert_post_data` hooks.
	 *
	 * Replays priority 5 (`AttributeSecurity`), 6 (`HtmlSecurity`), then 10 (dynamic content HTML
	 * disable via `DynamicContentFixes`), matching {@see self::initialize()}. When changing those
	 * filter callbacks or priorities, update this method and `initialize()` together.
	 *
	 * @since ??
	 *
	 * @param array $data Slashed post data; must include `post_content`.
	 *
	 * @return array Modified slashed post data.
	 */
	public static function apply_post_content_save_transforms( array $data ): array {
		$data = AttributeSecurity::sanitize_custom_attributes_fields( $data );
		$data = HtmlSecurity::sanitize_html_fields( $data );

		if ( false === strpos( $data['post_content'], 'enable_html' ) || current_user_can( 'unfiltered_html' ) ) {
			return $data;
		}

		return DynamicContentFixes::disable_html( $data );
	}
}

// Class doesn't have any dependencies yet but it might in the future.
$dependency_tree = new DependencyTree();

$security = new Security( $dependency_tree );

$security->initialize();
