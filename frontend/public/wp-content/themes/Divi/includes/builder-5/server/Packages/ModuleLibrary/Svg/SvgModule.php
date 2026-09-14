<?php
/**
 * Module Library: SVG Module.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Svg;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP uses snakeCase in \WP_Block_Parser_Block.

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Attributes\AttributeUtils;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleLibrary\Svg\SvgSanitizer;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use DOMDocument;
use DOMElement;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * SvgModule class.
 *
 * @since ??
 */
class SvgModule implements DependencyInterface {
	/**
	 * Get SVG inner content attributes.
	 *
	 * @param array $attrs Module attributes.
	 *
	 * @return array
	 */
	private static function _get_inner_content_attrs( array $attrs ): array {
		$inner_content = ModuleUtils::get_attr_value(
			[
				'attr'         => $attrs['svg']['innerContent'] ?? [],
				'breakpoint'   => 'desktop',
				'state'        => 'value',
				'mode'         => 'getAndInheritAll',
				'defaultValue' => [],
			]
		);

		return is_array( $inner_content ) ? $inner_content : [];
	}

	/**
	 * Style declaration for SVG fill values.
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function svg_fill_style_declaration( array $params ): string {
		$attr_value         = $params['attrValue'] ?? [];
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		$color   = $attr_value['color'] ?? '';
		$opacity = $attr_value['opacity'] ?? '';

		if ( is_string( $color ) && '' !== $color ) {
			$style_declarations->add( 'fill', $color );
		}

		if ( is_string( $opacity ) && '' !== $opacity ) {
			$style_declarations->add( 'fill-opacity', $opacity );
		}

		return $style_declarations->value();
	}

	/**
	 * Style declaration for SVG stroke values.
	 *
	 * @param array $params Declaration params.
	 *
	 * @return string
	 */
	public static function svg_stroke_style_declaration( array $params ): string {
		$attr_value         = $params['attrValue'] ?? [];
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		$color   = $attr_value['color'] ?? '';
		$width   = $attr_value['width'] ?? '';
		$opacity = $attr_value['opacity'] ?? '';

		if ( is_string( $color ) && '' !== $color ) {
			$style_declarations->add( 'stroke', $color );
		}

		if ( is_string( $width ) && '' !== $width ) {
			$style_declarations->add( 'stroke-width', $width );
		}

		if ( is_string( $opacity ) && '' !== $opacity ) {
			$style_declarations->add( 'stroke-opacity', $opacity );
		}

		return $style_declarations->value();
	}

	/**
	 * Resolve SVG markup from selected source.
	 *
	 * @param array $inner_content SVG inner content attributes.
	 *
	 * @return string
	 */
	private static function _resolve_svg_markup( array $inner_content ): string {
		$source_type = $inner_content['sourceType'] ?? 'code';
		$raw_markup  = '';

		if ( 'src' === $source_type ) {
			$raw_markup = self::_resolve_svg_markup_from_src( $inner_content['src'] ?? '' );
		} else {
			$raw_markup = is_string( $inner_content['code'] ?? null ) ? $inner_content['code'] : '';
		}

		$sanitized_markup = SvgSanitizer::sanitize_markup( $raw_markup );

		if ( '' === $sanitized_markup ) {
			return '';
		}

		return $sanitized_markup;
	}

	/**
	 * Get SVG target custom attributes from Advanced > Attributes option group.
	 *
	 * @param array $attrs Module attributes.
	 *
	 * @return array<string, string>
	 */
	private static function _get_svg_custom_attributes( array $attrs ): array {
		$custom_attributes_data = $attrs['module']['decoration']['attributes'] ?? [];

		if ( empty( $custom_attributes_data ) ) {
			return [];
		}

		$separated_attributes = AttributeUtils::separate_attributes_by_target_element( $custom_attributes_data );

		return is_array( $separated_attributes['svg'] ?? null ) ? $separated_attributes['svg'] : [];
	}

	/**
	 * Resolve SVG markup from local upload URL.
	 *
	 * @param string $src SVG source URL.
	 *
	 * @return string
	 */
	private static function _resolve_svg_markup_from_src( string $src ): string {
		$src_url = esc_url_raw( $src );

		if ( '' === $src_url || ! preg_match( '/\.svg(?:$|[?#])/i', $src_url ) ) {
			return '';
		}

		$uploads = wp_get_upload_dir();
		$baseurl = $uploads['baseurl'] ?? '';
		$basedir = $uploads['basedir'] ?? '';

		if ( '' === $baseurl || '' === $basedir || ! str_starts_with( $src_url, $baseurl ) ) {
			return '';
		}

		$relative_path = ltrim( (string) wp_parse_url( $src_url, PHP_URL_PATH ), '/' );
		$baseurl_path  = ltrim( (string) wp_parse_url( $baseurl, PHP_URL_PATH ), '/' );

		if ( ! str_starts_with( $relative_path, $baseurl_path ) ) {
			return '';
		}

		$file_path_rel = ltrim( substr( $relative_path, strlen( $baseurl_path ) ), '/' );
		$file_path     = trailingslashit( $basedir ) . $file_path_rel;

		// Resolve canonical paths before prefix validation to block traversal segments.
		// String prefix checks on unresolved paths can be bypassed with ../ patterns.
		$file_realpath = realpath( $file_path );
		$basedir_real  = realpath( $basedir );

		if ( false === $file_realpath || false === $basedir_real ) {
			return '';
		}

		$file_path_norm = wp_normalize_path( $file_realpath );
		$basedir_norm   = wp_normalize_path( trailingslashit( $basedir_real ) );

		if ( ! str_starts_with( $file_path_norm, $basedir_norm ) || ! is_readable( $file_path_norm ) ) {
			return '';
		}

		$file_contents = file_get_contents( $file_path_norm );

		return false !== $file_contents ? $file_contents : '';
	}

	/**
	 * Apply custom svg attributes to SVG root element.
	 *
	 * @param string $svg_markup Sanitized SVG markup.
	 * @param string $title SVG title from source settings.
	 * @param array  $svg_custom_attributes SVG custom attributes from Advanced > Attributes.
	 *
	 * @return string
	 */
	private static function _apply_svg_custom_attributes( string $svg_markup, string $title = '', array $svg_custom_attributes = [] ): string {
		if ( '' === trim( $svg_markup ) ) {
			return '';
		}

		$allowed_svg_custom_attributes = SvgAllowedList::get_allowed_root_svg_custom_attributes();
		$dom       = new DOMDocument( '1.0', 'UTF-8' );
		$is_loaded = $dom->loadXML( $svg_markup, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );

		if ( ! $is_loaded ) {
			return $svg_markup;
		}

		$svg = $dom->getElementsByTagName( 'svg' )->item( 0 );

		if ( ! ( $svg instanceof DOMElement ) ) {
			return $svg_markup;
		}

		$has_custom_title_attribute = false;
		foreach ( array_keys( $svg_custom_attributes ) as $attr_name ) {
			if ( ! is_string( $attr_name ) || '' === trim( $attr_name ) ) {
				continue;
			}

			$normalized_attr_name = strtolower( 'className' === $attr_name ? 'class' : trim( $attr_name ) );
			if ( isset( $allowed_svg_custom_attributes[ $normalized_attr_name ] ) && 'title' === $normalized_attr_name ) {
				$has_custom_title_attribute = true;
				break;
			}
		}

		$title_elements = $svg->getElementsByTagName( 'title' );

		while ( $title_elements->length > 0 ) {
			$title_element = $title_elements->item( 0 );

			if ( $title_element instanceof DOMElement && $title_element->parentNode ) {
				$title_element->parentNode->removeChild( $title_element );
			} else {
				break;
			}
		}

		if ( '' !== $title && ! $has_custom_title_attribute ) {
			$title_node = $dom->createElement( 'title' );
			$title_node->appendChild( $dom->createTextNode( $title ) );

			if ( $svg->firstChild ) {
				$svg->insertBefore( $title_node, $svg->firstChild );
			} else {
				$svg->appendChild( $title_node );
			}
		}

		foreach ( $svg_custom_attributes as $attr_name => $attr_value ) {
			if ( ! is_string( $attr_name ) || '' === $attr_name || ! is_scalar( $attr_value ) ) {
				continue;
			}

			$normalized_attr_name = strtolower( 'className' === $attr_name ? 'class' : trim( $attr_name ) );
			if ( ! isset( $allowed_svg_custom_attributes[ $normalized_attr_name ] ) ) {
				continue;
			}

			$attr_value = (string) $attr_value;

			if ( 'class' === $normalized_attr_name ) {
				$existing_class = $svg->getAttribute( 'class' );
				$svg->setAttribute( 'class', AttributeUtils::merge_attribute_values( 'class', $existing_class, $attr_value ) );
				continue;
			}

			if ( 'style' === $normalized_attr_name ) {
				$existing_style = $svg->getAttribute( 'style' );
				$svg->setAttribute( 'style', AttributeUtils::merge_attribute_values( 'style', $existing_style, $attr_value ) );
				continue;
			}

			$svg->setAttribute( $normalized_attr_name, $attr_value );
		}

		$svg_xml = $dom->saveXML( $svg );

		return false !== $svg_xml ? $svg_xml : $svg_markup;
	}

	/**
	 * SVG module script data.
	 *
	 * @since ??
	 *
	 * @param array $args Module script data args.
	 *
	 * @return void
	 */
	public static function module_script_data( array $args ): void {
		$id             = $args['id'] ?? '';
		$name           = $args['name'] ?? '';
		$selector       = $args['selector'] ?? '';
		$attrs          = $args['attrs'] ?? [];
		$elements       = $args['elements'];
		$store_instance = $args['storeInstance'] ?? null;

		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);

		MultiViewScriptData::set_content(
			[
				'id'            => $id,
				'name'          => $name,
				'selector'      => "{$selector} .et_pb_svg_inner",
				'hoverSelector' => $selector,
				'data'          => $attrs['svg']['innerContent'] ?? [],
				'valueResolver' => function ( $value, array $resolver_args = [] ) use ( $attrs ): string {
					unset( $resolver_args );

					if ( ! is_array( $value ) ) {
						return '';
					}

					$svg_markup = self::_resolve_svg_markup( $value );
					$title      = is_string( $value['title'] ?? null ) ? trim( $value['title'] ) : '';

					return self::_apply_svg_custom_attributes( $svg_markup, $title, self::_get_svg_custom_attributes( $attrs ) );
				},
				'sanitizer'     => 'et_core_esc_previously',
				'storeInstance' => $store_instance,
			]
		);
	}

	/**
	 * Generate classnames for the module.
	 *
	 * @since ??
	 *
	 * @param array $args Module classnames args.
	 *
	 * @return void
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => $attrs['module']['decoration'] ?? [],
				]
			)
		);
	}

	/**
	 * SVG module style components.
	 *
	 * @since ??
	 *
	 * @param array $args Module styles args.
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs       = $args['attrs'] ?? [];
		$elements    = $args['elements'];
		$settings    = $args['settings'] ?? [];
		$order_class = $args['orderClass'] ?? '';

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'disabledOn' => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
							],
						]
					),
					$elements->style(
						[
							'attrName'   => 'svg',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} .et_pb_svg_inner svg",
											'attr'     => $attrs['svg']['advanced']['fill'] ?? [],
											'declarationFunction' => [ self::class, 'svg_fill_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} .et_pb_svg_inner svg *",
											'attr'     => $attrs['svg']['advanced']['fill'] ?? [],
											'declarationFunction' => [ self::class, 'svg_fill_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} .et_pb_svg_inner svg",
											'attr'     => $attrs['svg']['advanced']['stroke'] ?? [],
											'declarationFunction' => [ self::class, 'svg_stroke_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} .et_pb_svg_inner svg *",
											'attr'     => $attrs['svg']['advanced']['stroke'] ?? [],
											'declarationFunction' => [ self::class, 'svg_stroke_style_declaration' ],
										],
									],
								],
							],
						]
					),
					CssStyle::style(
						[
							'selector'  => $args['orderClass'],
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => WP_Block_Type_Registry::get_instance()->get_registered( 'divi/svg' )->customCssFields ?? [],
						]
					),
				],
			]
		);
	}

	/**
	 * Render callback for the SVG module.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                 Block attributes.
	 * @param string         $child_modules_content Child modules content.
	 * @param WP_Block       $block                 Parsed block object.
	 * @param ModuleElements $elements              Module elements instance.
	 *
	 * @return string
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements ): string {
		$inner_content = self::_get_inner_content_attrs( $attrs );
		$svg_markup    = self::_resolve_svg_markup( $inner_content );
		$title         = is_string( $inner_content['title'] ?? null ) ? trim( $inner_content['title'] ) : '';
		$svg_markup    = self::_apply_svg_custom_attributes( $svg_markup, $title, self::_get_svg_custom_attributes( $attrs ) );

		$svg_inner = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'et_pb_svg_inner',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $svg_markup,
			]
		);

		$link_url = is_string( $inner_content['linkUrl'] ?? null ) ? trim( $inner_content['linkUrl'] ) : '';
		$content  = $svg_inner;

		if ( '' !== $link_url ) {
			$link_target = $inner_content['linkTarget'] ?? 'off';
			$link_rel    = is_string( $inner_content['rel'] ?? null ) ? trim( $inner_content['rel'] ) : '';
			$link_attrs  = [
				'class' => 'et_pb_svg_link',
				'href'  => esc_url( $link_url ),
			];

			if ( 'on' === $link_target ) {
				$link_attrs['target'] = '_blank';
				$link_attrs['rel']    = '' === $link_rel ? 'noopener noreferrer' : esc_attr( $link_rel );
			} elseif ( '' !== $link_rel ) {
				$link_attrs['rel'] = esc_attr( $link_rel );
			}

			$content = HTMLUtility::render(
				[
					'tag'               => 'a',
					'attributes'        => $link_attrs,
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $svg_inner,
				]
			);
		}

		$children_ids = ChildrenUtils::extract_children_ids( $block );
		$parent       = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		return Module::render(
			[
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],
				'attrs'               => $attrs,
				'elements'            => $elements,
				'id'                  => $block->parsed_block['id'],
				'name'                => $block->block_type->name,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'moduleCategory'      => $block->block_type->category,
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'parentAttrs'         => $parent->attrs ?? [],
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->blockName ?? '',
				'childrenIds'         => $children_ids,
				'children'            => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $elements->style_components(
					[
						'attrName' => 'svg',
					]
				) . $content . $child_modules_content,
			]
		);
	}

	/**
	 * Load and register SVG module.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/svg/';

		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
